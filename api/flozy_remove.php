<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/flozy_client.php';
header('Content-Type: application/json');

$input     = json_decode(file_get_contents('php://input'), true);
$profileId = (int) ($input['profile_id'] ?? 0);

$stmt = $pdo->prepare("SELECT flozy_lead_id FROM flozy_leads WHERE profile_id = ?");
$stmt->execute([$profileId]);
$flozyLeadId = $stmt->fetchColumn();

if (!$flozyLeadId) {
    http_response_code(404);
    echo json_encode(['error' => 'This profile was not found in the Flozy tab.']);
    exit;
}

// Real delete on Flozy's side — moves the lead to trash there and cascades
// to its tasks, exactly as requested.
$result = flozy_request('DELETE', '/leads/' . $flozyLeadId);

if (!$result['success']) {
    http_response_code(500);
    echo json_encode(['error' => 'Flozy delete failed: ' . $result['error']]);
    exit;
}

// Drop the local link. The profile itself and all its history are
// untouched — it just returns to being a normal Active/Archived entry.
$stmt = $pdo->prepare("DELETE FROM flozy_leads WHERE profile_id = ?");
$stmt->execute([$profileId]);

echo json_encode(['success' => true]);
