<?php
// api/archives-api.php
// REST API for Archives

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$role = $_SESSION['user_role'] ?? '';
if (!in_array($role, ['super_admin', 'owner'])) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit();
}

global $supabase;
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetArchives();
        break;
    case 'DELETE':
        handleRestore();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

function handleGetArchives() {
    global $supabase;
    $entityType = $_GET['entity_type'] ?? '';
    $search     = $_GET['search'] ?? '';
    try {
        $query = $supabase->from('archives')->select('*');
        if ($entityType && $entityType !== 'all') {
            $query->eq('entity_type', $entityType);
        }
        if ($search) {
            $query->ilike('entity_code', "%$search%");
        }
        $query->order('archived_at', false);
        $archives = $query->execute();
        echo json_encode(['success' => true, 'data' => $archives ?? []]);
    } catch (Throwable $e) {
        error_log('archives-api: ' . get_class($e) . ': ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handleRestore() {
    global $supabase;
    $role = $_SESSION['user_role'] ?? '';
    if (!in_array($role, ['super_admin', 'owner'])) {
        echo json_encode(['success' => false, 'error' => 'Only Super Admin and Owner can restore records']);
        return;
    }
    $id = $_GET['id'] ?? null;
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Archive ID required']);
        return;
    }
    try {
        $archive = $supabase->getById('archives', $id);
        if (!$archive) {
            echo json_encode(['success' => false, 'error' => 'Archive record not found']);
            return;
        }
        // record_data is a JSONB column — Supabase/PostgREST already returns it
        // decoded (a PHP array), since SupabaseClient::request() json_decode()s
        // the whole HTTP response body. Calling json_decode() on it again threw
        // a TypeError ("Argument #1 ($json) must be of type string, array
        // given") on every single restore, which the Exception-only catch below
        // never caught — that's why restoring silently failed for every type.
        $recordData = is_string($archive['record_data'])
            ? json_decode($archive['record_data'], true)
            : $archive['record_data'];
        if (!$recordData) {
            echo json_encode(['success' => false, 'error' => 'Invalid archive data']);
            return;
        }
        unset($recordData['id']);
        if (isset($recordData['status']) && $recordData['status'] === 'archived') {
            $defaultStatus = [
                'tasks' => 'pending', 'projects' => 'planning',
                'customers' => 'active', 'quotations' => 'draft',
                'installations' => 'scheduled', 'inventory' => 'active',
                'reports' => 'active', 'users' => 'active',
            ];
            $recordData['status'] = $defaultStatus[$archive['entity_type']] ?? 'active';
        }
        $recordData['updated_at'] = date('Y-m-d H:i:s');
        $result = $supabase->insert($archive['entity_type'], $recordData);
        if ($result) {
            $supabase->delete('archives', $id);
            logActivity($_SESSION['user_id'], 'restore', 'archives',
                "Restored {$archive['entity_type']}: {$archive['entity_code']}");
            echo json_encode(['success' => true,
                'message' => '"' . $archive['entity_code'] . '" has been restored successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to restore record']);
        }
    } catch (Throwable $e) {
        error_log('archives-api: ' . get_class($e) . ': ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
