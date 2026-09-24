-- Fixes a real bug: every follow-up type's prompt template fed the AI
-- the SAME stored {transcripts} and the SAME static per-niche
-- {message_angle_context} every single call, with NOTHING telling it
-- what was already sent in a previous follow-up to this same lead.
-- Combined with a narrow instruction ("reference something specific and
-- real from their content"), the AI reliably locked onto the same most-
-- salient detail every time — explaining "same angle, same topic, no
-- matter how many times I attempt" regardless of which follow-up type
-- was picked.
--
-- Fix: a new {previous_followups_context} placeholder, populated by
-- api/generate_followup.php from this profile's followup_messages
-- history, with an explicit "do not repeat these" instruction. Empty
-- string (no visible change to the prompt) on a lead's first follow-up
-- — same "degrade gracefully when there's nothing to fill" pattern
-- {message_angle_context} already uses.
--
-- Uses REPLACE() rather than overwriting template_text outright —
-- these 5 templates are user-editable in Settings, so a raw overwrite
-- could silently destroy any wording you'd already customized. This
-- only inserts the new placeholder immediately after the existing
-- {message_angle_context} token in whatever text is actually there now,
-- leaving everything else untouched. If a row's text doesn't contain
-- that exact token anymore (customized away), this UPDATE is a no-op
-- for that row — check it manually in Settings afterward if so.
--
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

UPDATE prompt_templates
SET template_text = REPLACE(template_text, '{message_angle_context}', '{message_angle_context}{previous_followups_context}'),
    available_placeholders = CONCAT(available_placeholders, ', {previous_followups_context}')
WHERE template_key IN ('followup_regular', 'followup_validation_script', 'followup_free_value', 'followup_win_insight', 'followup_custom_survey')
  AND template_text LIKE '%{message_angle_context}%'
  AND template_text NOT LIKE '%{previous_followups_context}%'; -- don't double-insert if this migration is somehow re-run
