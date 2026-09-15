-- Fixes a wrong field mapping from Layer 1: the actor has NO
-- monetization field at all (confirmed from a real raw output sample) —
-- 'isMonetized' was a guess from marketing copy, not a real key, and it
-- came back NULL on every row of Round 1 as a result. Dropping it rather
-- than leaving a column that will never populate.
--
-- The real field is isChannelVerified (YouTube's verified-checkmark
-- status, not a monetization signal) — genuinely useful as a light
-- trust/scale indicator, so adding it instead. Also adding channelId and
-- channelUsername, both present in every real sample and free to store.
--
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.
ALTER TABLE youtube_channels
    DROP COLUMN is_monetized,
    ADD COLUMN is_verified TINYINT(1) NULL AFTER country,
    ADD COLUMN youtube_channel_id VARCHAR(50) NULL AFTER channel_url,
    ADD COLUMN channel_username VARCHAR(150) NULL AFTER youtube_channel_id;

-- Round 1's 39 already-scraped rows will just have NULL for these three
-- new columns — it was a test run, not worth re-spending Apify credits
-- to backfill. Every round from here on will populate them correctly.
