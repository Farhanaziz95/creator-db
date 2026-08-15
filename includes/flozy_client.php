<?php
/**
 * Thin wrapper around Flozy's External API. All calls go through here so
 * auth, base URL, and error handling live in exactly one place.
 *
 * Returns: ['success' => bool, 'http_code' => int, 'data' => array|null, 'error' => string|null]
 */
function flozy_request(string $method, string $path, ?array $body = null): array
{
    $config  = require __DIR__ . '/../config/flozy.php';
    $apiKey  = $config['api_key'];
    $baseUrl = rtrim($config['base_url'], '/');

    $ch = curl_init($baseUrl . $path);

    $headers = [
        'X-API-Key: ' . $apiKey,
        'Content-Type: application/json',
    ];

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
    ];

    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($body);
    }

    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'http_code' => 0, 'data' => null, 'error' => $curlError];
    }

    $decoded = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'http_code' => $httpCode, 'data' => $decoded['data'] ?? null, 'error' => null];
    }

    $errorMsg = $decoded['message'] ?? "HTTP {$httpCode}";
    return ['success' => false, 'http_code' => $httpCode, 'data' => null, 'error' => $errorMsg];
}

/**
 * Returns [stage_id => ['name' => ..., 'tag' => ...]] across all pipelines.
 * Shared by api/sync_flozy_stage.php and api/create_missing_opportunities.php
 * — moved here in Round 32 so both can use the same source of truth
 * instead of two copies of the same pagination logic.
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
 * Returns [lead_id => ['stage_id' => ..., 'opportunity_id' => ...]] by
 * paginating through every opportunity. If a lead somehow has more than
 * one opportunity, the last one seen wins (opportunities are returned
 * newest-first by default per the API's default order=desc).
 * Shared the same way build_stage_lookup() is — see note above.
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
                $lookup[$leadId] = [
                    'stage_id'       => $opp['stage_id'] ?? null,
                    'opportunity_id' => $opp['id'] ?? null,
                ];
            }
        }
        $totalPages = $result['data']['pagination']['total_pages'] ?? 1;
        $page++;
    } while ($page <= $totalPages);

    return $lookup;
}

/**
 * Creates a Flozy Opportunity for an existing lead and stores its own ID
 * locally (flozy_opportunity_id — separate from flozy_lead_id, and
 * required to ever update this Opportunity later, e.g. Round 31's "Move
 * Stage"). Also stamps current_stage/current_stage_tag immediately using
 * the stage we just set it to, so there's no need for a follow-up sync
 * just to see it reflected.
 *
 * Shared by push_profile_to_flozy() (creates one at push time, as of
 * Round 28) and api/create_missing_opportunities.php (Round 32 — creates
 * one retroactively for leads pushed BEFORE Round 28 existed, which never
 * got one at all).
 */
