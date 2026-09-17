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
 * Optional secondary discovery path (previously on hold, now built) —
 * hashtag-based instead of keyword-search-based, using
 * apify/social-media-hashtag-research restricted to YouTube only.
 *
 * The hashtag actor only returns VIDEO-level data for YouTube (title,
 * video URL, channel NAME — no subscriber count, no channel URL at
 * all), so each result needs one follow-up call through the MAIN
 * youtube_scraper actor (single video URL) to resolve the actual
 * channel URL — confirmed from a real single-video output sample that
 * this attaches channelUrl/channelName/numberOfSubscribers to an
 * individual video result. That resolve call is billed under the normal
 * 'youtube_scraper' cost bucket, same as any other discovery-stage call
 * — only the hashtag lookup itself uses the separate
 * 'youtube_hashtag_scraper' cost key (different actor, different rate).
 *
 * One v1 simplification worth knowing: a resolve call fires per
 * hashtag-returned video even if two videos turn out to share the same
 * channel — cheap enough at typical maxPerSocial values not to bother
 * de-duping before resolving, but worth revisiting if hashtag rounds
 * get large.
 *
 * One assumption not 100% confirmed: the `socials` input field's
 * accepted value for YouTube is the string "youtube" (matches the
 * output's own `fromSocial: "youtube"` field, but the input schema page
 * didn't spell out its enum values explicitly) — first thing to check
 * if this comes back empty.
 */
function youtube_discover_channels_by_hashtag(PDO $pdo, string $hashtag, int $maxResults, ?array $preferredKeyIds, string $hashtagActorSlug, string $scraperActorSlug): array
{
    $estimatedCost = estimate_apify_cost($pdo, 'youtube_hashtag_scraper', $maxResults);
    $key = pick_apify_key($pdo, $estimatedCost, $preferredKeyIds);
    if (!$key) {
        return [];
    }

    $hashtagClean = ltrim(trim($hashtag), '#');

    $results = run_apify_actor($hashtagActorSlug, [
        'hashtags'     => [$hashtagClean],
        'socials'      => ['youtube'],
        'maxPerSocial' => $maxResults,
    ], $key['api_key']);

    if (!$results) {
        return [];
    }

    log_apify_usage($pdo, $key['id'], 'youtube_hashtag_scraper', count($results), $estimatedCost, null);

    $seen = [];
    $channels = [];

    foreach ($results as $r) {
        if (($r['fromSocial'] ?? '') !== 'youtube') continue; // defensive — we only asked for youtube, but be defensive about what comes back
        $videoUrl = $r['postUrl'] ?? null;
        if (!$videoUrl) continue;

        $resolveCost = estimate_apify_cost($pdo, 'youtube_scraper', 1);
        $resolveKey = pick_apify_key($pdo, $resolveCost, $preferredKeyIds);
        if (!$resolveKey) continue;

        $videoResult = run_apify_actor($scraperActorSlug, [
            'startUrls'         => [['url' => $videoUrl]],
            'maxResultsShorts'  => 0,
            'maxResultStreams'  => 0,
        ], $resolveKey['api_key']);

        if (!$videoResult) continue;
        log_apify_usage($pdo, $resolveKey['id'], 'youtube_scraper', count($videoResult), $resolveCost, null);

        $channelUrl = $videoResult[0]['channelUrl'] ?? null;
        if (!$channelUrl || isset($seen[$channelUrl])) continue;
        $seen[$channelUrl] = true;

        $channels[] = [
            'channel_url'  => $channelUrl,
            'channel_name' => $videoResult[0]['channelName'] ?? ($r['authorMeta.name'] ?? null),
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
 * Returns: ['titles' => array, 'descriptions' => array, 'urls' => array]
 * `urls` is stored on the channel row (sample_video_urls) so Comment
 * Insight can reuse this exact sample instead of re-running this stage.
 */
function youtube_fetch_recent_videos(PDO $pdo, string $channelUrl, int $count, ?array $preferredKeyIds, string $actorSlug): array
{
    $empty = ['titles' => [], 'descriptions' => [], 'urls' => []];
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
    $urls = [];
    foreach ($results as $r) {
        if (!empty($r['title'])) $titles[] = $r['title'];
        if (!empty($r['text'])) $descriptions[] = $r['text'];
        if (!empty($r['url'])) $urls[] = $r['url'];
    }

    return ['titles' => $titles, 'descriptions' => $descriptions, 'urls' => $urls];
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

/**
 * Stage 4 — Comment Insight input (additive, optional; only ever called
 * from the explicit "Get Comment Insight" action, never automatically).
 * SEPARATE actor from the one used everywhere above — the main scraper
 * has no comment-scraping capability at all — but same "Maintained by
 * Apify" standard confirmed on its own store page.
 *
 * $videoUrls is the SAME sample Stage 3 already pulled for scoring
 * (stored on the channel row as sample_video_urls) — reused here rather
 * than re-scraping a fresh video list, so this stage doesn't need its
 * own video-count setting, only its own comments_per_video.
 *
 * NOTE: this actor's own docs don't document a sort-order input, unlike
 * some competing comment scrapers — comments come back in whatever
 * default order the actor returns, not a guaranteed "Top comments"
 * order. Worth checking a real run's actual order before relying on it.
 */
function youtube_fetch_comments(PDO $pdo, array $videoUrls, int $perVideo, ?array $preferredKeyIds, string $actorSlug): array
{
    if (!$videoUrls || $perVideo <= 0) {
        return [];
    }

    $estimatedCost = estimate_apify_cost($pdo, 'youtube_comments_scraper', count($videoUrls) * $perVideo);
    $key = pick_apify_key($pdo, $estimatedCost, $preferredKeyIds);
    if (!$key) {
        return [];
    }

    $startUrls = array_map(fn($u) => ['url' => $u, 'method' => 'GET'], $videoUrls);

    $results = run_apify_actor($actorSlug, [
        'startUrls'   => $startUrls,
        'maxComments' => $perVideo,
    ], $key['api_key']);

    if (!$results) {
        return [];
    }

    log_apify_usage($pdo, $key['id'], 'youtube_comments_scraper', count($results), $estimatedCost, null);

    $comments = [];
    foreach ($results as $r) {
        $text = $r['comment'] ?? '';
        if ($text) $comments[] = $text;
    }

    return $comments;
}
