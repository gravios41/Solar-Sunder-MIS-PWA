<?php
// config/functions.php
// Helper functions for the application

// Format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Format date
function formatDate($date, $format = 'M d, Y') {
    if (!$date) return '-';
    return date($format, strtotime($date));
}

// Format datetime
function formatDateTime($datetime, $format = 'M d, Y h:i A') {
    if (!$datetime) return '-';
    return date($format, strtotime($datetime));
}

// Get status badge HTML
function getStatusBadge($status) {
    $badges = [
        'active' => 'badge-success',
        'inactive' => 'badge-danger',
        'pending' => 'badge-warning',
        'completed' => 'badge-success',
        'in_progress' => 'badge-info',
        'planning' => 'badge-secondary',
        'installation' => 'badge-warning',
        'on_hold' => 'badge-danger',
        'approved' => 'badge-success',
        'rejected' => 'badge-danger',
        'draft' => 'badge-secondary',
        'under_review' => 'badge-info',
        'scheduled' => 'badge-warning'
    ];
    
    $class = $badges[$status] ?? 'badge-secondary';
    $label = ucwords(str_replace('_', ' ', $status));
    
    return "<span class=\"badge $class\">$label</span>";
}

// Get priority badge
function getPriorityBadge($priority) {
    $badges = [
        'low' => 'badge-secondary',
        'medium' => 'badge-info',
        'high' => 'badge-warning',
        'urgent' => 'badge-danger'
    ];
    
    $class = $badges[$priority] ?? 'badge-secondary';
    $label = ucfirst($priority);
    
    return "<span class=\"badge $class\">$label</span>";
}

