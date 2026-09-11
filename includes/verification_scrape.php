<?php
require_once __DIR__ . '/apify_client.php';

/**
 * Round 35, item #7: pulled the "Stage 1: Reel Scraper" + "Stage 2:
 * Comment Scraper" block out of api/run_verification.php's full pipeline
 * so api/run_verify_only.php (scraping only, no Gemini) can share the
 * exact same scraping logic instead of a second copy that could drift
 * out of sync — the "onlyPostsNewerThan" incremental-scrape logic, the
 * top-K comment concentration, and the per-post dedup all matter for
 * cost, not just correctness.
 *
 * Returns:
 *   ['reel_results' => array, 'comments_text' => array, 'post_urls_used' => array, 'error' => string|null]
 * `error` is set (and everything else empty) only for a genuine failure
 * — no Apify keys, or the Reel Scraper returned nothing AND there's no
 * prior scrape to fall back on. A profile with nothing NEW to scrape
 * since last time is NOT an error — reel_results comes back empty in
 * that case with error left null, and each caller decides what that
 * means for it (the full pipeline falls back to AI-on-existing-data;
 * Verify Only just reports "nothing new since last time").
 */
function run_scrape_stage(PDO $pdo, int $profileId, string $username, array $config, ?array $preferredKeyIds): array
{
    $empty = ['reel_results' => [], 'comments_text' => [], 'post_urls_used' => []];

    // ---- Stage 1: Reel Scraper ----
    // If we've scraped this profile before, only ask for reels newer than
    // the most recent one already stored — confirmed real Apify param
    // (apify.com/apify/instagram-reel-scraper/input-schema#onlyPostsNewerThan).
    $stmt = $pdo->prepare("SELECT MAX(posted_at) FROM post_transcripts WHERE profile_id = ? AND posted_at IS NOT NULL");
    $stmt->execute([$profileId]);
    $mostRecentPostedAt = $stmt->fetchColumn();

    $estimatedReelCost = estimate_apify_cost($pdo, 'instagram_reel_scraper', $config['posts_to_check']);
    $reelKey = pick_apify_key($pdo, $estimatedReelCost, $preferredKeyIds);

    if (!$reelKey) {
        return $empty + ['error' => 'No active Apify keys configured. Add one in Settings first.'];
    }

    $reelInput = [
        'username'               => [$username],
        'resultsLimit'           => $config['posts_to_check'],
        'skipPinnedPosts'        => true,
        'skipTrialReels'         => false,
        'includeSharesCount'     => false,
        'includeTranscript'      => true,
        'includeDownloadedVideo' => false,
    ];
    if ($mostRecentPostedAt) {
        $reelInput['onlyPostsNewerThan'] = date('Y-m-d', strtotime($mostRecentPostedAt));
    }

    $reelResults = run_apify_actor($config['reel_scraper_actor'], $reelInput, $reelKey['api_key']);

    if (!$reelResults) {
        if ($mostRecentPostedAt) {
            // Not a failure — this profile just hasn't posted anything new
            // since the last scrape.
            return $empty + ['error' => null];
        }
        return $empty + ['error' => 'Reel Scraper returned nothing — check Apify input field names or that this profile has public reels.'];
    }

    log_apify_usage($pdo, $reelKey['id'], 'instagram_reel_scraper', count($reelResults), $estimatedReelCost, $profileId);

    foreach ($reelResults as $reel) {
        $postUrl = $reel['url'] ?? null;
        if (!$postUrl) continue;

        // onlyPostsNewerThan is date-based, not exact-timestamp, so the
        // boundary post could come back again — skip if we already have it.
        $stmt = $pdo->prepare("SELECT 1 FROM post_transcripts WHERE profile_id = ? AND post_url = ?");
        $stmt->execute([$profileId, $postUrl]);
        if ($stmt->fetchColumn()) continue;

        $transcript = trim($reel['transcript'] ?? '');
        $needsManualReview = ($transcript === '') ? 1 : 0;

        $stmt = $pdo->prepare("
            INSERT INTO post_transcripts (profile_id, post_url, shortcode, caption, transcript, likes_count, comments_count, posted_at, needs_manual_review)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $profileId, $postUrl,
            $reel['shortCode'] ?? $reel['shortcode'] ?? null,
            $reel['caption'] ?? '',
            $transcript,
            (int) ($reel['likesCount'] ?? 0),
            (int) ($reel['commentsCount'] ?? 0),
            !empty($reel['timestamp']) ? date('Y-m-d H:i:s', strtotime($reel['timestamp'])) : null,
            $needsManualReview,
        ]);
    }

    // ---- Stage 2: Comment Scraper — CONCENTRATED on top-K highest-engagement posts only ----
    usort($reelResults, fn($a, $b) =>
        (($b['likesCount'] ?? 0) + ($b['commentsCount'] ?? 0)) - (($a['likesCount'] ?? 0) + ($a['commentsCount'] ?? 0))
    );
    $topPosts = array_slice($reelResults, 0, $config['top_k_for_comments']);
    $postUrls = array_values(array_filter(array_map(fn($r) => $r['url'] ?? null, $topPosts)));

    $allCommentsText = [];
    if ($postUrls) {
        $estimatedCommentTotal = count($postUrls) * $config['comments_per_post'];
        $estimatedCommentCost  = estimate_apify_cost($pdo, 'instagram_comment_scraper', $estimatedCommentTotal);
        $commentKey = pick_apify_key($pdo, $estimatedCommentCost, $preferredKeyIds);

        $commentResults = run_apify_actor($config['comment_scraper_actor'], [
            'directUrls'   => $postUrls,
            'resultsLimit' => $config['comments_per_post'],
        ], $commentKey['api_key']);

        if ($commentResults) {
            log_apify_usage($pdo, $commentKey['id'], 'instagram_comment_scraper', count($commentResults), $estimatedCommentCost, $profileId);

            foreach ($commentResults as $comment) {
                $text = $comment['text'] ?? $comment['commentText'] ?? '';
                if (!$text) continue;
                $allCommentsText[] = $text;

                $stmt = $pdo->prepare("
                    INSERT INTO post_comments (profile_id, post_url, commenter_username, comment_text, likes_count)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $profileId,
                    $comment['postUrl'] ?? $comment['inputUrl'] ?? '',
                    $comment['ownerUsername'] ?? $comment['username'] ?? '',
                    $text,
                    (int) ($comment['likesCount'] ?? 0),
                ]);
            }
        }
    }
    // If comment scraping fails or yields nothing, we deliberately continue
    // — the caller decides what that means for it.

    return [
        'reel_results'   => $reelResults,
        'comments_text'  => $allCommentsText,
        'post_urls_used' => $postUrls,
        'error'          => null,
    ];
}