function create_opportunity_for_lead(PDO $pdo, int $profileId, int $flozyLeadId, array $config): array
{
    $stageResult = flozy_request('GET', '/pipelines');
    if (!$stageResult['success']) {
        return ['success' => false, 'error' => $stageResult['error']];
    }

    $targetStageId = null;
    $targetStageName = null;
    $targetStageTag = null;
    foreach ($stageResult['data'] ?? [] as $pipeline) {
        foreach ($pipeline['stages'] ?? [] as $stage) {
            if (strcasecmp($stage['name'], $config['default_opportunity_stage_name']) === 0) {
                $targetStageId = $stage['id'];
                $targetStageName = $stage['name']; // Flozy's exact casing, not the config string
                $targetStageTag = $stage['tag_name'] ?? null;
                break 2;
            }
        }
    }

    if (!$targetStageId) {
        return [
            'success' => false,
            'error'   => "No pipeline stage named '{$config['default_opportunity_stage_name']}' found — check config/flozy.php matches a real stage name.",
        ];
    }

    $closeDate = date('Y-m-d', strtotime('+' . (int) $config['default_opportunity_close_days'] . ' days'));
    $oppResult = flozy_request('POST', '/opportunities', [
        'stage_id'            => $targetStageId,
        'lead_id'             => $flozyLeadId,
        'value'               => 0, // unknown at creation time — Flozy requires SOME value
        'expected_close_date' => $closeDate,
        'confidence'          => (int) $config['default_opportunity_confidence'],
    ]);

    if (!$oppResult['success']) {
        return ['success' => false, 'error' => $oppResult['error']];
    }

    $opportunityId = $oppResult['data']['id'] ?? null; // confirmed as data.id per Flozy's real API docs
    if ($opportunityId) {
        $stmt = $pdo->prepare("
            UPDATE flozy_leads
            SET flozy_opportunity_id = ?, current_stage = ?, current_stage_tag = ?, stage_synced_at = NOW()
            WHERE profile_id = ?
        ");
        $stmt->execute([$opportunityId, $targetStageName, $targetStageTag, $profileId]);
    }

    return ['success' => true, 'opportunity_id' => $opportunityId, 'stage_name' => $targetStageName, 'stage_tag' => $targetStageTag];
}

/**
 * Paginates through EVERY task in the account and returns them all,
 * unfiltered. GET /tasks has no lead_id filter (confirmed against
 * Flozy's real docs), so both api/flozy_lead_tasks.php (filters to one
 * lead) and api/flozy_overdue_tasks.php (filters to overdue across every
 * lead) need this same full scan — shared here so there's one copy of
 * the pagination loop instead of two.
 */
function fetch_all_flozy_tasks(): array
{
    $tasks = [];
    $page = 1;

    do {
        $result = flozy_request('GET', '/tasks?page=' . $page . '&limit=100&order=desc');
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'], 'tasks' => []];
        }
        $tasks = array_merge($tasks, $result['data']['items'] ?? []);
        $totalPages = $result['data']['pagination']['total_pages'] ?? 1;
        $page++;
    } while ($page <= $totalPages);

    return ['success' => true, 'error' => null, 'tasks' => $tasks];
}

/**
 * Pushes one profile to Flozy: creates the lead, then creates every active
 * task template linked to it. If the lead is already pushed (exists in our
 * local flozy_leads table), this is a no-op and returns early.
 */
