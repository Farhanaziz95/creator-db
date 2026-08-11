<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$rows = $pdo->query("SELECT * FROM content_briefs ORDER BY created_at DESC LIMIT 100")->fetchAll();
echo json_encode(['data' => $rows]);
