<?php
/**
 * Run this on a schedule (e.g. Windows Task Scheduler every 5 minutes):
 *   C:\xampp\php\php.exe C:\xampp\htdocs\creator-db\jobs\process_niche_queue.php
 *
 * Same logic as the "Run AI Niche Check Now" button on the dashboard —
 * see includes/niche_queue_processor.php.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/niche_queue_processor.php';

$result = run_niche_queue_batch($pdo);

if ($result['attempted'] === 0) {
    echo "Queue empty — nothing to process.\n";
    exit;
}

foreach ($result['details'] as $line) {
    echo $line . "\n";
}

echo "Done. Classified {$result['classified']} of {$result['attempted']} attempted.\n";
