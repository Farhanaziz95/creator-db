<?php
/**
 * Handles three things: running an Apify actor synchronously, estimating
 * cost before a run, and picking which key from the rotation pool to use.
 *
 * Key selection is round-robin by least-recently-used, not "always the
 * first key with budget" — spreads traffic across every active key to
 * look like a human juggling several accounts rather than one app
 * grinding on a single one (which is exactly the pattern that flags a
 * borrowed account). Budget is still checked and still wins if a key is
 * genuinely out of room — rotation fairness never overrides safety.
 *
 * CONFIRMED (docs.apify.com/api/v2/users-me-limits-get): GET /users/me/limits
 * response shape is { "data": { "limits": {...}, "current": {...} } } —
 * the budget parsing below matches this exactly now, no longer a guess.
 */

function get_active_apify_keys(PDO $pdo): array
{
    // Least-recently-used first (NULL = never used, comes first) so
    // rotation naturally spreads traffic across every key instead of
    // hammering the first one with a budget — simulates a human juggling
    // several accounts rather than one app grinding on a single one.
    // sort_order is the tiebreaker for keys that are equally "due".
    return $pdo->query("
        SELECT * FROM apify_keys
        WHERE is_active = 1
        ORDER BY last_used_at IS NULL DESC, last_used_at ASC, sort_order ASC, id ASC
    ")->fetchAll();
}

function estimate_apify_cost(PDO $pdo, string $actorKey, int $resultsCount): float
{
    $stmt = $pdo->prepare("SELECT cost_per_1000 FROM apify_actor_costs WHERE actor_key = ?");
    $stmt->execute([$actorKey]);
    $rate = $stmt->fetchColumn();
    if (!$rate) {
        return 0.0;
    }
    return round(($resultsCount / 1000) * (float) $rate, 4);
}

/**
 * Returns ['remaining_usd' => float|null, 'raw' => array] for a given key.
 * remaining_usd is null if the response couldn't be confidently parsed —
 * caller should treat null as "unknown, proceed with caution" not "zero".
 */
function check_apify_key_budget(string $apiKey): array
{
    $ch = curl_init("https://api.apify.com/v2/users/me/limits");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$apiKey}"],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        return ['remaining_usd' => null, 'raw' => []];
    }

    $data = json_decode($response, true)['data'] ?? [];

    // Confirmed via docs.apify.com/api/v2/users-me-limits-get — real schema is:
    // { "data": { "limits": { "maxMonthlyUsageUsd": ... }, "current": { "monthlyUsageUsd": ... } } }
    $maxUsage     = $data['limits']['maxMonthlyUsageUsd'] ?? null;
    $currentUsage = $data['current']['monthlyUsageUsd'] ?? null;

    $remaining = null;
    if (is_numeric($maxUsage) && is_numeric($currentUsage)) {
        $remaining = (float) $maxUsage - (float) $currentUsage;
    }

    return ['remaining_usd' => $remaining, 'raw' => $data];
}

/**
 * Picks which key to use for an upcoming run of estimated cost $estimatedCost.
 * Checks each active key's remaining budget, skips ahead to the next key if
 * the estimate would eat past 90% of what's left. Falls back to the first
 * active key if no budget data is available for any of them (fail open —
 * better to attempt the run than silently do nothing).
 */
function pick_apify_key(PDO $pdo, float $estimatedCost, ?array $allowedKeyIds = null): ?array
{
    $keys = get_active_apify_keys($pdo); // already ordered least-recently-used first

    if ($allowedKeyIds !== null) {
        // Scheduled sweep passes today's randomly-chosen key subset — only
        // those are eligible today, even if other keys have more budget.
        $keys = array_values(array_filter($keys, fn($k) => in_array((int) $k['id'], $allowedKeyIds, true)));
    }

    if (!$keys) {
        return null;
    }

    foreach ($keys as $key) {
        $budget = check_apify_key_budget($key['api_key']);
        if ($budget['remaining_usd'] === null) {
            continue; // unknown — try the next key, don't assume this one is safe or unsafe
        }
        if ($estimatedCost <= $budget['remaining_usd'] * 0.9) {
            mark_key_used($pdo, (int) $key['id']);
            return $key;
        }
    }

    // Nothing confirmed safe — fall back to the least-recently-used active
    // key rather than blocking entirely (already first in $keys).
    mark_key_used($pdo, (int) $keys[0]['id']);
    return $keys[0];
}

function mark_key_used(PDO $pdo, int $keyId): void
{
    $stmt = $pdo->prepare("UPDATE apify_keys SET last_used_at = NOW() WHERE id = ?");
    $stmt->execute([$keyId]);
}

function log_apify_usage(PDO $pdo, int $apifyKeyId, string $actorKey, int $resultsCount, float $cost, ?int $profileId): void
{
    $stmt = $pdo->prepare("
        INSERT INTO apify_usage_log (apify_key_id, actor_key, results_count, estimated_cost, profile_id)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$apifyKeyId, $actorKey, $resultsCount, $cost, $profileId]);
}

/**
 * Runs an Apify actor synchronously and returns its dataset items.
 * $actorSlug example: 'apify~instagram-reel-scraper'
 */
function run_apify_actor(string $actorSlug, array $input, string $apiKey, int $timeoutSeconds = 120): ?array
{
    $url = "https://api.apify.com/v2/acts/{$actorSlug}/run-sync-get-dataset-items?token=" . urlencode($apiKey) . "&timeout={$timeoutSeconds}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($input),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => $timeoutSeconds + 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300 || !$response) {
        return null;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * Sums remaining budget across every active Apify key. Moved here from
 * api/budget_sweep.php so both Instagram (the Budget Sweep panel) and
 * YouTube (a lightweight read-only display) can call the same
 * function — this key pool is shared between both pipelines, so
 * checking it shouldn't mean switching dashboards to see the number.
 * Returns null only if every key's balance was unknown/inconclusive,
 * never as a stand-in for zero.
 */
function get_total_remaining_budget(PDO $pdo): ?float
{
    $keys = get_active_apify_keys($pdo);
    $total = 0.0;
    $anyKnown = false;

    foreach ($keys as $key) {
        $budget = check_apify_key_budget($key['api_key']);
        if ($budget['remaining_usd'] !== null) {
            $total += $budget['remaining_usd'];
            $anyKnown = true;
        }
    }

    return $anyKnown ? $total : null;
}
