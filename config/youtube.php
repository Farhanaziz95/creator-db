<?php
return [
    // Confirmed: the actual actor slug from Apify — one actor, all 3
    // discovery/detail stages (search-by-keyword, channel-info, channel-videos),
    // just different input shapes per call.
    'youtube_scraper_actor' => 'streamers~youtube-scraper',
    // Comment Insight (Layer 4, additive) uses a SEPARATE actor —
    // Maintained by Apify, confirmed on its own store page — since the
    // main scraper above has no comment-scraping capability at all.
    'youtube_comments_actor' => 'streamers~youtube-comments-scraper',
    // Optional secondary discovery — hashtag-based, genuinely published
    // under Apify's own org (not just "Maintained by Apify" on a
    // third-party account). Purely additive per round; keyword search
    // stays primary.
    'youtube_hashtag_actor' => 'apify~social-media-hashtag-research',
    'cache_days'            => 21,  // same "don't re-pay within N days" convention as Instagram's Verify+Personalize cache
    // video_sample_count, comments_per_video, gemini_call_delay_seconds,
    // and scoring_batch_limit all live in the DB (youtube_settings
    // table), not here — confirmed they must be live-editable from
    // Settings, unlike the values above which are still static/code-level for now.
];
