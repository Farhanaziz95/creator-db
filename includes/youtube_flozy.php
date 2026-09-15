<?php
require_once __DIR__ . '/flozy_client.php'; // reuses flozy_request() directly — that one's fully generic, no Instagram table coupling

/**
 * YouTube pipeline, Layer 3: pushes a channel to Flozy — Lead, Contact,
 * Opportunity, and default tasks, all in one call. Deliberately NOT
 * reusing push_profile_to_flozy()/create_opportunity_for_lead()/
 * push_contact_for_lead() from flozy_client.php — those update
 * `flozy_leads` keyed by profile_id internally, so reusing them would
 * mean either touching Instagram's proven code or faking a profile_id.
 * This is its own parallel function updating youtube_flozy_leads
 * instead — same shape, zero risk to the Instagram push path.
 *
 * Confirmed: email is already known at scrape time for YouTube (no
 * regex-extraction-after-the-fact gap the way Instagram had), so the
 * Contact push fires in THIS SAME call — no separate "Push Contact"
 * catch-up step needed here, unlike Instagram. Since channels can carry
 * TWO distinct emails (the regex-extracted `email` and the manually
 * entered `business_email` — YouTube's protected business-inquiries
 * address, never scrapeable), `business_email` is used as primary
 * whenever one is present.
 *
 * Judgment call: reuses the exact same flozy_task_templates and the
 * same default_status_name/opportunity settings from config/flozy.php
 * as Instagram, rather than a separate YouTube-specific template set —
 * matches "everyone ends up in Flozy the same way." The task wording
 * itself (e.g. a template mentioning "gameplan") is whatever you've
 * already configured there — edit those templates' text if any of them
 * read Instagram-specific once you see them on a YouTube lead.
 */
