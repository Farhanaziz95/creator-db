<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $pdo->query("SELECT * FROM flozy_task_templates ORDER BY sort_order ASC, id ASC")->fetchAll();
    echo json_encode(['data' => $rows]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
    $stmt = $pdo->prepare("
        INSERT INTO flozy_task_templates (title, description, status, priority, due_offset_days, sort_order, is_active)
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([
        $input['title'] ?? '',
        $input['description'] ?? null,
        (int) ($input['status'] ?? 1),
        (int) ($input['priority'] ?? 2),
        $input['due_offset_days'] !== '' && $input['due_offset_days'] !== null ? (int) $input['due_offset_days'] : null,
        (int) ($input['sort_order'] ?? 0),
    ]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

if ($method === 'PUT') {
    $id = (int) ($input['id'] ?? 0);
    $stmt = $pdo->prepare("
        UPDATE flozy_task_templates
        SET title = ?, description = ?, status = ?, priority = ?, due_offset_days = ?, sort_order = ?, is_active = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $input['title'] ?? '',
        $input['description'] ?? null,
        (int) ($input['status'] ?? 1),
        (int) ($input['priority'] ?? 2),
        $input['due_offset_days'] !== '' && $input['due_offset_days'] !== null ? (int) $input['due_offset_days'] : null,
        (int) ($input['sort_order'] ?? 0),
        (int) ($input['is_active'] ?? 1),
        $id,
    ]);
    echo json_encode(['success' => true]);
    exit;
}

if ($method === 'DELETE') {
    $id = (int) ($input['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM flozy_task_templates WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
