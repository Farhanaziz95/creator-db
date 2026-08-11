<?php
/**
 * The full pipeline for one lead, triggered manually (never bulk — this is
 * the expensive step). Steps:
 *   0. Cost-saver: if we already have transcripts/comments for this lead
 *      from within the last `cache_days`, skip Apify entirely and just
 *      re-run the AI passes on existing data (same as the "Retry AI Only"
 *      button, just automatic).
 *   1. Load the lead's gameplan text (must be uploaded first)
 *   2. Cost pre-flight + key pick, then run Reel Scraper (N recent posts,
 *      transcripts + captions)
 *   3. Concentrate comment-scraping budget on the TOP-K highest-engagement
 *      posts only, not all N — that's where the real audience signal is
 *      richest anyway, and it's the single biggest cost lever available
 *      without losing depth.
 *   4. Gemini Pass 1: does the gameplan's claimed problem/buying-intent
 *      actually show up in the real comments?
 *   5. Gemini Pass 2: draft a personalized message using real transcript
 *      content + the verification result
 *   6. Store everything, optionally attach as a Flozy task if this lead is
 *      already pushed to Flozy
 *
 * HONESTY NOTE ON APIFY INPUT FIELDS: username/resultsLimit/skipPinnedPosts/
 * includeTranscript for Reel Scraper and directUrls/resultsLimit for Comment
 * Scraper are CONFIRMED against the actors' real Input examples. Output
 * field names (url, shortCode, transcript, likesCount, etc.) are still
 * best-inference, not independently verified — if some fields come back
 * blank while others populate correctly, that's the likely spot to check.
 */

set_time_limit(0);
ignore_user_abort(true);

require_once __DIR__ . '/../includes/error_handler.php';
setup_json_error_handling();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/apify_client.php';
require_once __DIR__ . '/../includes/gemini_client.php';
require_once __DIR__ . '/../includes/message_generation.php';
header('Content-Type: application/json');

try {
    run_verification_pipeline($pdo);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unexpected error: ' . $e->getMessage() . ' (line ' . $e->getLine() . ' in ' . basename($e->getFile()) . ')']);
}

function fail_run(PDO $pdo, int $runId, string $message): void
{
    $stmt = $pdo->prepare("UPDATE content_analysis_runs SET status = 'failed', error_message = ?, finished_at = NOW() WHERE id = ?");
    $stmt->execute([$message, $runId]);
    http_response_code(500);
    echo json_encode(['error' => $message]);
}

