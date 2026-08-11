<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/quality_score.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $profileId = (int) ($_GET['profile_id'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT id, post_url, caption, posted_at
        FROM post_transcripts
        WHERE profile_id = ? AND needs_manual_review = 1 AND (review_status = 'pending' OR review_status IS NULL)
        ORDER BY posted_at DESC
    ");
    $stmt->execute([$profileId]);
    echo json_encode(['data' => $stmt->fetchAll()]);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $transcriptId = (int) ($input['transcript_id'] ?? 0);
    $status = $input['status'] ?? '';
    $profileId = (int) ($input['profile_id'] ?? 0);

    if (!in_array($status, ['confirmed', 'excluded'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Status must be confirmed or excluded.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE post_transcripts SET review_status = ? WHERE id = ?");
    $stmt->execute([$status, $transcriptId]);

    // Content-verified is a scoring signal — recompute now that a flag was resolved.
    if ($profileId) {
        recompute_quality_score_for_profile($pdo, $profileId);
    }

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
