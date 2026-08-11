-- Splits the draft message into a short scroll-stopping HOOK (what shows in
-- the DM preview/notification — this is what actually drives open rate) and
-- the FOLLOW-UP body (only read once they've already opened it).
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

ALTER TABLE content_analysis_runs
    ADD COLUMN draft_hook TEXT NULL AFTER draft_message;
