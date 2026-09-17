<?php
/**
 * YouTube pipeline, Layer 1: runs discovery + channel-detail for every
 * sub-niche keyword on a round (primary path), plus every hashtag if
 * any are set on the round (optional secondary path, purely additive —
 * a round with no hashtags behaves exactly as before). Inserts raw
 * candidates into youtube_channels. Deliberately does NOT run Stage 3
 * (video sampling) or any AI scoring here — those are their own step
 * (Layer 2), same split as Instagram's "Verify Only" vs "Retry AI Only":
 * scrape now, judge later.
 */
set_time_limit(0);
ignore_user_abort(true);

require_once __DIR__ . '/../includes/error_handler.php';
setup_json_error_handling();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/youtube_scrape.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$roundId = (int) ($input['round_id'] ?? 0);
$preferredKeyIds = isset($input['preferred_key_ids']) ? array_map('intval', $input['preferred_key_ids']) : null;

$config = require __DIR__ . '/../config/youtube.php';
$actorSlug = $config['youtube_scraper_actor'];
$hashtagActorSlug = $config['youtube_hashtag_actor'];

$stmt = $pdo->prepare("SELECT * FROM youtube_rounds WHERE id = ?");
$stmt->execute([$roundId]);
$round = $stmt->fetch();

if (!$round) {
    http_response_code(404);
    echo json_encode(['error' => 'Round not found.']);
    exit;
}

$subNiches = json_decode($round['sub_niches'], true) ?: [];
$hashtags = $round['hashtags'] ? (json_decode($round['hashtags'], true) ?: []) : [];
$subscriberMin = (int) $round['subscriber_min'];
$maxPerKeyword = (int) $round['max_channels_per_keyword'];

$insertStmt = $pdo->prepare("
    INSERT IGNORE INTO youtube_channels
        (round_id, channel_url, youtube_channel_id, channel_username, channel_name, sub_niche, subscribers, total_videos, total_views,
         is_verified, country, channel_description, email, email_source, website, social_links, needs_manual_review)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$discovered = 0;
$detailFetched = 0;
$belowSubscriberFloor = 0;
$detailFailed = 0;

/**
 * Shared by both the keyword loop and the hashtag loop below — fetches
 * Stage 2 detail for one candidate, applies the subscriber floor, and
 * inserts if it passes. $sourceLabel goes into the sub_niche column so
 * hashtag-sourced channels are visually distinguishable from
 * keyword-sourced ones (e.g. "#dividendinvesting" vs "dividend investing").
 */
function process_candidate(PDO $pdo, PDOStatement $insertStmt, int $roundId, array $candidate, string $sourceLabel, int $subscriberMin, ?array $preferredKeyIds, string $actorSlug, array &$counters): void
{
    $counters['discovered']++;

    $detail = youtube_fetch_channel_detail($pdo, $candidate['channel_url'], $preferredKeyIds, $actorSlug);

    if (!$detail) {
        $counters['detailFailed']++;
        return;
    }
    $counters['detailFetched']++;

    if ($detail['subscribers'] !== null && $detail['subscribers'] < $subscriberMin) {
        $counters['belowSubscriberFloor']++;
        return;
    }

    $insertStmt->execute([
        $roundId,
        $candidate['channel_url'],
        $detail['youtube_channel_id'],
        $detail['channel_username'],
        $detail['channel_name'] ?: $candidate['channel_name'],
        $sourceLabel,
        $detail['subscribers'],
        $detail['total_videos'],
        $detail['total_views'],
        $detail['is_verified'],
        $detail['country'],
        $detail['channel_description'],
        $detail['email'],
        $detail['email'] ? 'regex' : null,
        $detail['website'],
        json_encode($detail['social_links']),
        $detail['channel_description'] === '' ? 1 : 0, // empty About text needs a human glance, same philosophy as Instagram's empty-transcript flag
    ]);
}

$counters = ['discovered' => 0, 'detailFetched' => 0, 'belowSubscriberFloor' => 0, 'detailFailed' => 0];

// Primary path — keyword search.
foreach ($subNiches as $keyword) {
    $candidates = youtube_discover_channels($pdo, $keyword, $maxPerKeyword, $preferredKeyIds, $actorSlug);
    foreach ($candidates as $candidate) {
        // Skip if this exact channel is already in THIS round (the same
        // channel can legitimately surface under multiple keywords/
        // hashtags within one round — first source to find it wins the
        // sub_niche label, INSERT IGNORE handles the rest).
        process_candidate($pdo, $insertStmt, $roundId, $candidate, $keyword, $subscriberMin, $preferredKeyIds, $actorSlug, $counters);
    }
}

// Optional secondary path — hashtags, if any are set on this round.
foreach ($hashtags as $hashtag) {
    $candidates = youtube_discover_channels_by_hashtag($pdo, $hashtag, $maxPerKeyword, $preferredKeyIds, $hashtagActorSlug, $actorSlug);
    foreach ($candidates as $candidate) {
        process_candidate($pdo, $insertStmt, $roundId, $candidate, '#' . ltrim($hashtag, '#'), $subscriberMin, $preferredKeyIds, $actorSlug, $counters);
    }
}

echo json_encode([
    'success'                 => true,
    'discovered'              => $counters['discovered'],
    'detail_fetched'          => $counters['detailFetched'],
    'detail_failed'           => $counters['detailFailed'],
    'below_subscriber_floor'  => $counters['belowSubscriberFloor'],
    'saved'                   => $counters['discovered'] - $counters['detailFailed'] - $counters['belowSubscriberFloor'],
]);
