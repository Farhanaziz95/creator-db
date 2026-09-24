<?php
/**
 * Separate from the follow-up/messaging system entirely — this is purely
 * about not letting unused Apify budget expire unused. Refreshes data
 * (transcripts/comments) for active Flozy leads, prioritizing whoever's
 * data is oldest/never-fetched. Manually triggered — a "pointer" in the
 * dashboard, not an automated cron.
 */

set_time_limit(0);

require_once __DIR__ . '/../includes/error_handler.php';
setup_json_error_handling();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/apify_client.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $remaining = get_total_remaining_budget($pdo);

    $eligibleCount = (int) $pdo->query("
        SELECT COUNT(*) FROM profiles p
        JOIN flozy_leads fl ON fl.profile_id = p.id
        WHERE (fl.current_stage_tag IS NULL OR fl.current_stage_tag = 'active')
          AND (fl.current_stage IS NULL OR fl.current_stage NOT IN (SELECT stage_name FROM sweep_excluded_stages))
    ")->fetchColumn();

    $dayOfMonth = (int) date('j');

    echo json_encode([
        'remaining_budget' => $remaining,
        'eligible_leads'   => $eligibleCount,
        'is_sweep_window'  => in_array($dayOfMonth, [27, 28], true),
        'day_of_month'     => $dayOfMonth,
    ]);
    exit;
}

if ($method === 'POST') {
    $input    = json_decode(file_get_contents('php://input'), true);
    $maxLeads = (int) ($input['max_leads'] ?? 15); // safety cap per click, not a full auto-drain

    // Prioritize leads whose content was fetched longest ago (or never) —
    // that's who benefits most from a refresh.
    $stmt = $pdo->prepare("
        SELECT p.id
        FROM profiles p
        JOIN flozy_leads fl ON fl.profile_id = p.id
        LEFT JOIN (
            SELECT profile_id, MAX(fetched_at) AS last_fetch FROM post_transcripts GROUP BY profile_id
        ) pt ON pt.profile_id = p.id
        WHERE (fl.current_stage_tag IS NULL OR fl.current_stage_tag = 'active')
          AND (fl.current_stage IS NULL OR fl.current_stage NOT IN (SELECT stage_name FROM sweep_excluded_stages))
        ORDER BY pt.last_fetch ASC
        LIMIT :maxLeads
    ");
    $stmt->bindValue(':maxLeads', $maxLeads, PDO::PARAM_INT);
    $stmt->execute();
    $profileIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $refreshed = 0;
    $failed = [];

    // Reuses the existing verification pipeline via an internal call —
    // same logic as a manual "Verify+Personalize" click, just forced to
    // re-scrape instead of using the cache, and looped across leads.
    $selfUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/run_verification.php';

    foreach ($profileIds as $pid) {
        $ch = curl_init($selfUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['profile_id' => $pid, 'force_rescrape' => true]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 180,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);
        if ($decoded['success'] ?? false) {
            $refreshed++;
        } else {
            $failed[] = ['profile_id' => $pid, 'error' => $decoded['error'] ?? 'unknown'];
        }
    }

    echo json_encode([
        'success'   => true,
        'attempted' => count($profileIds),
        'refreshed' => $refreshed,
        'failed'    => $failed,
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
