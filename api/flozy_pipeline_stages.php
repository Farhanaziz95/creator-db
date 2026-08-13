<?php
/**
 * Returns every real pipeline stage (id, name, tag) straight from Flozy's
 * GET /pipelines — powers the stage picker in the "Move Stage" modal.
 * Deliberately NOT the same thing as api/flozy_stages_in_use.php, which
 * only lists stages your current leads already sit in (local, no API
 * call) — this one needs to offer every stage that EXISTS, including ones
 * no lead is in yet, and needs the numeric id Flozy actually requires for
 * the update call, which the local-only endpoint doesn't have.
 */
require_once __DIR__ . '/../includes/flozy_client.php';
header('Content-Type: application/json');

$result = flozy_request('GET', '/pipelines');

$stages = [];
if ($result['success']) {
    foreach ($result['data'] ?? [] as $pipeline) {
        foreach ($pipeline['stages'] ?? [] as $stage) {
            $stages[] = [
                'id'   => $stage['id'],
                'name' => $stage['name'],
                'tag'  => $stage['tag_name'] ?? null,
            ];
        }
    }
}

echo json_encode(['data' => $stages, 'flozy_reachable' => $result['success']]);
