<?php
// api/create-upgrade-quotation.php
// Creates a DRAFT quotation for just the upgrade items chosen on a new
// "upgrade of project X" project — same customer, linked to the new
// project. Goes through the exact same approve-quotation.php pipeline as
// any other quotation, so approving it deducts inventory and creates the
// installation/tasks exactly like a brand-new project would.

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
requireAuth();

if (!hasPermission('quotations', 'create')) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

global $supabase;
$data = json_decode(file_get_contents('php://input'), true) ?: [];
$projectId = $data['project_id'] ?? '';
$items = $data['items'] ?? [];

if (!$projectId) {
    echo json_encode(['success' => false, 'error' => 'Project ID required']);
    exit;
}
if (empty($items)) {
    echo json_encode(['success' => false, 'error' => 'Select at least one item for this upgrade']);
    exit;
}

try {
    $project = $supabase->getById('projects', $projectId);
    if (!$project) {
        throw new Exception('Project not found');
    }
    $customerId = $project['customer_id'] ?? null;
    if (!$customerId) {
        throw new Exception('This project has no customer on file');
    }
    $customer = $supabase->getById('customers', $customerId);

    $lineItems = [];
    $subtotal = 0;
    foreach ($items as $line) {
        $inventoryId = $line['inventory_id'] ?? null;
        $quantity = (float) ($line['quantity'] ?? 0);
        if (!$inventoryId || $quantity <= 0) continue;

        $inv = $supabase->getById('inventory', $inventoryId);
        if (!$inv) continue;

        $amount = $inv['unit_price'] * $quantity;
        $lineItems[] = [
            'description' => $inv['item_name'],
            'quantity' => $quantity,
            'unit_price' => $inv['unit_price'],
            'amount' => $amount,
        ];
        $subtotal += $amount;
    }

    if (empty($lineItems)) {
        throw new Exception('None of the selected items could be found in inventory');
    }

    $laborCost = $subtotal * 0.15;
    $total = $subtotal + $laborCost;
    $year = date('Y');

    $quotationData = [
        'customer_id' => $customerId,
        'project_id' => $projectId,
        'quotation_number' => generateSequentialCode($supabase, 'quotations', 'quotation_number', "Q-$year"),
        'status' => 'draft',
        'total_amount' => round($total, 2),
        'items_count' => count($lineItems) + 1,
        'quotation_date' => date('Y-m-d'),
        'valid_until' => date('Y-m-d', strtotime('+30 days')),
        'notes' => sprintf(
            "Upgrade for %s\nProject: %s (upgrade of project #%s)",
            $customer['name'] ?? 'Client',
            $project['project_name'] ?? '',
            $project['upgrade_of_project_id'] ?? '-'
        ),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    $quotationResponse = $supabase->insert('quotations', $quotationData);
    $quotation = $quotationResponse[0] ?? $quotationResponse;
    $quotationId = $quotation['id'] ?? null;
    if (!$quotationId) {
        throw new Exception('Failed to create the upgrade quotation');
    }

    foreach ($lineItems as $item) {
        $item['quotation_id'] = $quotationId;
        $supabase->insert('quotation_items', $item);
    }
    $supabase->insert('quotation_items', [
        'quotation_id' => $quotationId,
        'description' => 'Labor & Installation (15%)',
        'quantity' => 1,
        'unit_price' => round($laborCost, 2),
        'amount' => round($laborCost, 2),
    ]);

    logActivity($_SESSION['user_id'], 'create', 'quotations', "Created upgrade quotation {$quotationData['quotation_number']} for project {$project['project_name']}");

    echo json_encode([
        'success' => true,
        'message' => 'Upgrade quotation created. Review it in the Quotations module and approve it to deduct inventory.',
        'quotation_id' => $quotationId,
        'quotation_number' => $quotationData['quotation_number'],
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
