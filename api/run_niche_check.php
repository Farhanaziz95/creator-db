<?php
// This can legitimately take a while (up to 10 profiles x up to 5 fallback
// models x 25s timeout each, worst case) — Apache's default 30s PHP limit
// was killing it mid-batch. Remove the limit for this script specifically.
set_time_limit(0);
ignore_user_abort(true); // keep processing even if the browser tab closes/times out waiting

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/niche_queue_processor.php';
header('Content-Type: application/json');

$result = run_niche_queue_batch($pdo);
echo json_encode(['success' => true] + $result);