function push_channel_to_flozy(PDO $pdo, int $channelId): array
{
    $stmt = $pdo->prepare("SELECT flozy_lead_id FROM youtube_flozy_leads WHERE channel_id = ?");
    $stmt->execute([$channelId]);
    if ($stmt->fetchColumn()) {
        return ['success' => false, 'error' => 'Already pushed to Flozy'];
    }

    $stmt = $pdo->prepare("
        SELECT c.*, r.niche, s.total_score, s.grade
        FROM youtube_channels c
        JOIN youtube_rounds r ON r.id = c.round_id
        LEFT JOIN youtube_scores s ON s.channel_id = c.id
        WHERE c.id = ?
    ");
    $stmt->execute([$channelId]);
    $channel = $stmt->fetch();

    if (!$channel) {
        return ['success' => false, 'error' => 'Channel not found'];
    }

    $config = require __DIR__ . '/../config/flozy.php';

    $title = ($channel['channel_name'] ?: $channel['channel_username'] ?: 'YouTube channel') . ' (YouTube)';
    $description = sprintf(
        'Platform: YouTube | Subscribers: %s | Niche: %s | Score: %s%s',
        $channel['subscribers'] !== null ? number_format($channel['subscribers']) : 'n/a',
        $channel['niche'],
        $channel['total_score'] !== null ? $channel['total_score'] . '/100' : 'not yet scored',
        $channel['grade'] ? ' (' . $channel['grade'] . ')' : ''
    );
    $description = substr($description, 0, 500);

    $leadResult = flozy_request('POST', '/leads', [
        'title'       => substr($title, 0, 200),
        'status_name' => $config['default_status_name'],
        'meta_data'   => [
            'about' => [
                'description' => $description,
                'url'         => $channel['channel_url'],
            ],
        ],
    ]);

    if (!$leadResult['success']) {
        return ['success' => false, 'error' => $leadResult['error']];
    }

    $flozyLeadId = $leadResult['data']['id'] ?? null;
    if (!$flozyLeadId) {
        return ['success' => false, 'error' => 'Flozy did not return a lead ID'];
    }

    $stmt = $pdo->prepare("INSERT INTO youtube_flozy_leads (channel_id, flozy_lead_id) VALUES (?, ?)");
    $stmt->execute([$channelId, $flozyLeadId]);

    // Contact — fires now since an email's already known (one way or
    // another), no catch-up step needed. Confirmed: business_email
    // (YouTube's protected business-inquiries address, manually entered)
    // is PRIMARY over the regex-extracted `email` — it's the address the
    // creator actually wants business contact through.
    $primaryEmail = $channel['business_email'] ?: $channel['email'];
    $contactError = null;
    if ($primaryEmail) {
        $contactResult = flozy_request('POST', '/leads/' . $flozyLeadId . '/contacts', [
            'full_name' => $channel['channel_name'] ?: $channel['channel_username'] ?: $primaryEmail,
            'email'     => $primaryEmail,
        ]);
        if ($contactResult['success']) {
            $contactId = $contactResult['data']['id'] ?? null;
            if ($contactId) {
                $stmt = $pdo->prepare("UPDATE youtube_flozy_leads SET flozy_contact_id = ? WHERE channel_id = ?");
                $stmt->execute([$contactId, $channelId]);
            }
        } elseif ($contactResult['http_code'] !== 409) { // 409 = Flozy already has this email on this lead, not a real error
            $contactError = $contactResult['error'];
        }
    }

    // Opportunity — same config-driven stage/close-days/confidence Instagram uses.
    $opportunityError = null;
    $stageResult = flozy_request('GET', '/pipelines');
    if ($stageResult['success']) {
        $targetStageId = null;
        $targetStageName = null;
        $targetStageTag = null;
        foreach ($stageResult['data'] ?? [] as $pipeline) {
            foreach ($pipeline['stages'] ?? [] as $stage) {
                if (strcasecmp($stage['name'], $config['default_opportunity_stage_name']) === 0) {
                    $targetStageId = $stage['id'];
                    $targetStageName = $stage['name'];
                    $targetStageTag = $stage['tag_name'] ?? null;
                    break 2;
                }
            }
        }

        if ($targetStageId) {
            $closeDate = date('Y-m-d', strtotime('+' . (int) $config['default_opportunity_close_days'] . ' days'));
            $oppResult = flozy_request('POST', '/opportunities', [
                'stage_id'            => $targetStageId,
                'lead_id'             => $flozyLeadId,
                'value'               => 0,
                'expected_close_date' => $closeDate,
                'confidence'          => (int) $config['default_opportunity_confidence'],
            ]);

            if ($oppResult['success']) {
                $opportunityId = $oppResult['data']['id'] ?? null;
                if ($opportunityId) {
                    $stmt = $pdo->prepare("UPDATE youtube_flozy_leads SET flozy_opportunity_id = ?, current_stage = ? WHERE channel_id = ?");
                    $stmt->execute([$opportunityId, $targetStageName, $channelId]);
                }
            } else {
                $opportunityError = $oppResult['error'];
            }
        } else {
            $opportunityError = "No pipeline stage named '{$config['default_opportunity_stage_name']}' found.";
        }
    } else {
        $opportunityError = $stageResult['error'];
    }

    // Default tasks — same templates Instagram leads get.
    $templates = $pdo->query("SELECT * FROM flozy_task_templates WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
    $taskErrors = [];
    foreach ($templates as $tpl) {
        $dueDate = $tpl['due_offset_days'] !== null ? date('Y-m-d', strtotime('+' . (int) $tpl['due_offset_days'] . ' days')) : null;

        $taskResult = flozy_request('POST', '/tasks', [
            'title'       => $tpl['title'],
            'description' => $tpl['description'],
            'status'      => (int) $tpl['status'],
            'priority'    => (int) $tpl['priority'],
            'due_date'    => $dueDate,
            'lead_id'     => $flozyLeadId,
        ]);

        if (!$taskResult['success']) {
            $taskErrors[] = $tpl['title'] . ': ' . $taskResult['error'];
        }
    }

    $stmt = $pdo->prepare("UPDATE youtube_channels SET status = 'pushed' WHERE id = ?");
    $stmt->execute([$channelId]);

    return [
        'success'            => true,
        'flozy_lead_id'      => $flozyLeadId,
        'contact_error'      => $contactError,
        'opportunity_error'  => $opportunityError,
        'task_errors'        => $taskErrors,
    ];
}
