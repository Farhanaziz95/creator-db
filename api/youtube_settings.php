<?php
// YouTube pipeline settings — currently just video_sample_count, per the
// confirmed requirement that it be live-editable rather than hardcoded.
// Same GET/PUT shape as api/brand_voice.php and api/gameplan_match_settings.php.
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $row = $pdo->query("SELECT video_sample_count, gemini_call_delay_seconds, scoring_batch_limit FROM youtube_settings WHERE id = 1")->fetch();
    echo json_encode([
        'video_sample_count'        => $row ? (int) $row['video_sample_count'] : 5,
        'gemini_call_delay_seconds' => $row ? (int) $row['gemini_call_delay_seconds'] : 4,
        'scoring_batch_limit'       => $row ? (int) $row['scoring_batch_limit'] : 10,
    ]);
    exit;
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $videoCount = max(1, min(25, (int) ($input['video_sample_count'] ?? 5)));
    // Floors here matter: 0-second delay or an unbounded batch limit is
    // exactly the "burn the free tier in under a minute" failure mode
    // this migration exists to prevent — enforced here, not just left to
    // whoever's typing into the settings field.
    $delaySeconds = max(1, min(60, (int) ($input['gemini_call_delay_seconds'] ?? 4)));
    $batchLimit = max(1, min(100, (int) ($input['scoring_batch_limit'] ?? 10)));

    $stmt = $pdo->prepare("UPDATE youtube_settings SET video_sample_count = ?, gemini_call_delay_seconds = ?, scoring_batch_limit = ? WHERE id = 1");
    $stmt->execute([$videoCount, $delaySeconds, $batchLimit]);
    echo json_encode(['success' => true, 'video_sample_count' => $videoCount, 'gemini_call_delay_seconds' => $delaySeconds, 'scoring_batch_limit' => $batchLimit]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
