<?php
/**
 * "Why rejected" audit — deliberately cross-ROUND (not scoped to
 * whichever round happens to be selected in the dropdown), since the
 * actual point is spotting whether the AI's reject calls hold up
 * consistently across niches, not just within one. Lightweight: one
 * query, no new table, reads straight off youtube_channels'
 * reject_reason (already being written by both the AI verdict path and
 * the manual reject action).
 */
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$stmt = $pdo->query("
    SELECT c.id, c.channel_name, c.channel_username, c.channel_url, c.subscribers, c.reject_reason,
           r.niche, r.id AS round_id,
           s.total_score, s.grade
    FROM youtube_channels c
    JOIN youtube_rounds r ON r.id = c.round_id
    LEFT JOIN youtube_scores s ON s.channel_id = c.id
    WHERE c.status = 'rejected'
    ORDER BY c.imported_at DESC
");
$rows = $stmt->fetchAll();

foreach ($rows as &$row) {
    // Both reject paths write a distinguishable prefix — see
    // api/youtube_run_scoring.php ('AI: ...') and
    // api/youtube_set_channel_status.php ('Manually rejected...').
    $row['rejected_by'] = (strpos((string) $row['reject_reason'], 'AI:') === 0) ? 'AI' : 'Manual';
}

echo json_encode(['rejections' => $rows]);
