<?php
/**
 * Powers the "Pipeline Stage" filter dropdown on the Sent to Flozy tab.
 * Deliberately reads from the LOCAL flozy_leads.current_stage column
 * (already kept in sync via the 🔄 sync buttons / "Sync All Pipeline
 * Stages") rather than hitting Flozy's API again — this only needs to
 * list stages your current leads are actually sitting in, not every
 * stage that exists in your account, and it should load instantly.
 */
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$rows = $pdo->query("
    SELECT DISTINCT current_stage
    FROM flozy_leads
    WHERE current_stage IS NOT NULL AND current_stage != ''
    ORDER BY current_stage ASC
")->fetchAll(PDO::FETCH_COLUMN);

echo json_encode(['data' => $rows]);
