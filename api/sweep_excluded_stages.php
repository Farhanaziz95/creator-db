<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/flozy_client.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Pull your REAL live stage names from Flozy, not a guess.
    $result = flozy_request('GET', '/pipelines');
    $allStages = [];
    if ($result['success']) {
        foreach ($result['data'] ?? [] as $pipeline) {
            foreach ($pipeline['stages'] ?? [] as $stage) {
                $allStages[] = ['name' => $stage['name'], 'tag' => $stage['tag_name'] ?? null];
            }
        }
    }

    $excluded = $pdo->query("SELECT stage_name FROM sweep_excluded_stages")->fetchAll(PDO::FETCH_COLUMN);

    $data = array_map(fn($s) => [
        'name'     => $s['name'],
        'tag'      => $s['tag'],
        'excluded' => in_array($s['name'], $excluded, true),
    ], $allStages);

    echo json_encode(['data' => $data, 'flozy_reachable' => $result['success']]);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $stageName = trim($input['stage_name'] ?? '');
    $excluded  = !empty($input['excluded']);

    if ($excluded) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO sweep_excluded_stages (stage_name) VALUES (?)");
        $stmt->execute([$stageName]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM sweep_excluded_stages WHERE stage_name = ?");
        $stmt->execute([$stageName]);
    }

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
