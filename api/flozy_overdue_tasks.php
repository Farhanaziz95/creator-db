<?php
/**
 * Round 33: the actual "reminder" half of Round 32's Tasks & Reminders
 * feature. The per-lead modal only shows overdue status if you already
 * knew to check that specific lead — this scans every task across EVERY
 * Flozy lead in one pass and surfaces what's overdue without you having
 * to remember who to check. Powers the "⚠️ Overdue" panel on the
 * dashboard.
 */
require_once __DIR__ . '/../includes/error_handler.php';
setup_json_error_handling();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/flozy_client.php';
header('Content-Type: application/json');

$fetch = fetch_all_flozy_tasks();
if (!$fetch['success']) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not load tasks from Flozy: ' . $fetch['error']]);
    exit;
}

// Map flozy_lead_id -> {profile_id, username} so an overdue task can be
// attributed to a creator and linked straight back to their row/modal.
$rows = $pdo->query("
    SELECT fl.flozy_lead_id, fl.profile_id, p.username
    FROM flozy_leads fl
    JOIN profiles p ON p.id = fl.profile_id
")->fetchAll();

$leadMap = [];
foreach ($rows as $r) {
    $leadMap[(int) $r['flozy_lead_id']] = ['profile_id' => (int) $r['profile_id'], 'username' => $r['username']];
}

$today = date('Y-m-d');
$overdue = [];

foreach ($fetch['tasks'] as $task) {
    $status = (int) ($task['status'] ?? 0);
    $dueDate = $task['due_date'] ?? null;

    if ($status === 3 || !$dueDate) {
        continue; // done, or no due date to compare against — can't be "overdue" without one
    }

    $dueDateOnly = substr($dueDate, 0, 10);
    if ($dueDateOnly >= $today) {
        continue; // not overdue yet
    }

    $leadId = (int) ($task['lead_id'] ?? 0);
    $lead = $leadMap[$leadId] ?? null;
    if (!$lead) {
        continue; // task belongs to a lead not (or no longer) tracked locally — nothing to link this to
    }

    $overdue[] = [
        'profile_id'   => $lead['profile_id'],
        'username'     => $lead['username'],
        'task_id'      => $task['id'],
        'title'        => $task['title'] ?? '(untitled)',
        'due_date'     => $dueDateOnly,
        'days_overdue' => (int) floor((strtotime($today) - strtotime($dueDateOnly)) / 86400),
        'priority'     => (int) ($task['priority'] ?? 2),
    ];
}

// Most overdue first — that's the one that needs attention soonest.
usort($overdue, fn($a, $b) => $b['days_overdue'] <=> $a['days_overdue']);

echo json_encode(['success' => true, 'data' => $overdue]);
