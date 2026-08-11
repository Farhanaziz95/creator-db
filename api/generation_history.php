<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$profileId = (int) ($_GET['profile_id'] ?? 0);

// Cold-outreach generations (hook + follow-up from Verify+Personalize / Retry AI Only)
$stmt = $pdo->prepare("
    SELECT 'cold_outreach' AS type, draft_hook AS hook, draft_message AS message,
           NULL AS user_input, finished_at AS generated_at
    FROM content_analysis_runs
    WHERE profile_id = ? AND status = 'done'
");
$stmt->execute([$profileId]);
$coldOutreach = $stmt->fetchAll();

// Every guided follow-up ever generated (any type)
$stmt = $pdo->prepare("
    SELECT followup_type AS type, NULL AS hook, generated_message AS message,
           user_input, created_at AS generated_at
    FROM followup_messages
    WHERE profile_id = ?
");
$stmt->execute([$profileId]);
$followups = $stmt->fetchAll();

$combined = array_merge($coldOutreach, $followups);
usort($combined, fn($a, $b) => strtotime($b['generated_at']) <=> strtotime($a['generated_at'])); // newest first

// Data freshness — when was content last actually scraped for this lead
$stmt = $pdo->prepare("SELECT MAX(fetched_at) FROM post_transcripts WHERE profile_id = ?");
$stmt->execute([$profileId]);
$lastScraped = $stmt->fetchColumn();

$daysSinceScrape = $lastScraped ? floor((time() - strtotime($lastScraped)) / 86400) : null;

echo json_encode([
    'timeline'           => $combined,
    'last_scraped_at'    => $lastScraped,
    'days_since_scrape'  => $daysSinceScrape,
]);
