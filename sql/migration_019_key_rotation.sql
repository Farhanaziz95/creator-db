-- Enables round-robin key rotation. Previously the first key with enough
-- budget got used every single time — meaning 100% of traffic could sit
-- on one account indefinitely, which looks automated rather than the
-- spread-out pattern a human juggling multiple accounts would produce.
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

ALTER TABLE apify_keys
    ADD COLUMN last_used_at TIMESTAMP NULL;
