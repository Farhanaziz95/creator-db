<?php
require_once __DIR__ . '/../includes/error_handler.php';
setup_json_error_handling();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Composer's smalot/pdfparser
require_once __DIR__ . '/../includes/gameplan_storage.php';

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
// need history the way creator snapshots do). Round 35: this DB write +
// Flozy task-completion logic moved to includes/gameplan_storage.php so
// it's shared with the new bulk upload path.
$result = save_gameplan_for_profile($pdo, $profileId, $originalName, $storedFilename, $extractedText, $storageDir);

echo json_encode([
    'success' => true,
    'text_extracted' => $result['text_extracted'],
    'text_length' => $result['text_length'],
    'flozy_tasks_completed' => $result['flozy_tasks_completed'],
]);
}
