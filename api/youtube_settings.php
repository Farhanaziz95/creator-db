<?php
// YouTube pipeline settings — currently just video_sample_count, per the
// confirmed requirement that it be live-editable rather than hardcoded.
// Same GET/PUT shape as api/brand_voice.php and api/gameplan_match_settings.php.
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT video_sample_count FROM youtube_settings WHERE id = 1");
    $count = $stmt->fetchColumn();
    echo json_encode(['video_sample_count' => $count !== false ? (int) $count : 5]);
    exit;
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $count = (int) ($input['video_sample_count'] ?? 5);
    // Sane floor/ceiling — 0 would starve the AI pass of any content
    // signal, and there's no real benefit past a couple dozen videos for
    // a per-channel judgment call.
    $count = max(1, min(25, $count));

    $stmt = $pdo->prepare("UPDATE youtube_settings SET video_sample_count = ? WHERE id = 1");
    $stmt->execute([$count]);
    echo json_encode(['success' => true, 'video_sample_count' => $count]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
