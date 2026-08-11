-- Every AI prompt was hardcoded in PHP — same problem already solved for
-- keyword rules, task templates, score weights. Moves prompt WORDING into
-- editable templates, same architectural pattern as everything else in
-- this project. NOT a module-composition engine — that's disproportionate
-- for what this actually needs. Just: edit prompt text from Settings
-- instead of asking for a code change every time.
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

CREATE TABLE IF NOT EXISTS prompt_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(100) NOT NULL UNIQUE,
    label VARCHAR(200) NOT NULL,
    description TEXT,
    template_text LONGTEXT NOT NULL,
    available_placeholders VARCHAR(500),  -- shown as reference in Settings, not enforced
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- One shared block, auto-prepended to EVERY generated prompt — the
-- genuinely valuable "modular" piece: change tone/voice once instead of
-- editing 8 separate templates every time.
CREATE TABLE IF NOT EXISTS brand_voice (
    id INT PRIMARY KEY DEFAULT 1,
    voice_text TEXT
);
INSERT IGNORE INTO brand_voice (id, voice_text) VALUES (1, '');

INSERT INTO prompt_templates (template_key, label, description, template_text, available_placeholders) VALUES

('verification', 'Gameplan Verification',
 'Checks if the gameplan''s claimed audience problem/buying intent actually shows up in real comments.',
 'A creator has a monetization gameplan claiming a specific audience problem and buying intent. Below is the gameplan text, followed by REAL comments from their recent posts. Does the real audience comment data actually support the gameplan''s claims? Give a short, direct verdict (confirmed / partially confirmed / not confirmed) with 2-3 sentences of evidence quoting or paraphrasing specific comment themes. Be honest if the comments don''t support the claim.

=== GAMEPLAN ===
{gameplan}

=== REAL COMMENTS ===
{comments}',
 '{gameplan}, {comments}'),

('hook_followup', 'Cold Outreach Hook + Follow-up',
 'The initial cold-DM generator — a short scroll-stopping hook plus a natural follow-up.',
 'You''re writing an Instagram DM opener in TWO distinct parts. Base everything on the REAL content and verified audience signal below — nothing generic.{message_angle_context}

PART 1 — HOOK: An extremely short opening line, under 12 words. This is what shows in the DM preview/notification before they even open the message, so it has ONE job: make them curious enough to tap in. Reference one hyper-specific, real detail from their content — something only someone who actually watched it would know. No generic greetings like ''Hey love your content!''. It should read like a genuine reaction or a question, never like outreach.

PART 2 — FOLLOWUP: The natural next 1-3 sentences that continue the thought once they''ve opened it. If the verification result below shows a real, confirmed audience problem, you can gently work toward asking about their offer (e.g. do they offer coaching on this) — but only if it flows naturally, never salesy or forced. If verification is weak, just keep it conversational instead of pitching.

Respond in EXACTLY this format, nothing else before or after:
HOOK: <the short line>
FOLLOWUP: <the 1-3 sentences>

=== THEIR RECENT CONTENT ===
{transcripts}

=== VERIFICATION RESULT ===
{verification_summary}',
 '{message_angle_context}, {transcripts}, {verification_summary}'),

('followup_regular', 'Regular Follow-up',
 'Default follow-up type — a casual continuation referencing their content, no specific input required.',
 'Write a short, casual Instagram follow-up message to this creator, continuing an existing conversation. Reference something specific and real from their content below. Under 80 words, no hashtags, no emojis, sounds like a real person.{message_angle_context}

=== THEIR RECENT CONTENT ===
{transcripts}',
 '{message_angle_context}, {transcripts}'),

('followup_validation_script', 'Follow-up: Validation Script',
 'Naturally shares a validation result/script you provide.',
 'Write a short, casual Instagram follow-up message that naturally shares the validation/result below with this creator. It should read like a genuine check-in, not a sales pitch. Under 80 words.

What to share:
{user_input}{message_angle_context}

=== THEIR RECENT CONTENT (for extra context/personalization) ===
{transcripts}',
 '{user_input}, {message_angle_context}, {transcripts}'),

('followup_free_value', 'Follow-up: Free Value',
 'Shares a no-strings-attached piece of value you provide.',
 'Write a short, no-strings-attached Instagram follow-up sharing this piece of free value with the creator below. Frame it as genuinely helpful, not a lead-in to a pitch. Under 80 words.

What to share:
{user_input}{message_angle_context}

=== THEIR RECENT CONTENT (for extra context/personalization) ===
{transcripts}',
 '{user_input}, {message_angle_context}, {transcripts}'),

('followup_win_insight', 'Follow-up: Win/Insight Share',
 'Casual "saw this and thought of you" style share of a win, result, or insight you provide.',
 'Write a short Instagram follow-up that shares this win, result, or insight with the creator — framed as ''saw this and thought of you,'' casual and low-pressure. Under 80 words.

What to share:
{user_input}{message_angle_context}

=== THEIR RECENT CONTENT (for extra context/personalization) ===
{transcripts}',
 '{user_input}, {message_angle_context}, {transcripts}'),

('followup_custom_survey', 'Follow-up: Free Custom Survey Offer',
 'Offers a free mini-audit/survey of their account — repackages verification data as a value-add.',
 'Write a short Instagram follow-up OFFERING a free custom audit/survey of their account — a quick, no-cost analysis to show them real numbers about their content and audience. Reference the specific detail below about what this audit would surface for them. Casual, low pressure, genuinely helpful framing, not salesy. Under 80 words.

What this audit would show them:
{user_input}{message_angle_context}

=== THEIR RECENT CONTENT (for extra context/personalization) ===
{transcripts}',
 '{user_input}, {message_angle_context}, {transcripts}'),

('niche_classification', 'AI Niche Classification',
 'Used by the background job to guess a profile''s niche when keyword rules don''t match.',
 'You are classifying an Instagram creator into ONE short niche label (2-4 words), based on their bio and name. Respond with ONLY the niche label — no explanation, no reasoning, no punctuation, no quotes.

Full name: {full_name}
Bio: {bio}',
 '{full_name}, {bio}');
