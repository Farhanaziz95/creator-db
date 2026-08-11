<?php
set_time_limit(0);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/quality_score.php';
header('Content-Type: application/json');

$profileIds = $pdo->query("SELECT id FROM profiles")->fetchAll(PDO::FETCH_COLUMN);

foreach ($profileIds as $pid) {
    recompute_quality_score_for_profile($pdo, (int) $pid);
}

echo json_encode(['success' => true, 'recomputed' => count($profileIds)]);
