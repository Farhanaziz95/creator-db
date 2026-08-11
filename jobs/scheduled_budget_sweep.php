<?php
/**
 * Run this DAILY via Windows Task Scheduler (same pattern as
 * process_niche_queue.php):
 *   C:\xampp\php\php.exe C:\xampp\htdocs\creator-db\jobs\scheduled_budget_sweep.php
 *
 * Decides EVERYTHING internally, every day, fresh:
 *   - Are we even inside this month's sweep window? (last N days)
 *   - Is today an active day at all? (random chance, not every day)
 *   - What % of eligible leads get targeted today? (random each day)
 *   - Which subset of active keys is usable today? (not all of them)
 *   - Random pause between each lead within the day
 *
 * Point of all this randomness: a fixed 2-day burst, or even a fixed
 * 15-day window with even daily amounts, is still a detectable pattern.
 * Deliberately never tries to guarantee 100% of leftover budget gets used
 * — a forced catch-up burst near the window's end would be exactly the
 * kind of automation fingerprint this is trying to avoid.
 *
 * Manual "Run Sweep Now" button in the dashboard still exists separately
 * for on-demand testing/override — this file is the human-pattern version
 * meant for actual production use once this matters.
 */

set_time_limit(0);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/apify_client.php';

$config = require __DIR__ . '/../config/sweep_schedule.php';
$today  = new DateTime();
$dayOfMonth    = (int) $today->format('j');
$lastDayOfMonth = (int) $today->format('t');
$windowStart   = $lastDayOfMonth - $config['window_days'] + 1;

function log_sweep_day(PDO $pdo, bool $active, ?float $pct, string $keysUsed, int $attempted, int $refreshed, string $note): void
{
    $stmt = $pdo->prepare("
        INSERT INTO sweep_schedule_log (run_date, was_active_day, daily_percentage, keys_used, leads_attempted, leads_refreshed, note)
        VALUES (CURDATE(), ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$active ? 1 : 0, $pct, $keysUsed, $attempted, $refreshed, $note]);
}

// ---- Not in the window yet ----
if ($dayOfMonth < $windowStart) {
    log_sweep_day($pdo, false, null, '', 0, 0, "Outside sweep window (starts day {$windowStart})");
    echo "Not in sweep window yet (starts day {$windowStart} of {$lastDayOfMonth}).\n";
    exit;
}

// ---- Random chance today just isn't an active day ----
if ((mt_rand() / mt_getrandmax()) > $config['daily_activity_chance']) {
    log_sweep_day($pdo, false, null, '', 0, 0, 'In window, randomly skipped today');
    echo "In sweep window, but today randomly landed as an inactive day.\n";
    exit;
}

// ---- Pick today's key subset ----
$allKeys = get_active_apify_keys($pdo);
if (!$allKeys) {
    log_sweep_day($pdo, true, null, '', 0, 0, 'No active keys configured');
    echo "No active Apify keys — nothing to do.\n";
    exit;
}
shuffle($allKeys);
$subsetPct = mt_rand((int) ($config['key_subset_min_pct'] * 100), (int) ($config['key_subset_max_pct'] * 100)) / 100;
$subsetCount = max(1, (int) ceil(count($allKeys) * $subsetPct));
$todaysKeys = array_slice($allKeys, 0, $subsetCount);
$todaysKeyIds = array_map(fn($k) => (int) $k['id'], $todaysKeys);
$todaysKeyLabels = implode(', ', array_map(fn($k) => $k['label'], $todaysKeys));

// ---- Pick today's target % of eligible leads ----
$eligibleRows = $pdo->query("
    SELECT p.id
    FROM profiles p
    JOIN flozy_leads fl ON fl.profile_id = p.id
    LEFT JOIN (
        SELECT profile_id, MAX(fetched_at) AS last_fetch FROM post_transcripts GROUP BY profile_id
    ) pt ON pt.profile_id = p.id
    WHERE (fl.current_stage_tag IS NULL OR fl.current_stage_tag = 'active')
      AND (fl.current_stage IS NULL OR fl.current_stage NOT IN (SELECT stage_name FROM sweep_excluded_stages))
    ORDER BY pt.last_fetch ASC
")->fetchAll(PDO::FETCH_COLUMN);

if (!$eligibleRows) {
    log_sweep_day($pdo, true, null, $todaysKeyLabels, 0, 0, 'No eligible leads today');
    echo "Active day, but no eligible leads right now.\n";
    exit;
}

$dailyPct = mt_rand((int) ($config['daily_percent_min'] * 100), (int) ($config['daily_percent_max'] * 100)) / 100;
$targetCount = max(1, (int) ceil(count($eligibleRows) * $dailyPct));
$todaysLeads = array_slice($eligibleRows, 0, $targetCount);

echo "Active sweep day. Targeting " . round($dailyPct * 100, 1) . "% of " . count($eligibleRows) . " eligible leads ({$targetCount} lead(s)), using key(s): {$todaysKeyLabels}\n";

// ---- Run the sweep, one lead at a time, with random human-like pauses ----
$selfUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/creator-db/api/run_verification.php';
$refreshed = 0;

foreach ($todaysLeads as $pid) {
    $ch = curl_init($selfUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'profile_id'        => $pid,
            'force_rescrape'    => true,
            'preferred_key_ids' => $todaysKeyIds,
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT    => 180,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $decoded = json_decode($response, true);
    if ($decoded['success'] ?? false) {
        $refreshed++;
        echo "  Profile {$pid}: refreshed.\n";
    } else {
        echo "  Profile {$pid}: failed — " . ($decoded['error'] ?? 'unknown') . "\n";
    }

    // Random human-like pause before the next one — skip after the last lead.
    if ($pid !== end($todaysLeads)) {
        $delay = mt_rand($config['min_delay_seconds'], $config['max_delay_seconds']);
        sleep($delay);
    }
}

log_sweep_day($pdo, true, round($dailyPct * 100, 2), $todaysKeyLabels, count($todaysLeads), $refreshed, '');
echo "Done. Refreshed {$refreshed} of " . count($todaysLeads) . " targeted lead(s).\n";
