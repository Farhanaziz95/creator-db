-- Round 35, item #1: Email extraction + Flozy Contact push.
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

-- Regex-extracted from profile_snapshots.biography on every import (same
-- "keep it fresh" pattern as full_name/external_url already use), and
-- directly editable in the dashboard for the cases the regex misses or
-- mis-picks.
ALTER TABLE profiles
    ADD COLUMN email VARCHAR(255) NULL,
    ADD INDEX idx_email (email);

-- Flozy's Contact ID for this lead, once one has been pushed — mirrors the
-- existing flozy_opportunity_id column/pattern. Used so "Push Contact"
-- knows this lead already has one on record and doesn't try to create a
-- second (Flozy also 409s on a duplicate email per lead, but this avoids
-- the wasted round-trip and lets the UI show "already pushed" instead of
-- an error).
ALTER TABLE flozy_leads
    ADD COLUMN flozy_contact_id INT NULL;
