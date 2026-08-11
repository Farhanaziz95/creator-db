<?php
/**
 * Round 28: toggles the local outreach flag, and — only when marking
 * OUTREACHED (not on undo) — logs a completed task in Flozy as a
 * Flozy-side record that the DM went out. Undo is treated as a local
 * correction only; it deliberately does not touch Flozy, since there's
 * nothing meaningful to "un-log" there.
 *
 * Uses POST /tasks (confirmed endpoint, same one flozy_client.php already
 * uses elsewhere in this project) rather than a notes endpoint, since no
 * notes endpoint has been confirmed against Flozy's real API — per this
 * project's policy of never guessing third-party API fields.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/flozy_client.php';
header('Content-Type: application/json');

$input      = json_decode(file_get_contents('php://input'), true);
$profileId  = (int) ($input['profile_id'] ?? 0);
$outreached = !empty($input['outreached']);

$stmt = $pdo->prepare("SELECT flozy_lead_id FROM flozy_leads WHERE profile_id = ?");
$stmt->execute([$profileId]);
$flozyLeadId = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    UPDATE flozy_leads
    SET outreached_at = ?
    WHERE profile_id = ?
");
$stmt->execute([$outreached ? date('Y-m-d H:i:s') : null, $profileId]);

$flozyTaskError = null;

if ($outreached && $flozyLeadId) {
    $taskResult = flozy_request('POST', '/tasks', [
        'title'       => 'Outreach Sent',
        'description' => 'Marked as outreached in Creator DB on ' . date('Y-m-d H:i'),
        'status'      => 3, // 3 = completed — this is a record of something already done, not a todo
        'priority'    => 1, // low — informational, not actionable
        'lead_id'     => $flozyLeadId,
    ]);

    if (!$taskResult['success']) {
        // The local flag already saved successfully above — a Flozy-side
        // logging failure shouldn't roll that back or block the toggle,
        // just get surfaced so the person knows the Flozy record didn't land.
        $flozyTaskError = $taskResult['error'];
    }
}

echo json_encode(['success' => true, 'flozy_task_error' => $flozyTaskError]);
