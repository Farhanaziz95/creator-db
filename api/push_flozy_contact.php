<?php
/**
 * Round 35, item #1: "Push Contact" — for leads that were pushed to Flozy
 * before their email was ever extracted/entered. push_profile_to_flozy()
 * auto-fires this at push time going forward if the email is already
 * known then; this covers the retroactive gap, same shape as
 * api/create_missing_opportunities.php.
 */
require_once __DIR__ . '/../includes/error_handler.php';
setup_json_error_handling();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/flozy_client.php';
header('Content-Type: application/json');

function push_contact_for_profile(PDO $pdo, int $profileId): array
{
    $stmt = $pdo->prepare("
        SELECT p.full_name, p.username, p.email, fl.flozy_lead_id, fl.flozy_contact_id
        FROM profiles p
        JOIN flozy_leads fl ON fl.profile_id = p.id
        WHERE p.id = ?
    ");
    $stmt->execute([$profileId]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['success' => false, 'skipped' => false, 'error' => 'Profile not found or not pushed to Flozy.'];
    }
    if (!$row['email']) {
        return ['success' => false, 'skipped' => true, 'error' => 'No email on file for this profile.'];
    }
    if ($row['flozy_contact_id']) {
        return ['success' => false, 'skipped' => true, 'error' => 'Contact already pushed for this lead.'];
    }

    return push_contact_for_lead($pdo, $profileId, (int) $row['flozy_lead_id'], $row['full_name'] ?: $row['username'], $row['email']);
}

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'one';

if ($action === 'one') {
    $profileId = (int) ($input['profile_id'] ?? 0);
    if (!$profileId) {
        http_response_code(400);
        echo json_encode(['error' => 'profile_id is required.']);
        exit;
    }
    echo json_encode(push_contact_for_profile($pdo, $profileId));
    exit;
}

if ($action === 'selected') {
    $profileIds = array_map('intval', $input['profile_ids'] ?? []);
    $pushed = 0;
    $skipped = 0;
    $failed = [];

    foreach ($profileIds as $pid) {
        $result = push_contact_for_profile($pdo, $pid);
        if ($result['success']) {
            $pushed++;
        } elseif ($result['skipped']) {
            $skipped++;
        } else {
            $failed[] = ['profile_id' => $pid, 'error' => $result['error']];
        }
        usleep(300000); // ~3/sec, same pacing as the other bulk Flozy-writing actions
    }

    echo json_encode([
        'success'         => true,
        'pushed'          => $pushed,
        'skipped'         => $skipped,
        'total_attempted' => count($profileIds),
        'failed'          => $failed,
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown or missing action.']);
