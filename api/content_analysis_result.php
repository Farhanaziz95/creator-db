<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$profileId = (int) ($_GET['profile_id'] ?? 0);

$stmt = $pdo->prepare("SELECT id FROM gameplans WHERE profile_id = ?");
$stmt->execute([$profileId]);
$hasGameplan = (bool) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT * FROM content_analysis_runs WHERE profile_id = ? ORDER BY started_at DESC LIMIT 1");
$stmt->execute([$profileId]);
$latestRun = $stmt->fetch();

echo json_encode(['has_gameplan' => $hasGameplan, 'latest_run' => $latestRun ?: null]);
