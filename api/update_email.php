<?php
// Round 35, item #1: editable email field, same shape as api/notes.php —
// in case the regex missed the address entirely or picked up the wrong
// one out of a bio with more than one.
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$input     = json_decode(file_get_contents('php://input'), true);
$profileId = (int) ($input['profile_id'] ?? 0);
$email     = trim($input['email'] ?? '');

$stmt = $pdo->prepare("UPDATE profiles SET email = ? WHERE id = ?");
$stmt->execute([$email !== '' ? $email : null, $profileId]);

echo json_encode(['success' => true]);
