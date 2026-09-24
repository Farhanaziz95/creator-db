<?php
require_once __DIR__ . '/../includes/error_handler.php';
setup_json_error_handling();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/youtube_flozy.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'one';

if ($action === 'one') {
    $channelId = (int) ($input['channel_id'] ?? 0);
    if (!$channelId) {
        http_response_code(400);
        echo json_encode(['error' => 'channel_id is required.']);
        exit;
    }
    echo json_encode(push_channel_to_flozy($pdo, $channelId));
    exit;
}

if ($action === 'selected') {
    $channelIds = array_map('intval', $input['channel_ids'] ?? []);
    $pushed = 0;
    $failed = [];
    $crossPlatformWarnings = [];

    foreach ($channelIds as $id) {
        $result = push_channel_to_flozy($pdo, $id);
        if ($result['success']) {
            $pushed++;
            if (!empty($result['cross_platform_warning'])) {
                $crossPlatformWarnings[] = ['channel_id' => $id, 'warning' => $result['cross_platform_warning']];
            }
        } else {
            $failed[] = ['channel_id' => $id, 'error' => $result['error']];
        }
        usleep(400000); // same ~2.5/sec pacing as the Instagram bulk Flozy push
    }

    echo json_encode([
        'success'         => true,
        'pushed'          => $pushed,
        'total_attempted' => count($channelIds),
        'failed'          => $failed,
        'cross_platform_warnings' => $crossPlatformWarnings,
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown or missing action.']);
