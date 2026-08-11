<?php
/**
 * Calls Gemini with model fallback. Returns ['text' => string|null, 'errors' => array]
 * — errors contains Google's ACTUAL error message per failed model attempt,
 * since the free tier's console doesn't show request-level logs. This is
 * how we see what's really wrong instead of a generic failure message.
 */
function call_gemini(string $prompt, int $maxOutputTokens = 1024): array
{
    $config = require __DIR__ . '/../config/gemini.php';
    $errors = [];

    foreach ($config['models'] as $model) {
        $result = call_gemini_model($model, $prompt, $config['api_key'], $config['base_url'], $maxOutputTokens);

        if ($result['text'] !== null && trim($result['text']) !== '') {
            return ['text' => trim($result['text']), 'errors' => $errors];
        }

        $errors[] = "{$model}: {$result['error']}";
    }

    return ['text' => null, 'errors' => $errors];
}

function call_gemini_model(string $model, string $prompt, string $apiKey, string $baseUrl, int $maxOutputTokens): array
{
    // PDF-extracted gameplan text and scraped Instagram content (emojis,
    // smart quotes, PDF encoding artifacts) can contain invalid UTF-8 byte
    // sequences. json_encode() silently returns false on invalid UTF-8,
    // which means curl sends an essentially EMPTY body — exactly what
    // produces Google's "contents is not specified" error. Sanitizing here
    // fixes it at the source instead of chasing it downstream.
    $prompt = mb_convert_encoding($prompt, 'UTF-8', 'UTF-8');
    $prompt = iconv('UTF-8', 'UTF-8//IGNORE', $prompt) ?: $prompt;

    $url = "{$baseUrl}/{$model}:generateContent?key={$apiKey}";

    $payload = [
        'contents' => [
            ['parts' => [['text' => $prompt]]],
        ],
        'generationConfig' => [
            'maxOutputTokens' => $maxOutputTokens,
            'temperature'     => 0.5,
            // Gemini 2.5 Flash models can spend their output-token budget on
            // internal "thinking" before writing the visible answer, which
            // can silently eat a tight maxOutputTokens budget like ours.
            // Disabling it isn't 100% confirmed for every model version —
            // if you still get empty (not error) responses, this is worth
            // revisiting.
            'thinkingConfig'  => ['thinkingBudget' => 0],
        ],
    ];

    $body = json_encode($payload);
    if ($body === false) {
        // Sanitization above should prevent this, but if it still happens,
        // fail loudly with the specific reason instead of silently sending
        // an empty request.
        return ['text' => null, 'error' => 'json_encode failed: ' . json_last_error_msg()];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 60,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['text' => null, 'error' => "Connection error: {$curlError}"];
    }

    $data = json_decode($response, true);

    if ($httpCode !== 200) {
        // This is the part that used to get thrown away — Google's actual
        // reason (bad key, wrong model name, quota, safety block, etc.)
        $googleMessage = $data['error']['message'] ?? $response ?? 'no response body';
        return ['text' => null, 'error' => "HTTP {$httpCode} — {$googleMessage}"];
    }

    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if ($text === null) {
        // 200 OK but no usable text — often a safety block or the model
        // hit its token budget before producing visible output.
        $finishReason = $data['candidates'][0]['finishReason'] ?? 'unknown';
        return ['text' => null, 'error' => "200 OK but no text returned (finishReason: {$finishReason})"];
    }

    return ['text' => $text, 'error' => null];
}
