<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $pdo->query("SELECT * FROM prompt_templates ORDER BY label ASC")->fetchAll();
    echo json_encode(['data' => $rows]);
    exit;
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("UPDATE prompt_templates SET template_text = ? WHERE id = ?");
    $stmt->execute([$input['template_text'] ?? '', (int) ($input['id'] ?? 0)]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
