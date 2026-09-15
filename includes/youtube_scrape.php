<?php
require_once __DIR__ . '/apify_client.php';
require_once __DIR__ . '/email_extraction.php'; // extract_email_from_bio() — generic text->email regex, reused as-is for channel_description

/**
 * YouTube pipeline, Layer 1. Three stages, ONE actor
 * (streamers/youtube-scraper — see config/youtube.php), just different
 * input shapes per call — mirrors Instagram's cheap-discovery-then-
 * concentrated-detail cost philosophy.
 *
 * Field names below are confirmed from the actor's OWN official example
 * (apify.com/streamers/youtube-scraper/examples/find-youtube-influencers.md)
 * and a real raw output sample pulled from an actual run — not guessed
 * or borrowed from a different actor's schema. Confirmed from that real
 * sample: pointing startUrls at a channel's `/about` page (rather than
 * its bare URL or `/videos`) is what returns the full channel-detail
 * record (numberOfSubscribers, channelDescription, isChannelVerified,
 * etc.) — and confirmed there is NO monetization field anywhere in this
 * actor's output (Layer 1 originally guessed 'isMonetized' from
 * marketing copy; it doesn't exist and came back null on every row).
 *
 * IMPORTANT: maxResultsShorts and maxResultStreams must be explicitly
 * set to 0 on every call — the actor's own issue tracker confirms that
 * omitting them can mean "unlimited" rather than "none" on some
 * versions, which would silently balloon cost.
 */

/**
 * Stage 1 — discovery. Cheap, thin results (no subscriber count yet) —
 * just enough to build a deduped candidate channel list per keyword.
 * Returns: array of ['channel_url' => string, 'channel_name' => string]
 */
function youtube_discover_channels(PDO $pdo, string $keyword, int $maxResults, ?array $preferredKeyIds, string $actorSlug): array
{
    $estimatedCost = estimate_apify_cost($pdo, 'youtube_scraper', $maxResults);
    $key = pick_apify_key($pdo, $estimatedCost, $preferredKeyIds);
    if (!$key) {
        return [];
    }

    $results = run_apify_actor($actorSlug, [
        'searchQueries'     => [$keyword],
        'maxResults'        => $maxResults,
        'maxResultsShorts'  => 0,
        'maxResultStreams'  => 0,
    ], $key['api_key']);

    if (!$results) {
        return [];
    }

    log_apify_usage($pdo, $key['id'], 'youtube_scraper', count($results), $estimatedCost, null);

    $seen = [];
    $channels = [];
    foreach ($results as $r) {
        $url = $r['channelUrl'] ?? null;
        if (!$url || isset($seen[$url])) {
            continue;
        }
        $seen[$url] = true;
        $channels[] = [
            'channel_url'  => $url,
            'channel_name' => $r['channelName'] ?? null,
        ];
    }

    return $channels;
}

/**
 * Stage 2 — channel detail. One call per candidate channel (the
 * "/about" URL — see docblock above). This is where subscriber-count
 * filtering becomes possible at all, and where email gets regex-
 * extracted from channel_description exactly like an Instagram bio.
 * Returns null on failure (channel gone, actor returned nothing).
 */
