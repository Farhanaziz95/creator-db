-- Corrects the sweep-excluded stage list:
-- - Discovery Call Booked, Presentation Call Booked, Not A Right Fit:
--   excluded (already past the content-case-building phase, or dead)
-- - Ghosted: explicitly NOT excluded — per the touchpoint algorithm study
--   material, ghosted leads still get monthly touchpoints, so they still
--   need fresh data. (If added in a previous session, removed here.)
-- - Partnerships Won/Lost: already excluded automatically via tag check,
--   no manual entry needed.
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

DELETE FROM sweep_excluded_stages WHERE stage_name = 'Ghosted';

INSERT IGNORE INTO sweep_excluded_stages (stage_name) VALUES
('Discovery Call Booked'),
('Presentation Call Booked'),
('Not A Right Fit');
