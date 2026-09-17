<?php
/**
 * YouTube pipeline, Layer 4 (additive): "Get Comment Insight" — a
 * separate, optional, explicitly-triggered action. Never writes to
 * youtube_channels' status or youtube_scores — only to its own
 * youtube_comment_insights table. Prefers reusing whichever videos
 * Stage 3 already sampled for scoring (channel.sample_video_urls); if
 * that's empty (channel was scored before this column existed, or
 * scoring genuinely found nothing), fetches a fresh sample on the spot
 * rather than requiring a full re-score just to backfill a URL list —
 * see the fallback below. Only comments_per_video is a new setting.
 */
set_time_limit(0);
ignore_user_abort(true);

require_once __DIR__ . '/../includes/error_handler.php';
setup_json_error_handling();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/youtube_scrape.php';
require_once __DIR__ . '/../includes/youtube_comment_insight.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$channelId = isset($input['channel_id']) ? (int) $input['channel_id'] : null;
$channelIds = isset($input['channel_ids']) && is_array($input['channel_ids'])
    ? array_values(array_filter(array_map('intval', $input['channel_ids'])))
    : null;
$preferredKeyIds = isset($input['preferred_key_ids']) ? array_map('intval', $input['preferred_key_ids']) : null;

if (!$channelId && !$channelIds) {
    http_response_code(400);
    echo json_encode(['error' => 'channel_id or channel_ids is required.']);
    exit;
}

$youtubeConfig = require __DIR__ . '/../config/youtube.php';
$commentsActorSlug = $youtubeConfig['youtube_comments_actor'];
$scraperActorSlug = $youtubeConfig['youtube_scraper_actor'];

$settings = $pdo->query("SELECT video_sample_count, comments_per_video, gemini_call_delay_seconds FROM youtube_settings WHERE id = 1")->fetch();
$videoSampleCount = $settings ? (int) $settings['video_sample_count'] : 5;
$commentsPerVideo = $settings ? (int) $settings['comments_per_video'] : 15;
$callDelaySeconds = $settings ? (int) $settings['gemini_call_delay_seconds'] : 4;

// Same "explicit selection is never capped" treatment as scoring's
// channel_ids path — this action is always explicitly triggered anyway
// (there's no automatic sweep version of Comment Insight), so there's
// no batch-limit concept to bypass here in the first place.
$ids = $channelIds ?: [$channelId];
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM youtube_channels WHERE id IN ($placeholders)");
$stmt->execute($ids);
$channels = $stmt->fetchAll();

$upsertStmt = $pdo->prepare("
    INSERT INTO youtube_comment_insights
        (channel_id, buying_intent_score, pain_point_clarity_score, recurring_themes, summary, comments_analyzed)
    VALUES (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        buying_intent_score = VALUES(buying_intent_score),
        pain_point_clarity_score = VALUES(pain_point_clarity_score),
        recurring_themes = VALUES(recurring_themes),
        summary = VALUES(summary),
        comments_analyzed = VALUES(comments_analyzed),
        generated_at = CURRENT_TIMESTAMP
");

$analyzed = 0;
$skippedNoVideos = 0;
$skippedNoComments = 0;
$failed = 0;
$stoppedEarly = false;
$stopReason = null;

foreach ($channels as $i => $channel) {
    // Same pacing rationale as scoring — this is a second Gemini call
    // per channel on top of whatever scoring already used, sharing the
    // exact same free-tier quota with Instagram.
    if ($i > 0) {
        sleep($callDelaySeconds);
    }

    $videoUrls = $channel['sample_video_urls'] ? json_decode($channel['sample_video_urls'], true) : [];

    if (!$videoUrls) {
        // Scored before this feature existed (sample_video_urls wasn't
        // being saved yet), or scoring genuinely found no videos —
        // fetch a fresh video sample on the spot rather than requiring
        // a full re-score just to backfill a URL list. This does NOT
        // touch the channel's existing score/status — it only calls the
        // same Stage 3 scrape scoring already uses, then saves the URLs
        // for next time.
        $videos = youtube_fetch_recent_videos($pdo, $channel['channel_url'], $videoSampleCount, $preferredKeyIds, $scraperActorSlug);
        $videoUrls = $videos['urls'];

        if ($videoUrls) {
            $stmt = $pdo->prepare("UPDATE youtube_channels SET sample_video_urls = ? WHERE id = ?");
            $stmt->execute([json_encode($videoUrls), $channel['id']]);
        }
    }

    if (!$videoUrls) {
        // Even a fresh fetch came back empty — this channel genuinely
        // has no videos to pull comments from.
        $skippedNoVideos++;
        continue;
    }

    $comments = youtube_fetch_comments($pdo, $videoUrls, $commentsPerVideo, $preferredKeyIds, $commentsActorSlug);
    if (!$comments) {
        $skippedNoComments++;
        continue;
    }

    $result = analyze_comment_insight($channel, $comments);

    if (!empty($result['parse_failed'])) {
        $failed++;
        $errorText = strtolower(implode(' ', $result['errors'] ?? []));
        if (str_contains($errorText, 'quota') || str_contains($errorText, 'rate limit') || str_contains($errorText, '429') || str_contains($errorText, 'resource_exhausted')) {
            $stoppedEarly = true;
            $stopReason = 'Gemini quota/rate limit hit — stopped early to avoid blocking Instagram\'s AI features on the same key. Increase gemini_call_delay_seconds in Settings, or just re-run this later.';
            break;
        }
        continue;
    }

    $upsertStmt->execute([
        $channel['id'],
        $result['buying_intent_score'],
        $result['pain_point_clarity_score'],
        json_encode($result['recurring_themes']),
        $result['summary'],
        count($comments),
    ]);
    $analyzed++;
}

echo json_encode([
    'success'              => true,
    'total_seen'           => count($channels),
    'analyzed'             => $analyzed,
    'skipped_no_videos'    => $skippedNoVideos,
    'skipped_no_comments'  => $skippedNoComments,
    'failed'               => $failed,
    'stopped_early'        => $stoppedEarly,
    'stop_reason'          => $stopReason,
]);
