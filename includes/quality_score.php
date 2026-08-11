<?php
/**
 * Server-side port of the quality score logic (was client-side-only,
 * which made it un-sortable across pages). Same 5-point-per-signal
 * formula, weighted by score_weights, plus a new "content_verified"
 * signal factoring in unresolved manual-review flags.
 */

function get_engagement_tier(int $followers, float $engagementRate): string
{
    $benchmarks = [
        ['max' => 5000,        'high' => 6.16, 'above' => 3.85, 'avg' => 3.16, 'below' => 1.85],
        ['max' => 10000,       'high' => 2.09, 'above' => 1.13, 'avg' => 0.88, 'below' => 0.46],
        ['max' => 50000,       'high' => 1.27, 'above' => 0.65, 'avg' => 0.49, 'below' => 0.24],
        ['max' => 100000,      'high' => 0.91, 'above' => 0.43, 'avg' => 0.32, 'below' => 0.15],
        ['max' => 500000,      'high' => 0.93, 'above' => 0.46, 'avg' => 0.35, 'below' => 0.16],
        ['max' => 1000000,     'high' => 1.00, 'above' => 0.51, 'avg' => 0.39, 'below' => 0.19],
        ['max' => PHP_INT_MAX, 'high' => 1.08, 'above' => 0.57, 'avg' => 0.45, 'below' => 0.22],
    ];

    foreach ($benchmarks as $b) {
        if ($followers <= $b['max']) {
            if ($engagementRate > $b['high'])  return 'high';
            if ($engagementRate >= $b['above']) return 'above';
            if ($engagementRate >= $b['avg'])   return 'avg';
            if ($engagementRate >= $b['below']) return 'below';
            return 'low';
        }
    }
    return 'low';
}

/**
 * Computes and returns the quality score (0-100) for one snapshot. Also
 * checks for unresolved manual-review flags on the profile's stored
 * transcripts as an additional weighted signal.
 */
function calculate_quality_score(PDO $pdo, array $snapshot, ?int $nicheId, int $profileId): ?float
{
    $rows = $pdo->query("SELECT metric_key, weight, is_active FROM score_weights")->fetchAll();
    $weights = [];
    foreach ($rows as $r) {
        $weights[$r['metric_key']] = ['weight' => (float) $r['weight'], 'active' => (int) $r['is_active'] === 1];
    }

    $tier = get_engagement_tier((int) $snapshot['followers_count'], (float) $snapshot['engagement_rate']);
    $engPoints = ['high' => 5, 'above' => 4, 'avg' => 3, 'below' => 2, 'low' => 1][$tier];

    $postsPerWeek = $snapshot['posts_per_week'] ?? null;
    if ($postsPerWeek === null) {
        $postPoints = 3;
    } elseif ($postsPerWeek < 2 || $postsPerWeek > 7) {
        $postPoints = 2;
    } else {
        $postPoints = 5;
    }

    $growthPoints = !empty($snapshot['is_trending']) ? 5 : 3;
    $nichePoints  = $nicheId ? 5 : 3;

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM post_transcripts
        WHERE profile_id = ? AND needs_manual_review = 1 AND (review_status = 'pending' OR review_status IS NULL)
    ");
    $stmt->execute([$profileId]);
    $unresolvedFlags = (int) $stmt->fetchColumn();
    $verifiedPoints = $unresolvedFlags > 0 ? 2 : 5;

    $components = [
        'engagement_tier'     => $engPoints,
        'posting_consistency' => $postPoints,
        'growth_trend'        => $growthPoints,
        'niche_assigned'      => $nichePoints,
        'content_verified'    => $verifiedPoints,
    ];

    $weightedSum = 0;
    $maxPossible = 0;
    foreach ($components as $key => $points) {
        $w = $weights[$key] ?? null;
        if (!$w || !$w['active']) continue;
        $weightedSum += $points * $w['weight'];
        $maxPossible += 5 * $w['weight'];
    }

    if ($maxPossible == 0) return null;
    return round(($weightedSum / $maxPossible) * 100, 2);
}

/**
 * Recomputes and stores the quality score for a profile's MOST RECENT
 * snapshot only (older snapshots keep whatever score they had at the time).
 */
function recompute_quality_score_for_profile(PDO $pdo, int $profileId): void
{
    $stmt = $pdo->prepare("
        SELECT id, followers_count, engagement_rate, posts_per_week, is_trending
        FROM profile_snapshots
        WHERE profile_id = ?
        ORDER BY imported_at DESC LIMIT 1
    ");
    $stmt->execute([$profileId]);
    $snapshot = $stmt->fetch();
    if (!$snapshot) return;

    $stmt = $pdo->prepare("SELECT niche_id FROM profiles WHERE id = ?");
    $stmt->execute([$profileId]);
    $nicheId = $stmt->fetchColumn();

    $score = calculate_quality_score($pdo, $snapshot, $nicheId ?: null, $profileId);

    $stmt = $pdo->prepare("UPDATE profile_snapshots SET quality_score = ? WHERE id = ?");
    $stmt->execute([$score, $snapshot['id']]);
}
