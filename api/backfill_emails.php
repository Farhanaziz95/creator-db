<?php
/**
 * Round 35, item #1: one-time backfill for profiles imported before email
 * extraction existed. Same shape as api/recompute_scores.php ("Recompute
 * All Scores" in Settings) — runs once, on demand, over everyone.
 *
 * Uses each profile's LATEST snapshot biography (not every historical
 * snapshot) — same "most recent known state" approach the rest of this
 * project uses for bio/full_name/external_url. Only writes a value when
 * the regex actually finds one; profiles with no email in their bio, or
 * that already have a (possibly hand-corrected) email on file, are left
 * untouched either way — this never blanks out an existing value.
 */
set_time_limit(0);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/email_extraction.php';
header('Content-Type: application/json');

$rows = $pdo->query("
    SELECT p.id, p.email, s.biography
    FROM profiles p
    JOIN (
        SELECT ps1.profile_id, ps1.biography
        FROM profile_snapshots ps1
        INNER JOIN (
            SELECT profile_id, MAX(imported_at) AS max_date
            FROM profile_snapshots
            GROUP BY profile_id
        ) latest ON ps1.profile_id = latest.profile_id AND ps1.imported_at = latest.max_date
    ) s ON s.profile_id = p.id
")->fetchAll();

$updateStmt = $pdo->prepare("UPDATE profiles SET email = ? WHERE id = ?");

$checked = 0;
$found   = 0;
$skippedExisting = 0;

foreach ($rows as $row) {
    $checked++;

    if ($row['email']) {
        // Already has one on file (extracted earlier or hand-entered) —
        // never overwritten by a bulk backfill.
        $skippedExisting++;
        continue;
    }

    $extracted = extract_email_from_bio($row['biography']);
    if ($extracted !== null) {
        $updateStmt->execute([$extracted, $row['id']]);
        $found++;
    }
}

echo json_encode([
    'success'          => true,
    'checked'          => $checked,
    'found'            => $found,
    'skipped_existing' => $skippedExisting,
]);
