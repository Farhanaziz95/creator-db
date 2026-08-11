<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$rows = $pdo->query("SELECT * FROM import_batches ORDER BY imported_at DESC LIMIT 200")->fetchAll();
echo json_encode(['data' => $rows]);
