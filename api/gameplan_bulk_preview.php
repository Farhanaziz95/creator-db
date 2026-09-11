<?php
/**
 * Round 35, item #2: step 1 of the bulk gameplan upload guardrail. Parses
 * every selected PDF, matches its first line against the Settings-stored
 * prefix, and reports whether the matched username exists in the DB —
 * but writes NOTHING to the gameplans table and touches no Flozy task.
 * Files are held in storage/gameplans/pending/ under a random token until
 * the person reviews the preview table and calls
 * api/gameplan_bulk_confirm.php. This split is the whole point of the
 * feature (confirmed in chat): never silently auto-attach.
 */
require_once __DIR__ . '/../includes/error_handler.php';
setup_json_error_handling();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Composer's smalot/pdfparser
require_once __DIR__ . '/../includes/gameplan_match.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['gameplans'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No files uploaded. Field name must be gameplans[].']);
    exit;
}

$pendingDir = __DIR__ . '/../storage/gameplans/pending';
if (!is_dir($pendingDir)) {
    mkdir($pendingDir, 0755, true);
}

// Sweep anything left over from an abandoned preview (browser closed
// mid-review, etc.) — a token that's neither confirmed nor explicitly
// discarded within 24h was never going to be. Cheap enough to run on
// every preview call rather than needing its own scheduled job.
foreach (glob($pendingDir . '/*.pdf') ?: [] as $stale) {
    if (filemtime($stale) < time() - 86400) {
        unlink($stale);
    }
}

$prefix = $pdo->query("SELECT match_prefix FROM gameplan_match_settings WHERE id = 1")->fetchColumn();
$prefix = $prefix !== false ? $prefix : '';

$names    = $_FILES['gameplans']['name'];
$tmpPaths = $_FILES['gameplans']['tmp_name'];
$errors   = $_FILES['gameplans']['error'];

$rows = [];

foreach ($names as $i => $originalName) {
    if ($errors[$i] !== UPLOAD_ERR_OK) {
        $rows[] = [
            'token' => null, 'filename' => $originalName, 'first_line' => null,
            'matched_username' => null, 'found' => false, 'profile_id' => null,
            'has_existing_gameplan' => false, 'error' => 'Upload failed.',
        ];
        continue;
    }

    $token = bin2hex(random_bytes(16));
    $destPath = $pendingDir . '/' . $token . '.pdf';

    if (!move_uploaded_file($tmpPaths[$i], $destPath)) {
        $rows[] = [
            'token' => null, 'filename' => $originalName, 'first_line' => null,
            'matched_username' => null, 'found' => false, 'profile_id' => null,
            'has_existing_gameplan' => false, 'error' => 'Could not save uploaded file.',
        ];
        continue;
    }

    $firstLine = '';
    try {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($destPath);
        $firstLine = extract_first_nonempty_line($pdf->getText());
    } catch (\Throwable $e) {
        $firstLine = '';
    }

    $username = $firstLine !== '' ? match_gameplan_first_line($firstLine, $prefix) : null;

    $profileId = null;
    $hasExisting = false;
    if ($username !== null) {
        $stmt = $pdo->prepare("SELECT id FROM profiles WHERE LOWER(username) = LOWER(?)");
        $stmt->execute([$username]);
        $found = $stmt->fetchColumn();
        if ($found) {
            $profileId = (int) $found;
            $stmt2 = $pdo->prepare("SELECT id FROM gameplans WHERE profile_id = ?");
            $stmt2->execute([$profileId]);
            $hasExisting = (bool) $stmt2->fetchColumn();
        }
    }

    $rows[] = [
        'token'                 => $token,
        'filename'              => $originalName,
        'first_line'            => $firstLine !== '' ? $firstLine : null,
        'matched_username'      => $username,
        'found'                 => $profileId !== null,
        'profile_id'            => $profileId,
        'has_existing_gameplan' => $hasExisting,
        'error'                 => null,
    ];
}

echo json_encode(['success' => true, 'rows' => $rows]);
