<?php
// api/report-download.php — CSV download with period filtering
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) { http_response_code(403); exit('Unauthorized'); }
if (!hasPermission('reports', 'view')) { http_response_code(403); exit('Permission denied'); }

$id = $_GET['id'] ?? null;
if (!$id) { http_response_code(400); exit('Report ID required'); }

$report = $supabase->getById('reports', $id);
if (!$report) { http_response_code(404); exit('Report not found'); }

$reportType  = $report['report_type']  ?? 'Custom';
$reportName  = $report['report_name']  ?? 'Report';
$period      = $report['period']       ?? 'All Time';
$dateFrom    = $report['date_from']    ?? null;
$dateTo      = $report['date_to']      ?? null;

function applyDateFilter($query, $field, $from, $to) {
    if ($from) $query->gte($field, $from);
    if ($to)   $query->lte($field, $to . 'T23:59:59');
    return $query;
}

// ── Collect all sheets ─────────────────────────────────────────
$allTypes  = ['Sales','Financial','Inventory','Projects','Customers','Installations','Tasks'];
$types     = ($reportType === 'All') ? $allTypes : [$reportType];
$sheets    = [];

foreach ($types as $type) {
    $headers = [];
    $rows    = [];

    switch ($type) {

        case 'Sales':
            $q = $supabase->from('quotations')->select('*')->isNull('deleted_at')->order('quotation_date', false);
            applyDateFilter($q, 'quotation_date', $dateFrom, $dateTo);
            $data = $q->execute() ?? [];
            $headers = ['#','Quotation No.','Client','Project','Quotation Date','Valid Until',
                        'Status','Total Amount (PHP)','Items Count','Notes'];
            $n = 1;
            foreach ($data as $r) {
                $cust = $supabase->getById('customers', $r['customer_id'] ?? 0);
                $proj = $r['project_id'] ? $supabase->getById('projects', $r['project_id']) : null;
                $rows[] = [
                    $n++,
                    $r['quotation_number'] ?? '',
                    $cust['name'] ?? 'Unknown',
                    $proj['project_name'] ?? '—',
                    $r['quotation_date'] ?? '',
                    $r['valid_until'] ?? '',
                    ucwords(str_replace('_',' ',$r['status'] ?? '')),
                    number_format($r['total_amount'] ?? 0, 2),
                    $r['items_count'] ?? 0,
                    $r['notes'] ?? '',
                ];
            }
            break;

        // A real financial overview, not a copy of the Sales pipeline list:
        // revenue actually realized (approved quotations only) vs. revenue
        // still in the pipeline (pending/draft/under review), plus how much
        // money is currently tied up in inventory on hand. There's no
        // separate cost-price column in inventory (only the selling
        // unit_price), so a true profit margin can't be computed — this
        // sticks to what the real data actually supports.
        case 'Financial':
            $q = $supabase->from('quotations')->select('*')->isNull('deleted_at')->order('quotation_date', false);
            applyDateFilter($q, 'quotation_date', $dateFrom, $dateTo);
            $quotationRows = $q->execute() ?? [];

            $revenueRealized = 0;
            $pipelineValue   = 0;
            foreach ($quotationRows as $r) {
                $amt = (float)($r['total_amount'] ?? 0);
                if (($r['status'] ?? '') === 'approved') {
                    $revenueRealized += $amt;
                } elseif (in_array($r['status'] ?? '', ['pending', 'draft', 'under_review'], true)) {
                    $pipelineValue += $amt;
                }
            }

            $inventoryRows = $supabase->from('inventory')->select('quantity,unit_price')->execute() ?? [];
            $inventoryValue = array_sum(array_map(
                static fn($i) => (float)($i['quantity'] ?? 0) * (float)($i['unit_price'] ?? 0),
                $inventoryRows
            ));

            $sheets['Financial Summary'] = [
                'headers' => ['Metric', 'Value (PHP)'],
                'rows' => [
                    ['Revenue Realized (Approved Quotations)', number_format($revenueRealized, 2)],
                    ['Pipeline Value (Pending/Draft Quotations)', number_format($pipelineValue, 2)],
                    ['Inventory Value On Hand', number_format($inventoryValue, 2)],
                ],
            ];

            $headers = ['#','Quotation No.','Client','Project','Quotation Date',
                        'Status','Total Amount (PHP)','Counted As'];
            $n = 1;
            foreach ($quotationRows as $r) {
                $cust = $supabase->getById('customers', $r['customer_id'] ?? 0);
                $proj = $r['project_id'] ? $supabase->getById('projects', $r['project_id']) : null;
                $countedAs = ($r['status'] ?? '') === 'approved' ? 'Revenue Realized'
                    : (in_array($r['status'] ?? '', ['pending', 'draft', 'under_review'], true) ? 'Pipeline' : 'Not counted');
                $rows[] = [
                    $n++,
                    $r['quotation_number'] ?? '',
                    $cust['name'] ?? 'Unknown',
                    $proj['project_name'] ?? '—',
                    $r['quotation_date'] ?? '',
                    ucwords(str_replace('_',' ',$r['status'] ?? '')),
                    number_format($r['total_amount'] ?? 0, 2),
                    $countedAs,
                ];
            }
            break;

        case 'Inventory':
            $q = $supabase->from('inventory')->select('*')->order('item_name', true);
            $data = $q->execute() ?? [];
            $headers = ['#','Item Code','Item Name','Category','Brand','Model',
                        'Quantity','Unit','Unit Price (PHP)','Total Value (PHP)',
                        'Reorder Level','Supplier','Location','Status','Specification'];
            $n = 1;
            foreach ($data as $i) {
                $qty    = (int)($i['quantity'] ?? 0);
                $rl     = (int)($i['reorder_level'] ?? 5);
                $status = $qty <= 0 ? 'Critical' : ($qty <= $rl ? 'Low Stock' : 'In Stock');
                $rows[] = [
                    $n++,
                    $i['item_code'] ?? '',
                    $i['item_name'] ?? '',
                    ucwords(str_replace('_',' ',$i['category'] ?? '')),
                    $i['brand'] ?? '',
                    $i['model'] ?? '',
                    $qty,
                    $i['unit'] ?? '',
                    number_format($i['unit_price'] ?? 0, 2),
                    number_format($qty * ($i['unit_price'] ?? 0), 2),
                    $rl,
                    $i['supplier'] ?? '',
                    $i['location'] ?? '',
                    $status,
                    $i['specification'] ?? '',
                ];
            }
            break;

        case 'Projects':
            $q = $supabase->from('projects')->select('*')->isNull('deleted_at')->order('created_at', false);
            applyDateFilter($q, 'created_at', $dateFrom, $dateTo);
            $data = $q->execute() ?? [];
            $headers = ['#','Project Code','Project Name','Client','Status','Progress (%)',
                        'Budget (PHP)','Manager','Start Date','End Date','Description'];
            $n = 1;
            foreach ($data as $p) {
                $cust = $supabase->getById('customers', $p['customer_id'] ?? 0);
                $rows[] = [
                    $n++,
                    $p['project_code'] ?? '',
                    $p['project_name'] ?? '',
                    $cust['name'] ?? 'Unknown',
                    ucwords(str_replace('_',' ',$p['status'] ?? '')),
                    ($p['progress'] ?? 0) . '%',
                    number_format($p['estimated_cost'] ?? 0, 2),
                    $p['manager'] ?? '',
                    $p['start_date'] ?? '',
                    $p['expected_end_date'] ?? '',
                    $p['description'] ?? '',
                ];
            }
            break;

        case 'Customers':
            $q = $supabase->from('customers')->select('*')->isNull('deleted_at')->order('name', true);
            applyDateFilter($q, 'created_at', $dateFrom, $dateTo);
            $data = $q->execute() ?? [];
            $headers = ['#','Client Code','Name','Email','Phone','Address','Type','Status','Created At'];
            $n = 1;
            foreach ($data as $c) {
                $rows[] = [
                    $n++,
                    $c['customer_code'] ?? '',
                    $c['name'] ?? '',
                    $c['email'] ?? '',
                    $c['phone'] ?? '',
                    $c['address'] ?? '',
                    ucfirst($c['type'] ?? ''),
                    ucfirst($c['status'] ?? ''),
                    substr($c['created_at'] ?? '', 0, 10),
                ];
            }
            break;

        case 'Installations':
            $q = $supabase->from('installations')->select('*')->isNull('deleted_at')->order('installation_date', false);
            applyDateFilter($q, 'installation_date', $dateFrom, $dateTo);
            $data = $q->execute() ?? [];
            $headers = ['#','Code','Client','Project','Install Date','Completion Date',
                        'Status','Progress (%)','Technician','Team','Notes'];
            $n = 1;
            foreach ($data as $i) {
                $cust = empty($i['customer_name']) ? ($supabase->getById('customers', $i['customer_id'] ?? 0)['name'] ?? 'Unknown') : $i['customer_name'];
                $proj = empty($i['project_name'])  ? ($supabase->getById('projects',  $i['project_id']  ?? 0)['project_name'] ?? 'Unknown') : $i['project_name'];
                // There's no dedicated completion_date column — updated_at
                // is the closest real signal of when a completed
                // installation was actually marked done.
                $completionDate = ($i['status'] ?? '') === 'completed' ? substr($i['updated_at'] ?? '', 0, 10) : '';
                $rows[] = [
                    $n++,
                    $i['installation_code'] ?? '',
                    $cust,
                    $proj,
                    $i['installation_date'] ?? '',
                    $completionDate,
                    ucwords(str_replace('_',' ',$i['status'] ?? '')),
                    ($i['progress'] ?? 0) . '%',
                    $i['technician'] ?? '',
                    $i['team'] ?? '',
                    $i['notes'] ?? '',
                ];
            }
            break;

        case 'Tasks':
            $q = $supabase->from('tasks')->select('*')->isNull('deleted_at')->order('due_date', true);
            applyDateFilter($q, 'created_at', $dateFrom, $dateTo);
            $data = $q->execute() ?? [];
            $headers = ['#','Task Title','Project','Priority','Status','Progress (%)',
                        'Checklist','Assigned To','Due Date','Completed Date','Description'];
            $n = 1;
            foreach ($data as $t) {
                $projName = '—';
                if (!empty($t['project_id'])) {
                    $proj = $supabase->getById('projects', $t['project_id']);
                    $projName = $proj['project_name'] ?? '—';
                }
                $rows[] = [
                    $n++,
                    $t['task_title'] ?? '',
                    $projName,
                    ucfirst($t['priority'] ?? ''),
                    ucwords(str_replace('_',' ',$t['status'] ?? '')),
                    ($t['progress_percent'] ?? 0) . '%',
                    ($t['checklist_completed'] ?? 0) . '/' . ($t['checklist_count'] ?? 0),
                    $t['assigned_to'] ?? '',
                    $t['due_date'] ?? '',
                    $t['completed_date'] ?? '',
                    $t['description'] ?? '',
                ];
            }
            break;
    }

    if ($headers) $sheets[$type] = ['headers' => $headers, 'rows' => $rows];
}

// ── Stream CSV ─────────────────────────────────────────────────
$safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $reportName) . '_' . date('Ymd') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

// Cover page
fputcsv($out, ['SUNDER SOLAR ENERGY — MANAGEMENT INFORMATION SYSTEM']);
fputcsv($out, ['Report Name:', $reportName]);
fputcsv($out, ['Report Type:', $reportType]);
fputcsv($out, ['Period:',      $period]);
if ($dateFrom) { fputcsv($out, ['Date From:', $dateFrom]); fputcsv($out, ['Date To:', $dateTo ?? '']); }
fputcsv($out, ['Generated:',   date('F j, Y  H:i')]);
fputcsv($out, ['Generated By:', $_SESSION['full_name'] ?? 'Unknown']);
fputcsv($out, []);

foreach ($sheets as $type => $sheet) {
    fputcsv($out, [strtoupper($type) . ' REPORT — ' . count($sheet['rows']) . ' records']);
    fputcsv($out, $sheet['headers']);
    foreach ($sheet['rows'] as $row) fputcsv($out, $row);
    fputcsv($out, []); // blank separator between sections
}

fclose($out);
exit();
