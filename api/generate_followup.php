<?php
require_once __DIR__ . '/../includes/error_handler.php';
setup_json_error_handling();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/message_generation.php';
header('Content-Type: application/json');

try {
    handle_followup_generation($pdo);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unexpected error: ' . $e->getMessage() . ' (line ' . $e->getLine() . ')']);
}

function handle_followup_generation(PDO $pdo): void
{
    $input      = json_decode(file_get_contents('php://input'), true);
    $profileId  = (int) ($input['profile_id'] ?? 0);
    $type       = $input['followup_type'] ?? 'regular';
    $userInput  = trim($input['user_input'] ?? '');

    $validTypes = ['regular', 'validation_script', 'free_value', 'win_insight', 'custom_survey'];
    if (!in_array($type, $validTypes, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown follow-up type.']);
        return;
    }

    if ($type !== 'regular' && $userInput === '') {
        http_response_code(400);
        echo json_encode(['error' => 'This follow-up type needs you to say what you\'re sharing first.']);
        return;
    }

    $stmt = $pdo->prepare("SELECT caption, transcript FROM post_transcripts WHERE profile_id = ? AND (review_status != 'excluded' OR review_status IS NULL) ORDER BY fetched_at DESC");
    $stmt->execute([$profileId]);
    $transcripts = $stmt->fetchAll();

    if (!$transcripts && $type === 'regular') {
        // A "Regular" follow-up depends entirely on real stored content —
        // feeding a placeholder string into the prompt risks the AI treating
        // it as real context and hallucinating specifics. Block cleanly
        // instead; the other types can still work since they're built
        // around content YOU provide, not what's stored.
        http_response_code(400);
        echo json_encode(['error' => 'No stored content for this lead yet — run "Verify+Personalize" first, or pick a different follow-up type that doesn\'t depend on stored transcripts.']);
        return;
    }

    $transcriptsBlob = $transcripts
        ? implode("\n\n---\n\n", array_map(fn($r) => "Caption: " . ($r['caption'] ?? '') . "\nTranscript: " . ($r['transcript'] ?? ''), $transcripts))
        : '(no stored content — this follow-up type was chosen specifically because it does not depend on it)';

    $messageAngle = get_message_angle($pdo, $profileId);

    $result = generate_followup_message($pdo, $type, $transcriptsBlob, $messageAngle, $userInput ?: null);

    if ($result['text'] === null) {
        http_response_code(500);
        echo json_encode(['error' => 'Follow-up generation failed: ' . implode(' | ', $result['errors'])]);
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO followup_messages (profile_id, followup_type, user_input, generated_message)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$profileId, $type, $userInput ?: null, $result['text']]);

    echo json_encode(['success' => true, 'message' => $result['text']]);
}
