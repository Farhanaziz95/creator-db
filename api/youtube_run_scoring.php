<?php
/**
 * YouTube pipeline, Layer 2: runs Stage 3 (video sampling) + the AI
 * qualifying pass — for every 'raw' channel in a round (capped batch,
 * auto-resuming), a single channel (re-score), or an explicit hand-
 * picked list (channel_ids — bypasses the batch cap, since a person who
 * selected exactly what they want scored shouldn't be second-guessed).
 * Same "scrape now, judge later" split as Instagram's Verify Only vs
 * Retry AI Only.
 */
set_time_limit(0);
ignore_user_abort(true);

require_once __DIR__ . '/../includes/error_handler.php';
setup_json_error_handling();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/youtube_scrape.php';
require_once __DIR__ . '/../includes/youtube_scoring.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$roundId = isset($input['round_id']) ? (int) $input['round_id'] : null;
$channelId = isset($input['channel_id']) ? (int) $input['channel_id'] : null;
$channelIds = isset($input['channel_ids']) && is_array($input['channel_ids']) ? array_values(array_filter(array_map('intval', $input['channel_ids']))) : null;
$preferredKeyIds = isset($input['preferred_key_ids']) ? array_map('intval', $input['preferred_key_ids']) : null;

if (!$roundId && !$channelId && !$channelIds) {
    http_response_code(400);
    echo json_encode(['error' => 'round_id, channel_id, or channel_ids is required.']);
    exit;
}

$youtubeConfig = require __DIR__ . '/../config/youtube.php';
$actorSlug = $youtubeConfig['youtube_scraper_actor'];

$settings = $pdo->query("SELECT video_sample_count, gemini_call_delay_seconds, scoring_batch_limit FROM youtube_settings WHERE id = 1")->fetch();
$videoSampleCount = $settings ? (int) $settings['video_sample_count'] : 5;
$callDelaySeconds = $settings ? (int) $settings['gemini_call_delay_seconds'] : 4;
$batchLimit = $settings ? (int) $settings['scoring_batch_limit'] : 10;

