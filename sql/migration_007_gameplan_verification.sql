-- Round 9: Gameplan Verification & Personalized Outreach
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

-- The uploaded gameplan PDF (per lead) + its extracted text
CREATE TABLE IF NOT EXISTS gameplans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profile_id INT NOT NULL UNIQUE,
    original_filename VARCHAR(255),
    stored_filename VARCHAR(255),   -- UUID-based, lives in storage/gameplans/
    extracted_text LONGTEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profile_id) REFERENCES profiles(id)
);

-- Reel/post transcripts pulled for a lead, N most recent at time of the run
CREATE TABLE IF NOT EXISTS post_transcripts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profile_id INT NOT NULL,
    post_url VARCHAR(500),
    shortcode VARCHAR(100),
    caption TEXT,
    transcript LONGTEXT,
    likes_count INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    posted_at TIMESTAMP NULL,
    fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profile_id) REFERENCES profiles(id)
);

-- Actual comment text pulled from those same posts
CREATE TABLE IF NOT EXISTS post_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profile_id INT NOT NULL,
    post_url VARCHAR(500),
    commenter_username VARCHAR(255),
    comment_text TEXT,
    likes_count INT DEFAULT 0,
    fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profile_id) REFERENCES profiles(id)
);

-- One row per "Run Verification & Personalization" click
CREATE TABLE IF NOT EXISTS content_analysis_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profile_id INT NOT NULL,
    status ENUM('running', 'done', 'failed') DEFAULT 'running',
    posts_checked INT DEFAULT 0,
    comments_checked INT DEFAULT 0,
    verification_summary LONGTEXT,   -- AI Pass 1 output
    draft_message LONGTEXT,          -- AI Pass 2 output
    error_message TEXT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    finished_at TIMESTAMP NULL,
    FOREIGN KEY (profile_id) REFERENCES profiles(id)
);

-- Your rotation pool of borrowed/friends' Apify accounts
CREATE TABLE IF NOT EXISTS apify_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(150) NOT NULL,     -- e.g. "My account", "Ali's account"
    api_key VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,  -- currently in rotation
    sort_order INT DEFAULT 0,        -- rotation priority order
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Reference rates for cost estimation before each run
CREATE TABLE IF NOT EXISTS apify_actor_costs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_key VARCHAR(100) NOT NULL UNIQUE,
    label VARCHAR(150) NOT NULL,
    cost_per_1000 DECIMAL(6,3) NOT NULL,
    notes VARCHAR(255) NULL,          -- flags whether this rate is confirmed or estimated
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Comment Scraper's rate is CONFIRMED from Apify's own store page (checked
-- live). Reel Scraper's is an ESTIMATE based on the same range every other
-- official Apify Instagram actor charges ($2.30-$2.70/1000) — verify against
-- the actor's own Pricing tab in Apify Console and update here if different.
INSERT INTO apify_actor_costs (actor_key, label, cost_per_1000, notes) VALUES
('instagram_profile_scraper', 'Instagram Profile Scraper', 2.600, 'Confirmed — matches your actual billing'),
('instagram_reel_scraper', 'Instagram Reel Scraper', 2.700, 'ESTIMATED — verify on Apify Console Pricing tab, update if different'),
('instagram_comment_scraper', 'Instagram Comment Scraper', 2.300, 'Confirmed from Apify store page');

-- Actual logged cost of every run, per key — real spend history over time
CREATE TABLE IF NOT EXISTS apify_usage_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    apify_key_id INT NOT NULL,
    actor_key VARCHAR(100),
    results_count INT DEFAULT 0,
    estimated_cost DECIMAL(8,4) DEFAULT 0,
    profile_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (apify_key_id) REFERENCES apify_keys(id)
);
