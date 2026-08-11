<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $pdo->query("
        SELECT n.id, n.name, n.is_ai_generated, n.category_id, nc.name AS category_name,
               COUNT(p.id) AS profile_count
        FROM niches n
        LEFT JOIN profiles p ON p.niche_id = n.id
        LEFT JOIN niche_categories nc ON nc.id = n.category_id
        GROUP BY n.id, n.name, n.is_ai_generated, n.category_id, nc.name
        ORDER BY n.name ASC
    ")->fetchAll();
    echo json_encode(['data' => $rows]);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'set_category') {
    $nicheId    = (int) ($input['niche_id'] ?? 0);
    $categoryId = $input['category_id'] !== '' && $input['category_id'] !== null ? (int) $input['category_id'] : null;
    $stmt = $pdo->prepare("UPDATE niches SET category_id = ? WHERE id = ?");
    $stmt->execute([$categoryId, $nicheId]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'merge') {
    $fromId = (int) ($input['from_niche_id'] ?? 0);
    $intoId = (int) ($input['into_niche_id'] ?? 0);

    if ($fromId === $intoId || !$fromId || !$intoId) {
        http_response_code(400);
        echo json_encode(['error' => 'Pick two different niches to merge.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE profiles SET niche_id = ? WHERE niche_id = ?");
    $stmt->execute([$intoId, $fromId]);
    $moved = $stmt->rowCount();

    // keyword_rules stores niche by NAME (not a foreign key) — without this,
    // any bio matching an old keyword would silently recreate the niche you
    // just merged away via get_or_create_niche().
    $fromName = $pdo->query("SELECT name FROM niches WHERE id = " . (int) $fromId)->fetchColumn();
    $intoName = $pdo->prepare("SELECT name FROM niches WHERE id = ?");
    $intoName->execute([$intoId]);
    $intoNiceName = $intoName->fetchColumn();

    $pdo->prepare("DELETE FROM niches WHERE id = ?")->execute([$fromId]);

    if ($fromName && $intoNiceName) {
        $stmt = $pdo->prepare("UPDATE keyword_rules SET niche_name = ? WHERE niche_name = ?");
        $stmt->execute([$intoNiceName, $fromName]);
    }

    echo json_encode(['success' => true, 'profiles_moved' => $moved]);
    exit;
}

if ($action === 'rename') {
    $id      = (int) ($input['niche_id'] ?? 0);
    $newName = trim($input['new_name'] ?? '');
    if (!$newName) {
        http_response_code(400);
        echo json_encode(['error' => 'New name is required.']);
        exit;
    }
    $stmt = $pdo->prepare("UPDATE niches SET name = ? WHERE id = ?");
    $stmt->execute([$newName, $id]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown or missing action.']);
