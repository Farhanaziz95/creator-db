<?php
// Round 35, item #2: lets the bulk gameplan preview table's "fix a wrong
// match" input look a profile up by username or name, across every
// status (active/future/archived/flozy) — not just whichever tab happens
// to be open on the dashboard right now.
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode(['data' => []]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.id, p.username, p.full_name, p.status,
           EXISTS(SELECT 1 FROM gameplans g WHERE g.profile_id = p.id) AS has_gameplan
    FROM profiles p
    WHERE p.username LIKE ? OR p.full_name LIKE ?
    ORDER BY p.username ASC
    LIMIT 8
");
$like = '%' . $q . '%';
$stmt->execute([$like, $like]);
echo json_encode(['data' => $stmt->fetchAll()]);
