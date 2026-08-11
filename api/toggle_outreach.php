<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$profileId = (int) ($input['profile_id'] ?? 0);
$outreached = !empty($input['outreached']);

$stmt = $pdo->prepare("
    UPDATE flozy_leads
    SET outreached_at = ?
    WHERE profile_id = ?
");
$stmt->execute([$outreached ? date('Y-m-d H:i:s') : null, $profileId]);

echo json_encode(['success' => true]);
