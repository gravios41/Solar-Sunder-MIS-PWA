<?php
/**
 * Approve Quotation Workflow
 *
 * This is the point where a quotation becomes a real commitment: inventory
 * is deducted for whatever line items are ACTUALLY on the quotation right
 * now (not the original assessment guess — staff may have edited them via
 * the Quotations module first), a Project is created if the quotation
 * doesn't already have one, and an Installation + standard task list are
 * created so an employee can start work.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
requireAuth();

if (!hasPermission('quotations', 'edit')) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

global $supabase;
$data = json_decode(file_get_contents('php://input'), true) ?: [];
$quotationId = $data['quotation_id'] ?? '';

if (!$quotationId) {
    echo json_encode(['success' => false, 'error' => 'Quotation ID required']);
    exit;
}

try {
    $quotation = $supabase->getById('quotations', $quotationId);
    if (!$quotation) {
        throw new Exception('Quotation not found');
    }
    if ($quotation['status'] === 'approved') {
        throw new Exception('This quotation is already approved');
    }

    $quotationItems = $supabase->getAll('quotation_items', ['quotation_id' => 'eq.' . $quotationId]) ?: [];

    if (empty($quotationItems)) {
        throw new Exception('This quotation has no line items to approve');
    }

    // This is the moment a brand-new client becomes a real Customer record —
    // the quotation only had a name (and maybe contact details) until now.
    // An already-existing customer (quotation created manually, or for a
    // client already in the Customers module) skips straight past this.
    $customerId = $quotation['customer_id'] ?? null;
    $customer = $customerId ? $supabase->getById('customers', $customerId) : null;
    $customerCreated = false;

    if (!$customerId) {
        $clientName = trim($quotation['client_name'] ?? '');
        if ($clientName === '') {
            throw new Exception('This quotation has no customer and no client name on file — cannot approve');
        }
        $customerData = [
            'customer_code' => generateCode('CUST'),
            'name' => $clientName,
            'phone' => $quotation['client_phone'] ?? null,
            'email' => $quotation['client_email'] ?? null,
            'address' => $quotation['client_address'] ?? null,
            'type' => 'residential',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $customerResponse = $supabase->insert('customers', $customerData);
        $customer = $customerResponse[0] ?? $customerResponse;
        $customerId = $customer['id'] ?? null;

        if (!$customerId) {
            throw new Exception('Failed to create a Customer record for this client');
        }
        $customerCreated = true;

        $supabase->update('quotations', $quotationId, ['customer_id' => $customerId]);
        logActivity($_SESSION['user_id'], 'create', 'customers', "Created customer: $clientName (from approved quotation {$quotation['quotation_number']})");
    }

    // Use the quotation's existing project if it has one; otherwise this
    // quotation was created standalone, so create a project for it now.
    $projectId = $quotation['project_id'] ?? null;
    $projectCode = null;

    if (!$projectId) {
        $projectCode = generateSequentialCode($supabase, 'projects', 'project_code', 'PRJ');
        $projectData = [
            'customer_id' => $customerId,
            'project_code' => $projectCode,
            'project_name' => sprintf('%s - %s', $customer['name'] ?? 'Customer', $quotation['quotation_number']),
            'manager' => getOwnerFullName($supabase),
            'status' => 'planning',
            'progress' => 0,
            'estimated_cost' => $quotation['total_amount'] ?? 0,
            'start_date' => date('Y-m-d', strtotime('+7 days')),
            'expected_end_date' => date('Y-m-d', strtotime('+21 days')),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $projectResponse = $supabase->insert('projects', $projectData);
        $project = $projectResponse[0] ?? $projectResponse;
        $projectId = $project['id'] ?? null;

        if (!$projectId) {
            throw new Exception('Failed to create project for this quotation');
        }

        $supabase->update('quotations', $quotationId, ['project_id' => $projectId]);
    }

    // Deduct inventory for whatever's actually on the quotation now.
    // Line items are always chosen from a fixed dropdown (real inventory
    // items or the fixed Services list — see modules/quotations.php), so
    // an exact item_name match reliably tells inventory apart from
    // services; a service line simply won't match anything and is skipped.
    $inventoryDeductions = [];
    foreach ($quotationItems as $item) {
        $itemName = $item['description'] ?? '';
        $quantity = (float)($item['quantity'] ?? 0);
        if (!$itemName || $quantity <= 0) {
            continue;
        }

        $matches = $supabase->getAll('inventory', ['item_name' => 'eq.' . $itemName, 'select' => 'id,quantity']) ?: [];
        if (empty($matches)) {
            continue; // a service line item, or no longer in inventory
        }

        $inventoryId = $matches[0]['id'];
        $currentQty = $matches[0]['quantity'] ?? 0;
        $newQty = max(0, $currentQty - $quantity);

        $supabase->update('inventory', $inventoryId, ['quantity' => $newQty]);

        $supabase->insert('inventory_transactions', [
            'inventory_id' => $inventoryId,
            'transaction_type' => 'deduction',
            'quantity_change' => -$quantity,
            'reference_type' => 'quotation',
            'reference_id' => (string)$quotationId,
            'reason' => sprintf('Deducted for approved quotation %s', $quotation['quotation_number']),
            'created_by' => $_SESSION['user_id']
        ]);

        $inventoryDeductions[] = [
            'inventory_id' => $inventoryId,
            'item_name' => $itemName,
            'quantity_deducted' => $quantity,
            'new_available' => $newQty
        ];
    }

    // Create installation
    $installationData = [
        'customer_id' => $customerId,
        'project_id' => $projectId,
        'installation_code' => generateSequentialCode($supabase, 'installations', 'installation_code', 'INS'),
        'location' => $customer['address'] ?? '',
        'installation_date' => date('Y-m-d', strtotime('+14 days')),
        'status' => 'scheduled',
        'progress' => 0,
        'technician' => '',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    $installationResponse = $supabase->insert('installations', $installationData);
    $installation = $installationResponse[0] ?? $installationResponse;
    $installationId = $installation['id'] ?? null;

    if (!$installationId) {
        throw new Exception('Failed to create installation');
    }

    // Create installation tasks, spaced across the project timeline
    $projectRow = $supabase->getById('projects', $projectId);
    $startDate = $projectRow['start_date'] ?? date('Y-m-d');
    $standardTasks = [
        [
            'title' => 'Site Survey & Roof Assessment',
            'description' => 'Inspect roof structure and identify optimal panel placement (est. 2 hrs)',
            'days_offset' => 0,
            'checklist' => [
                'Inspect roof structure and condition',
                'Measure available roof/mounting area',
                'Check roof orientation and shading',
                'Assess main electrical panel and breaker box location',
                'Document findings and take site photos',
            ],
        ],
        [
            'title' => 'Obtain Permits & Approvals',
            'description' => 'File necessary permits with local authorities (est. 8 hrs)',
            'days_offset' => 2,
            'checklist' => [
                'Prepare permit application documents',
                'Submit application to local building office',
                'Submit utility interconnection application',
                'Follow up on permit status',
                'Receive approved permits',
            ],
        ],
        [
            'title' => 'Equipment Procurement',
            'description' => 'Order and receive all system components',
            'days_offset' => 5,
            'checklist' => [
                'Confirm equipment list against approved quotation',
                'Order solar panels',
                'Order inverter and battery',
                'Order mounting hardware and cables',
                'Receive and inspect delivered equipment',
            ],
        ],
        [
            'title' => 'Electrical Wiring & Panel Installation',
            'description' => 'Install mounting system and solar panels (est. 8 hrs)',
            'days_offset' => 9,
            'checklist' => [
                'Install roof mounting brackets/rails',
                'Mounting the panels onto racking',
                'Secure panel wirings and connectors',
                'Route DC cables to inverter location',
                'Install grounding and lightning protection',
            ],
        ],
        [
            'title' => 'Inverter & Battery Installation',
            'description' => 'Install inverter and connect to system (est. 4 hrs)',
            'days_offset' => 11,
            'checklist' => [
                'Mounting the inverter',
                'Mounting the battery',
                'Connect inverter to battery bank',
                'Wire inverter to panel DC input',
                'Mounting the breaker/disconnect switch',
            ],
        ],
        [
            'title' => 'Grid Connection & Testing',
            'description' => 'Connect to grid and perform comprehensive testing (est. 2 hrs)',
            'days_offset' => 12,
            'checklist' => [
                'Connect system to main electrical panel/breaker',
                'Perform continuity and insulation testing',
                'Power on system and check inverter readings',
                'Verify grid synchronization and net metering',
                'Run full system test under load',
            ],
        ],
        [
            'title' => 'Customer Training & Handover',
            'description' => 'Train customer on system operation and monitoring (est. 1 hr)',
            'days_offset' => 13,
            'checklist' => [
                'Walk customer through system components',
                'Demonstrate monitoring app/dashboard',
                'Explain maintenance and safety procedures',
                'Provide warranty documents and manuals',
                'Obtain customer sign-off',
            ],
        ],
    ];

    $createdTasks = [];
    foreach ($standardTasks as $taskTemplate) {
        $taskData = [
            'project_id' => $projectId,
            'task_title' => $taskTemplate['title'],
            'description' => $taskTemplate['description'],
            'status' => 'pending',
            'assigned_to' => '',
            'priority' => 'medium',
            'due_date' => date('Y-m-d', strtotime("+{$taskTemplate['days_offset']} days", strtotime($startDate))),
            'checklist_count' => count($taskTemplate['checklist']),
            'checklist_completed' => 0,
            'progress_percent' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $taskResponse = $supabase->insert('tasks', $taskData);
        $task = $taskResponse[0] ?? $taskResponse;
        $createdTasks[] = $task;

        // Pre-populate the checklist with the concrete installation steps
        // for this task, so the assigned employee checks off real work
        // instead of having to type every item themselves first.
        $taskId = $task['id'] ?? null;
        if ($taskId) {
            foreach ($taskTemplate['checklist'] as $sequence => $item) {
                $supabase->insert('task_checklists', [
                    'task_id' => $taskId,
                    'checklist_item' => $item,
                    'is_completed' => false,
                    'sequence' => $sequence + 1,
                    'created_by' => $_SESSION['user_id'],
                ]);
            }
        }
    }

    // Mark the quotation approved
    $supabase->update('quotations', $quotationId, [
        'status' => 'approved',
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    // If this quotation traces back to an energy assessment, close that out
    // too — link it to the now-real project and customer, and mark it
    // approved. status stays 'quoted' — that check constraint's allowed
    // values don't include 'approved' — but approval_status is the field
    // the UI actually reads for this.
    $linkedAssessments = $supabase->getAll('energy_assessments', ['quotation_id' => 'eq.' . $quotationId]) ?: [];
    if (!empty($linkedAssessments)) {
        $supabase->update('energy_assessments', $linkedAssessments[0]['id'], [
            'project_id' => $projectId,
            'customer_id' => $customerId,
            'approval_status' => 'approved'
        ]);
    }

    logActivity($_SESSION['user_id'], 'update', 'quotations', "Approved quotation {$quotation['quotation_number']} — created installation and tasks");

    echo json_encode([
        'success' => true,
        'message' => $customerCreated
            ? 'Quotation approved — customer record created, inventory deducted, installation scheduled, and tasks created.'
            : 'Quotation approved — inventory deducted, installation scheduled, and tasks created.',
        'created' => [
            'customer_id' => $customerId,
            'customer_created' => $customerCreated,
            'project_id' => $projectId,
            'project_code' => $projectCode,
            'installation_id' => $installationId,
            'installation_code' => $installationData['installation_code'],
            'task_count' => count($createdTasks),
            'inventory_deductions' => $inventoryDeductions
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
