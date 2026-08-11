<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$profileId = (int) ($_GET['profile_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT imported_at, followers_count, engagement_rate, avg_likes, avg_comments
    FROM profile_snapshots
    WHERE profile_id = ?
    ORDER BY imported_at ASC
");
$stmt->execute([$profileId]);
$rows = $stmt->fetchAll();

$usernameStmt = $pdo->prepare("SELECT username FROM profiles WHERE id = ?");
$usernameStmt->execute([$profileId]);
$username = $usernameStmt->fetchColumn();

echo json_encode(['username' => $username, 'history' => $rows]);
