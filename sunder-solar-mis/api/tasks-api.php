<?php
// api/tasks-api.php
// REST API for Tasks

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

global $supabase;

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['assignable_users'])) {
            handleGetAssignableUsers();
        } else {
            handleGetTasks();
        }
        break;
    case 'POST':
        handlePostTask();
        break;
    case 'PUT':
        handlePutTask();
        break;
    case 'DELETE':
        handleDeleteTask();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

function handleGetAssignableUsers() {
    global $supabase;

    if (!hasPermission('tasks', 'view')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }

    try {
        $users = $supabase->getAll('users', ['select' => 'id,full_name,role', 'order' => 'full_name.asc']) ?: [];
        echo json_encode(['success' => true, 'data' => $users]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handleGetTasks() {
    global $supabase;

    $id = $_GET['id'] ?? null;
    $projectId = $_GET['project_id'] ?? null;
    $assignedTo = $_GET['assigned_to'] ?? '';
    $status = $_GET['status'] ?? '';
    $priority = $_GET['priority'] ?? '';
    $search = $_GET['search'] ?? '';
    
    try {
        if ($id) {
            $task = $supabase->getById('tasks', $id);
            if ($task && $task['project_id']) {
                $project = $supabase->getById('projects', $task['project_id']);
                $task['project_name'] = $project ? $project['project_name'] : 'Unknown';
            }
            echo json_encode(['success' => true, 'data' => $task]);
            return;
        }
        
        $query = $supabase->from('tasks')->select('*')->isNull('deleted_at');

        if ($projectId) {
            $query->eq('project_id', $projectId);
        }
        
        if ($assignedTo) {
            $query->eq('assigned_to', $assignedTo);
        }
        
        if ($status && $status !== 'all') {
            $query->eq('status', $status);
        }
        
        if ($priority && $priority !== 'all') {
            $query->eq('priority', $priority);
        }
        
        if ($search) {
            $query->ilike('task_title', "%$search%");
        }
        
        $query->order('due_date', true);
        
        $tasks = $query->execute();
        
        foreach ($tasks as &$task) {
            if ($task['project_id']) {
                $project = $supabase->getById('projects', $task['project_id']);
                $task['project_name'] = $project ? $project['project_name'] : 'Unknown';
            } else {
                $task['project_name'] = null;
            }
        }
        
        echo json_encode(['success' => true, 'data' => $tasks]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handlePostTask() {
    global $supabase;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['task_title'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    if (!hasPermission('tasks', 'create')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    try {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['status'] = $data['status'] ?? 'pending';
        $data['priority'] = $data['priority'] ?? 'medium';
        
        $result = $supabase->insert('tasks', $data);

        if ($result) {
            logActivity($_SESSION['user_id'], 'create', 'tasks', "Created task: {$data['task_title']}");
            // A new pending task should immediately pull the project's
            // rollup progress down to reflect the added work, not leave it
            // showing a now-stale (too high) percentage.
            updateProjectProgressFromTasks($supabase, $data['project_id'] ?? null);
            echo json_encode(['success' => true, 'data' => $result, 'message' => 'Task created successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create task']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handlePutTask() {
    global $supabase;
    
    $id = $_GET['id'] ?? null;
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$id || !$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        return;
    }
    
    if (!hasPermission('tasks', 'edit')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    try {
        $data['updated_at'] = date('Y-m-d H:i:s');

        if (isset($data['status']) && $data['status'] === 'completed' && !isset($data['completed_date'])) {
            $data['completed_date'] = date('Y-m-d');
        }
        // A task marked complete this way (the quick Start/Complete button,
        // bypassing the checklist) still needs an accurate progress_percent —
        // otherwise the project's rollup progress (see
        // updateProjectProgressFromTasks()) would keep averaging in a stale
        // 0% for a task that's actually done.
        if (isset($data['status']) && $data['status'] === 'completed' && !isset($data['progress_percent'])) {
            $data['progress_percent'] = 100;
        }

        $result = $supabase->update('tasks', $id, $data);

        if ($result) {
            logActivity($_SESSION['user_id'], 'update', 'tasks', "Updated task ID: $id");

            $task = $supabase->getById('tasks', $id);
            updateProjectProgressFromTasks($supabase, $task['project_id'] ?? null);

            if (($data['status'] ?? '') === 'completed') {
                completeProjectPipelineIfDone($supabase, $task['project_id'] ?? null);
            }

            echo json_encode(['success' => true, 'data' => $result, 'message' => 'Task updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update task']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handleDeleteTask() {
    global $supabase;
    
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Task ID required']);
        return;
    }
    
    if (!hasPermission('tasks', 'delete')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    try {
        // Captured before archiving hard-deletes the row — a removed task
        // should no longer be averaged into its project's rollup progress.
        $task = $supabase->getById('tasks', $id);
        if (!$task) {
            echo json_encode(['success' => false, 'error' => 'Task not found']);
            return;
        }
        // Only a completed task can be archived — enforced here too (not
        // just hidden in the UI) so the API can't be called directly to
        // archive still-active work.
        if (($task['status'] ?? '') !== 'completed') {
            echo json_encode(['success' => false, 'error' => 'Only completed tasks can be archived']);
            return;
        }
        $projectId = $task['project_id'] ?? null;

        $result = archiveRecord('tasks', $id);
        if ($result) {
            logActivity($_SESSION['user_id'], 'archive', 'tasks', "Archived record ID: $id");
            updateProjectProgressFromTasks($supabase, $projectId);
            echo json_encode(['success' => true, 'message' => 'Record archived successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to archive record']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>