// Escape HTML
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Generate random code
function generateCode($prefix, $length = 6) {
    return $prefix . str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

// Log activity - CORRECTED VERSION
function logActivity($userId, $action, $module, $description) {
    // Access global supabase connection
    global $supabase;
    
    // If $supabase is not set or is null, try to get it from the $GLOBALS array
    if (!isset($supabase) || $supabase === null) {
        if (isset($GLOBALS['supabase']) && $GLOBALS['supabase'] !== null) {
            $supabase = $GLOBALS['supabase'];
        } else {
            error_log("Supabase connection not available in logActivity() function");
            return false;
        }
    }
    
    // Check if supabase has the insert method
    if (!method_exists($supabase, 'insert')) {
        error_log("Supabase client doesn't have 'insert' method");
        return false;
    }
    
    try {
        // Direct insert using SupabaseClient's insert method
        $result = $supabase->insert('activity_logs', [
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error in logActivity: " . $e->getMessage());
        return false;
    }
}

// Show toast message (stores in session for next page load)
function setToast($message, $type = 'success') {
    $_SESSION['toast'] = ['message' => $message, 'type' => $type];
}

// Display toast if exists
function displayToast() {
    if (isset($_SESSION['toast'])) {
        $toast = $_SESSION['toast'];
        unset($_SESSION['toast']);
        echo "<script>showToast('{$toast['message']}', '{$toast['type']}');</script>";
    }
}

// Check if page is accessible
function checkPageAccess($module) {
    if (!hasPermission($module, 'view')) {
        setToast('You do not have permission to access this page', 'error');
        header('Location: ' . SITE_URL . firstAccessibleModuleUrl());
        exit();
    }
}

// The first module (in priority order) the current role actually has 'view'
// on — used as a safe redirect target so a role locked out of dashboard.php
// itself (e.g. admin) doesn't get bounced right back into another denial.
function firstAccessibleModuleUrl() {
    $priority = ['dashboard', 'tasks', 'customers', 'quotations', 'projects', 'installations', 'reports', 'inventory', 'energy-assessments'];
    foreach ($priority as $module) {
        if (hasPermission($module, 'view')) {
            return "modules/$module.php";
        }
    }
    return 'auth/logout.php';
}

// Get user initials
function getUserInitials($name) {
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    return substr($initials, 0, 2);
}

// Pagination helper
function paginate($total, $currentPage, $perPage = 20) {
    $totalPages = ceil($total / $perPage);
    $prevPage = max(1, $currentPage - 1);
    $nextPage = min($totalPages, $currentPage + 1);

    return [
        'current' => $currentPage,
        'total' => $totalPages,
        'prev' => $prevPage,
        'next' => $nextPage,
        'per_page' => $perPage,
        'offset' => ($currentPage - 1) * $perPage
    ];
}

// Archive a record: copies to archives table, then hard-deletes from original table
function archiveRecord($entityType, $id) {
    global $supabase;
    $codeFields = [
        'tasks'         => 'task_title',
        'projects'      => 'project_name',
        'customers'     => 'name',
        'quotations'    => 'quotation_number',
        'installations' => 'installation_code',
        'inventory'     => 'item_name',
        'reports'       => 'report_name',
        'users'         => 'full_name',
    ];
    $record = $supabase->getById($entityType, $id);
    if (!$record) {
        throw new Exception("Record not found in '$entityType' (ID: $id)");
    }
    $codeField  = $codeFields[$entityType] ?? 'id';
    $entityCode = $record[$codeField] ?? "ID: {$record['id']}";

    // Pass $record as a PHP array — json_encode in the HTTP layer serialises it
    // as a proper JSON object, which Supabase accepts for JSONB columns.
    // Passing json_encode($record) here would double-encode it into a string.
    $archiveResult = $supabase->insert('archives', [
        'entity_type'      => $entityType,
        'entity_id'        => (int)$id,
        'entity_code'      => $entityCode,
        'record_data'      => $record,
        'archived_by'      => (int)($_SESSION['user_id'] ?? 0),
        'archived_by_name' => $_SESSION['full_name'] ?? 'Unknown',
        'archived_at'      => date('Y-m-d H:i:s'),
        'created_at'       => date('Y-m-d H:i:s'),
    ]);
    if (!$archiveResult) {
        throw new Exception("Failed to insert into archives table. Make sure the 'archives' table exists in Supabase.");
    }
    $supabase->delete($entityType, $id);
    return true;
}

// This company's project manager is always its owner — a project never
// gets assigned to anyone else, so the field is auto-filled from the one
// 'owner' account instead of asking anyone to type a name in. Returns ''
// (rather than throwing) if no owner account exists yet, so project
// creation still succeeds with an empty manager field.
function getOwnerFullName($supabase) {
    $owners = $supabase->getAll('users', ['role' => 'eq.owner', 'select' => 'full_name', 'limit' => 1]) ?: [];
    return $owners[0]['full_name'] ?? '';
}

// Extracts the leading number in front of a unit from an inventory item's
// `specification` text (e.g. "550W monocrystalline..." -> 550.0,
// "12kW three-phase hybrid" -> 12.0, "5.12kWh, 51.2V..." -> 5.12).
function parseSpecNumber($specification, $pattern) {
    if ($specification && preg_match($pattern, $specification, $matches)) {
        return (float)$matches[1];
    }
    return null;
}

// Picks one item from an inventory category by tier: budget = cheapest
// available, luxury = most premium (priciest) available, standard =
// lowest id (a stable, arbitrary "default" pick — used for categories
// where there's no meaningful spec to optimize against, like mounting
// hardware or accessories).
function pickInventoryByTier($supabase, $category, $tier) {
    $items = $supabase->from('inventory')->select('*')->eq('category', $category)->execute() ?: [];
    if (!$items) {
        return null;
    }
    if ($tier === 'budget') {
        usort($items, fn($a, $b) => ($a['unit_price'] ?? 0) <=> ($b['unit_price'] ?? 0));
    } elseif ($tier === 'luxury') {
        usort($items, fn($a, $b) => ($b['unit_price'] ?? 0) <=> ($a['unit_price'] ?? 0));
    } else {
        usort($items, fn($a, $b) => $a['id'] <=> $b['id']);
    }
    return $items[0];
}

// Builds the real bill of materials for a recommended solar system by
// matching actual inventory specs — not just grabbing whichever item in a
// category happens to have the lowest id regardless of whether it's the
// right size. This is the SINGLE source of truth used by both the live
// Recommendation preview (api/recommendation-preview.php, called from
// energy-assessments.php) and the real creation on approval
// (create-approved-project.php) — they must never diverge, since the
// preview is a promise about what approval will actually create.
//
// $tier selects which of the three recommendations to build:
//   'budget'   — cheapest components that still meet the technical minimum
//   'standard' — the actual recommendation: closest wattage match, smallest
//                inverter that meets headroom, cheapest way to cover ~1 day
//                of storage (this is what approval actually uses)
//   'luxury'   — most premium components available, extra headroom, more
//                battery autonomy
//
// $systemType changes battery/backup sizing to match how the system
// actually connects to the grid:
//   'hybrid'    (default) — grid-connected with battery backup; the tier's
//                normal autonomy target applies as-is
//   'grid_tied' — no battery at all; excess power exports to the grid via
//                net metering instead of being stored
//   'off_grid'  — no grid connection to fall back on, so battery autonomy
//                is tripled and inverter headroom is higher
function buildRecommendationMaterials($supabase, $requestedPanelWattage, $systemKw, $averageDailyKwh, $tier = 'standard', $systemType = 'hybrid') {
    $items = [];

    // --- Panels: standard matches the requested wattage as closely as
    // possible; budget/luxury pick the cheapest/priciest panel available
    // instead. Either way, the count is recomputed against the SELECTED
    // panel's real wattage so the system still reaches the target kW. ---
    $panels = $supabase->from('inventory')->select('*')->eq('category', 'solar_panel')->order('id', true)->execute() ?: [];
    $panelCandidates = [];
    foreach ($panels as $p) {
        $watts = parseSpecNumber($p['specification'] ?? '', '/(\d+(?:\.\d+)?)\s*W\b/i');
        if ($watts !== null) {
            $panelCandidates[] = ['item' => $p, 'watts' => $watts];
        }
    }
    $bestPanel = null; $bestPanelWattage = $requestedPanelWattage;
    if ($tier === 'budget') {
        usort($panelCandidates, fn($a, $b) => ($a['item']['unit_price'] ?? 0) <=> ($b['item']['unit_price'] ?? 0));
        if ($panelCandidates) { $bestPanel = $panelCandidates[0]['item']; $bestPanelWattage = $panelCandidates[0]['watts']; }
    } elseif ($tier === 'luxury') {
        usort($panelCandidates, fn($a, $b) => ($b['item']['unit_price'] ?? 0) <=> ($a['item']['unit_price'] ?? 0));
        if ($panelCandidates) { $bestPanel = $panelCandidates[0]['item']; $bestPanelWattage = $panelCandidates[0]['watts']; }
    } else {
        $bestDiff = null;
        foreach ($panelCandidates as $c) {
            $diff = abs($c['watts'] - $requestedPanelWattage);
            if ($bestDiff === null || $diff < $bestDiff) {
                $bestDiff = $diff;
                $bestPanel = $c['item'];
                $bestPanelWattage = $c['watts'];
            }
        }
    }
    $panelCount = max(1, (int)ceil($systemKw * 1000 / $bestPanelWattage));
    $panelUnitPrice = $bestPanel['unit_price'] ?? 300;
    $items[] = [
        'inventory_id' => $bestPanel['id'] ?? null,
        'item_name' => $bestPanel['item_name'] ?? ('Solar Panels ' . $requestedPanelWattage . 'W'),
        'category' => 'solar_panel',
        'quantity' => $panelCount,
        'unit_price' => $panelUnitPrice,
        'total_price' => $panelUnitPrice * $panelCount,
    ];

    // --- Inverter: needs at least 20% headroom over the system's kW
    // rating (luxury adds more for future expansion room; off-grid adds
    // more still since there's no grid to lean on if it's undersized).
    // Among the inverters that qualify, standard picks the smallest
    // (just enough), budget the cheapest, luxury the priciest. ---
    $headroomMultiplier = $tier === 'luxury' ? 1.5 : 1.2;
    if ($systemType === 'off_grid') {
        $headroomMultiplier += 0.3;
    }
    $neededInverterKw = $systemKw * $headroomMultiplier;
    $inverters = $supabase->from('inventory')->select('*')->eq('category', 'inverter')->order('id', true)->execute() ?: [];
    $qualifying = [];
    foreach ($inverters as $inv) {
        $kw = parseSpecNumber($inv['specification'] ?? '', '/(\d+(?:\.\d+)?)\s*kW\b/i');
        if ($kw !== null && $kw >= $neededInverterKw) {
            $qualifying[] = ['item' => $inv, 'kw' => $kw];
        }
    }
    if (!$qualifying) {
        // Nothing in stock is big enough — fall back to the largest
        // available rather than silently recommending an undersized unit.
        foreach ($inverters as $inv) {
            $kw = parseSpecNumber($inv['specification'] ?? '', '/(\d+(?:\.\d+)?)\s*kW\b/i');
            if ($kw !== null) {
                $qualifying[] = ['item' => $inv, 'kw' => $kw];
            }
        }
        usort($qualifying, fn($a, $b) => $b['kw'] <=> $a['kw']);
    } elseif ($tier === 'budget') {
        usort($qualifying, fn($a, $b) => ($a['item']['unit_price'] ?? 0) <=> ($b['item']['unit_price'] ?? 0));
    } elseif ($tier === 'luxury') {
        usort($qualifying, fn($a, $b) => ($b['item']['unit_price'] ?? 0) <=> ($a['item']['unit_price'] ?? 0));
    } else {
        usort($qualifying, fn($a, $b) => $a['kw'] <=> $b['kw']);
    }
    $bestInverter = $qualifying[0]['item'] ?? null;
    $bestInverterKw = $qualifying[0]['kw'] ?? null;
    $inverterUnitPrice = $bestInverter['unit_price'] ?? 2000;
    $items[] = [
        'inventory_id' => $bestInverter['id'] ?? null,
        'item_name' => $bestInverter['item_name'] ?? ('Inverter ' . ceil($neededInverterKw) . 'kW'),
        'category' => 'inverter',
        'quantity' => 1,
        'unit_price' => $inverterUnitPrice,
        'total_price' => $inverterUnitPrice,
    ];

    // --- Battery: a Grid-Tied system has nothing to store excess power in
    // (it exports to the grid via net metering instead), so it gets NO
    // battery line item at all — not even a zero-quantity placeholder,
    // since the whole point is to only show what this system type
    // actually needs. Otherwise, size storage to a tier-appropriate
    // backup autonomy — half a day for budget, one day for the standard
    // recommendation, two days for luxury — tripled for Off-Grid, since
    // there's no grid to fall back on if it runs out. Budget/standard
    // minimize total cost across every available battery option; luxury
    // picks the most premium single battery brand instead. ---
    if ($systemType !== 'grid_tied') {
        $autonomyDays = match ($tier) { 'budget' => 0.5, 'luxury' => 2.0, default => 1.0 };
        if ($systemType === 'off_grid') {
            $autonomyDays *= 3;
        }
        $requiredKwh = $averageDailyKwh * $autonomyDays;
        $batteries = $supabase->from('inventory')->select('*')->eq('category', 'battery')->order('id', true)->execute() ?: [];
        $batteryCandidates = [];
        foreach ($batteries as $b) {
            $kwh = parseSpecNumber($b['specification'] ?? '', '/(\d+(?:\.\d+)?)\s*kWh\b/i');
            if ($kwh === null || $kwh <= 0) continue;
            $quantity = max(1, (int)ceil($requiredKwh / $kwh));
            $batteryCandidates[] = [
                'item' => $b,
                'quantity' => $quantity,
                'cost' => $quantity * ($b['unit_price'] ?? PHP_FLOAT_MAX),
            ];
        }
        if ($tier === 'luxury') {
            usort($batteryCandidates, fn($a, $b) => ($b['item']['unit_price'] ?? 0) <=> ($a['item']['unit_price'] ?? 0));
        } else {
            usort($batteryCandidates, fn($a, $b) => $a['cost'] <=> $b['cost']);
        }
        $bestBattery = $batteryCandidates[0]['item'] ?? null;
        $batteryQuantity = $batteryCandidates[0]['quantity'] ?? max(1, (int)ceil($requiredKwh / 5));
        $batteryUnitPrice = $bestBattery['unit_price'] ?? 1200;
        $items[] = [
            'inventory_id' => $bestBattery['id'] ?? null,
            'item_name' => $bestBattery['item_name'] ?? 'Battery Storage',
            'category' => 'battery',
            'quantity' => $batteryQuantity,
            'unit_price' => $batteryUnitPrice,
            'total_price' => $batteryUnitPrice * $batteryQuantity,
        ];
    }

    // --- Mounting, cable & accessories: budget/luxury pick the cheapest/
    // priciest hardware available in each category; standard keeps the
    // stable default (lowest id). Quantities still scale with panel count
    // where that makes physical sense — no better data (roof layout,
    // inverter-room distance) is available to size these more precisely. ---
    $mounting = pickInventoryByTier($supabase, 'mounting', $tier);
    $mountingUnitPrice = $mounting['unit_price'] ?? 75;
    $items[] = [
        'inventory_id' => $mounting['id'] ?? null,
        'item_name' => $mounting['item_name'] ?? 'Mounting System & Hardware',
        'category' => 'mounting',
        'quantity' => $panelCount,
        'unit_price' => $mountingUnitPrice,
        'total_price' => $mountingUnitPrice * $panelCount,
    ];

    $cableQuantity = $panelCount * 5;
    $cable = pickInventoryByTier($supabase, 'cable', $tier);
    $cableUnitPrice = $cable['unit_price'] ?? 2.5;
    $items[] = [
        'inventory_id' => $cable['id'] ?? null,
        'item_name' => $cable['item_name'] ?? 'DC Cable 4mm',
        'category' => 'cable',
        'quantity' => $cableQuantity,
        'unit_price' => $cableUnitPrice,
        'total_price' => $cableUnitPrice * $cableQuantity,
    ];

    $accessories = pickInventoryByTier($supabase, 'accessories', $tier);
    $accessoriesUnitPrice = $accessories['unit_price'] ?? 950;
    $items[] = [
        'inventory_id' => $accessories['id'] ?? null,
        'item_name' => $accessories['item_name'] ?? 'Breaker & Accessories',
        'category' => 'accessories',
        'quantity' => 1,
        'unit_price' => $accessoriesUnitPrice,
        'total_price' => $accessoriesUnitPrice,
    ];

    return [
        'tier' => $tier,
        'system_type' => $systemType,
        'items' => $items,
        'panel_count' => $panelCount,
        'panel_wattage_selected' => $bestPanelWattage,
        'inverter_kw_needed' => round($neededInverterKw, 2),
        'inverter_kw_selected' => $bestInverterKw,
    ];
}

// Convenience wrapper: builds all three recommendation tiers at once, so
// callers that need to show/compare Budget, Standard, and Luxury side by
// side don't have to call buildRecommendationMaterials() three times themselves.
function buildAllRecommendationTiers($supabase, $requestedPanelWattage, $systemKw, $averageDailyKwh, $systemType = 'hybrid') {
    $tiers = [];
    foreach (['budget', 'standard', 'luxury'] as $tier) {
        $tiers[$tier] = buildRecommendationMaterials($supabase, $requestedPanelWattage, $systemKw, $averageDailyKwh, $tier, $systemType);
    }
    return $tiers;
}

// Generates the next sequential code (e.g. "PRJ-004", "Q-2026-011") for a
// table+column that has a unique constraint. Scans EVERY row, including
// soft-deleted ones — filtering to only active rows was the actual bug:
// deleted_at soft-deletes are permanent (see the projects table's DB-level
// delete trigger), so a soft-deleted row's code is still occupied as far
// as the unique constraint is concerned. Counting only active rows could
// regenerate a code that already exists on an archived row and fail with
// "duplicate key value violates unique constraint".
function generateSequentialCode($supabase, $table, $column, $prefix, $padLength = 3) {
    $rows = $supabase->getAll($table, ['select' => $column]) ?: [];
    $max = 0;
    $escapedPrefix = preg_quote($prefix, '/');
    foreach ($rows as $row) {
        $value = $row[$column] ?? '';
        if (preg_match('/' . $escapedPrefix . '-?0*(\d+)$/', $value, $matches)) {
            $max = max($max, (int)$matches[1]);
        }
    }
    return $prefix . '-' . str_pad($max + 1, $padLength, '0', STR_PAD_LEFT);
}

// Recomputes a project's overall progress as the average of its (non-
// deleted) tasks' own progress_percent — which is itself accurate, since
// it's driven by real checklist completion (see updateTaskChecklistProgress()
// in task-checklist-api.php). Called whenever a task's progress or
// completion status could have changed, so the Projects module always
// shows real, current progress instead of a number someone typed in once
// and never updated. Skips a project already marked 'completed' — that
// terminal state is set by completeProjectPipelineIfDone() and shouldn't
// be second-guessed by a rollup average.
function updateProjectProgressFromTasks($supabase, $projectId) {
    if (!$projectId) return;

    try {
        $project = $supabase->getById('projects', $projectId);
        if (!$project || ($project['status'] ?? '') === 'completed') {
            return;
        }

        $tasks = $supabase->getAll('tasks', ['project_id' => 'eq.' . $projectId, 'deleted_at' => 'is.null', 'select' => 'progress_percent']) ?: [];
        if (empty($tasks)) {
            return;
        }

        $total = array_sum(array_map(fn($t) => (int)($t['progress_percent'] ?? 0), $tasks));
        $average = (int)round($total / count($tasks));

        if ($average !== (int)($project['progress'] ?? -1)) {
            $supabase->update('projects', $projectId, [
                'progress' => $average,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    } catch (Exception $e) {
        error_log('updateProjectProgressFromTasks failed: ' . $e->getMessage());
    }
}

// Called whenever a task's status becomes 'completed'. If every task on that
// task's project is now completed too, the whole pipeline is closed out:
// the project and its installation(s) are marked completed, and a
// completion report is auto-generated. This is the "Task -> Report" link
// shared by both automated approval chains (Assessment/Quotation -> Project
// -> Installation -> Task -> Report) — kept as a single function so both
// task-checklist-api.php and tasks-api.php can call it without duplicating
// the completion logic.
function completeProjectPipelineIfDone($supabase, $projectId) {
    if (!$projectId) return;

    try {
        $project = $supabase->getById('projects', $projectId);
        if (!$project || ($project['status'] ?? '') === 'completed') {
            return; // no project, or pipeline already closed out
        }

        $tasks = $supabase->getAll('tasks', ['project_id' => 'eq.' . $projectId, 'deleted_at' => 'is.null', 'select' => 'status']) ?: [];
        if (empty($tasks)) return;

        foreach ($tasks as $t) {
            if (($t['status'] ?? '') !== 'completed') {
                return; // still work left to do
            }
        }

        $today = date('Y-m-d');

        $supabase->update('projects', $projectId, [
            'status'     => 'completed',
            'progress'   => 100,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $installations = $supabase->getAll('installations', ['project_id' => 'eq.' . $projectId, 'deleted_at' => 'is.null']) ?: [];
        foreach ($installations as $inst) {
            $supabase->update('installations', $inst['id'], [
                'status'     => 'completed',
                'progress'   => 100,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $customer = !empty($project['customer_id']) ? $supabase->getById('customers', $project['customer_id']) : null;

        $supabase->insert('reports', [
            'report_name'    => sprintf('Installation Completion — %s (%s)', $project['project_name'] ?? 'Project', $customer['name'] ?? 'Customer'),
            'report_type'    => 'Installations',
            'period'         => date('F Y'),
            'date_from'      => $project['start_date'] ?? null,
            'date_to'        => $today,
            'generated_date' => $today,
            'format'         => 'PDF',
            'file_size'      => '—',
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        logActivity($_SESSION['user_id'] ?? 0, 'update', 'projects', "All tasks complete — project \"{$project['project_name']}\" and its installation auto-marked completed, completion report generated");
    } catch (Exception $e) {
        error_log('completeProjectPipelineIfDone failed: ' . $e->getMessage());
    }
}
?>