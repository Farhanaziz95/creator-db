<?php
/**
 * YouTube pipeline, Layer 1: runs discovery + channel-detail for every
 * sub-niche keyword on a round, inserting raw candidates into
 * youtube_channels. Deliberately does NOT run Stage 3 (video sampling)
 * or any AI scoring here — those are their own step (Layer 2), same
 * split as Instagram's "Verify Only" vs "Retry AI Only": scrape now,
 * judge later.
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

$stmt = $pdo->prepare("SELECT * FROM youtube_rounds WHERE id = ?");
$stmt->execute([$roundId]);
$round = $stmt->fetch();

if (!$round) {
    http_response_code(404);
    echo json_encode(['error' => 'Round not found.']);
    exit;
}

$subNiches = json_decode($round['sub_niches'], true) ?: [];
$subscriberMin = (int) $round['subscriber_min'];
$maxPerKeyword = (int) $round['max_channels_per_keyword'];

$insertStmt = $pdo->prepare("
    INSERT IGNORE INTO youtube_channels
        (round_id, channel_url, channel_name, sub_niche, subscribers, total_videos, total_views,
         is_monetized, country, channel_description, email, email_source, website, social_links, needs_manual_review)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$discovered = 0;
$detailFetched = 0;
$belowSubscriberFloor = 0;
$detailFailed = 0;

foreach ($subNiches as $keyword) {
    $candidates = youtube_discover_channels($pdo, $keyword, $maxPerKeyword, $preferredKeyIds, $actorSlug);

    foreach ($candidates as $candidate) {
        $discovered++;

        // Skip if this exact channel is already in THIS round (the
        // same channel can legitimately surface under multiple
        // sub-niche keywords within one round — first keyword to find
        // it wins the sub_niche label, INSERT IGNORE handles the rest).
        $detail = youtube_fetch_channel_detail($pdo, $candidate['channel_url'], $preferredKeyIds, $actorSlug);

        if (!$detail) {
            $detailFailed++;
            continue;
        }
        $detailFetched++;

        if ($detail['subscribers'] !== null && $detail['subscribers'] < $subscriberMin) {
            $belowSubscriberFloor++;
            continue;
        }

        $insertStmt->execute([
            $roundId,
            $candidate['channel_url'],
            $detail['channel_name'] ?: $candidate['channel_name'],
            $keyword,
            $detail['subscribers'],
            $detail['total_videos'],
            $detail['total_views'],
            $detail['is_monetized'],
            $detail['country'],
            $detail['channel_description'],
            $detail['email'],
            $detail['email'] ? 'regex' : null,
            $detail['website'],
            json_encode($detail['social_links']),
            $detail['channel_description'] === '' ? 1 : 0, // empty About text needs a human glance, same philosophy as Instagram's empty-transcript flag
        ]);
    }
}

echo json_encode([
    'success'                 => true,
    'discovered'               => $discovered,
    'detail_fetched'           => $detailFetched,
    'detail_failed'            => $detailFailed,
    'below_subscriber_floor'   => $belowSubscriberFloor,
    'saved'                    => $discovered - $detailFailed - $belowSubscriberFloor,
]);
