<?php
return [
    // Confirmed: the actual actor slug from Apify — one actor, all 3
    // stages (search-by-keyword, channel-info, channel-videos), just
    // different input shapes per call.
    'youtube_scraper_actor' => 'streamers~youtube-scraper',
    'cache_days'            => 21,  // same "don't re-pay within N days" convention as Instagram's Verify+Personalize cache
    // video_sample_count lives in the DB (youtube_settings table), not
    // here — confirmed it must be live-editable from Settings, unlike
    // the values above which are still static/code-level for now.
];
