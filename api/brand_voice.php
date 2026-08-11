<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $voice = $pdo->query("SELECT voice_text FROM brand_voice WHERE id = 1")->fetchColumn();
    echo json_encode(['voice_text' => $voice ?: '']);
    exit;
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("UPDATE brand_voice SET voice_text = ? WHERE id = 1");
    $stmt->execute([$input['voice_text'] ?? '']);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
