-- Adds pacing controls to youtube_settings so a scoring run can't burn
-- through the Gemini free-tier quota in under a minute — that key is
-- SHARED with Instagram's Verify+Personalize/Retry AI Only, so a
-- careless batch here would leave Instagram's AI features blocked for
-- the rest of the day too, not just YouTube's.
--
-- Both live-editable, same as video_sample_count, per the confirmed
-- "every business rule is tunable" convention — exact numbers here are
-- a conservative starting guess (~15/min), not a confirmed rate limit
-- for whichever Gemini model is currently active; tune based on what
-- you actually observe.
--
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.
ALTER TABLE youtube_settings
    ADD COLUMN gemini_call_delay_seconds INT NOT NULL DEFAULT 4,
    ADD COLUMN scoring_batch_limit INT NOT NULL DEFAULT 10;
