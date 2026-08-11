<?php
/**
 * Re-runs ONLY the Gemini passes, reusing transcripts/comments already
 * sitting in the database from a previous run. No new Apify calls, no new
 * cost — for when Gemini failed (bad key, quota, etc.) but the scraping
 * itself already succeeded.
 */

set_time_limit(0);

require_once __DIR__ . '/../includes/error_handler.php';
setup_json_error_handling();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/gemini_client.php';
require_once __DIR__ . '/../includes/message_generation.php';
header('Content-Type: application/json');

try {
    rerun_ai_only($pdo);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unexpected error: ' . $e->getMessage() . ' (line ' . $e->getLine() . ')']);
}

function rerun_ai_only(PDO $pdo): void
{
    $input     = json_decode(file_get_contents('php://input'), true);
    $profileId = (int) ($input['profile_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT extracted_text FROM gameplans WHERE profile_id = ?");
    $stmt->execute([$profileId]);
    $gameplanText = $stmt->fetchColumn();

    if (!$gameplanText) {
        http_response_code(400);
        echo json_encode(['error' => 'No gameplan found for this lead.']);
        return;
    }

    $stmt = $pdo->prepare("SELECT caption, transcript FROM post_transcripts WHERE profile_id = ? AND (review_status != 'excluded' OR review_status IS NULL) ORDER BY fetched_at DESC");
    $stmt->execute([$profileId]);
    $transcripts = $stmt->fetchAll();

    if (!$transcripts) {
        http_response_code(400);
        echo json_encode(['error' => 'No stored transcripts for this lead yet — run "Verify+Personalize" first (that does the actual scraping).']);
        return;
    }

    $stmt = $pdo->prepare("SELECT comment_text FROM post_comments WHERE profile_id = ? ORDER BY fetched_at DESC LIMIT 300");
    $stmt->execute([$profileId]);
    $comments = array_column($stmt->fetchAll(), 'comment_text');

    $commentsBlob = implode("\n", $comments);
    $transcriptsBlob = implode("\n\n---\n\n", array_map(
        fn($r) => "Caption: " . ($r['caption'] ?? '') . "\nTranscript: " . ($r['transcript'] ?? ''),
        $transcripts
    ));

    $verificationPrompt = render_prompt_template($pdo, 'verification', [
        'gameplan' => $gameplanText,
        'comments' => $commentsBlob ?: '(no comments were retrieved for this run)',
    ]);

    $verificationResult = call_gemini($verificationPrompt, 500);

    if ($verificationResult['text'] === null) {
        http_response_code(500);
        echo json_encode(['error' => 'Gemini verification failed. Details: ' . implode(' | ', $verificationResult['errors'])]);
        return;
    }
    $verificationSummary = $verificationResult['text'];

    $messageAngle = get_message_angle($pdo, $profileId);
    $message = generate_hook_and_followup($pdo, $transcriptsBlob, $verificationSummary, $messageAngle);
    $draftHook = $message['hook'];
    $draftFollowup = $message['followup'];

    $stmt = $pdo->prepare("
        INSERT INTO content_analysis_runs (profile_id, status, posts_checked, comments_checked, verification_summary, draft_hook, draft_message, finished_at)
        VALUES (?, 'done', ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$profileId, count($transcripts), count($comments), $verificationSummary, $draftHook, $draftFollowup]);

    echo json_encode([
        'success'              => true,
        'verification_summary' => $verificationSummary,
        'draft_hook'           => $draftHook,
        'draft_message'        => $draftFollowup,
    ]);
}
