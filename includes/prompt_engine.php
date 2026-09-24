<?php
/**
 * Every AI prompt's WORDING lives in the `prompt_templates` table, editable
 * from Settings — this is the one place that turns a template + variables
 * into the final string sent to the AI. Falls back to a minimal safe
 * default if a template is somehow missing (e.g. migration not run yet),
 * so a blank/missing row degrades gracefully instead of breaking generation.
 */

function get_brand_voice(PDO $pdo): string
{
    $voice = $pdo->query("SELECT voice_text FROM brand_voice WHERE id = 1")->fetchColumn();
    return $voice ? trim($voice) : '';
}

/**
 * Renders a named template: prepends the shared brand voice block (if any),
 * then substitutes {placeholder} tokens with the given values.
 */
function render_prompt_template(PDO $pdo, string $key, array $vars): string
{
    $stmt = $pdo->prepare("SELECT template_text FROM prompt_templates WHERE template_key = ?");
    $stmt->execute([$key]);
    $template = $stmt->fetchColumn();

    if (!$template) {
        // Migration not run, or the row was deleted — fail safe rather than
        // crash generation entirely.
        $template = implode("\n", array_map(fn($k) => "{{$k}}", array_keys($vars)));
    }

    $replacements = [];
    foreach ($vars as $k => $v) {
        $replacements['{' . $k . '}'] = (string) $v;
    }
    $rendered = strtr($template, $replacements);

    $brandVoice = get_brand_voice($pdo);
    if ($brandVoice !== '') {
        $rendered = "BRAND VOICE / STYLE (apply throughout):\n{$brandVoice}\n\n---\n\n{$rendered}";
    }

    return $rendered;
}

/**
 * Small helper — most templates have an optional {message_angle_context}
 * slot that should just be empty when there's no angle, rather than every
 * caller needing to build this conditional string itself.
 */
function format_message_angle_context(?string $angle): string
{
    return $angle ? "\n\nThis creator's category suggests a specific angle for what they actually care about: {$angle}\n" : '';
}

/**
 * Fixes a real bug: without this, every follow-up call to a given lead
 * got the exact same {message_angle_context} and the exact same
 * {transcripts} every single time, with nothing telling the AI what was
 * already sent — so it reliably converged on the same angle/topic no
 * matter how many follow-ups you generated. $previousMessages is this
 * lead's prior followup_messages rows (generated_message + followup_type
 * + created_at), oldest first; empty array on a lead's first follow-up
 * degrades gracefully to an empty string, same pattern as
 * format_message_angle_context() above.
 */
function format_previous_followups_context(array $previousMessages): string
{
    if (!$previousMessages) {
        return '';
    }

    $lines = [];
    foreach ($previousMessages as $i => $m) {
        $lines[] = ($i + 1) . ". (" . $m['followup_type'] . ") " . $m['generated_message'];
    }

    return "\n\nPREVIOUSLY SENT to this same creator — do NOT repeat these angles, topics, or specific details. Take a genuinely different angle this time:\n" . implode("\n", $lines) . "\n";
}
