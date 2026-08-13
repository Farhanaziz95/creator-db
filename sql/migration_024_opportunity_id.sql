-- Round 31: lets you move a lead's Flozy Opportunity to a different
-- pipeline stage directly from this app instead of switching over to
-- Flozy's UI. Confirmed against Flozy's real API docs first (PUT
-- /opportunities/{id}, body: {stage_id}, response for both Create and
-- List confirmed to return the Opportunity's own id as data.id / 
-- data.items[].id) — see README round 31 notes.
--
-- Needs the Opportunity's own ID, which is NOT the same as the Lead ID
-- already stored in flozy_lead_id — this column was never captured
-- before this round.
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

ALTER TABLE flozy_leads
    ADD COLUMN flozy_opportunity_id INT NULL;
