<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $pdo->query("SELECT * FROM niche_categories ORDER BY name ASC")->fetchAll();
    echo json_encode(['data' => $rows]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
    $stmt = $pdo->prepare("INSERT INTO niche_categories (name, message_angle) VALUES (?, ?)");
    $stmt->execute([trim($input['name'] ?? ''), trim($input['message_angle'] ?? '')]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

if ($method === 'PUT') {
    $id = (int) ($input['id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE niche_categories SET name = ?, message_angle = ? WHERE id = ?");
    $stmt->execute([trim($input['name'] ?? ''), trim($input['message_angle'] ?? ''), $id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($method === 'DELETE') {
    $id = (int) ($input['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM niche_categories WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
