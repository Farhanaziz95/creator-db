-- Flozy's own tag system marks "Ghosted" and "Not A Right Fit" as ACTIVE
-- (same tag as New Lead, Discovery Call Booked, etc.) — so filtering the
-- sweep by tag alone would waste budget re-scraping dead-end leads. This
-- lets you explicitly exclude specific stage names, configurable since
-- your stage names are custom to your pipeline, not something to hardcode.
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

CREATE TABLE IF NOT EXISTS sweep_excluded_stages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stage_name VARCHAR(150) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
