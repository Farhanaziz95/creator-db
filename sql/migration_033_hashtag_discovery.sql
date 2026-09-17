-- Optional secondary discovery path (previously on hold, now confirmed
-- to build) — hashtag-based instead of keyword-search-based, using
-- apify/social-media-hashtag-research (genuinely published under
-- Apify's own org). Purely additive to a round; keyword search stays
-- the primary path.
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

ALTER TABLE youtube_rounds
    ADD COLUMN hashtags JSON NULL;

-- The wrapper actor itself is free but triggers
-- streamers/youtube-video-scraper-by-hashtag under the hood — confirmed
-- $2.00 per 1,000 results from the wrapper's own FAQ. Logged under its
-- own cost key since it's a different actor/rate than 'youtube_scraper'.
INSERT INTO apify_actor_costs (actor_key, label, cost_per_1000, notes) VALUES
('youtube_hashtag_scraper', 'Social Media Hashtag Research → YouTube (apify/social-media-hashtag-research)', 2.000, 'Wrapper is free; this is the underlying streamers/youtube-video-scraper-by-hashtag rate it triggers, confirmed from the wrapper''s own FAQ');
