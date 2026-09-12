<?php
// api/quotations-api.php
// REST API for Quotations

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

// Handle different request methods
switch ($method) {
    case 'GET':
        handleGetQuotations();
        break;
    case 'POST':
        handlePostQuotation();
        break;
    case 'PUT':
        handlePutQuotation();
        break;
    case 'DELETE':
        handleDeleteQuotation();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

// ============================================
// GET: Fetch quotations
// ============================================
function handleGetQuotations() {
    global $supabase;
    
    $id = $_GET['id'] ?? null;
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    
    try {
        if ($id) {
            // Get single quotation
            $quotation = $supabase->getById('quotations', $id);
            if ($quotation) {
                // Get quotation items
                $items = $supabase->getAll('quotation_items', ['quotation_id' => 'eq.' . $id]);
                $quotation['items'] = $items ?: [];

                // Get customer name — a brand-new client has no Customer
                // record yet (customer_id null), so fall back to the raw
                // client_name captured on the quotation until it's approved.
                $customer = $quotation['customer_id'] ? $supabase->getById('customers', $quotation['customer_id']) : null;
                $quotation['customer_name'] = $customer ? $customer['name'] : ($quotation['client_name'] ?: 'Unknown');

                // Get project name
                if ($quotation['project_id']) {
                    $project = $supabase->getById('projects', $quotation['project_id']);
                    $quotation['project_name'] = $project ? $project['project_name'] : 'Unknown';
                }
            }
            echo json_encode(['success' => true, 'data' => $quotation]);
            return;
        }
        
        // Get all quotations
        $query = $supabase->from('quotations')->select('*')->isNull('deleted_at');

        if ($status && $status !== 'all') {
            $query->eq('status', $status);
        }
        
        if ($search) {
            $query->ilike('quotation_number', "%$search%");
        }
        
        $query->order('created_at', false);
        
        $quotations = $query->execute();
        
        // Add customer names
        foreach ($quotations as &$q) {
            $customer = $q['customer_id'] ? $supabase->getById('customers', $q['customer_id']) : null;
            $q['customer_name'] = $customer ? $customer['name'] : ($q['client_name'] ?: 'Unknown');

            if ($q['project_id']) {
                $project = $supabase->getById('projects', $q['project_id']);
                $q['project_name'] = $project ? $project['project_name'] : 'Unknown';
            }
        }
        
        echo json_encode(['success' => true, 'data' => $quotations]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================
// POST: Create new quotation
// ============================================
function handlePostQuotation() {
    global $supabase;
    
    $data = json_decode(file_get_contents('php://input'), true);

    // Either an existing customer, or a name for a client who doesn't have
    // a Customer record yet (one gets created automatically on approval).
    if (!$data || (empty($data['customer_id']) && empty($data['client_name']))) {
        echo json_encode(['success' => false, 'error' => 'Select a customer or enter a client name']);
        return;
    }
    
    if (!hasPermission('quotations', 'create')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    try {
        // Extract items from data
        $items = $data['items'] ?? [];
        unset($data['items']);
        
        // Generate quotation number (numbering resets each year)
        $year = date('Y');
        $data['quotation_number'] = generateSequentialCode($supabase, 'quotations', 'quotation_number', "Q-$year");
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // Set default dates if not provided
        if (!isset($data['quotation_date'])) {
            $data['quotation_date'] = date('Y-m-d');
        }
        if (!isset($data['valid_until'])) {
            $data['valid_until'] = date('Y-m-d', strtotime('+30 days'));
        }
        
        // Insert quotation
        $insertResult = $supabase->insert('quotations', $data);
        // Supabase returns [{...}] with Prefer: return=representation — unwrap the first element
        $result = (is_array($insertResult) && isset($insertResult[0])) ? $insertResult[0] : $insertResult;

        if ($result && isset($result['id']) && !empty($items)) {
            // Insert quotation items
            foreach ($items as $item) {
                $item['quotation_id'] = (int)$result['id'];
                $item['amount'] = ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
                $supabase->insert('quotation_items', $item);
            }

            // Update items count and total amount
            $totalAmount = 0;
            foreach ($items as $item) {
                $totalAmount += ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
            }
            $supabase->update('quotations', $result['id'], [
                'items_count' => count($items),
                'total_amount' => $totalAmount
            ]);
            $result['items_count'] = count($items);
            $result['total_amount'] = $totalAmount;
        }

        if ($result && isset($result['id'])) {
            logActivity($_SESSION['user_id'], 'create', 'quotations', "Created quotation: {$result['quotation_number']}");
            echo json_encode(['success' => true, 'data' => $result, 'message' => 'Quotation created successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create quotation']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================
// PUT: Update quotation
// ============================================
function handlePutQuotation() {
    global $supabase;
    
    $id = $_GET['id'] ?? null;
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$id || !$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        return;
    }
    
    if (!hasPermission('quotations', 'edit')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }

    // Once approved, inventory has already been deducted against these
    // exact line items — editing them afterward would silently desync
    // what's recorded here from what's actually missing from stock, with
    // no re-deduction/re-credit to keep the two in sync. Blocked outright
    // rather than trying to reconcile it.
    $existing = $supabase->getById('quotations', $id);
    if ($existing && ($existing['status'] ?? '') === 'approved') {
        echo json_encode(['success' => false, 'error' => 'This quotation is already approved — its items are locked since inventory has already been deducted against them']);
        return;
    }

    try {
        // Check if items are being updated
        if (isset($data['items'])) {
            $items = $data['items'];
            unset($data['items']);
            
            // Delete existing items by quotation_id
            $supabase->request('DELETE', 'quotation_items', ['quotation_id' => 'eq.' . $id]);
            
            // Insert new items
            foreach ($items as $item) {
                $item['quotation_id'] = $id;
                $item['amount'] = ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
                $supabase->insert('quotation_items', $item);
            }
            
            // Update items count and total amount
            $totalAmount = array_sum(array_column($items, 'amount'));
            $data['items_count'] = count($items);
            $data['total_amount'] = $totalAmount;
        }
        
        $data['updated_at'] = date('Y-m-d H:i:s');
        $result = $supabase->update('quotations', $id, $data);
        
        if ($result) {
            logActivity($_SESSION['user_id'], 'update', 'quotations', "Updated quotation ID: $id");
            echo json_encode(['success' => true, 'data' => $result, 'message' => 'Quotation updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update quotation']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================
// DELETE: Delete quotation
// ============================================
function handleDeleteQuotation() {
    global $supabase;
    
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Quotation ID required']);
        return;
    }
    
    if (!hasPermission('quotations', 'delete')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    try {
        $result = archiveRecord('quotations', $id);
        if ($result) {
            logActivity($_SESSION['user_id'], 'archive', 'quotations', "Archived record ID: $id");
            echo json_encode(['success' => true, 'message' => 'Record archived successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to archive record']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>