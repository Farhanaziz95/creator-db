<?php
/**
 * Round 34: a quick way to move a lead OFF the Sent to Flozy tab and
 * into Archived, while reflecting that decision in Flozy too — without
 * doing a destructive delete like api/flozy_remove.php does.
 *
 * What this does NOT do: call DELETE on the Lead/Opportunity in Flozy.
 * The Lead and Opportunity stay fully intact there, just moved to the
 * "Not A Right Fit" stage.
 *
 * Round 35 FIX: this used to also DELETE the local flozy_leads row,
 * which wiped stage/outreach/opportunity-ID data and contradicted this
 * project's own "archive over delete, never destroy data" principle —
 * caught during testing. The row is now kept. api/profiles.php's
 * Archived view LEFT JOINs flozy_leads so Progress/Pipeline
 * Stage/Outreach still show for anything that came from Flozy, exactly
 * as they did before archiving. Only the Opportunity's STAGE changes;
 * nothing local or remote is deleted by this action.
 */
require_once __DIR__ . '/../includes/error_handler.php';
setup_json_error_handling();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/flozy_client.php';
header('Content-Type: application/json');

function archive_one_flozy_lead(PDO $pdo, int $profileId, string $notRightFitStageName): array
{
    $stageMove = move_opportunity_to_named_stage($pdo, $profileId, $notRightFitStageName);

    // A real failure (bad config, or a real Flozy error) still lets the
    // local archive proceed below — better to have it correctly filed
    // away locally with a known Flozy-side gap than stuck in limbo.
    $stageMoveError = (!$stageMove['success'] && !$stageMove['skipped']) ? $stageMove['error'] : null;

    $pdo->prepare("UPDATE profiles SET status = 'archived', archived = 1, archived_at = NOW() WHERE id = ?")->execute([$profileId]);

    return ['success' => true, 'stage_move_error' => $stageMoveError];
}

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'one';

$flozyConfig = require __DIR__ . '/../config/flozy.php';
$notRightFitStageName = $flozyConfig['default_not_right_fit_stage_name'] ?? '';

if ($action === 'one') {
    $profileId = (int) ($input['profile_id'] ?? 0);
    if (!$profileId) {
        http_response_code(400);
        echo json_encode(['error' => 'profile_id is required.']);
        exit;
    }
    echo json_encode(archive_one_flozy_lead($pdo, $profileId, $notRightFitStageName));
    exit;
}

if ($action === 'selected') {
    $profileIds = array_map('intval', $input['profile_ids'] ?? []);
    $archived = 0;
    $failed = [];

    foreach ($profileIds as $pid) {
        $result = archive_one_flozy_lead($pdo, $pid, $notRightFitStageName);
        $archived++;
        if ($result['stage_move_error']) {
            $failed[] = ['profile_id' => $pid, 'error' => $result['stage_move_error']];
        }
        usleep(300000); // ~3/sec, same pacing as other bulk Flozy-writing actions
    }

    echo json_encode(['success' => true, 'archived' => $archived, 'total_attempted' => count($profileIds), 'failed' => $failed]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown or missing action.']);
