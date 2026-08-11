-- Adds a proper status column instead of a single archived boolean, so we
-- can support more than two states (Active / Archived / Future) cleanly.
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

ALTER TABLE profiles
    ADD COLUMN status ENUM('active', 'archived', 'future') DEFAULT 'active',
    ADD INDEX idx_status (status);

-- Carry over existing archived flags into the new column.
-- (The old `archived` column is left in place, unused, to avoid touching
-- any existing queries you may have run manually — safe to ignore it.)
UPDATE profiles SET status = 'archived' WHERE archived = 1;
