<?php
// Round 35, item #2: the Settings-stored prefix used to match a bulk
// gameplan upload's first line to a username. Same GET/PUT shape as
// api/brand_voice.php.
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $prefix = $pdo->query("SELECT match_prefix FROM gameplan_match_settings WHERE id = 1")->fetchColumn();
    echo json_encode(['match_prefix' => $prefix !== false ? $prefix : '']);
    exit;
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("UPDATE gameplan_match_settings SET match_prefix = ? WHERE id = 1");
    $stmt->execute([$input['match_prefix'] ?? '']);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
