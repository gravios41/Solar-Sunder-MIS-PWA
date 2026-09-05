<?php
/**
 * Task Checklist API
 * Anyone who can view tasks can view a checklist, but only employees
 * (task-checklist: edit) can add, update, or delete checklist items —
 * and only on a task actually assigned to them (see canEditTaskChecklist()).
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
requireAuth();

if (!hasPermission('tasks', 'view')) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

global $supabase;
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Get checklists for a task
        $taskId = $_GET['task_id'] ?? null;
        
        if (!$taskId) {
            echo json_encode(['success' => false, 'error' => 'task_id required']);
            exit;
        }

        $checklists = $supabase->from('task_checklists')
            ->select('*')
            ->eq('task_id', $taskId)
            ->order('sequence', true)
            ->order('created_at', true)
            ->execute() ?: [];

        // Calculate progress
        $total = count($checklists);
        $completed = count(array_filter($checklists, fn($c) => $c['is_completed']));
        $progressPercent = $total > 0 ? round(($completed / $total) * 100) : 0;

        echo json_encode([
            'success' => true,
            'data' => $checklists,
            'summary' => [
                'total' => $total,
                'completed' => $completed,
                'progress_percent' => $progressPercent
            ]
        ]);
        exit;
    }

    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $taskId = $data['task_id'] ?? null;
        $checklistItem = trim($data['checklist_item'] ?? '');

        if (!$taskId || !$checklistItem) {
            echo json_encode(['success' => false, 'error' => 'task_id and checklist_item required']);
            exit;
        }

        if (!canEditTaskChecklist($supabase, $taskId)) {
            echo json_encode(['success' => false, 'error' => 'Permission denied']);
            exit;
        }

        // Get current max sequence
        $existing = $supabase->from('task_checklists')
            ->select('sequence')
            ->eq('task_id', $taskId)
            ->order('sequence', false)
            ->limit(1)
            ->execute() ?: [];

        $maxSequence = 0;
        if ($existing && is_array($existing) && count($existing) > 0) {
            $maxSequence = is_array($existing[0]) ? ($existing[0]['sequence'] ?? 0) : ($existing['sequence'] ?? 0);
        }

        $checklistData = [
            'task_id' => $taskId,
            'checklist_item' => $checklistItem,
            'is_completed' => false,
            'sequence' => $maxSequence + 1,
            'created_by' => $_SESSION['user_id']
        ];

        $response = $supabase->insert('task_checklists', $checklistData);
        $checklist = $response[0] ?? $response;

        // Update task with checklist count
        updateTaskChecklistProgress($supabase, $taskId);

        logActivity($_SESSION['user_id'], 'create', 'tasks', "Added checklist item to task $taskId");
        echo json_encode(['success' => true, 'data' => $checklist, 'message' => 'Checklist item added']);
        exit;
    }

    if ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $checklistId = $data['id'] ?? null;

        if (!$checklistId) {
            echo json_encode(['success' => false, 'error' => 'Checklist ID required']);
            exit;
        }

        // Get checklist to find task_id
        $checklist = $supabase->from('task_checklists')
            ->select('*')
            ->eq('id', $checklistId)
            ->single()
            ->execute();

        if (!$checklist) {
            echo json_encode(['success' => false, 'error' => 'Checklist not found']);
            exit;
        }

        // single() always returns a bare row or null (never a list to unwrap)
        $taskId = $checklist['task_id'] ?? null;

        if (!canEditTaskChecklist($supabase, $taskId)) {
            echo json_encode(['success' => false, 'error' => 'Permission denied']);
            exit;
        }

        $updateData = [];
        
        if (isset($data['is_completed'])) {
            $updateData['is_completed'] = (bool)$data['is_completed'];
            if ($data['is_completed']) {
                $updateData['completed_by'] = $_SESSION['user_id'];
                $updateData['completed_at'] = date('Y-m-d H:i:s');
            } else {
                $updateData['completed_by'] = null;
                $updateData['completed_at'] = null;
            }
        }

        if (isset($data['checklist_item'])) {
            $updateData['checklist_item'] = $data['checklist_item'];
        }

        if (empty($updateData)) {
            echo json_encode(['success' => false, 'error' => 'No fields to update']);
            exit;
        }

        $supabase->from('task_checklists')
            ->update($updateData)
            ->eq('id', $checklistId)
            ->execute();

        // Update task progress
        if ($taskId) {
            updateTaskChecklistProgress($supabase, $taskId);
        }

        logActivity($_SESSION['user_id'], 'update', 'tasks', "Updated checklist item in task $taskId");
        echo json_encode(['success' => true, 'message' => 'Checklist item updated']);
        exit;
    }

    if ($method === 'DELETE') {
        $checklistId = $_GET['id'] ?? null;
        if (!$checklistId) {
            echo json_encode(['success' => false, 'error' => 'Checklist ID required']);
            exit;
        }

        // Get checklist to find task_id
        $checklist = $supabase->from('task_checklists')
            ->select('task_id')
            ->eq('id', $checklistId)
            ->single()
            ->execute();

        if (!$checklist) {
            echo json_encode(['success' => false, 'error' => 'Checklist not found']);
            exit;
        }

        // single() always returns a bare row or null (never a list to unwrap)
        $taskId = $checklist['task_id'] ?? null;

        if (!canEditTaskChecklist($supabase, $taskId)) {
            echo json_encode(['success' => false, 'error' => 'Permission denied']);
            exit;
        }

        $supabase->from('task_checklists')
            ->delete()
            ->eq('id', $checklistId)
            ->execute();

        // Update task progress
        if ($taskId) {
            updateTaskChecklistProgress($supabase, $taskId);
        }

        logActivity($_SESSION['user_id'], 'delete', 'tasks', "Deleted checklist item from task $taskId");
        echo json_encode(['success' => true, 'message' => 'Checklist item deleted']);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Whether the current user may add/toggle/delete checklist items on $taskId.
 * Requires the role-level 'task-checklist' edit permission (employees only),
 * AND — only when the task's assigned_to matches a real user account — that
 * the current user is that assigned person. Tasks assigned to a name that
 * doesn't match any account (legacy/demo data with no real owner) fall back
 * to allowing any employee, rather than locking everyone out of them.
 */
