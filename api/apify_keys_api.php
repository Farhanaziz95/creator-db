<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Never send full keys to the browser — mask them.
    $rows = $pdo->query("SELECT id, label, is_active, sort_order, created_at, last_used_at,
                          CONCAT(LEFT(api_key, 6), '••••••••') AS masked_key
                          FROM apify_keys ORDER BY sort_order ASC")->fetchAll();
    echo json_encode(['data' => $rows]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
    $stmt = $pdo->prepare("INSERT INTO apify_keys (label, api_key, sort_order) VALUES (?, ?, ?)");
    $stmt->execute([
        trim($input['label'] ?? ''),
        trim($input['api_key'] ?? ''),
        (int) ($input['sort_order'] ?? 0),
    ]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

if ($method === 'PUT') {
    $id = (int) ($input['id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE apify_keys SET is_active = ?, sort_order = ? WHERE id = ?");
    $stmt->execute([(int) ($input['is_active'] ?? 1), (int) ($input['sort_order'] ?? 0), $id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($method === 'DELETE') {
    $id = (int) ($input['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM apify_keys WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
