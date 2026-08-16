<?php
// api/installations-api.php
// REST API for Installations

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
        handleGetInstallations();
        break;
    case 'POST':
        handlePostInstallation();
        break;
    case 'PUT':
        handlePutInstallation();
        break;
    case 'DELETE':
        handleDeleteInstallation();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

function handleGetInstallations() {
    global $supabase;
    
    $id = $_GET['id'] ?? null;
    $status = $_GET['status'] ?? '';
    $team = $_GET['team'] ?? '';
    $search = $_GET['search'] ?? '';
    
    try {
        if ($id) {
            $installation = $supabase->getById('installations', $id);
            if ($installation) {
                $project = $supabase->getById('projects', $installation['project_id']);
                $customer = $supabase->getById('customers', $installation['customer_id']);
                $installation['project_name'] = $project ? $project['project_name'] : 'Unknown';
                $installation['customer_name'] = $customer ? $customer['name'] : 'Unknown';
            }
            echo json_encode(['success' => true, 'data' => $installation]);
            return;
        }
        
        $query = $supabase->from('installations')->select('*');

        if ($status && $status !== 'all') {
            $query->eq('status', $status);
        }
        
        if ($team && $team !== 'all') {
            $query->eq('team', $team);
        }
        
        if ($search) {
            $query->ilike('installation_code', "%$search%");
        }
        
        $query->order('installation_date', false);
        
        $installations = $query->execute();
        
        foreach ($installations as &$inst) {
            $project = $supabase->getById('projects', $inst['project_id']);
            $customer = $supabase->getById('customers', $inst['customer_id']);
            $inst['project_name'] = $project ? $project['project_name'] : 'Unknown';
            $inst['customer_name'] = $customer ? $customer['name'] : 'Unknown';
        }
        
        echo json_encode(['success' => true, 'data' => $installations]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handlePostInstallation() {
    global $supabase;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['project_id'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    if (!hasPermission('installations', 'create')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    try {
        // Generate installation code
        $installations = $supabase->getAll('installations');
        $maxId = count($installations) + 1;
        $data['installation_code'] = 'INS-' . str_pad($maxId, 3, '0', STR_PAD_LEFT);
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // Set default progress based on status
        if ($data['status'] === 'completed') {
            $data['progress'] = 100;
        } elseif ($data['status'] === 'in_progress') {
            $data['progress'] = $data['progress'] ?? 50;
        } else {
            $data['progress'] = 0;
        }
        
        $result = $supabase->insert('installations', $data);
        
        if ($result) {
            logActivity($_SESSION['user_id'], 'create', 'installations', "Created installation: {$data['installation_code']}");
            echo json_encode(['success' => true, 'data' => $result, 'message' => 'Installation created successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create installation']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handlePutInstallation() {
    global $supabase;
    
    $id = $_GET['id'] ?? null;
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$id || !$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        return;
    }
    
    if (!hasPermission('installations', 'edit')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    try {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // Auto-update progress based on status
        if (isset($data['status'])) {
            if ($data['status'] === 'completed') {
                $data['progress'] = 100;
            } elseif ($data['status'] === 'scheduled') {
                $data['progress'] = 0;
            }
        }
        
        $result = $supabase->update('installations', $id, $data);
        
        if ($result) {
            logActivity($_SESSION['user_id'], 'update', 'installations', "Updated installation ID: $id");
            echo json_encode(['success' => true, 'data' => $result, 'message' => 'Installation updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update installation']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handleDeleteInstallation() {
    global $supabase;
    
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Installation ID required']);
        return;
    }
    
    if (!hasPermission('installations', 'delete')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    try {
        $result = archiveRecord('installations', $id);
        if ($result) {
            logActivity($_SESSION['user_id'], 'archive', 'installations', "Archived record ID: $id");
            echo json_encode(['success' => true, 'message' => 'Record archived successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to archive record']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>