function canEditTaskChecklist($supabase, $taskId) {
    if (!hasPermission('task-checklist', 'edit')) {
        return false;
    }

    $task = $supabase->getById('tasks', $taskId);
    if (!$task) {
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

/**
 * Update task progress based on checklist completion
 */
function updateTaskChecklistProgress($supabase, $taskId) {
    try {
        $checklists = $supabase->from('task_checklists')
            ->select('is_completed')
            ->eq('task_id', $taskId)
            ->execute() ?: [];

        $total = count($checklists);
        $completed = 0;

        if ($total > 0) {
            foreach ($checklists as $c) {
                if (is_array($c)) {
                    if ($c['is_completed'] ?? false) $completed++;
                } else if ($c->is_completed ?? false) {
                    $completed++;
                }
            }
        }

        $progressPercent = $total > 0 ? (int)round(($completed / $total) * 100) : 0;

        $taskUpdate = [
            'progress_percent' => $progressPercent,
            'checklist_count' => $total,
            'checklist_completed' => $completed
        ];

        $task = $supabase->getById('tasks', $taskId);
        $allDone = $total > 0 && $completed === $total;

        if ($allDone) {
            // All checklist items done — move the task itself to completed
            $taskUpdate['status'] = 'completed';
            $taskUpdate['completed_date'] = date('Y-m-d');
        } elseif ($task && ($task['status'] ?? '') === 'completed') {
            // Was auto-completed by this same mechanism, but an item got
            // unchecked again — move it back off "completed" rather than
            // leaving progress and status out of sync.
            $taskUpdate['status'] = 'in_progress';
            $taskUpdate['completed_date'] = null;
        }

        $supabase->update('tasks', $taskId, $taskUpdate);

    } catch (Exception $e) {
        error_log("Failed to update task progress: " . $e->getMessage());
    }
}
?>
