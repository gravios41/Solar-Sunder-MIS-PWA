<?php
// api/project-upgrade-recommendations.php
// Read-only: given a past project, look at what was actually approved for
// it (its approved quotation's line items) and suggest CURRENT inventory
// items compatible with each one — e.g. a battery at the same voltage as
// the one the customer already has, so a "just add a battery" upgrade
// stays compatible with their existing system instead of guessing.

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
requireAuth();

if (!hasPermission('projects', 'view')) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

global $supabase;
$projectId = $_GET['project_id'] ?? '';
if (!$projectId) {
    echo json_encode(['success' => false, 'error' => 'Project ID required']);
    exit;
}

try {
    $quotations = $supabase->getAll('quotations', [
        'project_id' => 'eq.' . $projectId,
        'status' => 'eq.approved',
    ]) ?: [];

    if (empty($quotations)) {
        echo json_encode(['success' => true, 'data' => [], 'message' => 'This project has no approved quotation yet — nothing to base an upgrade on.']);
        exit;
    }

    $quotationIds = array_column($quotations, 'id');
    $items = [];
    foreach ($quotationIds as $qid) {
        $rows = $supabase->getAll('quotation_items', ['quotation_id' => 'eq.' . $qid]) ?: [];
        $items = array_merge($items, $rows);
    }

    $allInventory = $supabase->from('inventory')->select('*')->execute() ?: [];
    $inventoryByName = [];
    foreach ($allInventory as $inv) {
        $inventoryByName[$inv['item_name']] = $inv;
    }

    $groups = []; // keyed by original item description, so duplicates in the quotation collapse
    foreach ($items as $item) {
        $desc = $item['description'] ?? '';
        if ($desc === '' || isset($groups[$desc])) continue;

        $original = $inventoryByName[$desc] ?? null;
        if (!$original) continue; // a service line, or item no longer in inventory — nothing to compare against

        $category = $original['category'];
        $voltage  = parseSpecNumber($original['specification'] ?? '', '/(\d+(?:\.\d+)?)\s*V\b/i');
        $wattage  = parseSpecNumber($original['specification'] ?? '', '/(\d+(?:\.\d+)?)\s*W\b/i');
        $brand    = $original['brand'] ?? null;

        $candidates = array_values(array_filter($allInventory, function ($inv) use ($category, $voltage, $wattage, $brand, $desc) {
            if ($inv['category'] !== $category) return false;
            if ($inv['item_name'] === $desc) return true; // the exact item they already have, if still stocked

            if ($category === 'battery' && $voltage !== null) {
                $v = parseSpecNumber($inv['specification'] ?? '', '/(\d+(?:\.\d+)?)\s*V\b/i');
                return $v !== null && abs($v - $voltage) < 0.5;
            }
            if ($category === 'solar_panel' && $wattage !== null) {
                $w = parseSpecNumber($inv['specification'] ?? '', '/(\d+(?:\.\d+)?)\s*W\b/i');
                return $w !== null && abs($w - $wattage) < 0.5;
            }
            if ($category === 'inverter' && $brand) {
                return ($inv['brand'] ?? null) === $brand;
            }
            // mounting/cable/accessories/other: same category is compatible enough
            return true;
        }));

        // Exact same item first, then everything else in the compatible set.
        usort($candidates, fn($a, $b) => ($b['item_name'] === $desc) <=> ($a['item_name'] === $desc));

        $groups[$desc] = [
            'existing_item' => $desc,
            'existing_category' => $category,
            'existing_quantity' => (float) ($item['quantity'] ?? 1),
            'compatible' => array_map(fn($c) => [
                'id' => $c['id'],
                'item_name' => $c['item_name'],
                'category' => $c['category'],
                'brand' => $c['brand'] ?? null,
                'specification' => $c['specification'] ?? null,
                'unit_price' => $c['unit_price'],
                'quantity_available' => $c['quantity'],
                'is_exact_match' => $c['item_name'] === $desc,
            ], $candidates),
        ];
    }

    echo json_encode(['success' => true, 'data' => array_values($groups)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