function run_ai_passes_on_existing_data(PDO $pdo, int $profileId, string $gameplanText, int $runId): void
{
    $stmt = $pdo->prepare("SELECT caption, transcript FROM post_transcripts WHERE profile_id = ? AND (review_status != 'excluded' OR review_status IS NULL) ORDER BY fetched_at DESC");
    $stmt->execute([$profileId]);
    $transcripts = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT comment_text FROM post_comments WHERE profile_id = ? ORDER BY fetched_at DESC LIMIT 300");
    $stmt->execute([$profileId]);
    $comments = array_column($stmt->fetchAll(), 'comment_text');

    [$verificationSummary, $draftHook, $draftFollowup] = run_gemini_passes($pdo, $profileId, $gameplanText, $transcripts, $comments);

    $stmt = $pdo->prepare("
        UPDATE content_analysis_runs
        SET status = 'done', posts_checked = ?, comments_checked = ?, verification_summary = ?, draft_hook = ?, draft_message = ?, finished_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([count($transcripts), count($comments), $verificationSummary, $draftHook, $draftFollowup, $runId]);

    attach_flozy_task_if_pushed($pdo, $profileId, $verificationSummary, $draftHook, $draftFollowup);

    echo json_encode([
        'success' => true, 'posts_checked' => count($transcripts), 'comments_checked' => count($comments),
        'verification_summary' => $verificationSummary, 'draft_hook' => $draftHook, 'draft_message' => $draftFollowup,
        'reused_cached_data' => true,
    ]);
}

function run_gemini_passes(PDO $pdo, int $profileId, string $gameplanText, array $transcripts, array $comments): array
{
    $commentsBlob = implode("\n", array_slice($comments, 0, 300));
    $transcriptsBlob = implode("\n\n---\n\n", array_map(
        fn($r) => "Caption: " . ($r['caption'] ?? '') . "\nTranscript: " . ($r['transcript'] ?? ''),
        $transcripts
    ));

    $verificationPrompt = render_prompt_template($pdo, 'verification', [
        'gameplan' => $gameplanText,
        'comments' => $commentsBlob ?: '(no comments were retrieved for this run)',
    ]);

    $verificationResult = call_gemini($verificationPrompt, 500);
    $verificationSummary = $verificationResult['text'] ?? ('AI verification failed: ' . implode(' | ', $verificationResult['errors']));

    $messageAngle = get_message_angle($pdo, $profileId);
    $message = generate_hook_and_followup($pdo, $transcriptsBlob, $verificationSummary, $messageAngle);

    return [$verificationSummary, $message['hook'], $message['followup']];
}

function attach_flozy_task_if_pushed(PDO $pdo, int $profileId, string $verificationSummary, string $draftHook, string $draftFollowup): void
{
    $stmt = $pdo->prepare("SELECT flozy_lead_id FROM flozy_leads WHERE profile_id = ?");
    $stmt->execute([$profileId]);
    $flozyLeadId = $stmt->fetchColumn();

    if ($flozyLeadId) {
        require_once __DIR__ . '/../includes/flozy_client.php';
        flozy_request('POST', '/tasks', [
            'title'       => 'Verification & Personalized Message Ready',
            'description' => "VERIFICATION:\n{$verificationSummary}\n\nHOOK (opener):\n{$draftHook}\n\nFOLLOW-UP:\n{$draftFollowup}",
            'status'      => 1,
            'priority'    => 3,
            'lead_id'     => $flozyLeadId,
        ]);
    }
}

function run_verification_pipeline(PDO $pdo): void
{
    $input     = json_decode(file_get_contents('php://input'), true);
    $profileId = (int) ($input['profile_id'] ?? 0);
    $forceRescrape = !empty($input['force_rescrape']);
    // Scheduled sweep passes today's randomly-chosen key subset — null
    // means normal behavior (any active key eligible).
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

    $stmt = $pdo->prepare("SELECT extracted_text FROM gameplans WHERE profile_id = ?");
    $stmt->execute([$profileId]);
    $gameplanText = $stmt->fetchColumn();

    if (!$gameplanText) {
        http_response_code(400);
        echo json_encode(['error' => 'No gameplan uploaded for this lead yet (or text extraction failed on upload).']);
        return;
    }

    $runStmt = $pdo->prepare("INSERT INTO content_analysis_runs (profile_id, status) VALUES (?, 'running')");
    $runStmt->execute([$profileId]);
    $runId = $pdo->lastInsertId();

    // ---- Cost-saver: reuse recent data instead of re-scraping ----
    if (!$forceRescrape) {
        $stmt = $pdo->prepare("SELECT MAX(fetched_at) FROM post_transcripts WHERE profile_id = ?");
        $stmt->execute([$profileId]);
        $lastFetched = $stmt->fetchColumn();

        if ($lastFetched && (time() - strtotime($lastFetched)) < ($config['cache_days'] * 86400)) {
            run_ai_passes_on_existing_data($pdo, $profileId, $gameplanText, $runId);
            return;
        }
    }

    // ---- Stage 1: Reel Scraper ----
    // If we've scraped this profile before, only ask for reels newer than
    // the most recent one already stored — confirmed real Apify param
    // (apify.com/apify/instagram-reel-scraper/input-schema#onlyPostsNewerThan).
    // Avoids re-paying for transcripts (billed per minute of audio, NOT
    // per-1000-results as the cost table previously assumed) on content
    // already fetched — matters most for the budget sweep hitting the
    // same leads repeatedly.
    $stmt = $pdo->prepare("SELECT MAX(posted_at) FROM post_transcripts WHERE profile_id = ? AND posted_at IS NOT NULL");
    $stmt->execute([$profileId]);
    $mostRecentPostedAt = $stmt->fetchColumn();

    $estimatedReelCost = estimate_apify_cost($pdo, 'instagram_reel_scraper', $config['posts_to_check']);
    $reelKey = pick_apify_key($pdo, $estimatedReelCost, $preferredKeyIds);

    if (!$reelKey) {
        fail_run($pdo, $runId, 'No active Apify keys configured. Add one in Settings first.');
        return;
    }

    $reelInput = [
        'username'               => [$profile['username']],
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
            // since the last scrape. Re-run AI on existing data instead of
            // burning an Apify call just to confirm nothing changed.
            run_ai_passes_on_existing_data($pdo, $profileId, $gameplanText, $runId);
            return;
        }
        fail_run($pdo, $runId, 'Reel Scraper returned nothing — check Apify input field names or that this profile has public reels.');
        return;
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
        // No actual spoken transcript came back — likely music-only/b-roll
        // content, not the creator talking. No OCR available, so this
        // needs a human glance before trusting any AI personalization
        // built from it.
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
    // This is the main cost lever: a highly-engaged post naturally yields
    // close to the full per-post comment cap regardless, so fetching
    // comments from all N posts scales cost with N for little extra
    // signal. The richest audience-intent signal lives on the
    // highest-engagement posts anyway, so we concentrate spend there
    // instead of spreading it thin.
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
    // — verification will just note comments weren't available,
    // personalization can still work off transcripts alone.

    // ---- Stage 3 & 4: Gemini passes ----
    [$verificationSummary, $draftHook, $draftFollowup] = run_gemini_passes($pdo, $profileId, $gameplanText, $reelResults, $allCommentsText);

    $stmt = $pdo->prepare("
        UPDATE content_analysis_runs
        SET status = 'done', posts_checked = ?, comments_checked = ?, verification_summary = ?, draft_hook = ?, draft_message = ?, finished_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([count($reelResults), count($allCommentsText), $verificationSummary, $draftHook, $draftFollowup, $runId]);

    attach_flozy_task_if_pushed($pdo, $profileId, $verificationSummary, $draftHook, $draftFollowup);

    echo json_encode([
        'success'              => true,
        'posts_checked'        => count($reelResults),
        'comments_checked'     => count($allCommentsText),
        'comments_concentrated_on' => count($postUrls) . ' of ' . count($reelResults) . ' posts',
        'verification_summary' => $verificationSummary,
        'draft_hook'           => $draftHook,
        'draft_message'        => $draftFollowup,
        'reused_cached_data'   => false,
    ]);
}
