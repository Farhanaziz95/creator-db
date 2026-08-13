<?php
return [
    'posts_to_check'        => 8,   // N — how many recent reels/posts to pull per lead
    'comments_per_post'     => 12,  // lowered from 20 — still plenty for qualitative theme detection, meaningful cost cut
    'top_k_for_comments'    => 4,   // only pull comments from the K highest-engagement posts of the N pulled, not all of them
    'cache_days'            => 21,  // if we already scraped this lead within N days, reuse it instead of paying again
    'reel_scraper_actor'    => 'apify~instagram-reel-scraper',
    'comment_scraper_actor' => 'apify~instagram-comment-scraper',
];
