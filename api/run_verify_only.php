<?php
/**
 * Round 35, item #7: "Verify Only" — runs just the Apify scraping half
 * (via includes/verification_scrape.php, shared with the full
 * Verify+Personalize pipeline in api/run_verification.php), skips both
 * Gemini passes entirely. Confirmed: respects the same 21-day cache as
 * normal Verify+Personalize — if there's already fresh data, this
 * reports the cache hit and does nothing (does NOT force a rescrape).
 * Pairs with the already-existing "Retry AI Only" (api/rerun_ai_analysis.php),
 * which runs just the AI half on whatever's already scraped — together
 * they split scraping and AI generation into two independently-triggered
 * steps.
 *
 * Deliberately writes no content_analysis_runs row: there's no AI
 * verification result to store, and item #8's Gameplan/Verify filter
 * defines "Verified Only" as "has post_transcripts rows but no
 * content_analysis_runs with status='done'" — staying out of that table
 * entirely is what keeps that condition meaningful, rather than
 * inventing a new status value the filter doesn't know about.
 *
 * Judgment call, not spelled out in chat: unlike the full pipeline, this
 * does NOT require a gameplan to already be uploaded — scraping doesn't
 * touch the gameplan text at all (only the Gemini prompt does), so
 * there's no reason to block it. This also means the person can pre-scrape
 * a lead before its gameplan even exists, then run "Retry AI Only" once
 * it's uploaded — a natural extra benefit of splitting these steps.
 */

set_time_limit(0);
ignore_user_abort(true);

require_once __DIR__ . '/../includes/error_handler.php';
setup_json_error_handling();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/apify_client.php';
require_once __DIR__ . '/../includes/verification_scrape.php';
header('Content-Type: application/json');

try {
    run_verify_only($pdo);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unexpected error: ' . $e->getMessage() . ' (line ' . $e->getLine() . ' in ' . basename($e->getFile()) . ')']);
}

function count_stored(PDO $pdo, int $profileId): array
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM post_transcripts WHERE profile_id = ?");
    $stmt->execute([$profileId]);
    $transcripts = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM post_comments WHERE profile_id = ?");
    $stmt->execute([$profileId]);
    $comments = (int) $stmt->fetchColumn();

    return [$transcripts, $comments];
}

function run_verify_only(PDO $pdo): void
{
    $input     = json_decode(file_get_contents('php://input'), true);
    $profileId = (int) ($input['profile_id'] ?? 0);
    $forceRescrape = !empty($input['force_rescrape']);
    $preferredKeyIds = isset($input['preferred_key_ids']) ? array_map('intval', $input['preferred_key_ids']) : null;

    $config = require __DIR__ . '/../config/content_analysis.php';

    $stmt = $pdo->prepare("SELECT username FROM profiles WHERE id = ?");
    $stmt->execute([$profileId]);
    $profile = $stmt->fetch();

    if (!$profile) {
        http_response_code(404);
        echo json_encode(['error' => 'Profile not found.']);
        return;
    }

    // ---- Same cost-saver cache as normal Verify+Personalize ----
    if (!$forceRescrape) {
        $stmt = $pdo->prepare("SELECT MAX(fetched_at) FROM post_transcripts WHERE profile_id = ?");
        $stmt->execute([$profileId]);
        $lastFetched = $stmt->fetchColumn();

        if ($lastFetched && (time() - strtotime($lastFetched)) < ($config['cache_days'] * 86400)) {
            [$transcriptCount, $commentCount] = count_stored($pdo, $profileId);
            echo json_encode([
                'success'            => true,
                'reused_cached_data' => true,
                'posts_checked'      => $transcriptCount,
                'comments_checked'   => $commentCount,
            ]);
            return;
        }
    }

    $scrape = run_scrape_stage($pdo, $profileId, $profile['username'], $config, $preferredKeyIds);

    if ($scrape['error']) {
        http_response_code(500);
        echo json_encode(['error' => $scrape['error']]);
        return;
    }

    [$transcriptCount, $commentCount] = count_stored($pdo, $profileId);

    echo json_encode([
        'success'                  => true,
        'reused_cached_data'       => false,
        'new_posts_found'          => count($scrape['reel_results']),
        'posts_checked'            => $transcriptCount,
        'comments_checked'         => $commentCount,
        'comments_concentrated_on' => count($scrape['post_urls_used']) . ' of ' . count($scrape['reel_results']) . ' new post(s)',
    ]);
}
