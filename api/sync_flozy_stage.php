<?php
/**
 * CORRECTED VERSION: pulls real pipeline stage from Flozy's Opportunities +
 * Pipelines resources, not the Lead's own status_name field (that was a
 * different, simpler field — my original mistake, caught by inspecting the
 * actual Flozy UI).
 *
 * An Opportunity has a numeric stage_id; Pipelines gives the readable name
 * for that ID (e.g. "Presentation Call Booked") plus a tag_name
 * (active/won/lost). Since Opportunities' list endpoint doesn't have a
 * confirmed lead_id filter param, this fetches pipelines once + all
 * opportunities once (paginated), builds a lookup map, then matches every
 * profile against it locally — actually MORE efficient than my original
 * per-lead-call design, not less.
 */

require_once __DIR__ . '/../includes/error_handler.php';
setup_json_error_handling();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/flozy_client.php';
header('Content-Type: application/json');

/**
 * Returns [stage_id => ['name' => ..., 'tag' => ...]] across all pipelines.
 */
function build_stage_lookup(): array
{
    $result = flozy_request('GET', '/pipelines');
    if (!$result['success']) {
        return [];
    }

    $lookup = [];
    foreach ($result['data'] ?? [] as $pipeline) {
        foreach ($pipeline['stages'] ?? [] as $stage) {
            $lookup[$stage['id']] = ['name' => $stage['name'], 'tag' => $stage['tag_name'] ?? null];
        }
    }
    return $lookup;
}

/**
 * Returns [lead_id => stage_id] by paginating through every opportunity.
 * If a lead somehow has more than one opportunity, the last one seen wins
 * (opportunities are returned newest-first by default per the API's
 * default order=desc).
 */
function build_lead_opportunity_lookup(): array
{
    $lookup = [];
    $page = 1;

    do {
        $result = flozy_request('GET', '/opportunities?page=' . $page . '&limit=100');
        if (!$result['success']) {
            break;
        }
        $items = $result['data']['items'] ?? [];
        foreach ($items as $opp) {
            $leadId = $opp['lead_id'] ?? null;
            if ($leadId && !isset($lookup[$leadId])) { // first one seen = most recent, since newest-first
                $lookup[$leadId] = $opp['stage_id'];
            }
        }
        $totalPages = $result['data']['pagination']['total_pages'] ?? 1;
        $page++;
    } while ($page <= $totalPages);

    return $lookup;
}

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'sync_one';

$stageLookup = build_stage_lookup();
$leadOpportunityLookup = build_lead_opportunity_lookup();

function apply_stage(PDO $pdo, int $profileId, int $flozyLeadId, array $stageLookup, array $leadOpportunityLookup): array
{
    $stageId = $leadOpportunityLookup[$flozyLeadId] ?? null;

    if ($stageId === null) {
        $stmt = $pdo->prepare("UPDATE flozy_leads SET current_stage = NULL, current_stage_tag = NULL, stage_synced_at = NOW() WHERE profile_id = ?");
        $stmt->execute([$profileId]);
        return ['success' => true, 'stage' => null, 'note' => 'No opportunity found for this lead yet.'];
    }

    $stageInfo = $stageLookup[$stageId] ?? null;
    $stageName = $stageInfo['name'] ?? "Unknown stage (id {$stageId})";
    $stageTag  = $stageInfo['tag'] ?? null;

    $stmt = $pdo->prepare("UPDATE flozy_leads SET current_stage = ?, current_stage_tag = ?, stage_synced_at = NOW() WHERE profile_id = ?");
    $stmt->execute([$stageName, $stageTag, $profileId]);

    return ['success' => true, 'stage' => $stageName, 'tag' => $stageTag];
}

if ($action === 'sync_one') {
    $profileId = (int) ($input['profile_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT flozy_lead_id FROM flozy_leads WHERE profile_id = ?");
    $stmt->execute([$profileId]);
    $flozyLeadId = $stmt->fetchColumn();

    if (!$flozyLeadId) {
        http_response_code(404);
        echo json_encode(['error' => 'This profile is not in the Flozy tab.']);
        exit;
    }

    echo json_encode(apply_stage($pdo, $profileId, (int) $flozyLeadId, $stageLookup, $leadOpportunityLookup));
    exit;
}

if ($action === 'sync_all') {
    $rows = $pdo->query("SELECT profile_id, flozy_lead_id FROM flozy_leads")->fetchAll();
    $synced = 0;

    foreach ($rows as $row) {
        apply_stage($pdo, (int) $row['profile_id'], (int) $row['flozy_lead_id'], $stageLookup, $leadOpportunityLookup);
        $synced++;
    }

    echo json_encode(['success' => true, 'synced' => $synced, 'total' => count($rows)]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action.']);
