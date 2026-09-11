<?php
/**
 * Shared by the single-file upload (api/gameplan_upload.php) and the
 * bulk pattern-matched upload (api/gameplan_bulk_confirm.php) — pulled
 * out in Round 35 so the "one gameplan per lead, overwrite if one
 * already exists" + "mark the Flozy gameplan task complete" logic lives
 * in exactly one place instead of two copies drifting apart.
 *
 * Expects the PDF to already be sitting at $storageDir/$storedFilename —
 * both callers place it there before calling this. This function only
 * touches the database + Flozy, plus deleting a REPLACED gameplan's old
 * file (never the just-uploaded one).
 */
function save_gameplan_for_profile(PDO $pdo, int $profileId, string $originalFilename, string $storedFilename, string $extractedText, string $storageDir): array
{
    $stmt = $pdo->prepare("SELECT id, stored_filename FROM gameplans WHERE profile_id = ?");
    $stmt->execute([$profileId]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['stored_filename'] && $existing['stored_filename'] !== $storedFilename) {
            $oldPath = $storageDir . '/' . $existing['stored_filename'];
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }
        $stmt = $pdo->prepare("UPDATE gameplans SET original_filename = ?, stored_filename = ?, extracted_text = ?, uploaded_at = NOW() WHERE profile_id = ?");
        $stmt->execute([$originalFilename, $storedFilename, $extractedText, $profileId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO gameplans (profile_id, original_filename, stored_filename, extracted_text) VALUES (?, ?, ?, ?)");
        $stmt->execute([$profileId, $originalFilename, $storedFilename, $extractedText]);
    }

    // Same "if this lead already has an open gameplan task in Flozy, mark
    // it complete" behavior the single upload always had.
    require_once __DIR__ . '/flozy_client.php';
    $stmt = $pdo->prepare("SELECT flozy_task_id FROM flozy_lead_tasks WHERE profile_id = ? AND title LIKE '%gameplan%'");
    $stmt->execute([$profileId]);
    $taskIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($taskIds as $taskId) {
        flozy_request('PUT', '/tasks/' . $taskId, ['status' => 3]); // 3 = completed
    }

    return [
        'text_extracted'         => $extractedText !== '',
        'text_length'            => strlen($extractedText),
        'flozy_tasks_completed'  => count($taskIds),
    ];
}