if ($channelIds) {
    // An explicit hand-picked selection deliberately BYPASSES
    // scoring_batch_limit — that cap exists to stop an *automatic*
    // round-wide sweep from blowing through the shared Gemini quota
    // unattended, not to second-guess a person who sorted by subscriber
    // count, picked exactly the range they want scored, and asked for
    // it. The per-call pacing delay below still applies regardless —
    // that's the part actually protecting the quota, not the count.
    $placeholders = implode(',', array_fill(0, count($channelIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM youtube_channels WHERE id IN ($placeholders)");
    $stmt->execute($channelIds);
    $channels = $stmt->fetchAll();
} elseif ($channelId) {
    $stmt = $pdo->prepare("SELECT * FROM youtube_channels WHERE id = ?");
    $stmt->execute([$channelId]);
    $channels = array_filter([$stmt->fetch()]);
} else {
    // Only channels not yet scored, capped at scoring_batch_limit per
    // run — same "small batch per invocation, re-run to continue" shape
    // as the existing niche_queue_processor.php. This is what keeps a
    // single call to this endpoint from ever burning through the
    // Gemini free-tier quota in one go: re-scoring THIS round after a
    // capped run just picks up wherever it left off, since 'raw'
    // channels already scored move to 'qualified'/'rejected' and drop
    // out of this WHERE clause naturally.
    $stmt = $pdo->prepare("SELECT * FROM youtube_channels WHERE round_id = ? AND status = 'raw' LIMIT " . (int) $batchLimit);
    $stmt->execute([$roundId]);
    $channels = $stmt->fetchAll();
}

$updateChannelStmt = $pdo->prepare("
    UPDATE youtube_channels
    SET sample_video_titles = ?, sample_video_descriptions = ?, status = ?, needs_manual_review = ?, reject_reason = ?
    WHERE id = ?
");

$upsertScoreStmt = $pdo->prepare("
    INSERT INTO youtube_scores
        (channel_id, audience_score, engagement_score, monetization_score, content_score, opportunity_score,
         total_score, grade, ai_reasoning, ai_product_potential, ai_pain_opportunity, ai_verdict)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        audience_score = VALUES(audience_score), engagement_score = VALUES(engagement_score),
        monetization_score = VALUES(monetization_score), content_score = VALUES(content_score),
        opportunity_score = VALUES(opportunity_score), total_score = VALUES(total_score),
        grade = VALUES(grade), ai_reasoning = VALUES(ai_reasoning),
        ai_product_potential = VALUES(ai_product_potential), ai_pain_opportunity = VALUES(ai_pain_opportunity),
        ai_verdict = VALUES(ai_verdict), scored_at = CURRENT_TIMESTAMP
");

$scored = 0;
$qualified = 0;
$rejected = 0;
$needsReview = 0;
$failed = 0;
$stoppedEarly = false;
$stopReason = null;

foreach ($channels as $i => $channel) {
    // Pace every call, including the first — Stage 3's Apify call plus
    // the Gemini call together are the actual per-channel cost; the
    // delay is what keeps this under the shared free-tier RPM ceiling
    // regardless of how fast the rest of the loop runs.
    if ($i > 0) {
        sleep($callDelaySeconds);
    }

    $videos = youtube_fetch_recent_videos($pdo, $channel['channel_url'], $videoSampleCount, $preferredKeyIds, $actorSlug);

    $result = score_youtube_channel($channel, $videos['titles'], $videos['descriptions']);

    if (!empty($result['parse_failed'])) {
        $failed++;
        $updateChannelStmt->execute([
            json_encode($videos['titles']),
            json_encode($videos['descriptions']),
            $channel['status'], // leave status as-is on a scoring failure
            1,                  // flag for manual review — something needs a human look
            null,
            $channel['id'],
        ]);

        // A real quota/rate-limit hit means every remaining channel in
        // this batch would fail the exact same way — stop now rather
        // than burning through the rest of the list on calls that can't
        // succeed, and rather than risking Instagram's shared key
        // getting flagged further. An ordinary one-off parse hiccup
        // (bad JSON, a safety block on one weird channel) does NOT stop
        // the batch — only an explicit quota/rate signal does.
        $errorText = strtolower(implode(' ', $result['errors'] ?? []));
        if (str_contains($errorText, 'quota') || str_contains($errorText, 'rate limit') || str_contains($errorText, '429') || str_contains($errorText, 'resource_exhausted')) {
            $stoppedEarly = true;
            $stopReason = 'Gemini quota/rate limit hit — stopped early to avoid blocking Instagram\'s AI features on the same key for the rest of the day. Increase gemini_call_delay_seconds in Settings, or just re-run this later — it resumes from wherever it left off.';
            break;
        }
        continue;
    }

    $newStatus = $channel['status'];
    $rejectReason = null;
    $needsReviewFlag = 0;

    if ($result['verdict'] === 'qualify') {
        $newStatus = 'qualified';
        $qualified++;
    } elseif ($result['verdict'] === 'reject') {
        $newStatus = 'rejected';
        $rejectReason = 'AI: ' . ($result['pain_opportunity'] ?: 'scored below qualifying threshold');
        $rejected++;
    } else {
        $needsReviewFlag = 1; // stays 'raw' — surfaces in the Raw tab flagged for a human decision
        $needsReview++;
    }

    $updateChannelStmt->execute([
        json_encode($videos['titles']),
        json_encode($videos['descriptions']),
        $newStatus,
        $needsReviewFlag,
        $rejectReason,
        $channel['id'],
    ]);

    $s = $result['scores'];
    $upsertScoreStmt->execute([
        $channel['id'],
        $s['audience_score'], $s['engagement_score'], $s['monetization_score'], $s['content_score'], $s['opportunity_score'],
        $result['total_score'], $result['grade'], json_encode($result['reasoning']),
        $result['product_potential'], $result['pain_opportunity'], $result['verdict'],
    ]);

    $scored++;
}

echo json_encode([
    'success'      => true,
    'total_seen'   => count($channels),
    'scored'       => $scored,
    'qualified'    => $qualified,
    'rejected'     => $rejected,
    'needs_review' => $needsReview,
    'failed'       => $failed,
    'stopped_early' => $stoppedEarly,
    'stop_reason'   => $stopReason,
    'note'          => ($roundId && !$channelId && !$channelIds)
        ? "Processed up to {$batchLimit} per run (scoring_batch_limit in Settings) — re-run this same call to continue with the rest of the round."
        : null,
]);
