-- Adds a cached pipeline stage column to flozy_leads, refreshed on demand
-- via a sync button rather than fetched live on every table load (avoids
-- hammering Flozy's rate limit for a table that could have many rows).
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

ALTER TABLE flozy_leads
    ADD COLUMN current_stage VARCHAR(100) NULL,
    ADD COLUMN stage_synced_at TIMESTAMP NULL;
