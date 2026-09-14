-- YouTube pipeline, Layer 1: schema.
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.
--
-- Deliberately parallel to the Instagram tables, not merged into them —
-- confirmed decision: separate tab, separate pipeline, only Flozy is the
-- shared destination. Reuses existing Instagram infrastructure that was
-- never actually Instagram-specific: `apify_keys` (same key pool, no
-- platform column), `pick_apify_key()`, `log_apify_usage()`,
-- `check_apify_key_budget()` — all actor-key-parameterized already, so
-- nothing there needs to change. Only `apify_actor_costs` needs a new row.

-- Confirmed from Apify's own pricing FAQ: streamers/youtube-scraper is
-- $5.00 per 1,000 results, price-per-result regardless of mode (video
-- search, channel-info, or channel-videos) — verify in Console if actual
-- multi-mode billing turns out to differ once real runs are logged.
INSERT INTO apify_actor_costs (actor_key, label, cost_per_1000, notes) VALUES
('youtube_scraper', 'YouTube Scraper (streamers/youtube-scraper)', 5.000, 'Confirmed from Apify store FAQ — same actor used for all 3 pipeline stages');

-- A round = one niche experiment batch (confirmed: the round itself IS
-- the niche label — no separate niches table needed, since every channel
-- pulled into a round is definitionally that round's niche by virtue of
-- the search keywords used to find it).
CREATE TABLE IF NOT EXISTS youtube_rounds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    niche VARCHAR(150) NOT NULL,
    sub_niches JSON NOT NULL,             -- the actual search keywords used, e.g. ["budgeting","dividend investing"]
    subscriber_min INT NOT NULL DEFAULT 1000,
    max_channels_per_keyword INT NOT NULL DEFAULT 100,
    status ENUM('active','complete') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- One row per candidate channel discovered in a round.
CREATE TABLE IF NOT EXISTS youtube_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    round_id INT NOT NULL,
    channel_url VARCHAR(500) NOT NULL,
    channel_name VARCHAR(255) NULL,
    sub_niche VARCHAR(150) NULL,           -- which search keyword surfaced this channel
    subscribers INT NULL,
    total_videos INT NULL,
    total_views BIGINT NULL,
    is_monetized TINYINT(1) NULL,          -- YouTube's own signal, straight from the scrape — no AI guessing needed
    country VARCHAR(100) NULL,
    channel_description TEXT NULL,         -- the About text — same role as an Instagram bio
    email VARCHAR(255) NULL,               -- regex-extracted from channel_description via the SAME extract_email_from_bio() Instagram already uses
    email_source ENUM('regex','manual') NULL,
    website VARCHAR(500) NULL,
    social_links JSON NULL,
    sample_video_titles JSON NULL,         -- Stage 3 content signal, feeds the AI qualifying pass
    sample_video_descriptions JSON NULL,
    status ENUM('raw','qualified','rejected','pushed') NOT NULL DEFAULT 'raw',
    needs_manual_review TINYINT(1) DEFAULT 0,
    reject_reason VARCHAR(255) NULL,       -- set when an auto-reject red flag fires, or a human rejects manually
    notes TEXT NULL,
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (round_id) REFERENCES youtube_rounds(id),
    UNIQUE KEY uniq_round_channel (round_id, channel_url),  -- de-dupe within a round, same channel can appear in multiple rounds
    INDEX idx_status (status),
    INDEX idx_email (email)
);

-- AI qualifying pass output — one row per channel, 5-bucket score package.
CREATE TABLE IF NOT EXISTS youtube_scores (
    channel_id INT PRIMARY KEY,
    audience_score INT NOT NULL,
    engagement_score INT NOT NULL,
    monetization_score INT NOT NULL,
    content_score INT NOT NULL,
    opportunity_score INT NOT NULL,
    total_score INT NOT NULL,
    grade CHAR(1) NULL,                    -- A/B/C/D, same tiering convention as Instagram's quality_score
    ai_reasoning JSON NOT NULL,            -- one short string per bucket — why it scored this way
    ai_product_potential TEXT NULL,
    ai_pain_opportunity TEXT NULL,
    ai_verdict ENUM('qualify','reject','needs_review') NOT NULL,
    scored_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (channel_id) REFERENCES youtube_channels(id)
);

-- Flozy bridge — exact same shape/pattern as flozy_leads, kept as its own
-- table rather than reusing flozy_leads so nothing about the proven
-- Instagram push path is touched. Email is already known at scrape time
-- here (no regex-extraction-after-the-fact gap the way Instagram had),
-- so the Contact push can fire in the SAME call as the Lead push —
-- no separate "Push Contact" catch-up step needed for YouTube.
CREATE TABLE IF NOT EXISTS youtube_flozy_leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_id INT NOT NULL UNIQUE,
    flozy_lead_id INT NULL,
    flozy_opportunity_id INT NULL,
    flozy_contact_id INT NULL,
    current_stage VARCHAR(100) NULL,
    pushed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (channel_id) REFERENCES youtube_channels(id)
);

-- Single-row settings, same shape as gameplan_match_settings/brand_voice.
-- Confirmed: video_sample_count must be live-editable, not hardcoded —
-- how many recent videos per channel Stage 3 pulls for the AI's content
-- judgment (more videos = better signal, more Apify spend).
CREATE TABLE IF NOT EXISTS youtube_settings (
    id TINYINT PRIMARY KEY DEFAULT 1,
    video_sample_count INT NOT NULL DEFAULT 5,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
INSERT IGNORE INTO youtube_settings (id, video_sample_count) VALUES (1, 5);
