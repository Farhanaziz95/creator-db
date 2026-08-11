<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/keyword_rules.php';
require_once __DIR__ . '/../includes/metrics.php';
require_once __DIR__ . '/../includes/openrouter_client.php'; // for get_or_create_niche()
require_once __DIR__ . '/../includes/quality_score.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['jsonFile'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded. Field name must be jsonFile.']);
    exit;
}

$filePath = $_FILES['jsonFile']['tmp_name'];
$filename = $_FILES['jsonFile']['name'];
$json     = file_get_contents($filePath);
$profiles = json_decode($json, true);

if (!is_array($profiles)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON file — expected an array of profile objects.']);
    exit;
}

$newCount       = 0;
$duplicateCount = 0;
$total          = count($profiles);

// One batch record per uploaded file
$stmt = $pdo->prepare("INSERT INTO import_batches (filename, total_in_file) VALUES (?, ?)");
$stmt->execute([$filename, $total]);
$batchId = $pdo->lastInsertId();

foreach ($profiles as $p) {
    $username = $p['username'] ?? null;
    if (!$username) {
        continue;
    }

    $fullName         = $p['fullName'] ?? '';
    $bio              = $p['biography'] ?? '';
    $externalUrl      = $p['externalUrl'] ?? null;
    $followers        = (int) ($p['followersCount'] ?? 0);
    $following        = (int) ($p['followsCount'] ?? 0);
    $postsCount       = (int) ($p['postsCount'] ?? 0);
    $businessCategory = $p['businessCategoryName'] ?? null;
    $latestPosts      = $p['latestPosts'] ?? [];

    // ---- Dedup check ----
    $stmt = $pdo->prepare("SELECT id, niche_id FROM profiles WHERE username = ?");
    $stmt->execute([$username]);
    $existing = $stmt->fetch();

    $previousFollowers = null;
    if ($existing) {
        $profileId = $existing['id'];
        $duplicateCount++;

        // Grab their most recent prior snapshot for the growth-alert comparison
        $stmt = $pdo->prepare("SELECT followers_count FROM profile_snapshots WHERE profile_id = ? ORDER BY imported_at DESC LIMIT 1");
        $stmt->execute([$profileId]);
        $previousFollowers = $stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare("INSERT INTO profiles (username, full_name, external_url) VALUES (?, ?, ?)");
        $stmt->execute([$username, $fullName, $externalUrl]);
        $profileId = $pdo->lastInsertId();
        $newCount++;
    }

    // ---- Snapshot (history preserved, nothing overwritten) ----
    $metrics = calculate_engagement($latestPosts, $followers);

    // Growth alert: flag as trending if followers grew 15%+ since the last
    // time we saw this profile. Only meaningful for duplicates (new
    // profiles have nothing to compare against).
    $growthPct = null;
    $isTrending = 0;
    if ($previousFollowers !== null && $previousFollowers !== false && (int) $previousFollowers > 0) {
        $growthPct = round((($followers - (int) $previousFollowers) / (int) $previousFollowers) * 100, 2);
        $isTrending = $growthPct >= 15 ? 1 : 0;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO profile_snapshots
            (profile_id, batch_id, followers_count, following_count, posts_count, biography,
             business_category_name, avg_likes, avg_comments, engagement_rate, posts_analyzed,
             pinned_posts_excluded, posts_per_week, is_inconsistent, follower_growth_pct, is_trending)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $profileId, $batchId, $followers, $following, $postsCount, $bio, $businessCategory,
        $metrics['avg_likes'], $metrics['avg_comments'], $metrics['engagement_rate'], $metrics['posts_analyzed'],
        $metrics['pinned_posts_excluded'], $metrics['posts_per_week'], $metrics['is_inconsistent'] ? 1 : 0,
        $growthPct, $isTrending,
    ]);

    // Keep bio/external_url on the profiles table fresh too (latest known values)
    $stmt = $pdo->prepare("UPDATE profiles SET full_name = ?, external_url = ? WHERE id = ?");
    $stmt->execute([$fullName, $externalUrl, $profileId]);

    // ---- Niche detection (only if this profile doesn't have one yet) ----
    $stmt = $pdo->prepare("SELECT niche_id FROM profiles WHERE id = ?");
    $stmt->execute([$profileId]);
    $currentNiche = $stmt->fetchColumn();

    if (!$currentNiche) {
        $rawCategory = trim((string) ($businessCategory ?? ''));
        $invalidCategoryValues = ['none', 'n/a', 'na', 'null', 'unknown', 'unavailable', ''];
        $hasValidCategory = $rawCategory !== '' && !in_array(strtolower($rawCategory), $invalidCategoryValues, true);

        if ($hasValidCategory) {
            $nicheId = get_or_create_niche($pdo, $rawCategory, false);
            $stmt = $pdo->prepare("UPDATE profiles SET niche_id = ?, niche_source = 'rule' WHERE id = ?");
            $stmt->execute([$nicheId, $profileId]);
        } else {
            $guessed = guess_niche_from_keywords($bio, $fullName, $pdo);
            if ($guessed) {
                $nicheId = get_or_create_niche($pdo, $guessed, false);
                $stmt = $pdo->prepare("UPDATE profiles SET niche_id = ?, niche_source = 'rule' WHERE id = ?");
                $stmt->execute([$nicheId, $profileId]);
            } else {
                // No keyword match and no business category — hand off to the AI queue.
                $stmt = $pdo->prepare("INSERT IGNORE INTO ai_queue (profile_id) VALUES (?)");
                $stmt->execute([$profileId]);
            }
        }
    }

    // Quality score computed with whatever niche is known at this point —
    // if this profile goes to the AI queue, the score gets recomputed once
    // classification finishes (see includes/niche_queue_processor.php).
    recompute_quality_score_for_profile($pdo, $profileId);
}

$stmt = $pdo->prepare("UPDATE import_batches SET new_count = ?, duplicate_count = ? WHERE id = ?");
$stmt->execute([$newCount, $duplicateCount, $batchId]);

echo json_encode([
    'success'    => true,
    'total'      => $total,
    'new'        => $newCount,
    'duplicates' => $duplicateCount,
    'batch_id'   => $batchId,
]);
