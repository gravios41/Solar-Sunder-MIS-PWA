<?php
// api/users-api.php
// REST API for Users

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

// Check permission for user management
if (!hasPermission('users', 'view') && $method !== 'PUT') {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit();
}

switch ($method) {
    case 'GET':
        handleGetUsers();
        break;
    case 'POST':
        handlePostUser();
        break;
    case 'PUT':
        handlePutUser();
        break;
    case 'DELETE':
        handleDeleteUser();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

function handleGetUsers() {
    global $supabase;
    
    $id = $_GET['id'] ?? null;
    
    try {
        if ($id) {
            $user = $supabase->getById('users', $id);
            unset($user['password']);
            echo json_encode(['success' => true, 'data' => $user]);
            return;
        }
        
        $users = $supabase->getAll('users');
        
        foreach ($users as &$user) {
            unset($user['password']);
        }
        
        echo json_encode(['success' => true, 'data' => $users]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handlePostUser() {
    global $supabase;
    
    if (!hasPermission('users', 'create')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['username']) || !isset($data['email']) || !isset($data['full_name']) || !isset($data['role'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    $currentUserRole = $_SESSION['user_role'];
    if ($currentUserRole === 'owner' && in_array($data['role'], ['super_admin', 'owner'])) {
        echo json_encode(['success' => false, 'error' => 'Cannot create Super Admin or Owner user']);
        return;
    }
    
    try {
        $existing = $supabase->from('users')->select('*')->eq('username', $data['username'])->execute();
        if (!empty($existing)) {
            echo json_encode(['success' => false, 'error' => 'Username already exists']);
            return;
        }
        
        $existing = $supabase->from('users')->select('*')->eq('email', $data['email'])->execute();
        if (!empty($existing)) {
            echo json_encode(['success' => false, 'error' => 'Email already exists']);
            return;
        }
        
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['is_active'] = $data['is_active'] ?? 1;
        
        if (!isset($data['password']) || empty($data['password'])) {
            $data['password'] = 'password123';
        }
        
        $result = $supabase->insert('users', $data);
        
        if ($result) {
            $created = is_array($result) && isset($result[0]) ? $result[0] : $result;
            unset($created['password']);
            logActivity($_SESSION['user_id'], 'create', 'users', "Created user: {$data['full_name']}");
            echo json_encode(['success' => true, 'data' => $created, 'message' => 'User created successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create user']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handlePutUser() {
    global $supabase;
    
    $id = $_GET['id'] ?? null;
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$id || !$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        return;
    }
    
    $isSelf = ($id == $_SESSION['user_id']);
    if (!$isSelf && !hasPermission('users', 'edit')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    try {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        if (isset($data['current_password']) && isset($data['new_password'])) {
            $user = $supabase->getById('users', $id);
            if (!$user) {
                echo json_encode(['success' => false, 'error' => 'User not found']);
                return;
            }
            if ($user['password'] !== $data['current_password']) {
                echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
                return;
            }
            $data['password'] = $data['new_password'];
            unset($data['current_password']);
            unset($data['new_password']);
        }
        
        if (isset($data['password']) && empty($data['password'])) {
            unset($data['password']);
        }
        
        if (isset($data['role']) && !$isSelf) {
            $currentUserRole = $_SESSION['user_role'];
            if ($currentUserRole === 'owner' && $data['role'] === 'super_admin') {
                echo json_encode(['success' => false, 'error' => 'Cannot assign Super Admin role']);
                return;
            }
        }
        
        $result = $supabase->update('users', $id, $data);
        
        if ($result) {
            unset($result['password']);
            
            if ($isSelf) {
                $_SESSION['full_name'] = $result['full_name'] ?? $_SESSION['full_name'];
                $_SESSION['email'] = $result['email'] ?? $_SESSION['email'];
            }
            
            logActivity($_SESSION['user_id'], 'update', 'users', "Updated user ID: $id");
            echo json_encode(['success' => true, 'data' => $result, 'message' => 'User updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update user']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handleDeleteUser() {
    global $supabase;
    
    if (!hasPermission('users', 'delete')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'User ID required']);
        return;
    }
    
    if ($id == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete your own account']);
        return;
    }
    
    if ($_SESSION['user_role'] === 'owner') {
        $user = $supabase->getById('users', $id);
        if ($user && $user['role'] === 'super_admin') {
            echo json_encode(['success' => false, 'error' => 'Cannot delete Super Admin user']);
            return;
        }
    }
    
    try {
        $result = archiveRecord('users', $id);
        if ($result) {
            logActivity($_SESSION['user_id'], 'archive', 'users', "Archived record ID: $id");
            echo json_encode(['success' => true, 'message' => 'Record archived successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to archive record']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>