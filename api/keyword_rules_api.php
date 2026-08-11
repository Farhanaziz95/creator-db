<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $pdo->query("SELECT * FROM keyword_rules ORDER BY niche_name ASC, keyword ASC")->fetchAll();
    echo json_encode(['data' => $rows]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
    $stmt = $pdo->prepare("INSERT INTO keyword_rules (niche_name, keyword) VALUES (?, ?)");
    $stmt->execute([trim($input['niche_name'] ?? ''), trim(strtolower($input['keyword'] ?? ''))]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

if ($method === 'DELETE') {
    $id = (int) ($input['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM keyword_rules WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
