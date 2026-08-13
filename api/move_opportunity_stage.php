<?php
/**
 * Moves a lead's Flozy Opportunity to a different pipeline stage directly
 * from this app, instead of switching over to Flozy's UI.
 *
 * Built against Flozy's real API docs (confirmed, not guessed):
 *   PUT /opportunities/{id}
 *   body: { stage_id: int }  — a partial update; Flozy's own docs example
 *   only sends stage_id + confidence, so other fields (value, lead_id,
 *   expected_close_date) don't need to be resent.
 *
 * Needs the Opportunity's own ID (flozy_leads.flozy_opportunity_id), which
 * is NOT the same as the Lead ID. Leads pushed before Round 31 won't have
 * this captured yet — they get backfilled automatically the next time
 * "Sync Pipeline Stage" (or "Sync All") runs, via api/sync_flozy_stage.php.
 */
require_once __DIR__ . '/../includes/error_handler.php';
setup_json_error_handling();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/flozy_client.php';
header('Content-Type: application/json');

$input      = json_decode(file_get_contents('php://input'), true);
$profileId  = (int) ($input['profile_id'] ?? 0);
$stageId    = (int) ($input['stage_id'] ?? 0);
$stageName  = $input['stage_name'] ?? null; // used only to refresh the local display, not sent to Flozy
$stageTag   = $input['stage_tag'] ?? null;

if (!$profileId || !$stageId) {
    http_response_code(400);
    echo json_encode(['error' => 'profile_id and stage_id are required.']);
    exit;
}

$stmt = $pdo->prepare("SELECT flozy_opportunity_id FROM flozy_leads WHERE profile_id = ?");
$stmt->execute([$profileId]);
$opportunityId = $stmt->fetchColumn();

if (!$opportunityId) {
    http_response_code(400);
    echo json_encode([
        'error' => 'No Flozy Opportunity ID on record for this lead yet — click "Sync Pipeline Stage" (🔄) on this row first, then try again.',
    ]);
    exit;
}

$result = flozy_request('PUT', '/opportunities/' . $opportunityId, [
    'stage_id' => $stageId,
]);

if (!$result['success']) {
    http_response_code(500);
    echo json_encode(['error' => 'Flozy update failed: ' . $result['error']]);
    exit;
}

// Refresh the local display immediately using what the frontend already
// knows (it just fetched the real stage list to build the picker) —
// avoids a second API round-trip just to re-read back what we set.
$stmt = $pdo->prepare("UPDATE flozy_leads SET current_stage = ?, current_stage_tag = ?, stage_synced_at = NOW() WHERE profile_id = ?");
$stmt->execute([$stageName, $stageTag, $profileId]);

echo json_encode(['success' => true, 'stage' => $stageName, 'tag' => $stageTag]);
