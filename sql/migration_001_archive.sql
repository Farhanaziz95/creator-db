-- Run this ONLY if you already ran the original schema.sql before this update.
-- It adds archive support without touching any existing data.
-- In phpMyAdmin: select creator_db -> SQL tab -> paste this -> Go.

ALTER TABLE profiles
    ADD COLUMN archived TINYINT(1) DEFAULT 0,
    ADD COLUMN archived_at TIMESTAMP NULL,
    ADD INDEX idx_archived (archived);
