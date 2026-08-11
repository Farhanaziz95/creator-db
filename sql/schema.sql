-- Creator Database Schema
-- Run this once in phpMyAdmin (or `mysql -u root < schema.sql`) to set everything up.

CREATE DATABASE IF NOT EXISTS creator_db;
USE creator_db;

-- Niche categories (both keyword-matched and AI-generated end up here)
CREATE TABLE IF NOT EXISTS niches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    is_ai_generated TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- One row per unique Instagram username. Never deleted.
CREATE TABLE IF NOT EXISTS profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    full_name VARCHAR(255),
    external_url VARCHAR(500),
    niche_id INT NULL,
    niche_source ENUM('rule', 'ai', 'manual') NULL,
    archived TINYINT(1) DEFAULT 0,
    archived_at TIMESTAMP NULL,
    status ENUM('active', 'archived', 'future') DEFAULT 'active',
    first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (niche_id) REFERENCES niches(id),
    INDEX idx_username (username),
    INDEX idx_archived (archived),
    INDEX idx_status (status)
);

-- One row per import batch (one JSON file upload = one batch)
CREATE TABLE IF NOT EXISTS import_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255),
    total_in_file INT DEFAULT 0,
    new_count INT DEFAULT 0,
    duplicate_count INT DEFAULT 0,
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- One row per profile PER import. History is preserved, nothing overwritten.
CREATE TABLE IF NOT EXISTS profile_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profile_id INT NOT NULL,
    batch_id INT NOT NULL,
    followers_count INT DEFAULT 0,
    following_count INT DEFAULT 0,
    posts_count INT DEFAULT 0,
    biography TEXT,
    business_category_name VARCHAR(255) NULL,
    avg_likes DECIMAL(12,2) DEFAULT 0,
    avg_comments DECIMAL(12,2) DEFAULT 0,
    engagement_rate DECIMAL(8,4) DEFAULT 0,
    posts_analyzed INT DEFAULT 0,
    pinned_posts_excluded INT DEFAULT 0,
    posts_per_week DECIMAL(6,2) NULL,
    is_inconsistent TINYINT(1) DEFAULT 0,
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profile_id) REFERENCES profiles(id),
    FOREIGN KEY (batch_id) REFERENCES import_batches(id),
    INDEX idx_profile_date (profile_id, imported_at)
);

-- Profiles waiting on AI niche classification (no keyword match, no businessCategoryName)
CREATE TABLE IF NOT EXISTS ai_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profile_id INT NOT NULL UNIQUE,
    status ENUM('pending', 'processing') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    last_attempt_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profile_id) REFERENCES profiles(id)
);

-- Log of every OpenRouter attempt, so you can see which free models are actually reliable
CREATE TABLE IF NOT EXISTS ai_model_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profile_id INT NOT NULL,
    model_used VARCHAR(255),
    success TINYINT(1),
    response_snippet TEXT,
    response_time_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
