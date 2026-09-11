<?php
// Round 35, item #12: sets a Flozy lead's Low/Mid priority tier. Purely
// local — confirmed no Flozy sync needed for this at all — same simple
// shape as api/notes.php.
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$input     = json_decode(file_get_contents('php://input'), true);
$profileId = (int) ($input['profile_id'] ?? 0);
$tierRaw   = $input['tier'] ?? '';
// Whitelisted — anything else (including '' for "un-triaged") stores NULL,
// which is what puts a lead back under "All" only.
$tier = in_array($tierRaw, ['low', 'mid'], true) ? $tierRaw : null;

$stmt = $pdo->prepare("UPDATE flozy_leads SET priority_tier = ? WHERE profile_id = ?");
$stmt->execute([$tier, $profileId]);

echo json_encode(['success' => true]);
