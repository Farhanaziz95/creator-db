-- A simple manual flag for "have I actually sent the DM yet" — separate
-- from Flozy's pipeline stage since that requires a sync click and may lag
-- behind reality. This is a one-click toggle you control directly, so
-- contacted vs not-yet-contacted stays clear as volume grows.
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

ALTER TABLE flozy_leads
    ADD COLUMN outreached_at TIMESTAMP NULL;
