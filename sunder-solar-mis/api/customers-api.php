<?php
// api/customers-api.php
// REST API for Customers

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/config.php';

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        handleGet($supabase);
        break;
    case 'POST':
        handlePost($supabase);
        break;
    case 'PUT':
        handlePut($supabase);
        break;
    case 'DELETE':
        handleDelete($supabase);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid method']);
}

function handleGet($supabase) {
    $id = $_GET['id'] ?? null;
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $type = $_GET['type'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    
    try {
        $query = $supabase->from('customers')->select('*');

        if ($id) {
            $result = $supabase->getById('customers', $id);
            echo json_encode(['success' => true, 'data' => $result]);
            return;
        }

        if ($search) {
            $query->ilike('name', "%$search%");
        }

        if ($status && $status !== 'all') {
            $query->eq('status', $status);
        }
        
        if ($type && $type !== 'all') {
            $query->eq('type', $type);
        }
        
        $query->order('created_at', false);
        $query->limit($limit);
        $query->offset($offset);
        
        $result = $query->execute();
        
        // Get total count
        $countQuery = $supabase->from('customers')->select('*', ['count' => 'exact']);
        if ($search) $countQuery->ilike('name', "%$search%");
        if ($status && $status !== 'all') $countQuery->eq('status', $status);
        if ($type && $type !== 'all') $countQuery->eq('type', $type);
        $countResult = $countQuery->execute();
        $total = count($countResult);
        
        echo json_encode([
            'success' => true,
            'data' => $result,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => ceil($total / $limit)
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handlePost($supabase) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        return;
    }
    
    // Check permission
    if (!hasPermission('customers', 'create')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    try {
        $data['customer_code'] = $data['customer_code'] ?? generateCode('CUST');
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $result = $supabase->insert('customers', $data);
        
        if ($result) {
            logActivity($_SESSION['user_id'], 'create', 'customers', "Created customer: {$data['name']}");
            echo json_encode(['success' => true, 'data' => $result, 'message' => 'Customer created successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create customer']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handlePut($supabase) {
    $id = $_GET['id'] ?? null;
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$id || !$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        return;
    }
    
    // Check permission
    if (!hasPermission('customers', 'edit')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    try {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $result = $supabase->update('customers', $id, $data);
        
        if ($result) {
            logActivity($_SESSION['user_id'], 'update', 'customers', "Updated customer ID: $id");
            echo json_encode(['success' => true, 'data' => $result, 'message' => 'Customer updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update customer']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handleDelete($supabase) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Customer ID required']);
        return;
    }
    
    // Check permission
    if (!hasPermission('customers', 'delete')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    try {
        $result = archiveRecord('customers', $id);
        if ($result) {
            logActivity($_SESSION['user_id'], 'archive', 'customers', "Archived record ID: $id");
            echo json_encode(['success' => true, 'message' => 'Record archived successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to archive record']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>