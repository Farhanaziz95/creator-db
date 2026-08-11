<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$addedToday   = (int) $pdo->query("SELECT COUNT(*) FROM profiles WHERE DATE(first_seen_at) = CURDATE()")->fetchColumn();
$addedWeek    = (int) $pdo->query("SELECT COUNT(*) FROM profiles WHERE first_seen_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
$addedMonth   = (int) $pdo->query("SELECT COUNT(*) FROM profiles WHERE first_seen_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$duplicates   = (int) $pdo->query("SELECT COALESCE(SUM(duplicate_count), 0) FROM import_batches")->fetchColumn();
$pendingNiche = (int) $pdo->query("SELECT COUNT(*) FROM ai_queue WHERE status = 'pending'")->fetchColumn();
$totalProfiles = (int) $pdo->query("SELECT COUNT(*) FROM profiles")->fetchColumn();
$archivedCount = (int) $pdo->query("SELECT COUNT(*) FROM profiles WHERE status = 'archived'")->fetchColumn();
$futureCount   = (int) $pdo->query("SELECT COUNT(*) FROM profiles WHERE status = 'future'")->fetchColumn();
$flozyCount    = (int) $pdo->query("SELECT COUNT(*) FROM flozy_leads")->fetchColumn();
$activeCount   = $totalProfiles - $archivedCount - $futureCount - $flozyCount;

echo json_encode([
    'added_today'     => $addedToday,
    'added_week'      => $addedWeek,
    'added_month'     => $addedMonth,
    'duplicates_found'=> $duplicates,
    'pending_niche'   => $pendingNiche,
    'total_profiles'  => $totalProfiles,
    'active_count'    => $activeCount,
    'archived_count'  => $archivedCount,
    'future_count'    => $futureCount,
    'flozy_count'     => $flozyCount,
]);
