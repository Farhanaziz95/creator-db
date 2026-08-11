<?php
/**
 * ONE-TIME CLEANUP — run this once via browser or CLI after updating
 * openrouter_client.php. Finds niches that look like reasoning-dump garbage
 * or non-answers (from before the validation fix), clears them off the
 * affected profiles, and re-queues those profiles for AI classification.
 *
 * Usage: http://localhost/creator-db/jobs/requeue_invalid_niches.php
 * (or run via php.exe from the command line — safe to run more than once)
 */

require_once __DIR__ . '/../config/db.php';

// Find AI-generated niches that look like garbage: long text, sentence-like,
// or known non-answers.
// Two kinds of garbage to find:
// 1. AI-generated niches that leaked reasoning text or gave a non-answer
// 2. Rule-based niches that are literally "None"/"N/A" (Apify sometimes
//    returns that as a literal string for businessCategoryName, and the
//    old import code treated any non-empty string as a real category)
$stmt = $pdo->query("
    SELECT id, name FROM niches
    WHERE (
        is_ai_generated = 1
        AND (
            LENGTH(name) > 40
            OR name LIKE '%we need%'
            OR name LIKE '%the user%'
            OR name LIKE '%let me%'
            OR name LIKE '%i think%'
            OR name LIKE '%based on%'
            OR name LIKE '%:%'
        )
    )
    OR LOWER(name) IN ('none', 'n/a', 'na', 'null', 'unknown', 'unclear', 'not sure', 'no niche', 'unavailable')
");
$badNiches = $stmt->fetchAll();

if (!$badNiches) {
    echo "No garbage niches found. Nothing to clean up.\n";
    exit;
}

$badIds = array_column($badNiches, 'id');
echo "Found " . count($badIds) . " garbage niche(s):\n";
foreach ($badNiches as $n) {
    echo "  - [{$n['id']}] " . substr($n['name'], 0, 60) . "\n";
}

$placeholders = implode(',', array_fill(0, count($badIds), '?'));

// Find affected profiles
$stmt = $pdo->prepare("SELECT id FROM profiles WHERE niche_id IN ($placeholders)");
$stmt->execute($badIds);
$affectedProfileIds = array_column($stmt->fetchAll(), 'id');

echo "\nAffected profiles: " . count($affectedProfileIds) . "\n";

if ($affectedProfileIds) {
    // Clear their niche
    $pdo->prepare("UPDATE profiles SET niche_id = NULL, niche_source = NULL WHERE niche_id IN ($placeholders)")
        ->execute($badIds);

    // Re-queue them for AI classification
    foreach ($affectedProfileIds as $pid) {
        $pdo->prepare("INSERT IGNORE INTO ai_queue (profile_id, status, attempts) VALUES (?, 'pending', 0)")
            ->execute([$pid]);
    }
    echo "Cleared and re-queued " . count($affectedProfileIds) . " profile(s).\n";
}

// Delete the garbage niche rows themselves
$pdo->prepare("DELETE FROM niches WHERE id IN ($placeholders)")->execute($badIds);
echo "Deleted " . count($badIds) . " garbage niche row(s).\n";

echo "\nDone. The background job will pick these profiles up on its next scheduled run.\n";