function push_profile_to_flozy(PDO $pdo, int $profileId): array
{
    // Already pushed? Don't duplicate.
    $stmt = $pdo->prepare("SELECT flozy_lead_id FROM flozy_leads WHERE profile_id = ?");
    $stmt->execute([$profileId]);
    if ($stmt->fetchColumn()) {
        return ['success' => false, 'error' => 'Already pushed to Flozy'];
    }

    $stmt = $pdo->prepare("
        SELECT p.username, p.full_name, p.external_url, n.name AS niche,
               s.followers_count, s.engagement_rate, s.avg_likes, s.avg_comments, s.posts_per_week
        FROM profiles p
        LEFT JOIN niches n ON n.id = p.niche_id
        JOIN (
            SELECT ps1.* FROM profile_snapshots ps1
            INNER JOIN (
                SELECT profile_id, MAX(imported_at) AS max_date FROM profile_snapshots GROUP BY profile_id
            ) latest ON ps1.profile_id = latest.profile_id AND ps1.imported_at = latest.max_date
        ) s ON s.profile_id = p.id
        WHERE p.id = ?
    ");
    $stmt->execute([$profileId]);
    $profile = $stmt->fetch();

    if (!$profile) {
        return ['success' => false, 'error' => 'Profile not found'];
    }

    $config = require __DIR__ . '/../config/flozy.php';

    $title = '@' . $profile['username'] . ' | ' . ($profile['full_name'] ?: $profile['username']);

    $description = sprintf(
        'Followers: %s | Engagement: %s%% | Niche: %s | Avg Likes: %s | Avg Comments: %s | Posts/Week: %s',
        number_format($profile['followers_count']),
        $profile['engagement_rate'],
        $profile['niche'] ?: 'unassigned',
        $profile['avg_likes'],
        $profile['avg_comments'],
        $profile['posts_per_week'] ?? 'n/a'
    );
    $description = substr($description, 0, 500); // Flozy's cap on meta_data.about.description

    // Always the Instagram profile itself — the bio link (external_url) is
    // a different thing and shouldn't be what the Flozy lead points to.
    $leadUrl = 'https://instagram.com/' . $profile['username'];

    $result = flozy_request('POST', '/leads', [
        'title'       => substr($title, 0, 200), // Flozy's title cap
        'status_name' => $config['default_status_name'],
        'meta_data'   => [
            'about' => [
                'description' => $description,
                'url'         => $leadUrl,
            ],
        ],
    ]);

    if (!$result['success']) {
        return ['success' => false, 'error' => $result['error']];
    }

    $flozyLeadId = $result['data']['id'] ?? null;
    if (!$flozyLeadId) {
        return ['success' => false, 'error' => 'Flozy did not return a lead ID'];
    }

    $stmt = $pdo->prepare("INSERT INTO flozy_leads (profile_id, flozy_lead_id) VALUES (?, ?)");
    $stmt->execute([$profileId, $flozyLeadId]);

    // Attach every active default task to the new lead. If a gameplan was
    // already uploaded (pre-qualify workflow), create the gameplan task as
    // already-done rather than an open todo.
    $templates = $pdo->query("SELECT * FROM flozy_task_templates WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
    $taskErrors = [];

    $stmt = $pdo->prepare("SELECT id FROM gameplans WHERE profile_id = ?");
    $stmt->execute([$profileId]);
    $hasGameplan = (bool) $stmt->fetchColumn();

    foreach ($templates as $tpl) {
        $dueDate = null;
        if ($tpl['due_offset_days'] !== null) {
            $dueDate = date('Y-m-d', strtotime('+' . (int) $tpl['due_offset_days'] . ' days'));
        }

        $isGameplanTask = stripos($tpl['title'], 'gameplan') !== false;
        $status = ($isGameplanTask && $hasGameplan) ? 3 : (int) $tpl['status']; // 3 = completed

        $taskResult = flozy_request('POST', '/tasks', [
            'title'       => $tpl['title'],
            'description' => $tpl['description'],
            'status'      => $status,
            'priority'    => (int) $tpl['priority'],
            'due_date'    => $dueDate,
            'lead_id'     => $flozyLeadId,
        ]);

        if (!$taskResult['success']) {
            $taskErrors[] = $tpl['title'] . ': ' . $taskResult['error'];
        } else {
            $flozyTaskId = $taskResult['data']['id'] ?? null;
            if ($flozyTaskId) {
                $stmt = $pdo->prepare("INSERT INTO flozy_lead_tasks (profile_id, flozy_task_id, title) VALUES (?, ?, ?)");
                $stmt->execute([$profileId, $flozyTaskId, $tpl['title']]);
            }
        }
    }

    // Also creates the OPPORTUNITY, not just the lead — without this, a
    // pushed lead doesn't show up in your Pipeline view at all until you
    // manually create one in Flozy's own UI. Shared with the
    // "Create Missing Opportunities" retroactive fixer (Round 32) via
    // create_opportunity_for_lead() — see that function's docblock.
    $oppCreation = create_opportunity_for_lead($pdo, $profileId, $flozyLeadId, $config);
    $opportunityError = $oppCreation['success'] ? null : $oppCreation['error'];

    return [
        'success'          => true,
        'flozy_lead_id'    => $flozyLeadId,
        'task_errors'      => $taskErrors, // lead push still counts as success even if a task or two failed
        'opportunity_error' => $opportunityError, // null if the Opportunity was created fine
    ];
}
