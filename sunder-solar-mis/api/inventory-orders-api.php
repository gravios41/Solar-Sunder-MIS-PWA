<?php
// api/inventory-orders-api.php
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
    case 'GET':    handleGet();    break;
    case 'POST':   handlePost();   break;
    case 'PUT':    handlePut();    break;
    case 'DELETE': handleDelete(); break;
    default: echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

function handleGet() {
    global $supabase;
    $id = $_GET['id'] ?? null;
    try {
        if ($id) {
            $order = $supabase->getById('inventory_orders', $id);
            echo json_encode(['success' => true, 'data' => $order]);
            return;
        }
        $orders = $supabase->from('inventory_orders')
            ->select('*')
            ->order('created_at', false)
            ->execute();
        echo json_encode(['success' => true, 'data' => $orders ?: []]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handlePost() {
    global $supabase;
    if (!hasPermission('inventory', 'create')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || empty($data['item_name']) || empty($data['quantity_ordered'])) {
        echo json_encode(['success' => false, 'error' => 'Item name and quantity are required']);
        return;
    }
    try {
        $data['ordered_by']      = (int)$_SESSION['user_id'];
        $data['ordered_by_name'] = $_SESSION['full_name'] ?? 'Unknown';
        $data['status']          = $data['status'] ?? 'pending';
        $data['order_date']      = $data['order_date'] ?? date('Y-m-d');
        $data['created_at']      = date('Y-m-d H:i:s');
        $data['updated_at']      = date('Y-m-d H:i:s');

        $result = $supabase->insert('inventory_orders', $data);
        $created = is_array($result) && isset($result[0]) ? $result[0] : $result;

        logActivity($_SESSION['user_id'], 'create', 'inventory', "Created order for: {$data['item_name']}");
        echo json_encode(['success' => true, 'data' => $created, 'message' => 'Order created successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handlePut() {
    global $supabase;
    $role = $_SESSION['user_role'] ?? '';
    if (!in_array($role, ['super_admin', 'owner'])) {
        echo json_encode(['success' => false, 'error' => 'Only Owner or Super Admin can edit orders']);
        return;
    }
    $id   = $_GET['id'] ?? null;
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$id || !$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        return;
    }
    try {
        $data['updated_at'] = date('Y-m-d H:i:s');

        // When marked arrived → set actual_delivery and add stock
        if (($data['status'] ?? '') === 'arrived') {
            if (empty($data['actual_delivery'])) {
                $data['actual_delivery'] = date('Y-m-d');
            }
            // Add quantity to inventory item if linked
            $order = $supabase->getById('inventory_orders', $id);
            if ($order && !empty($order['inventory_id']) && ($order['status'] ?? '') !== 'arrived') {
                $item = $supabase->getById('inventory', $order['inventory_id']);
                if ($item) {
                    $supabase->update('inventory', $order['inventory_id'], [
                        'quantity'   => (int)$item['quantity'] + (int)$order['quantity_ordered'],
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        $result = $supabase->update('inventory_orders', $id, $data);
        logActivity($_SESSION['user_id'], 'update', 'inventory', "Updated order ID: $id to status: " . ($data['status'] ?? ''));
        echo json_encode(['success' => true, 'data' => $result, 'message' => 'Order updated successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handleDelete() {
    global $supabase;
    $role = $_SESSION['user_role'] ?? '';
    if (!in_array($role, ['super_admin', 'owner'])) {
        echo json_encode(['success' => false, 'error' => 'Only Owner or Super Admin can cancel orders']);
        return;
    }
    $id = $_GET['id'] ?? null;
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Order ID required']);
        return;
    }
    try {
        $supabase->update('inventory_orders', $id, ['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')]);
        logActivity($_SESSION['user_id'], 'update', 'inventory', "Cancelled order ID: $id");
        echo json_encode(['success' => true, 'message' => 'Order cancelled']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
