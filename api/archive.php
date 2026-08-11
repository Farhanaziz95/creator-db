<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

$latestSnapshotJoin = "
    JOIN (
        SELECT ps1.*
        FROM profile_snapshots ps1
        INNER JOIN (
            SELECT profile_id, MAX(imported_at) AS max_date
            FROM profile_snapshots
            GROUP BY profile_id
        ) latest ON ps1.profile_id = latest.profile_id AND ps1.imported_at = latest.max_date
    ) s ON s.profile_id = p.id
";

if ($action === 'bulk_future_in_range') {
    $minFollowers  = (int) ($input['min_followers'] ?? 0);
    $maxFollowers  = ($input['max_followers'] ?? '') !== '' ? (int) $input['max_followers'] : PHP_INT_MAX;
    $minEngagement = (float) ($input['min_engagement'] ?? 0);
    $maxEngagement = ($input['max_engagement'] ?? '') !== '' ? (float) $input['max_engagement'] : 999999;

    // Moves everyone currently ACTIVE and WITHIN this band (inclusive) to
    // Future — for carving out a middle ground that's neither a clear
    // qualifier nor a clear archive candidate.
    $stmt = $pdo->prepare("
        UPDATE profiles p
        $latestSnapshotJoin
        SET p.status = 'future'
        WHERE p.status = 'active'
          AND s.followers_count BETWEEN :minFollowers AND :maxFollowers
          AND s.engagement_rate BETWEEN :minEngagement AND :maxEngagement
    ");
    $stmt->execute([
        ':minFollowers' => $minFollowers, ':maxFollowers' => $maxFollowers,
        ':minEngagement' => $minEngagement, ':maxEngagement' => $maxEngagement,
    ]);

    echo json_encode(['success' => true, 'moved_count' => $stmt->rowCount()]);
    exit;
}

if ($action === 'archive_selected') {
    $ids = array_map('intval', $input['profile_ids'] ?? []);
    if (!$ids) { echo json_encode(['success' => true, 'archived_count' => 0]); exit; }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("UPDATE profiles SET status = 'archived', archived = 1, archived_at = NOW() WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    echo json_encode(['success' => true, 'archived_count' => $stmt->rowCount()]);
    exit;
}

if ($action === 'future_selected') {
    $ids = array_map('intval', $input['profile_ids'] ?? []);
    if (!$ids) { echo json_encode(['success' => true, 'moved_count' => 0]); exit; }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("UPDATE profiles SET status = 'future' WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    echo json_encode(['success' => true, 'moved_count' => $stmt->rowCount()]);
    exit;
}

if ($action === 'restore_selected') {
    $ids = array_map('intval', $input['profile_ids'] ?? []);
    if (!$ids) { echo json_encode(['success' => true, 'restored_count' => 0]); exit; }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("UPDATE profiles SET status = 'active', archived = 0, archived_at = NULL WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    echo json_encode(['success' => true, 'restored_count' => $stmt->rowCount()]);
    exit;
}

if ($action === 'bulk_archive_below_threshold') {
    $minFollowers  = (int) ($input['min_followers'] ?? 0);
    $minEngagement = (float) ($input['min_engagement'] ?? 0);

    // AND logic — only archives profiles failing BOTH thresholds at once.
    $stmt = $pdo->prepare("
        UPDATE profiles p
        $latestSnapshotJoin
        SET p.status = 'archived', p.archived = 1, p.archived_at = NOW()
        WHERE p.status = 'active'
          AND s.followers_count < :minFollowers
          AND s.engagement_rate < :minEngagement
    ");
    $stmt->execute([':minFollowers' => $minFollowers, ':minEngagement' => $minEngagement]);

    echo json_encode(['success' => true, 'archived_count' => $stmt->rowCount()]);
    exit;
}

if ($action === 'archive_one') {
    $profileId = (int) ($input['profile_id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE profiles SET status = 'archived', archived = 1, archived_at = NOW() WHERE id = ?");
    $stmt->execute([$profileId]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'send_to_future') {
    $profileId = (int) ($input['profile_id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE profiles SET status = 'future' WHERE id = ?");
    $stmt->execute([$profileId]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'restore_one') {
    // Works from either Archived or Future back to Active.
    $profileId = (int) ($input['profile_id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE profiles SET status = 'active', archived = 0, archived_at = NULL WHERE id = ?");
    $stmt->execute([$profileId]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown or missing action.']);
