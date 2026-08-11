-- Corrects the pipeline-stage tracking to use Flozy's actual Opportunities +
-- Pipelines resources (what the screenshots showed) instead of the Lead's
-- own status_name field (a different, simpler field — my original mistake).
-- Adds tag_name too (active/won/lost) — directly useful later for warm-up
-- priority (e.g. skip won/lost leads).
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

ALTER TABLE flozy_leads
    ADD COLUMN current_stage_tag VARCHAR(20) NULL;
