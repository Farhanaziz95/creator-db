<?php
require_once __DIR__ . '/openrouter_client.php';
require_once __DIR__ . '/quality_score.php';

/**
 * Processes one batch off ai_queue. Shared by the scheduled Task Scheduler
 * job (jobs/process_niche_queue.php) and the manual "Run AI Niche Check
 * Now" button on the dashboard (api/run_niche_check.php) — same logic
 * either way, just triggered differently.
 */
function run_niche_queue_batch(PDO $pdo): array
{
    $config     = require __DIR__ . '/../config/openrouter.php';
    $batchSize  = $config['queue_batch_size'] ?? 10;
    $maxAttempts = $config['max_attempts'] ?? 5;

    $stmt = $pdo->prepare("
        SELECT q.id AS queue_id, q.profile_id, q.attempts, p.full_name,
               (SELECT s.biography FROM profile_snapshots s
                WHERE s.profile_id = q.profile_id
                ORDER BY s.imported_at DESC LIMIT 1) AS biography
        FROM ai_queue q
        JOIN profiles p ON p.id = q.profile_id
        WHERE q.status = 'pending'
        ORDER BY q.created_at ASC
        LIMIT :batchSize
    ");
    $stmt->bindValue(':batchSize', $batchSize, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    $result = ['attempted' => count($items), 'classified' => 0, 'still_pending' => 0, 'gave_up' => 0, 'details' => []];

    foreach ($items as $item) {
        $niche = classify_niche_with_openrouter(
            $item['biography'] ?? '',
            $item['full_name'] ?? '',
            $item['profile_id'],
            $pdo
        );

        if ($niche) {
            $nicheId = get_or_create_niche($pdo, $niche, true);

            $stmt = $pdo->prepare("UPDATE profiles SET niche_id = ?, niche_source = 'ai' WHERE id = ?");
            $stmt->execute([$nicheId, $item['profile_id']]);

            $stmt = $pdo->prepare("DELETE FROM ai_queue WHERE id = ?");
            $stmt->execute([$item['queue_id']]);

            recompute_quality_score_for_profile($pdo, $item['profile_id']);

            $result['classified']++;
            $result['details'][] = "Profile {$item['profile_id']}: classified as '{$niche}'";
        } else {
            $newAttempts = (int) $item['attempts'] + 1;

            if ($newAttempts >= $maxAttempts) {
                // Give up gracefully — mark Uncategorized rather than
                // retrying forever, and stop it consuming future batch slots.
                $uncategorizedId = get_or_create_niche($pdo, 'Uncategorized', true);

                $stmt = $pdo->prepare("UPDATE profiles SET niche_id = ?, niche_source = 'ai' WHERE id = ?");
                $stmt->execute([$uncategorizedId, $item['profile_id']]);

                $stmt = $pdo->prepare("UPDATE ai_queue SET status = 'failed', attempts = ?, last_attempt_at = NOW() WHERE id = ?");
                $stmt->execute([$newAttempts, $item['queue_id']]);

                recompute_quality_score_for_profile($pdo, $item['profile_id']);

                $result['gave_up']++;
                $result['details'][] = "Profile {$item['profile_id']}: gave up after {$newAttempts} attempts, marked Uncategorized";
            } else {
                $stmt = $pdo->prepare("UPDATE ai_queue SET attempts = ?, last_attempt_at = NOW() WHERE id = ?");
                $stmt->execute([$newAttempts, $item['queue_id']]);

                $result['still_pending']++;
                $result['details'][] = "Profile {$item['profile_id']}: all models failed this run ({$newAttempts}/{$maxAttempts}), will retry";
            }
        }
    }

    return $result;
}
