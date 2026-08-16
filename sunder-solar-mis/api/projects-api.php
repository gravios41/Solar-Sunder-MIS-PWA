<?php
// api/projects-api.php
// REST API for Projects

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Correct include paths
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Make sure $supabase is available globally
global $supabase;

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetProjects($supabase);
        break;
    case 'POST':
        handlePostProject($supabase);
        break;
    case 'PUT':
        handlePutProject($supabase);
        break;
    case 'DELETE':
        handleDeleteProject($supabase);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid method']);
}

function handleGetProjects($supabase) {
    $id = $_GET['id'] ?? null;
    $customerId = $_GET['customer_id'] ?? null;
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    
    try {
        if ($id) {
            $project = $supabase->getById('projects', $id);
            if ($project) {
                $customer = $supabase->getById('customers', $project['customer_id']);
                $project['customer_name'] = $customer ? $customer['name'] : 'Unknown';
            }
            echo json_encode(['success' => true, 'data' => $project]);
            return;
        }
        
        $query = $supabase->from('projects')->select('*');

        if ($customerId) {
            $query->eq('customer_id', $customerId);
        }
        
        if ($status && $status !== 'all') {
            $query->eq('status', $status);
        }
        
        if ($search) {
            $query->ilike('project_name', "%$search%");
        }
        
        $query->order('created_at', false);
        
        $projects = $query->execute();
        
        // Add customer names
        foreach ($projects as &$project) {
            $customer = $supabase->getById('customers', $project['customer_id']);
            $project['customer_name'] = $customer ? $customer['name'] : 'Unknown';
        }
        
        echo json_encode(['success' => true, 'data' => $projects]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handlePostProject($supabase) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['project_name']) || !isset($data['customer_id'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    if (!hasPermission('projects', 'create')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    try {
        // Generate project code
        $projects = $supabase->getAll('projects');
        $maxId = count($projects) + 1;
        $data['project_code'] = 'PRJ-' . str_pad($maxId, 3, '0', STR_PAD_LEFT);
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $result = $supabase->insert('projects', $data);
        
        if ($result) {
            // Update customer project count
            $customer = $supabase->getById('customers', $data['customer_id']);
            if ($customer) {
                $supabase->update('customers', $data['customer_id'], [
                    'projects_count' => ($customer['projects_count'] ?? 0) + 1
                ]);
            }
            
            logActivity($_SESSION['user_id'], 'create', 'projects', "Created project: {$data['project_name']}");
            echo json_encode(['success' => true, 'data' => $result, 'message' => 'Project created successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create project']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handlePutProject($supabase) {
    $id = $_GET['id'] ?? null;
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$id || !$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        return;
    }
    
    if (!hasPermission('projects', 'edit')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    try {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $result = $supabase->update('projects', $id, $data);
        
        if ($result) {
            logActivity($_SESSION['user_id'], 'update', 'projects', "Updated project ID: $id");
            echo json_encode(['success' => true, 'data' => $result, 'message' => 'Project updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update project']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handleDeleteProject($supabase) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Project ID required']);
        return;
    }
    
    if (!hasPermission('projects', 'delete')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    try {
        $result = archiveRecord('projects', $id);
        if ($result) {
            logActivity($_SESSION['user_id'], 'archive', 'projects', "Archived record ID: $id");
            echo json_encode(['success' => true, 'message' => 'Record archived successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to archive record']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>