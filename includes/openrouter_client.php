<?php
require_once __DIR__ . '/prompt_engine.php';
/**
 * Tries each free model in config/openrouter.php's fallback list, in order,
 * until one returns a CLEAN, usable niche label. Logs every attempt
 * (success or fail) into ai_model_log.
 *
 * Some free reasoning models (deepseek-r1-distill, gpt-oss, etc.) dump their
 * chain-of-thought straight into the response instead of just the answer
 * ("We need to output only the niche label..."). That garbage used to get
 * accepted as a valid niche because it was non-empty. extract_niche_label()
 * below filters that out — anything that isn't a clean 1-4 word label is
 * treated as a FAILURE, so the next model gets tried instead of the profile
 * being permanently poisoned with junk.
 *
 * Returns the niche label string on success, or null if every model failed
 * (caller should leave the profile in the queue to retry on the next run).
 */
function classify_niche_with_openrouter(string $bio, string $fullName, int $profileId, PDO $pdo): ?string
{
    $config = require __DIR__ . '/../config/openrouter.php';
    $models = $config['models'];
    $apiKey = $config['api_key'];

    $prompt = render_prompt_template($pdo, 'niche_classification', [
        'full_name' => $fullName,
        'bio'       => $bio,
    ]);

    foreach ($models as $model) {
        $start   = microtime(true);
        $raw     = call_openrouter_model($model, $prompt, $apiKey, $config['base_url']);
        $elapsed = (int) ((microtime(true) - $start) * 1000);

        $clean   = extract_niche_label($raw);
        $success = $clean !== null;

        $stmt = $pdo->prepare(
            "INSERT INTO ai_model_log (profile_id, model_used, success, response_snippet, response_time_ms)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $profileId,
            $model,
            $success ? 1 : 0,
            $raw !== null ? substr(trim($raw), 0, 255) : null, // log the raw response either way, for debugging
            $elapsed,
        ]);

        if ($success) {
            return $clean;
        }
        // Falls through to the next model in the list automatically.
    }

    return null;
}

/**
 * Cleans a raw model response down to a usable niche label, or returns null
 * if it looks like reasoning text, a non-answer ("none", "n/a", "unclear"),
 * or is otherwise not a plausible 1-4 word label.
 */
function extract_niche_label(?string $raw): ?string
{
    if ($raw === null || trim($raw) === '') {
        return null;
    }

    $text = trim($raw);

    // Strip explicit <think>...</think> blocks some models wrap around their CoT
    $text = preg_replace('/<think>.*?<\/think>/is', '', $text);
    $text = trim($text);

    // If the model returned multiple lines, the actual answer is usually the
    // last non-empty line (reasoning first, answer last).
    $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), fn($l) => $l !== ''));
    if (count($lines) > 0) {
        $text = end($lines);
    }

    // Strip surrounding quotes/punctuation/bullets
    $text = trim($text, " \t\n\r\0\x0B\"'.-•*");

    if ($text === '') {
        return null;
    }

    $lower = strtolower($text);

    // Reject obvious reasoning leakage
    $reasoningIndicators = [
        'we need', 'i need', 'the user', 'let me', "let's", 'okay,', 'so the',
        'first,', 'based on', 'this is', 'i think', 'looking at', 'given the',
    ];
    foreach ($reasoningIndicators as $indicator) {
        if (strpos($lower, $indicator) !== false) {
            return null;
        }
    }

    // Reject non-answers
    $nonAnswers = ['none', 'n/a', 'na', 'unknown', 'unclear', 'not sure', 'no niche', 'unavailable'];
    if (in_array($lower, $nonAnswers, true)) {
        return null;
    }

    // A real label should be short — reject anything that reads like a sentence
    $wordCount = str_word_count($text);
    if ($wordCount < 1 || $wordCount > 5 || strlen($text) > 40 || strpos($text, ':') !== false) {
        return null;
    }

    return $text;
}

function call_openrouter_model(string $model, string $prompt, string $apiKey, string $baseUrl): ?string
{
    $ch = curl_init($baseUrl);

    $payload = [
        'model'       => $model,
        'messages'    => [['role' => 'user', 'content' => $prompt]],
        'max_tokens'  => 60, // a little more headroom in case a reasoning model needs it before the final line
        'temperature' => 0.3,
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$apiKey}",
            "Content-Type: application/json",
        ],
        CURLOPT_TIMEOUT => 15, // was 25 — fail faster so a bad model doesn't stall the whole batch
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        return null;
    }

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? null;
}

/**
 * Gets an existing niche's ID by name, or creates it if it doesn't exist yet.
 */
function get_or_create_niche(PDO $pdo, string $name, bool $isAi): int
{
    $stmt = $pdo->prepare("SELECT id FROM niches WHERE name = ?");
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int) $id;
    }

    $stmt = $pdo->prepare("INSERT INTO niches (name, is_ai_generated) VALUES (?, ?)");
    $stmt->execute([$name, $isAi ? 1 : 0]);
    return (int) $pdo->lastInsertId();
}
