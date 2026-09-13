<?php
/**
 * Complete Task (one-click)
 *
 * Field workers don't have time to tick off a checklist item by item while
 * they're mid-install — this lets the assigned employee (or an admin/owner)
 * mark the whole task done in a single action once the work is actually
 * finished, instead of requiring every checklist item to be checked
 * individually first. Any checklist items are auto-checked to keep them
 * consistent with the now-completed task.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
requireAuth();

if (!hasPermission('tasks', 'view')) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

global $supabase;
$data = json_decode(file_get_contents('php://input'), true) ?: [];
$taskId = $data['task_id'] ?? null;

if (!$taskId) {
    echo json_encode(['success' => false, 'error' => 'task_id required']);
    exit;
}

try {
    $task = $supabase->getById('tasks', $taskId);
    if (!$task) {
        echo json_encode(['success' => false, 'error' => 'Task not found']);
        exit;
    }

    if (!canCompleteTask($supabase, $task)) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }

    if (($task['status'] ?? '') === 'completed') {
        echo json_encode(['success' => false, 'error' => 'Task is already completed']);
        exit;
    }

    // Auto-check any existing checklist items so progress/status stay
    // consistent with the task now being done, instead of leaving stale
    // unchecked items behind a "completed" task.
    $checklists = $supabase->from('task_checklists')
        ->select('id,is_completed')
        ->eq('task_id', $taskId)
        ->execute() ?: [];

    foreach ($checklists as $item) {
        if (empty($item['is_completed'])) {
            $supabase->from('task_checklists')
                ->update([
                    'is_completed' => true,
                    'completed_by' => $_SESSION['user_id'],
                    'completed_at' => date('Y-m-d H:i:s'),
                ])
                ->eq('id', $item['id'])
                ->execute();
        }
    }

    $total = count($checklists);
    $supabase->update('tasks', $taskId, [
        'status' => 'completed',
        'progress_percent' => 100,
        'checklist_count' => $total,
        'checklist_completed' => $total,
        'completed_date' => date('Y-m-d'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    logActivity($_SESSION['user_id'], 'update', 'tasks', "Marked task complete: {$task['task_title']}");

    updateProjectProgressFromTasks($supabase, $task['project_id'] ?? null);
    completeProjectPipelineIfDone($supabase, $task['project_id'] ?? null);

    echo json_encode(['success' => true, 'message' => 'Task marked complete']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Same ownership rule as the checklist (assigned employee, or any employee
 * when the task is unassigned/assigned to a name with no matching account),
 * plus admin/owner who can already bypass the checklist via the board's
 * quick Start/Complete buttons.
 */
function canCompleteTask($supabase, $task) {
    if (in_array($_SESSION['role'] ?? '', ['super_admin', 'owner'], true)) {
        return true;
    }
    if (!hasPermission('task-checklist', 'edit')) {
        return false;
    }

    $assignedName = trim($task['assigned_to'] ?? '');
    if ($assignedName === '') {
        return true;
    }

    $matchingUsers = $supabase->getAll('users', ['full_name' => 'eq.' . $assignedName, 'select' => 'id']) ?: [];
    if (empty($matchingUsers)) {
        return true;
    }

    return strcasecmp($assignedName, trim($_SESSION['full_name'] ?? '')) === 0;
}
