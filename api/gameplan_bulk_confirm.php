<?php
/**
 * Round 35, item #2: step 2 of the bulk gameplan upload guardrail —
 * only called after the person has reviewed the preview table from
 * api/gameplan_bulk_preview.php and explicitly confirmed. `attach`
 * writes those items; `discard` deletes the held pending file for
 * anything the person chose NOT to attach (a wrong/no match, or an
 * override to "skip"), so nothing lingers silently after a decision was
 * made either way. A token the client never mentions in either list
 * simply ages out via the 24h sweep in gameplan_bulk_preview.php.
 */
require_once __DIR__ . '/../includes/error_handler.php';
setup_json_error_handling();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Composer's smalot/pdfparser
require_once __DIR__ . '/../includes/gameplan_storage.php';
header('Content-Type: application/json');

// Tokens are always our own random_bytes(16) hex (see the preview
// endpoint) — reject anything that isn't exactly that shape rather than
// trusting client input to build a filesystem path.
function safe_gameplan_token(string $token): string
{
    return preg_match('/^[a-f0-9]{32}$/', $token) ? $token : '';
}

$input   = json_decode(file_get_contents('php://input'), true);
$attach  = $input['attach'] ?? [];
$discard = $input['discard'] ?? [];

$pendingDir = __DIR__ . '/../storage/gameplans/pending';
$storageDir = __DIR__ . '/../storage/gameplans';

$attached = 0;
$failed = [];

foreach ($attach as $item) {
    $token = safe_gameplan_token((string) ($item['token'] ?? ''));
    $profileId = (int) ($item['profile_id'] ?? 0);
    $originalFilename = (string) ($item['filename'] ?? ($token . '.pdf'));

    $pendingPath = $pendingDir . '/' . $token . '.pdf';
    if ($token === '' || $profileId <= 0 || !file_exists($pendingPath)) {
        $failed[] = ['filename' => $originalFilename, 'error' => 'File missing or expired — re-run the preview and try again.'];
        continue;
    }

    $extractedText = '';
    try {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($pendingPath);
        $extractedText = $pdf->getText();
    } catch (\Throwable $e) {
        $extractedText = '';
    }

    $storedFilename = $token . '.pdf';
    if (!rename($pendingPath, $storageDir . '/' . $storedFilename)) {
        $failed[] = ['filename' => $originalFilename, 'error' => 'Could not move file into storage.'];
        continue;
    }

    // Confirmed: overwrites an existing gameplan on a matched profile,
    // same as the single-file upload already does.
    save_gameplan_for_profile($pdo, $profileId, $originalFilename, $storedFilename, $extractedText, $storageDir);
    $attached++;
}

foreach ($discard as $token) {
    $token = safe_gameplan_token((string) $token);
    if ($token === '') {
        continue;
    }
    $path = $pendingDir . '/' . $token . '.pdf';
    if (file_exists($path)) {
        unlink($path);
    }
}

echo json_encode([
    'success'         => true,
    'attached'        => $attached,
    'total_attempted' => count($attach),
    'discarded'       => count($discard),
    'failed'          => $failed,
]);