function youtube_fetch_channel_detail(PDO $pdo, string $channelUrl, ?array $preferredKeyIds, string $actorSlug): ?array
{
    $estimatedCost = estimate_apify_cost($pdo, 'youtube_scraper', 1);
    $key = pick_apify_key($pdo, $estimatedCost, $preferredKeyIds);
    if (!$key) {
        return null;
    }

    $aboutUrl = rtrim($channelUrl, '/') . '/about';

    $results = run_apify_actor($actorSlug, [
        'startUrls'         => [['url' => $aboutUrl]],
        'maxResultsShorts'  => 0,
        'maxResultStreams'  => 0,
    ], $key['api_key']);

    if (!$results) {
        return null;
    }

    log_apify_usage($pdo, $key['id'], 'youtube_scraper', count($results), $estimatedCost, null);

    $r = $results[0];
    $description = $r['channelDescription'] ?? '';

    return [
        'channel_name'        => $r['channelName'] ?? null,
        'youtube_channel_id'  => $r['channelId'] ?? null,
        'channel_username'    => $r['channelUsername'] ?? null,
        'subscribers'         => isset($r['numberOfSubscribers']) ? (int) $r['numberOfSubscribers'] : null,
        'total_videos'        => isset($r['channelTotalVideos']) ? (int) $r['channelTotalVideos'] : null,
        'total_views'         => isset($r['channelTotalViews']) ? (int) str_replace(',', '', (string) $r['channelTotalViews']) : null,
        // Confirmed from a real raw sample: this actor has NO
        // monetization field at all — 'isMonetized' (Layer 1's original
        // guess) never existed and always came back null. The real,
        // useful field here is isChannelVerified (YouTube's verified
        // checkmark) — a trust/scale signal, not a monetization one.
        'is_verified'         => isset($r['isChannelVerified']) ? (int) (bool) $r['isChannelVerified'] : null,
        'country'             => $r['channelLocation'] ?? null,
        'channel_description' => $description,
        'email'               => extract_email_from_bio($description),
        // channelDescriptionLinks is an array of {text, url} — first URL
        // that isn't a known social platform is our best guess at
        // "website"; anything social-looking goes in social_links
        // instead. Kept deliberately simple for v1 — a manual "website"
        // edit field can cover what this heuristic misses, same
        // philosophy as the editable email column.
        'website'             => youtube_guess_website($r['channelDescriptionLinks'] ?? []),
        'social_links'        => youtube_extract_socials($r['channelDescriptionLinks'] ?? []),
    ];
}

/**
 * Stage 3 — content signal. Only run for channels that already survived
 * the subscriber filter (this is the "concentrated spend" stage, same
 * role as Instagram's Comment Scraper only hitting top-K posts).
 * $count comes from the live-editable youtube_settings.video_sample_count
 * — never hardcode it here.
 * Returns: ['titles' => array, 'descriptions' => array]
 */
function youtube_fetch_recent_videos(PDO $pdo, string $channelUrl, int $count, ?array $preferredKeyIds, string $actorSlug): array
{
    $empty = ['titles' => [], 'descriptions' => []];
    if ($count <= 0) {
        return $empty;
    }

    $estimatedCost = estimate_apify_cost($pdo, 'youtube_scraper', $count);
    $key = pick_apify_key($pdo, $estimatedCost, $preferredKeyIds);
    if (!$key) {
        return $empty;
    }

    $videosUrl = rtrim($channelUrl, '/') . '/videos';

    $results = run_apify_actor($actorSlug, [
        'startUrls'         => [['url' => $videosUrl]],
        'maxResults'        => $count,
        'maxResultsShorts'  => 0,
        'maxResultStreams'  => 0,
    ], $key['api_key']);

    if (!$results) {
        return $empty;
    }

    log_apify_usage($pdo, $key['id'], 'youtube_scraper', count($results), $estimatedCost, null);

    $titles = [];
    $descriptions = [];
    foreach ($results as $r) {
        if (!empty($r['title'])) $titles[] = $r['title'];
        if (!empty($r['text'])) $descriptions[] = $r['text'];
    }

    return ['titles' => $titles, 'descriptions' => $descriptions];
}

/**
 * Very simple v1 heuristic — anything NOT matching a known social
 * platform domain is treated as "website". Good enough to seed the
 * column; a wrong guess is manually correctable the same way a missed
 * email is.
 */
function youtube_guess_website(array $descriptionLinks): ?string
{
    $socialDomains = ['instagram.com', 'twitter.com', 'x.com', 'tiktok.com', 'facebook.com', 'discord.com', 'discord.gg', 'linkedin.com', 'threads.net', 'patreon.com'];
    foreach ($descriptionLinks as $link) {
        $url = $link['url'] ?? '';
        if (!$url) continue;
        $isSocial = false;
        foreach ($socialDomains as $domain) {
            if (stripos($url, $domain) !== false) { $isSocial = true; break; }
        }
        if (!$isSocial) {
            return $url;
        }
    }
    return null;
}

function youtube_extract_socials(array $descriptionLinks): array
{
    $socials = [];
    foreach ($descriptionLinks as $link) {
        $url = $link['url'] ?? '';
        if ($url) $socials[] = $url;
    }
    return $socials;
}
