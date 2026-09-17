<?php
// Creates a round — the container for one niche experiment. Confirmed:
// the round itself IS the niche label, no separate niche taxonomy needed.
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$niche = trim($input['niche'] ?? '');
$subNiches = array_values(array_filter(array_map('trim', $input['sub_niches'] ?? [])));
// Optional secondary discovery path — purely additive, a round with no
// hashtags behaves exactly as before.
$hashtags = array_values(array_filter(array_map('trim', $input['hashtags'] ?? [])));
$subscriberMin = (int) ($input['subscriber_min'] ?? 1000);
$maxPerKeyword = (int) ($input['max_channels_per_keyword'] ?? 100);

if ($niche === '' || !$subNiches) {
    http_response_code(400);
    echo json_encode(['error' => 'Niche and at least one sub-niche keyword are required.']);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO youtube_rounds (niche, sub_niches, hashtags, subscriber_min, max_channels_per_keyword)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([$niche, json_encode($subNiches), $hashtags ? json_encode($hashtags) : null, $subscriberMin, $maxPerKeyword]);

echo json_encode(['success' => true, 'round_id' => (int) $pdo->lastInsertId()]);
