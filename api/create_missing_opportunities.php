<?php
/**
 * Round 32, part 3: creates a real Flozy Opportunity for any locally
 * pushed lead that never got one — specifically leads pushed BEFORE
 * Round 28 added automatic Opportunity creation on push. A regular sync
 * can't fix this: there's genuinely no Opportunity in Flozy for these
 * leads to sync FROM. One has to actually be created.
 *
 * Safety: before creating anything for a given lead, this confirms
 * against a FRESH live check (build_lead_opportunity_lookup(), same one
 * api/sync_flozy_stage.php uses) that Flozy really doesn't already have
 * an Opportunity for it — not just trusting the local flozy_opportunity_id
 * column, which could simply be un-synced rather than genuinely missing.
 * This avoids creating a duplicate Opportunity for a lead that already
 * has one.
 */
set_time_limit(0);

require_once __DIR__ . '/../includes/error_handler.php';
setup_json_error_handling();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/flozy_client.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$config = require __DIR__ . '/../config/flozy.php';

$stageLookup = build_stage_lookup();
$leadOpportunityLookup = build_lead_opportunity_lookup();

$rows = $pdo->query("SELECT profile_id, flozy_lead_id, flozy_opportunity_id FROM flozy_leads")->fetchAll();

$created = 0;
$alreadyHadOne = 0;
$failed = [];

foreach ($rows as $row) {
    $profileId = (int) $row['profile_id'];
    $flozyLeadId = (int) $row['flozy_lead_id'];

    $liveOpportunity = $leadOpportunityLookup[$flozyLeadId] ?? null;

    if ($liveOpportunity) {
        // Flozy already has one for real — this lead was never actually
        // missing an Opportunity, just possibly un-synced locally.
        // Backfill locally instead of creating a duplicate.
        $alreadyHadOne++;
        if (!$row['flozy_opportunity_id']) {
            $stageInfo = $stageLookup[$liveOpportunity['stage_id']] ?? null;
            $stmt = $pdo->prepare("
                UPDATE flozy_leads
                SET flozy_opportunity_id = ?, current_stage = ?, current_stage_tag = ?, stage_synced_at = NOW()
                WHERE profile_id = ?
            ");
            $stmt->execute([
                $liveOpportunity['opportunity_id'],
                $stageInfo['name'] ?? null,
                $stageInfo['tag'] ?? null,
                $profileId,
            ]);
        }
        continue;
    }

    $result = create_opportunity_for_lead($pdo, $profileId, $flozyLeadId, $config);
    if ($result['success']) {
        $created++;
    } else {
        $failed[] = ['profile_id' => $profileId, 'error' => $result['error']];
    }

    usleep(300000); // ~3/sec — stay well under Flozy's rate limit across what could be a large one-time batch
}

echo json_encode([
    'success'         => true,
    'total_leads'     => count($rows),
    'created'         => $created,
    'already_had_one' => $alreadyHadOne,
    'failed'          => $failed,
]);
