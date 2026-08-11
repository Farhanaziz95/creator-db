<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $pdo->query("SELECT * FROM score_weights ORDER BY id ASC")->fetchAll();
    echo json_encode(['data' => $rows]);
    exit;
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("UPDATE score_weights SET weight = ?, is_active = ? WHERE id = ?");
    $stmt->execute([
        (float) ($input['weight'] ?? 1),
        (int) ($input['is_active'] ?? 1),
        (int) ($input['id'] ?? 0),
    ]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
