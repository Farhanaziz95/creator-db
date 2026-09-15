<?php
// Both YouTube email fields are editable — `email` in case the regex
// missed or picked up a wrong address (same as Instagram's editable
// email), and `business_email` since it's ALWAYS manually entered (no
// way to scrape YouTube's protected business-inquiries email).
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$channelId = (int) ($input['channel_id'] ?? 0);
$field = $input['field'] ?? '';
$value = trim($input['value'] ?? '');

$allowedFields = ['email', 'business_email']; // whitelisted before interpolation below — never trust $field directly into SQL
if (!$channelId || !in_array($field, $allowedFields, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'channel_id and a valid field (email/business_email) are required.']);
    exit;
}

$stmt = $pdo->prepare("UPDATE youtube_channels SET {$field} = ? WHERE id = ?");
$stmt->execute([$value !== '' ? $value : null, $channelId]);

echo json_encode(['success' => true]);
