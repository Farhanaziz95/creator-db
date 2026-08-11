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
    // manually create one in Flozy's own UI.
    $opportunityError = null;
    $stageResult = flozy_request('GET', '/pipelines');
    if ($stageResult['success']) {
        $targetStageId = null;
        foreach ($stageResult['data'] ?? [] as $pipeline) {
            foreach ($pipeline['stages'] ?? [] as $stage) {
                if (strcasecmp($stage['name'], $config['default_opportunity_stage_name']) === 0) {
                    $targetStageId = $stage['id'];
                    break 2;
                }
            }
        }

        if ($targetStageId) {
            $closeDate = date('Y-m-d', strtotime('+' . (int) $config['default_opportunity_close_days'] . ' days'));
            $oppResult = flozy_request('POST', '/opportunities', [
                'stage_id'            => $targetStageId,
                'lead_id'             => $flozyLeadId,
                'value'               => 0, // unknown at push time — Flozy requires SOME value
                'expected_close_date' => $closeDate,
                'confidence'          => (int) $config['default_opportunity_confidence'],
            ]);
            if (!$oppResult['success']) {
                $opportunityError = $oppResult['error'];
            }
        } else {
            $opportunityError = "No pipeline stage named '{$config['default_opportunity_stage_name']}' found — check config/flozy.php matches a real stage name.";
        }
    } else {
        $opportunityError = $stageResult['error'];
    }

    return [
        'success'          => true,
        'flozy_lead_id'    => $flozyLeadId,
        'task_errors'      => $taskErrors, // lead push still counts as success even if a task or two failed
        'opportunity_error' => $opportunityError, // null if the Opportunity was created fine
    ];
}
