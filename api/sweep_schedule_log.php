<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$rows = $pdo->query("SELECT * FROM sweep_schedule_log ORDER BY run_date DESC, id DESC LIMIT 60")->fetchAll();
echo json_encode(['data' => $rows]);
