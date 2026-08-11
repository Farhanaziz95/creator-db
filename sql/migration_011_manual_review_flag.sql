-- Flags reels that came back with no actual transcript (likely just
-- background music / b-roll, not the creator talking) — we have no OCR,
-- so these need a human glance before trusting AI-generated personalization
-- built from them.
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

ALTER TABLE post_transcripts
    ADD COLUMN needs_manual_review TINYINT(1) DEFAULT 0;
