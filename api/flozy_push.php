<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/flozy_client.php';
header('Content-Type: application/json');

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'push_one') {
    $profileId = (int) ($input['profile_id'] ?? 0);
    $result = push_profile_to_flozy($pdo, $profileId);
    echo json_encode($result);
    exit;
}

if ($action === 'push_selected') {
    $profileIds = array_map('intval', $input['profile_ids'] ?? []);

    $pushed = 0;
    $failed = [];
    $crossPlatformWarnings = [];

    foreach ($profileIds as $pid) {
        $result = push_profile_to_flozy($pdo, $pid);
        if ($result['success']) {
            $pushed++;
            if (!empty($result['cross_platform_warning'])) {
                $crossPlatformWarnings[] = ['profile_id' => $pid, 'warning' => $result['cross_platform_warning']];
            }
        } else {
            $failed[] = ['profile_id' => $pid, 'error' => $result['error']];
        }
        usleep(400000);
    }

    echo json_encode([
        'success' => true,
        'pushed' => $pushed,
        'total_attempted' => count($profileIds),
        'failed' => $failed,
        'cross_platform_warnings' => $crossPlatformWarnings,
    ]);
    exit;
}

if ($action === 'push_qualified') {
    $minFollowers  = (int) ($input['min_followers'] ?? 0);
    $maxFollowers  = ($input['max_followers'] ?? '') !== '' ? (int) $input['max_followers'] : PHP_INT_MAX;
    $minEngagement = (float) ($input['min_engagement'] ?? 0);
    $maxEngagement = ($input['max_engagement'] ?? '') !== '' ? (float) $input['max_engagement'] : 999999;

    // Every currently-active profile that meets the threshold AND isn't
    // already pushed.
    $stmt = $pdo->prepare("
        SELECT p.id
        FROM profiles p
        JOIN (
            SELECT ps1.* FROM profile_snapshots ps1
            INNER JOIN (
                SELECT profile_id, MAX(imported_at) AS max_date FROM profile_snapshots GROUP BY profile_id
            ) latest ON ps1.profile_id = latest.profile_id AND ps1.imported_at = latest.max_date
        ) s ON s.profile_id = p.id
        WHERE p.status = 'active'
          AND s.followers_count BETWEEN :minFollowers AND :maxFollowers
          AND s.engagement_rate BETWEEN :minEngagement AND :maxEngagement
          AND p.id NOT IN (SELECT profile_id FROM flozy_leads)
    ");
    $stmt->execute([
        ':minFollowers' => $minFollowers, ':maxFollowers' => $maxFollowers,
        ':minEngagement' => $minEngagement, ':maxEngagement' => $maxEngagement,
    ]);
    $profileIds = array_column($stmt->fetchAll(), 'id');

    $pushed = 0;
    $failed = [];

    foreach ($profileIds as $pid) {
        $result = push_profile_to_flozy($pdo, $pid);
        if ($result['success']) {
            $pushed++;
        } else {
            $failed[] = ['profile_id' => $pid, 'error' => $result['error']];
        }
        usleep(400000); // ~2.5/sec — stays well under Flozy's 100 req/min per key, even with task calls stacked on top
    }

    echo json_encode(['success' => true, 'pushed' => $pushed, 'total_attempted' => count($profileIds), 'failed' => $failed]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown or missing action.']);
