-- YouTube pipeline: Comment Insight (Layer 4, additive — never touches
-- youtube_scores or the existing 5-bucket total/grade/verdict).
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

-- Live-editable, same pattern as video_sample_count/gemini_call_delay_seconds.
ALTER TABLE youtube_settings
    ADD COLUMN comments_per_video INT NOT NULL DEFAULT 15;

-- Confirmed pricing from streamers/youtube-comments-scraper's own store
-- page ("Maintained by Apify" badge confirmed) — $0.90 per 1,000 comments.
INSERT INTO apify_actor_costs (actor_key, label, cost_per_1000, notes) VALUES
('youtube_comments_scraper', 'YouTube Comments Scraper (streamers/youtube-comments-scraper)', 0.900, 'Maintained by Apify — confirmed from Apify store page');

-- Stage 3 (video sampling for scoring) already picks specific videos per
-- channel — storing their URLs means Comment Insight can reuse the exact
-- same sample instead of re-running Stage 3 or needing its own
-- video-count setting.
ALTER TABLE youtube_channels
    ADD COLUMN sample_video_urls JSON NULL AFTER sample_video_descriptions;

-- One row per channel — its own scoring, deliberately separate from
-- youtube_scores. Buying Intent and Pain Point Clarity are the two
-- confirmed sub-scores; recurring_themes and summary are free text.
CREATE TABLE IF NOT EXISTS youtube_comment_insights (
    channel_id INT PRIMARY KEY,
    buying_intent_score INT NOT NULL,
    pain_point_clarity_score INT NOT NULL,
    recurring_themes JSON NULL,
    summary TEXT NULL,
    comments_analyzed INT NOT NULL DEFAULT 0,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (channel_id) REFERENCES youtube_channels(id)
);
