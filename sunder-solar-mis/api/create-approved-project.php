<?php
/**
 * Create Project + Draft Quotation from an Energy Assessment
 *
 * Approving an assessment only gets you this far: a Project container and
 * a DRAFT Quotation pre-filled with the system's recommendation. Nothing
 * is deducted from inventory and no Installation/Tasks exist yet — that
 * only happens once the quotation itself is reviewed (line items can be
 * added/changed/removed in the Quotations module) and then approved via
 * api/approve-quotation.php. See that file for the rest of the flow.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
requireAuth();

if (!hasPermission('energy-assessments', 'create')) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

global $supabase;
$data = json_decode(file_get_contents('php://input'), true) ?: [];
$assessmentId = $data['assessment_id'] ?? '';

if (!$assessmentId) {
    echo json_encode(['success' => false, 'error' => 'Assessment ID required']);
    exit;
}

try {
    // Get assessment with customer details
    $assessments = $supabase->from('energy_assessments')
        ->select('*')
        ->eq('id', $assessmentId)
        ->single()
        ->execute();

    if (!$assessments || count($assessments) === 0) {
        throw new Exception('Assessment not found');
    }

    // single() always returns a bare row or null (never a list to unwrap)
    $assessment = $assessments;

    if (!empty($assessment['project_id'])) {
        throw new Exception('This assessment already has a project — check the Projects module instead of approving again');
    }

    $customerId = $assessment['customer_id'];
    $customer = $supabase->getById('customers', $customerId);

    // Get inventory items (panels, inverter, etc.)
    // Inventory categories match the Inventory module's own taxonomy:
    // solar_panel, inverter, battery, mounting, cable, accessories
    $panelInventory = $supabase->from('inventory')
        ->select('*')
        ->eq('category', 'solar_panel')
        ->limit(1)
        ->single()
        ->execute();

    $inverterInventory = $supabase->from('inventory')
        ->select('*')
        ->eq('category', 'inverter')
        ->limit(1)
        ->single()
        ->execute();

    $panelItem = $panelInventory;
    $inverterItem = $inverterInventory;

    // Build recommendation items — a starting point for the quotation,
    // not yet a commitment against stock (nothing is deducted here)
    $recommendationItems = [];

    if ($panelItem) {
        $panelRecItem = [
            'assessment_id' => $assessmentId,
            'inventory_id' => $panelItem['id'] ?? null,
            // Use the real inventory item's own name, not a generic
            // description — approve-quotation.php later matches quotation
            // line items back to inventory by exact name to deduct stock.
            'item_name' => $panelItem['item_name'] ?? ('Solar Panels ' . $assessment['panel_wattage'] . 'W'),
            'category' => 'panel',
            'quantity' => $assessment['recommended_panel_count'],
            'estimated_unit_price' => $panelItem['unit_price'] ?? 300,
            'estimated_total_price' => ($panelItem['unit_price'] ?? 300) * $assessment['recommended_panel_count']
        ];
        $panelResponse = $supabase->insert('recommendation_items', $panelRecItem);
        $recommendationItems[] = $panelResponse[0] ?? $panelResponse;
    }

    $inverterCapacity = ceil($assessment['recommended_system_kw'] * 1.2); // 20% headroom
    if ($inverterItem) {
        $inverterRecItem = [
            'assessment_id' => $assessmentId,
            'inventory_id' => $inverterItem['id'] ?? null,
            'item_name' => $inverterItem['item_name'] ?? "Inverter {$inverterCapacity}kW",
            'category' => 'inverter',
            'quantity' => 1,
            'estimated_unit_price' => $inverterItem['unit_price'] ?? 2000,
            'estimated_total_price' => $inverterItem['unit_price'] ?? 2000
        ];
        $inverterResponse = $supabase->insert('recommendation_items', $inverterRecItem);
        $recommendationItems[] = $inverterResponse[0] ?? $inverterResponse;
    }

    $mountingRecItem = [
        'assessment_id' => $assessmentId,
        'inventory_id' => null,
        'item_name' => 'Mounting System & Hardware',
        'category' => 'mounting',
        'quantity' => 1,
        'estimated_unit_price' => 2000,
        'estimated_total_price' => 2000
    ];
    $mountingResponse = $supabase->insert('recommendation_items', $mountingRecItem);
    $recommendationItems[] = $mountingResponse[0] ?? $mountingResponse;

    // Calculate total system cost
    $totalSystemCost = array_sum(array_map(static fn($item) => $item['estimated_total_price'] ?? 0, $recommendationItems));
    $laborCost = $totalSystemCost * 0.15; // 15% for labor
    $totalQuotationPrice = $totalSystemCost + $laborCost;

    // Create project first — quotations reference project_id, not the other way around
    $existingProjects = $supabase->getAll('projects', ['deleted_at' => 'is.null']) ?: [];
    $projectData = [
        'customer_id' => $customerId,
        'project_code' => 'PRJ-' . str_pad(count($existingProjects) + 1, 3, '0', STR_PAD_LEFT),
        'project_name' => sprintf('%s - Solar Installation %.2f kW', $customer['name'] ?? 'Customer', $assessment['recommended_system_kw']),
        'status' => 'planning',
        'progress' => 0,
        'estimated_cost' => round($totalQuotationPrice, 2),
        'start_date' => date('Y-m-d', strtotime('+7 days')),
        'expected_end_date' => date('Y-m-d', strtotime('+21 days')),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    $projectResponse = $supabase->insert('projects', $projectData);
    $project = $projectResponse[0] ?? $projectResponse;
    $projectId = $project['id'] ?? null;

    if (!$projectId) {
        throw new Exception('Failed to create project');
    }

    // Update assessment with project link — status stays short of
    // 'approved' until the quotation itself is approved (see
    // approve-quotation.php), since that's the point real commitments
    // (inventory deduction, installation, tasks) actually get made
    $supabase->update('energy_assessments', $assessmentId, [
        'project_id' => $projectId,
        'status' => 'quoted',
        'approval_status' => 'pending',
        'approved_by' => $_SESSION['user_id'],
        'approved_at' => date('Y-m-d H:i:s')
    ]);

    // Create draft quotation
    $year = date('Y');
    $existingQuotations = $supabase->getAll('quotations', ['deleted_at' => 'is.null']) ?: [];
    $maxNum = 0;
    foreach ($existingQuotations as $q) {
        if (preg_match("/Q-$year-(\d+)/", $q['quotation_number'] ?? '', $matches)) {
            $maxNum = max($maxNum, intval($matches[1]));
        }
    }

    $quotationData = [
        'customer_id' => $customerId,
        'project_id' => $projectId,
        'quotation_number' => 'Q-' . $year . '-' . str_pad($maxNum + 1, 3, '0', STR_PAD_LEFT),
        'status' => 'draft',
        'total_amount' => round($totalQuotationPrice, 2),
        'items_count' => count($recommendationItems) + 1,
        'quotation_date' => date('Y-m-d'),
        'valid_until' => date('Y-m-d', strtotime('+30 days')),
        'notes' => sprintf(
            "Solar PV System for %s\nRecommended size: %.2f kW\nPanel count: %d x %dW\nAverage consumption: %.2f kWh/month",
            $customer['name'] ?? 'Customer',
            $assessment['recommended_system_kw'],
            $assessment['recommended_panel_count'],
            $assessment['panel_wattage'],
            $assessment['average_monthly_kwh']
        ),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    $quotationResponse = $supabase->insert('quotations', $quotationData);
    $quotation = $quotationResponse[0] ?? $quotationResponse;
    $quotationId = $quotation['id'] ?? null;

    if (!$quotationId) {
        throw new Exception('Failed to create quotation');
    }

    // Quotation line items — equipment plus labor. Staff can still edit,
    // add, or remove these in the Quotations module before approving it.
    foreach ($recommendationItems as $item) {
        $supabase->insert('quotation_items', [
            'quotation_id' => $quotationId,
            'description' => $item['item_name'] ?? 'Item',
            'quantity' => $item['quantity'] ?? 1,
            'unit_price' => $item['estimated_unit_price'] ?? 0,
            'amount' => $item['estimated_total_price'] ?? 0
        ]);
    }
    $supabase->insert('quotation_items', [
        'quotation_id' => $quotationId,
        'description' => 'Labor & Installation (15%)',
        'quantity' => 1,
        'unit_price' => round($laborCost, 2),
        'amount' => round($laborCost, 2)
    ]);

    logActivity($_SESSION['user_id'], 'create', 'projects', "Created project and draft quotation from approved assessment");

    echo json_encode([
        'success' => true,
        'message' => 'Created project and draft quotation. Review the quotation and approve it to deduct inventory and schedule installation.',
        'created' => [
            'project_id' => $projectId,
            'project_code' => $projectData['project_code'],
            'quotation_id' => $quotationId,
            'quotation_number' => $quotationData['quotation_number'],
            'total_cost' => $totalQuotationPrice,
            'recommendation_items_count' => count($recommendationItems)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
