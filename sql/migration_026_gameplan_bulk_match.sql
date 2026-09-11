-- Round 35, item #2: Bulk gameplan upload with pattern matching.
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

-- Single-row settings table (same shape as `brand_voice` from
-- migration_021) for the configurable first-line prefix this feature
-- matches against — confirmed real examples: "Monetisation Audit: Full
-- Name (@username)" or "Monetisation Audit: @username". Settings-stored
-- and editable, never hardcoded, same as every other tunable business
-- rule in this project.
CREATE TABLE IF NOT EXISTS gameplan_match_settings (
    id TINYINT PRIMARY KEY DEFAULT 1,
    match_prefix VARCHAR(255) NOT NULL DEFAULT 'Monetisation Audit:',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
INSERT IGNORE INTO gameplan_match_settings (id, match_prefix) VALUES (1, 'Monetisation Audit:');
