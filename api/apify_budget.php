<?php
// Lightweight, read-only — just the number, no sweep/eligible-leads logic
// (that's Instagram-specific and stays in api/budget_sweep.php). This
// key pool is SHARED between Instagram and YouTube, so both dashboards
// checking it here means never having to tab over just to see the number.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/apify_client.php';
header('Content-Type: application/json');

echo json_encode(['remaining_budget' => get_total_remaining_budget($pdo)]);
