<?php
require_once __DIR__ . '/../includes/error_handler.php';
setup_json_error_handling();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Composer's smalot/pdfparser

header('Content-Type: application/json');

try {
    handle_gameplan_upload($pdo);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unexpected error: ' . $e->getMessage() . ' (line ' . $e->getLine() . ')']);
}

function handle_gameplan_upload(PDO $pdo): void
{

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['gameplan']) || !isset($_POST['profile_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing file (field: gameplan) or profile_id.']);
    exit;
}

$profileId = (int) $_POST['profile_id'];
$tmpPath   = $_FILES['gameplan']['tmp_name'];
$originalName = $_FILES['gameplan']['name'];

$storageDir = __DIR__ . '/../storage/gameplans';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

$uuid = bin2hex(random_bytes(16));
$storedFilename = $uuid . '.pdf';
$destPath = $storageDir . '/' . $storedFilename;

if (!move_uploaded_file($tmpPath, $destPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save uploaded file.']);
    exit;
}

// Extract text via smalot/pdfparser (pure PHP, no external binary needed)
$extractedText = '';
try {
    $parser = new \Smalot\PdfParser\Parser();
    $pdf = $parser->parseFile($destPath);
    $extractedText = $pdf->getText();
} catch (\Throwable $e) {
    // Keep the file even if extraction fails — better to have the PDF and
    // no text than lose the upload entirely. You can still open it manually.
    $extractedText = '';
}

// One gameplan per lead — replace if one already exists (gameplans don't
// need history the way creator snapshots do)
$stmt = $pdo->prepare("SELECT id, stored_filename FROM gameplans WHERE profile_id = ?");
$stmt->execute([$profileId]);
$existing = $stmt->fetch();

if ($existing) {
    $oldPath = $storageDir . '/' . $existing['stored_filename'];
    if (file_exists($oldPath)) {
        unlink($oldPath);
    }
    $stmt = $pdo->prepare("UPDATE gameplans SET original_filename = ?, stored_filename = ?, extracted_text = ?, uploaded_at = NOW() WHERE profile_id = ?");
    $stmt->execute([$originalName, $storedFilename, $extractedText, $profileId]);
} else {
    $stmt = $pdo->prepare("INSERT INTO gameplans (profile_id, original_filename, stored_filename, extracted_text) VALUES (?, ?, ?, ?)");
    $stmt->execute([$profileId, $originalName, $storedFilename, $extractedText]);
}

// If this lead is already in Flozy with a gameplan-related task already
// created, mark it complete now instead of leaving it as an open todo.
require_once __DIR__ . '/../includes/flozy_client.php';

$stmt = $pdo->prepare("SELECT flozy_task_id FROM flozy_lead_tasks WHERE profile_id = ? AND title LIKE '%gameplan%'");
$stmt->execute([$profileId]);
$taskIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($taskIds as $taskId) {
    flozy_request('PUT', '/tasks/' . $taskId, ['status' => 3]); // 3 = completed
}

echo json_encode([
    'success' => true,
    'text_extracted' => $extractedText !== '',
    'text_length' => strlen($extractedText),
    'flozy_tasks_completed' => count($taskIds),
]);
}
