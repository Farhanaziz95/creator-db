-- Run this if you already ran schema.sql (or migration_001) before this update.
-- Adds pinned-post exclusion tracking + posting consistency columns.
-- In phpMyAdmin: select creator_db -> SQL tab -> paste this -> Go.

ALTER TABLE profile_snapshots
    ADD COLUMN pinned_posts_excluded INT DEFAULT 0,
    ADD COLUMN posts_per_week DECIMAL(6,2) NULL,
    ADD COLUMN is_inconsistent TINYINT(1) DEFAULT 0;

-- Note: this only affects NEW imports going forward. Existing snapshot rows
-- were calculated including pinned posts (before this fix), so their
-- avg_likes/avg_comments/engagement_rate may be slightly inflated and their
-- posts_per_week/is_inconsistent will be blank. Re-import the same Apify
-- files if you want existing profiles recalculated cleanly.
