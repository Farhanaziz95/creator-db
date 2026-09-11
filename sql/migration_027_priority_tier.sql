-- Round 35, item #12: Low/Mid sub-tabs inside Sent to Flozy.
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

-- NULL = shows only under "All" (not yet triaged into a tier) —
-- confirmed purely local, no Flozy sync needed for this at all.
ALTER TABLE flozy_leads
    ADD COLUMN priority_tier ENUM('low','mid') NULL;
