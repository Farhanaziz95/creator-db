<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$rounds = $pdo->query("
    SELECT r.*,
        (SELECT COUNT(*) FROM youtube_channels c WHERE c.round_id = r.id) AS total_channels,
        (SELECT COUNT(*) FROM youtube_channels c WHERE c.round_id = r.id AND c.status = 'raw') AS raw_count,
        (SELECT COUNT(*) FROM youtube_channels c WHERE c.round_id = r.id AND c.status = 'qualified') AS qualified_count,
        (SELECT COUNT(*) FROM youtube_channels c WHERE c.round_id = r.id AND c.status = 'rejected') AS rejected_count,
        (SELECT COUNT(*) FROM youtube_channels c WHERE c.round_id = r.id AND c.status = 'pushed') AS pushed_count,
        (SELECT COUNT(*) FROM youtube_channels c WHERE c.round_id = r.id AND ((c.email IS NOT NULL AND c.email != '') OR (c.business_email IS NOT NULL AND c.business_email != ''))) AS with_email_count
    FROM youtube_rounds r
    ORDER BY r.started_at DESC
")->fetchAll();

foreach ($rounds as &$r) {
    $r['sub_niches'] = json_decode($r['sub_niches'], true) ?: [];
    $r['hashtags'] = $r['hashtags'] ? (json_decode($r['hashtags'], true) ?: []) : [];
}

echo json_encode(['rounds' => $rounds]);
