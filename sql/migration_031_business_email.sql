-- YouTube channels can genuinely have two distinct emails:
--   1. `email` — regex-extracted from the public About text (existing)
--   2. `business_email` — YouTube's protected "business inquiries" email,
--      only revealed manually (click-through + captcha on YouTube's
--      side), so this is always hand-entered, never scraped.
--
-- Confirmed: business_email is treated as PRIMARY wherever only one
-- email can be used (the Flozy Contact push) — it's the address the
-- creator actually wants business contact through, vs. `email` which is
-- just whatever regex happened to find in free-text About copy.
--
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.
ALTER TABLE youtube_channels
    ADD COLUMN business_email VARCHAR(255) NULL AFTER email;
