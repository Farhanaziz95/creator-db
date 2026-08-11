<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$input     = json_decode(file_get_contents('php://input'), true);
$profileId = (int) ($input['profile_id'] ?? 0);
$notes     = $input['notes'] ?? '';

$stmt = $pdo->prepare("UPDATE profiles SET notes = ? WHERE id = ?");
$stmt->execute([$notes, $profileId]);

echo json_encode(['success' => true]);
