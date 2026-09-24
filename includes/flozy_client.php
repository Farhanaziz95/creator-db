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
 * Moves an EXISTING Opportunity to a named pipeline stage (by name, not
 * by ID — the caller doesn't know the numeric ID, only a real stage name
 * from config/flozy.php). Shared by api/toggle_outreach.php (moves to
 * the "contacted" stage on mark-outreached) and api/archive_flozy_lead.php
 * (moves to "Not A Right Fit" on archive). Also updates the local
 * current_stage/current_stage_tag cache on success, same as every other
 * stage-touching function in this file.
 *
 * Returns ['success' => bool, 'skipped' => bool, 'error' => ?string, ...]
 * — 'skipped' (not 'success' => false) is used for the two non-error
 * cases where there's genuinely nothing to do: no stage name configured,
 * or no Opportunity ID on record yet for this lead. Callers should treat
 * skipped as "fine, nothing happened" rather than a failure to surface.
 */
function move_opportunity_to_named_stage(PDO $pdo, int $profileId, string $stageName): array
{
    if (trim($stageName) === '') {
        return ['success' => false, 'skipped' => true, 'error' => null];
    }

    $stmt = $pdo->prepare("SELECT flozy_opportunity_id FROM flozy_leads WHERE profile_id = ?");
    $stmt->execute([$profileId]);
    $opportunityId = $stmt->fetchColumn();

    if (!$opportunityId) {
        return [
            'success' => false, 'skipped' => true,
            'error'   => 'No Flozy Opportunity ID on record yet — click 🔄 Sync on this lead first.',
        ];
    }

    $stageLookup = build_stage_lookup();
    $targetStageId = null;
    $targetStageName = null;
    $targetStageTag = null;
    foreach ($stageLookup as $id => $info) {
        if (strcasecmp($info['name'], $stageName) === 0) {
            $targetStageId = $id;
            $targetStageName = $info['name'];
            $targetStageTag = $info['tag'];
            break;
        }
    }

    if (!$targetStageId) {
        return [
            'success' => false, 'skipped' => false,
            'error'   => "No pipeline stage named '{$stageName}' found — check config/flozy.php matches a real stage name.",
        ];
    }

    $result = flozy_request('PUT', '/opportunities/' . $opportunityId, ['stage_id' => $targetStageId]);
    if (!$result['success']) {
        return ['success' => false, 'skipped' => false, 'error' => $result['error']];
    }

    $stmt = $pdo->prepare("UPDATE flozy_leads SET current_stage = ?, current_stage_tag = ?, stage_synced_at = NOW() WHERE profile_id = ?");
    $stmt->execute([$targetStageName, $targetStageTag, $profileId]);

    return ['success' => true, 'skipped' => false, 'error' => null, 'stage_name' => $targetStageName, 'stage_tag' => $targetStageTag];
}

/**
 * Round 35, item #1: pushes a Contact (POST /leads/{leadId}/contacts) for
 * an already-pushed lead, using whatever email is currently on file for
 * this profile. Mirrors create_opportunity_for_lead()'s
 * success/skipped/error shape (see move_opportunity_to_named_stage()'s
 * docblock for why 'skipped' is kept separate from a real failure).
 *
 * Skipped (not an error) when: no email on file yet, or this lead already
 * has a flozy_contact_id on record — never fires twice for the same lead.
 *
 * Flozy's Contacts API is POST-only per the docs pulled for this round —
 * no update/PUT endpoint was found, so there's no "update" path to take
 * even though the column is named the same way as flozy_opportunity_id.
 * A 409 means Flozy already has a contact with this email under this
 * lead (created some other way — directly in Flozy, or a retry that
 * actually landed before this one got recorded locally); since the docs
 * don't return the existing Contact's ID on a 409, that case is treated
 * as skipped rather than an error — there's genuinely nothing more to do,
 * just no local ID to store.
 */
function push_contact_for_lead(PDO $pdo, int $profileId, int $flozyLeadId, string $fullName, ?string $email): array
{
    if (!$email) {
        return ['success' => false, 'skipped' => true, 'error' => null];
    }

    $stmt = $pdo->prepare("SELECT flozy_contact_id FROM flozy_leads WHERE profile_id = ?");
    $stmt->execute([$profileId]);
    if ($stmt->fetchColumn()) {
        return ['success' => false, 'skipped' => true, 'error' => null];
    }

    $result = flozy_request('POST', '/leads/' . $flozyLeadId . '/contacts', [
        'full_name' => $fullName ?: ('@' . $email), // Flozy requires full_name; falls back to something non-empty
        'email'     => $email,
    ]);

    if (!$result['success']) {
        if ($result['http_code'] === 409) {
            return ['success' => false, 'skipped' => true, 'error' => null];
        }
        return ['success' => false, 'skipped' => false, 'error' => $result['error']];
    }

    $contactId = $result['data']['id'] ?? null;
    if ($contactId) {
        $stmt = $pdo->prepare("UPDATE flozy_leads SET flozy_contact_id = ? WHERE profile_id = ?");
        $stmt->execute([$contactId, $profileId]);
    }

    return ['success' => true, 'skipped' => false, 'error' => null, 'contact_id' => $contactId];
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
        SELECT p.username, p.full_name, p.external_url, p.email, n.name AS niche,
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

    // Cross-platform contact dedup (lightweight — warns, never blocks
    // the push). The two pipelines don't share a "person" table, so
    // email is the only identity signal available across them. Checks
    // whether this same email already belongs to a YouTube channel
    // that's already been pushed to Flozy.
    $crossPlatformWarning = null;
    if ($profile['email']) {
        $stmt = $pdo->prepare("
            SELECT yc.channel_name FROM youtube_channels yc
            JOIN youtube_flozy_leads yfl ON yfl.channel_id = yc.id
            WHERE yc.email = ? OR yc.business_email = ?
            LIMIT 1
        ");
        $stmt->execute([$profile['email'], $profile['email']]);
        $match = $stmt->fetchColumn();
        if ($match) {
            $crossPlatformWarning = "This email is already linked to YouTube channel \"{$match}\", already pushed to Flozy — check for a duplicate Lead before treating this as a brand-new contact.";
        }
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

    // Round 35, item #1: auto-fires a Contact push at push time if the
    // email is already known then. If it isn't known yet (extracted
    // later, or manually added afterward), "Push Contact" on the Sent to
    // Flozy tab covers it retroactively — same shape as
    // "Create Missing Opportunities" for leads pushed before Round 28.
    $contactPush = push_contact_for_lead($pdo, $profileId, $flozyLeadId, $profile['full_name'] ?: $profile['username'], $profile['email']);
    $contactError = (!$contactPush['success'] && !$contactPush['skipped']) ? $contactPush['error'] : null;

    return [
        'success'          => true,
        'flozy_lead_id'    => $flozyLeadId,
        'task_errors'      => $taskErrors, // lead push still counts as success even if a task or two failed
        'opportunity_error' => $opportunityError, // null if the Opportunity was created fine
        'contact_error'    => $contactError, // null if contact push succeeded, was skipped, or no email known yet
        'cross_platform_warning' => $crossPlatformWarning, // null unless the same email is already a pushed YouTube lead
    ];
}
