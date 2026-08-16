<?php
// api/inventory-api.php
// REST API for Inventory

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetInventory($supabase);
        break;
    case 'POST':
        handlePostInventory($supabase);
        break;
    case 'PUT':
        handlePutInventory($supabase);
        break;
    case 'DELETE':
        handleDeleteInventory($supabase);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid method']);
}

function handleGetInventory($supabase) {
    $id = $_GET['id'] ?? null;
    $category = $_GET['category'] ?? '';
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    $lowStock = $_GET['low_stock'] ?? false;
    
    try {
        if ($id) {
            $item = $supabase->getById('inventory', $id);
            echo json_encode(['success' => true, 'data' => $item]);
            return;
        }
        
        $query = $supabase->from('inventory')->select('*');
        
        if ($category && $category !== 'all') {
            $query->eq('category', $category);
        }
        
        if ($search) {
            $query->ilike('item_name', "%$search%");
        }
        
        // low_stock filter is applied in PHP after fetch (REST API can't compare two columns)

        
        $query->order('item_name', true);
        
        $items = $query->execute();
        
        // Add status and apply low_stock / status filters in PHP
        foreach ($items as &$item) {
            $item['status_text'] = getInventoryStatus($item['quantity'], $item['reorder_level'] ?? 0);
        }
        unset($item);

        if ($lowStock) {
            $items = array_values(array_filter($items, function($item) {
                return ($item['quantity'] ?? 0) <= ($item['reorder_level'] ?? 0);
            }));
        }

        if ($status && $status !== 'all') {
            $items = array_values(array_filter($items, function($item) use ($status) {
                return $item['status_text'] === $status;
            }));
        }
        
        echo json_encode(['success' => true, 'data' => $items]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handlePostInventory($supabase) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['item_name']) || !isset($data['category'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    if (!hasPermission('inventory', 'create')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    try {
        $data['item_code'] = $data['item_code'] ?? generateCode('INV');
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $result = $supabase->insert('inventory', $data);
        
        if ($result) {
            logActivity($_SESSION['user_id'], 'create', 'inventory', "Added inventory item: {$data['item_name']}");
            echo json_encode(['success' => true, 'data' => $result, 'message' => 'Item added successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to add item']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handlePutInventory($supabase) {
    $id = $_GET['id'] ?? null;
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$id || !$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        return;
    }
    
    if (!hasPermission('inventory', 'edit')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    try {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $result = $supabase->update('inventory', $id, $data);
        
        if ($result) {
            logActivity($_SESSION['user_id'], 'update', 'inventory', "Updated inventory item ID: $id");
            echo json_encode(['success' => true, 'data' => $result, 'message' => 'Item updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update item']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handleDeleteInventory($supabase) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Item ID required']);
        return;
    }
    
    if (!hasPermission('inventory', 'delete')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    try {
        $result = archiveRecord('inventory', $id);
        if ($result) {
            logActivity($_SESSION['user_id'], 'archive', 'inventory', "Archived record ID: $id");
            echo json_encode(['success' => true, 'message' => 'Record archived successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to archive record']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getInventoryStatus($quantity, $reorderLevel) {
    if ($quantity <= 0) return 'Critical';
    if ($quantity <= $reorderLevel) return 'Low Stock';
    return 'In Stock';
}
?>