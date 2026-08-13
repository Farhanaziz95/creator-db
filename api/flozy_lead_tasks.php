<?php
/**
 * Round 32: a per-lead task view that's a genuine live window into
 * Flozy — not a local copy. GET returns every task Flozy has for this
 * lead, whether it was created by this app (via push or the "add task"
 * action below) or added by hand directly in Flozy's UI. POST handles
 * adding a new task or marking one complete, both via already-confirmed
 * endpoints this project uses elsewhere (POST /tasks, PUT /tasks/{id}).
 *
 * IMPORTANT: GET /tasks has no lead_id filter (confirmed against Flozy's
 * real docs — only page/limit/order/search exist as params), so listing
 * a single lead's tasks means paginating through ALL tasks and filtering
 * locally — the same approach api/sync_flozy_stage.php already uses for
 * opportunities, for the same reason.
 */
require_once __DIR__ . '/../includes/error_handler.php';
setup_json_error_handling();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/flozy_client.php';
header('Content-Type: application/json');

function get_flozy_lead_id(PDO $pdo, int $profileId): ?int
{
    $stmt = $pdo->prepare("SELECT flozy_lead_id FROM flozy_leads WHERE profile_id = ?");
    $stmt->execute([$profileId]);
    $id = $stmt->fetchColumn();
    return $id ? (int) $id : null;
}

/**
 * Paginates through every task Flozy has and returns only the ones
 * belonging to $flozyLeadId. No lead_id filter exists server-side, so
 * this has to scan — mirrors build_lead_opportunity_lookup() in
 * api/sync_flozy_stage.php.
 */
function fetch_tasks_for_lead(int $flozyLeadId): array
{
    $matched = [];
    $page = 1;

    do {
        $result = flozy_request('GET', '/tasks?page=' . $page . '&limit=100&order=desc');
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'], 'tasks' => []];
        }
        $items = $result['data']['items'] ?? [];
        foreach ($items as $task) {
            if ((int) ($task['lead_id'] ?? 0) === $flozyLeadId) {
                $matched[] = $task;
            }
        }
        $totalPages = $result['data']['pagination']['total_pages'] ?? 1;
        $page++;
    } while ($page <= $totalPages);

    return ['success' => true, 'error' => null, 'tasks' => $matched];
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $profileId = (int) ($_GET['profile_id'] ?? 0);
    $flozyLeadId = get_flozy_lead_id($pdo, $profileId);

    if (!$flozyLeadId) {
        http_response_code(404);
        echo json_encode(['error' => 'This profile is not in the Flozy tab.']);
        exit;
    }

    $fetch = fetch_tasks_for_lead($flozyLeadId);
    if (!$fetch['success']) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not load tasks from Flozy: ' . $fetch['error']]);
        exit;
    }

    // Mark which tasks this app created (so the UI can show origin) —
    // anything not in our local flozy_lead_tasks table was added by hand
    // directly in Flozy.
    $stmt = $pdo->prepare("SELECT flozy_task_id FROM flozy_lead_tasks WHERE profile_id = ?");
    $stmt->execute([$profileId]);
    $localTaskIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $tasks = array_map(function ($t) use ($localTaskIds) {
        $t['created_in_app'] = in_array((int) $t['id'], $localTaskIds, true);
        return $t;
    }, $fetch['tasks']);

    // Sort: incomplete first (status != 3), then by due_date ascending
    // where present, so anything overdue naturally floats near the top.
    usort($tasks, function ($a, $b) {
        $aDone = ((int) ($a['status'] ?? 0)) === 3;
        $bDone = ((int) ($b['status'] ?? 0)) === 3;
        if ($aDone !== $bDone) {
            return $aDone <=> $bDone;
        }
        $aDue = $a['due_date'] ?? null;
        $bDue = $b['due_date'] ?? null;
        if ($aDue && $bDue) {
            return strtotime($aDue) <=> strtotime($bDue);
        }
        return $aDue ? -1 : ($bDue ? 1 : 0);
    });

    echo json_encode(['success' => true, 'data' => $tasks]);
    exit;
}

if ($method === 'POST') {
    $input  = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $profileId = (int) ($input['profile_id'] ?? 0);

    if ($action === 'add') {
        $flozyLeadId = get_flozy_lead_id($pdo, $profileId);
        if (!$flozyLeadId) {
            http_response_code(404);
            echo json_encode(['error' => 'This profile is not in the Flozy tab.']);
            exit;
        }

        $title = trim($input['title'] ?? '');
        if ($title === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Title is required.']);
            exit;
        }

        $result = flozy_request('POST', '/tasks', [
            'title'       => $title,
            'description' => trim($input['description'] ?? ''),
            'status'      => 1, // todo
            'priority'    => (int) ($input['priority'] ?? 2),
            'due_date'    => !empty($input['due_date']) ? $input['due_date'] : null,
            'lead_id'     => $flozyLeadId,
        ]);

        if (!$result['success']) {
            http_response_code(500);
            echo json_encode(['error' => 'Flozy task creation failed: ' . $result['error']]);
            exit;
        }

        // Record locally too — same table push_profile_to_flozy() already
        // writes to, so this task is correctly recognized as app-created
        // next time this view loads, and stays consistent with the rest
        // of the project's existing task-tracking pattern.
        $flozyTaskId = $result['data']['id'] ?? null;
        if ($flozyTaskId) {
            $stmt = $pdo->prepare("INSERT INTO flozy_lead_tasks (profile_id, flozy_task_id, title) VALUES (?, ?, ?)");
            $stmt->execute([$profileId, $flozyTaskId, $title]);
        }

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'complete') {
        $taskId = (int) ($input['task_id'] ?? 0);
        if (!$taskId) {
            http_response_code(400);
            echo json_encode(['error' => 'task_id is required.']);
            exit;
        }

        $result = flozy_request('PUT', '/tasks/' . $taskId, ['status' => 3]);

        if (!$result['success']) {
            http_response_code(500);
            echo json_encode(['error' => 'Flozy update failed: ' . $result['error']]);
            exit;
        }

        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unknown action.']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
