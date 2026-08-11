<?php
require_once __DIR__ . '/gemini_client.php';
require_once __DIR__ . '/prompt_engine.php';

/**
 * Generates TWO distinct pieces instead of one blended message:
 *   - hook: an extremely short opening line — this is what shows in
 *     Instagram's DM preview/notification BEFORE they tap in, so it's the
 *     thing actually driving open rate. Needs to feel like a genuine
 *     reaction, not outreach.
 *   - followup: the natural next 1-3 sentences, only relevant once they've
 *     already opened the message. Can gently move toward asking about
 *     their offer/coaching if the verification data supports real intent.
 *
 * Prompt WORDING lives in the `prompt_templates` table (key: hook_followup),
 * editable from Settings — this function just fills in the variables.
 *
 * Returns ['hook' => string, 'followup' => string, 'errors' => array]
 */
function generate_hook_and_followup(PDO $pdo, string $transcriptsBlob, string $verificationSummary, ?string $messageAngle = null): array
{
    $prompt = render_prompt_template($pdo, 'hook_followup', [
        'transcripts'            => $transcriptsBlob,
        'verification_summary'   => $verificationSummary,
        'message_angle_context'  => format_message_angle_context($messageAngle),
    ]);

    $result = call_gemini($prompt, 300);

    if ($result['text'] === null) {
        return ['hook' => '', 'followup' => 'Message generation failed: ' . implode(' | ', $result['errors']), 'errors' => $result['errors']];
    }

    return parse_hook_and_followup($result['text']);
}

/**
 * Splits Gemini's "HOOK: ...\nFOLLOWUP: ..." response into the two parts.
 * If Gemini doesn't follow the format exactly (it usually does, but models
 * occasionally drift), falls back to treating the whole response as the
 * followup rather than crashing or losing the content.
 */
function parse_hook_and_followup(string $text): array
{
    if (preg_match('/HOOK:\s*(.+?)\s*FOLLOWUP:\s*(.+)/is', $text, $matches)) {
        return ['hook' => trim($matches[1]), 'followup' => trim($matches[2]), 'errors' => []];
    }

    // Format wasn't followed — don't lose the content, just can't split it cleanly.
    return ['hook' => '', 'followup' => trim($text), 'errors' => ['Response did not follow the HOOK:/FOLLOWUP: format — shown as one block.']];
}

/**
 * Looks up a profile's message angle via profiles -> niches -> niche_categories.
 * Returns null if the profile's niche isn't categorized (fine — the prompt
 * just runs without the extra angle context in that case).
 */
function get_message_angle(PDO $pdo, int $profileId): ?string
{
    $stmt = $pdo->prepare("
        SELECT nc.message_angle
        FROM profiles p
        JOIN niches n ON n.id = p.niche_id
        JOIN niche_categories nc ON nc.id = n.category_id
        WHERE p.id = ?
    ");
    $stmt->execute([$profileId]);
    $angle = $stmt->fetchColumn();
    return $angle ?: null;
}

/**
 * Generates a follow-up message for an already-contacted lead. This is a
 * GUIDED system, not an autonomous picker — the person chooses the type
 * and (for anything but 'regular') provides the actual content being
 * shared. The AI's job is to frame it well, not invent what's being shared.
 *
 * Each type maps to its own template_key in prompt_templates, editable
 * from Settings (followup_regular, followup_validation_script,
 * followup_free_value, followup_win_insight, followup_custom_survey).
 */
function generate_followup_message(PDO $pdo, string $type, string $transcriptsBlob, ?string $messageAngle, ?string $userInput): array
{
    $validTypes = ['regular', 'validation_script', 'free_value', 'win_insight', 'custom_survey'];
    $templateKey = 'followup_' . (in_array($type, $validTypes, true) ? $type : 'regular');

    $prompt = render_prompt_template($pdo, $templateKey, [
        'transcripts'            => $transcriptsBlob,
        'user_input'             => $userInput ?? '',
        'message_angle_context'  => format_message_angle_context($messageAngle),
    ]);

    return call_gemini($prompt, 250);
}
