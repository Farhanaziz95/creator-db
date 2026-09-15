<?php
// Manual override of a channel's status — the human-in-the-loop half of
// the AI qualifying pass. 'raw' is included so a wrong AI call (qualify
// or reject) can be reset back to needing another look. Single
// (channel_id) or bulk (channel_ids array) — same shape as
// youtube_push_flozy.php's action: 'one'|'selected'.
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$statusRaw = $input['status'] ?? '';
$status = in_array($statusRaw, ['qualified', 'rejected', 'raw'], true) ? $statusRaw : null;

if (!$status) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid status (qualified/rejected/raw) is required.']);
    exit;
}

$reasonNote = $status === 'rejected' ? 'Manually rejected' : null;
$stmt = $pdo->prepare("UPDATE youtube_channels SET status = ?, needs_manual_review = 0, reject_reason = ? WHERE id = ?");

$channelIds = $input['channel_ids'] ?? null;
if (is_array($channelIds)) {
    $updated = 0;
    foreach ($channelIds as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $stmt->execute([$status, $reasonNote, $id]);
            $updated++;
        }
    }
    echo json_encode(['success' => true, 'updated' => $updated]);
    exit;
}

$channelId = (int) ($input['channel_id'] ?? 0);
if (!$channelId) {
    http_response_code(400);
    echo json_encode(['error' => 'channel_id or channel_ids is required.']);
    exit;
}

$stmt->execute([$status, $reasonNote, $channelId]);
echo json_encode(['success' => true]);
