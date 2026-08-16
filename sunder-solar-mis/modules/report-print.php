<?php
// modules/report-print.php — Comprehensive printable report
require_once __DIR__ . '/../config/config.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../auth/login.php'); exit(); }

$id = $_GET['id'] ?? null;
if (!$id) die('Report ID required');

$report = $supabase->getById('reports', $id);
if (!$report) die('Report not found');

$reportType  = $report['report_type']  ?? 'Custom';
$reportName  = $report['report_name']  ?? 'Report';
$period      = $report['period']       ?? 'All Time';
$dateFrom    = $report['date_from']    ?? null;
$dateTo      = $report['date_to']      ?? null;
$generatedBy = $_SESSION['full_name']  ?? 'Unknown';
$generatedAt = date('F j, Y \a\t H:i');

// ── Helper: apply date filters to a query builder ──────────────
function applyDateFilter($query, $field, $from, $to) {
    if ($from) $query->gte($field, $from);
    if ($to)   $query->lte($field, $to . 'T23:59:59');
    return $query;
}

// ── Fetch data per type ────────────────────────────────────────
function fetchSection($supabase, $type, $dateFrom, $dateTo) {
    switch ($type) {

        case 'Sales':
        case 'Financial':
            $q = $supabase->from('quotations')->select('*')->order('quotation_date', false);
            applyDateFilter($q, 'quotation_date', $dateFrom, $dateTo);
            $rows = $q->execute() ?? [];
            $enriched = [];
            foreach ($rows as $r) {
                $cust = $supabase->getById('customers', $r['customer_id'] ?? 0);
                $proj = $r['project_id'] ? $supabase->getById('projects', $r['project_id']) : null;
                $items = $supabase->getAll('quotation_items', ['quotation_id' => 'eq.' . $r['id']]);
                $r['customer_name'] = $cust['name']         ?? 'Unknown';
                $r['project_name']  = $proj['project_name'] ?? '—';
                $r['items']         = $items ?? [];
                $enriched[] = $r;
            }
            return $enriched;

        case 'Inventory':
            $q = $supabase->from('inventory')->select('*')->order('item_name', true);
            $rows = $q->execute() ?? [];
            foreach ($rows as &$i) {
                $qty = (int)($i['quantity'] ?? 0);
                $rl  = (int)($i['reorder_level'] ?? 5);
                $i['status_text'] = $qty <= 0 ? 'Critical' : ($qty <= $rl ? 'Low Stock' : 'In Stock');
            }
            return $rows;

        case 'Projects':
            $q = $supabase->from('projects')->select('*')->order('created_at', false);
            applyDateFilter($q, 'created_at', $dateFrom, $dateTo);
            $rows = $q->execute() ?? [];
            foreach ($rows as &$p) {
                $cust = $supabase->getById('customers', $p['customer_id'] ?? 0);
                $p['customer_name'] = $cust['name'] ?? 'Unknown';
            }
            return $rows;

        case 'Customers':
            $q = $supabase->from('customers')->select('*')->order('name', true);
            applyDateFilter($q, 'created_at', $dateFrom, $dateTo);
            return $q->execute() ?? [];

        case 'Installations':
            $q = $supabase->from('installations')->select('*')->order('installation_date', false);
            applyDateFilter($q, 'installation_date', $dateFrom, $dateTo);
            $rows = $q->execute() ?? [];
            foreach ($rows as &$i) {
                if (empty($i['customer_name'])) {
                    $cust = $supabase->getById('customers', $i['customer_id'] ?? 0);
                    $i['customer_name'] = $cust['name'] ?? 'Unknown';
                }
                if (empty($i['project_name'])) {
                    $proj = $supabase->getById('projects', $i['project_id'] ?? 0);
                    $i['project_name'] = $proj['project_name'] ?? 'Unknown';
                }
            }
            return $rows;

        case 'Tasks':
            $q = $supabase->from('tasks')->select('*')->order('due_date', true);
            applyDateFilter($q, 'created_at', $dateFrom, $dateTo);
            $rows = $q->execute() ?? [];
            foreach ($rows as &$t) {
                if (!empty($t['project_id'])) {
                    $proj = $supabase->getById('projects', $t['project_id']);
                    $t['project_name'] = $proj['project_name'] ?? '—';
                } else {
                    $t['project_name'] = '—';
                }
            }
            return $rows;

        default:
            return [];
    }
}

// ── Determine which types to render ───────────────────────────
$allTypes = ['Sales','Financial','Inventory','Projects','Customers','Installations','Tasks'];
$types = ($reportType === 'All') ? $allTypes : [$reportType];

// Pre-fetch all sections
$sections = [];
foreach ($types as $t) {
    $sections[$t] = fetchSection($supabase, $t, $dateFrom, $dateTo);
}

function fmt($v) { return htmlspecialchars($v ?? '—'); }
function money($v) { return '₱' . number_format((float)$v, 2); }
function pct($v) { return (int)$v . '%'; }
function status($v) { return ucwords(str_replace('_', ' ', $v ?? '')); }
function shortDate($v) { return $v ? date('M d, Y', strtotime($v)) : '—'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo fmt($reportName); ?> — Sunder Solar MIS</title>
<style>
* { margin:0;padding:0;box-sizing:border-box; }
body { font-family:Arial,sans-serif;font-size:10.5px;color:#111;background:#fff;padding:20px 28px; }

/* Header */
.rpt-header { display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:14px;border-bottom:3px solid #F97316;margin-bottom:18px; }
.rpt-brand h1 { font-size:20px;font-weight:800;color:#F97316; }
.rpt-brand p  { font-size:10px;color:#6B7280;margin-top:2px; }
.rpt-meta     { text-align:right; }
.rpt-meta h2  { font-size:14px;font-weight:700;margin-bottom:5px; }
.rpt-meta td:first-child { color:#6B7280;text-align:right;padding-right:6px; }
.rpt-meta td  { font-size:10px;padding:1px 0; }

/* Summary strip */
.summary-strip { display:flex;gap:24px;flex-wrap:wrap;background:#FFF7ED;border:1px solid #FED7AA;border-radius:8px;padding:8px 16px;margin-bottom:16px; }
.summary-strip span { font-size:10.5px;color:#374151; }
.summary-strip strong { color:#F97316; }

/* Section heading */
.section-title { font-size:12px;font-weight:700;color:#F97316;text-transform:uppercase;letter-spacing:.04em;
                 margin:20px 0 8px;padding-bottom:5px;border-bottom:2px solid #FED7AA;display:flex;align-items:center;gap:6px; }
.section-count { font-size:9px;background:#F97316;color:#fff;padding:1px 7px;border-radius:99px;font-weight:700; }

/* Stat chips row */
.stat-chips { display:flex;gap:12px;flex-wrap:wrap;margin-bottom:10px; }
.stat-chip  { background:#F9FAFB;border:1px solid #E5E7EB;border-radius:8px;padding:6px 12px;font-size:10px; }
.stat-chip strong { color:#F97316;font-size:13px;display:block; }

/* Data table */
table.dt { width:100%;border-collapse:collapse;margin-top:4px;font-size:10px; }
.dt thead tr { background:#F97316;color:#fff; }
.dt thead th { padding:6px 8px;text-align:left;font-weight:700;white-space:nowrap;font-size:9.5px; }
.dt tbody tr:nth-child(even) { background:#FFF7ED; }
.dt tbody td { padding:5px 8px;border-bottom:1px solid #F3F4F6;vertical-align:top; }
.dt tbody tr:last-child td { border-bottom:none; }
.dt .num { text-align:right; }
.dt .empty td { text-align:center;padding:18px;color:#9CA3AF;font-style:italic; }

/* Status badges */
.badge { display:inline-block;padding:1px 7px;border-radius:99px;font-size:8.5px;font-weight:700; }
.b-green  { background:#D1FAE5;color:#065F46; }
.b-blue   { background:#DBEAFE;color:#1E40AF; }
.b-yellow { background:#FEF9C3;color:#92400E; }
.b-red    { background:#FEE2E2;color:#991B1B; }
.b-gray   { background:#F3F4F6;color:#374151; }
.b-purple { background:#EDE9FE;color:#5B21B6; }

/* Items sub-table */
.items-sub { margin:3px 0 3px 12px;font-size:9.5px;color:#374151; }
.items-sub td { padding:2px 6px; }

/* Page break */
.page-break { page-break-before:always;margin-top:24px; }

/* Print controls */
.print-ctrl { margin-bottom:16px;display:flex;gap:8px;justify-content:flex-end; }
.print-ctrl button { background:#F97316;color:#fff;border:none;padding:7px 18px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer; }
.print-ctrl button.sec { background:#6B7280; }

/* Footer */
.rpt-footer { margin-top:24px;padding-top:10px;border-top:1px solid #E5E7EB;display:flex;justify-content:space-between;font-size:9.5px;color:#9CA3AF; }

@media print {
  .print-ctrl { display:none; }
  body { padding:10px 16px; }
  .dt tbody tr { page-break-inside:avoid; }
}
</style>
</head>
<body>

<div class="print-ctrl">
    <button class="sec" onclick="window.close()">Close</button>
    <button onclick="window.print()">🖨 Print / Save PDF</button>
</div>

<!-- Report header -->
<div class="rpt-header">
    <div class="rpt-brand">
        <h1>☀ Sunder Solar Energy</h1>
        <p>Management Information System</p>
        <p style="margin-top:4px;font-size:9.5px;color:#9CA3AF">Confidential — Internal Use Only</p>
    </div>
    <div class="rpt-meta">
        <h2><?php echo fmt($reportName); ?></h2>
        <table>
            <tr><td>Type:</td><td><?php echo fmt($reportType); ?></td></tr>
            <tr><td>Period:</td><td><?php echo fmt($period); ?></td></tr>
            <?php if ($dateFrom): ?>
            <tr><td>From:</td><td><?php echo shortDate($dateFrom); ?></td></tr>
            <tr><td>To:</td><td><?php echo shortDate($dateTo); ?></td></tr>
            <?php endif; ?>
            <tr><td>Generated:</td><td><?php echo $generatedAt; ?></td></tr>
            <tr><td>By:</td><td><?php echo fmt($generatedBy); ?></td></tr>
        </table>
    </div>
</div>

<!-- Summary strip -->
<div class="summary-strip">
    <span>Report: <strong><?php echo fmt($reportName); ?></strong></span>
    <span>Period: <strong><?php echo fmt($period); ?></strong></span>
    <?php foreach ($sections as $t => $rows): ?>
    <span><?php echo $t; ?>: <strong><?php echo count($rows); ?> records</strong></span>
    <?php endforeach; ?>
</div>

<?php
// ── Render each section ──────────────────────────────────────
foreach ($sections as $type => $rows):
    $count = count($rows);
?>

<?php if (count($sections) > 1): ?><div class="<?php echo $type !== array_key_first($sections) ? 'page-break' : ''; ?>"><?php endif; ?>

<?php /* ═══ SALES / FINANCIAL ═══ */ if (in_array($type, ['Sales','Financial'])): ?>

<div class="section-title">
    📊 <?php echo $type; ?> Report
    <span class="section-count"><?php echo $count; ?> quotations</span>
</div>

<?php
    $totalAmt   = array_sum(array_column($rows, 'total_amount'));
    $approved   = count(array_filter($rows, fn($r) => $r['status'] === 'approved'));
    $pending    = count(array_filter($rows, fn($r) => $r['status'] === 'pending'));
    $draft      = count(array_filter($rows, fn($r) => $r['status'] === 'draft'));
?>
<div class="stat-chips">
    <div class="stat-chip"><strong><?php echo money($totalAmt); ?></strong>Total Value</div>
    <div class="stat-chip"><strong><?php echo $approved; ?></strong>Approved</div>
    <div class="stat-chip"><strong><?php echo $pending; ?></strong>Pending</div>
    <div class="stat-chip"><strong><?php echo $draft; ?></strong>Draft</div>
    <div class="stat-chip"><strong><?php echo $count > 0 ? round($approved/$count*100) : 0; ?>%</strong>Conversion Rate</div>
</div>

<table class="dt">
    <thead><tr>
        <th>#</th><th>Quotation No.</th><th>Customer</th><th>Project</th>
        <th>Date</th><th>Valid Until</th><th>Status</th>
        <th class="num">Amount (₱)</th><th>Notes</th>
    </tr></thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr class="empty"><td colspan="9">No quotations found for this period.</td></tr>
    <?php else: foreach ($rows as $i => $r): ?>
        <tr>
            <td style="color:#9CA3AF"><?php echo $i+1; ?></td>
            <td style="font-weight:700"><?php echo fmt($r['quotation_number']); ?></td>
            <td><?php echo fmt($r['customer_name']); ?></td>
            <td><?php echo fmt($r['project_name']); ?></td>
            <td><?php echo shortDate($r['quotation_date']); ?></td>
            <td><?php echo shortDate($r['valid_until']); ?></td>
            <td><?php
                $sc = ['approved'=>'b-green','pending'=>'b-yellow','draft'=>'b-gray','rejected'=>'b-red','under_review'=>'b-blue'];
                echo '<span class="badge '.($sc[$r['status']]??'b-gray').'">'.status($r['status']).'</span>';
            ?></td>
            <td class="num"><?php echo number_format($r['total_amount']??0,2); ?></td>
            <td><?php echo fmt($r['notes']); ?></td>
        </tr>
        <?php if (!empty($r['items'])): ?>
        <tr style="background:#FFFBF5">
            <td></td>
            <td colspan="8" style="padding:2px 8px 6px">
                <table class="items-sub">
                    <tr style="color:#9CA3AF"><td>Item</td><td>Qty</td><td>Unit Price</td><td>Amount</td></tr>
                    <?php foreach ($r['items'] as $it): ?>
                    <tr>
                        <td><?php echo fmt($it['description']); ?></td>
                        <td><?php echo fmt($it['quantity']); ?></td>
                        <td><?php echo money($it['unit_price']??0); ?></td>
                        <td><?php echo money(($it['quantity']??0)*($it['unit_price']??0)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </td>
        </tr>
        <?php endif; ?>
    <?php endforeach; endif; ?>
    <?php if ($rows): ?>
    <tr style="background:#FFF7ED;font-weight:700">
        <td colspan="7" style="text-align:right;padding-right:12px">Total</td>
        <td class="num"><?php echo number_format($totalAmt,2); ?></td>
        <td></td>
    </tr>
    <?php endif; ?>
    </tbody>
</table>

<?php /* ═══ INVENTORY ═══ */ elseif ($type === 'Inventory'): ?>

<div class="section-title">
    📦 Inventory Report
    <span class="section-count"><?php echo $count; ?> items</span>
</div>

<?php
    $inStock  = count(array_filter($rows, fn($r) => $r['status_text']==='In Stock'));
    $lowStock = count(array_filter($rows, fn($r) => $r['status_text']==='Low Stock'));
    $critical = count(array_filter($rows, fn($r) => $r['status_text']==='Critical'));
    $totalVal = array_sum(array_map(fn($r) => ($r['quantity']??0)*($r['unit_price']??0), $rows));
?>
<div class="stat-chips">
    <div class="stat-chip"><strong><?php echo $inStock; ?></strong>In Stock</div>
    <div class="stat-chip"><strong><?php echo $lowStock; ?></strong>Low Stock</div>
    <div class="stat-chip"><strong><?php echo $critical; ?></strong>Critical</div>
    <div class="stat-chip"><strong><?php echo money($totalVal); ?></strong>Total Value</div>
</div>

<table class="dt">
    <thead><tr>
        <th>#</th><th>Item Code</th><th>Item Name</th><th>Category</th>
        <th>Brand</th><th>Model</th><th class="num">Qty</th><th>Unit</th>
        <th class="num">Unit Price (₱)</th><th class="num">Total Value (₱)</th>
        <th class="num">Reorder Lvl</th><th>Supplier</th><th>Location</th><th>Status</th>
    </tr></thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr class="empty"><td colspan="14">No inventory items found.</td></tr>
    <?php else: foreach ($rows as $i => $r): ?>
        <tr>
            <td style="color:#9CA3AF"><?php echo $i+1; ?></td>
            <td style="font-weight:700"><?php echo fmt($r['item_code']); ?></td>
            <td><?php echo fmt($r['item_name']); ?></td>
            <td><?php echo fmt(ucwords(str_replace('_',' ',$r['category']??''))); ?></td>
            <td><?php echo fmt($r['brand']); ?></td>
            <td><?php echo fmt($r['model']); ?></td>
            <td class="num"><?php echo $r['quantity']??0; ?></td>
            <td><?php echo fmt($r['unit']); ?></td>
            <td class="num"><?php echo number_format($r['unit_price']??0,2); ?></td>
            <td class="num"><?php echo number_format(($r['quantity']??0)*($r['unit_price']??0),2); ?></td>
            <td class="num"><?php echo $r['reorder_level']??0; ?></td>
            <td><?php echo fmt($r['supplier']); ?></td>
            <td><?php echo fmt($r['location']); ?></td>
            <td><?php
                $sc = ['In Stock'=>'b-green','Low Stock'=>'b-yellow','Critical'=>'b-red'];
                echo '<span class="badge '.($sc[$r['status_text']]??'b-gray').'">'.$r['status_text'].'</span>';
            ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>

<?php /* ═══ PROJECTS ═══ */ elseif ($type === 'Projects'): ?>

<div class="section-title">
    🔆 Projects Report
    <span class="section-count"><?php echo $count; ?> projects</span>
</div>

<?php
    $active    = count(array_filter($rows, fn($r) => in_array($r['status'],['in_progress','installation'])));
    $completed = count(array_filter($rows, fn($r) => $r['status']==='completed'));
    $planning  = count(array_filter($rows, fn($r) => $r['status']==='planning'));
    $totalBudget = array_sum(array_column($rows, 'estimated_cost'));
?>
<div class="stat-chips">
    <div class="stat-chip"><strong><?php echo $active; ?></strong>Active</div>
    <div class="stat-chip"><strong><?php echo $completed; ?></strong>Completed</div>
    <div class="stat-chip"><strong><?php echo $planning; ?></strong>Planning</div>
    <div class="stat-chip"><strong><?php echo money($totalBudget); ?></strong>Total Budget</div>
</div>

<table class="dt">
    <thead><tr>
        <th>#</th><th>Project Code</th><th>Project Name</th><th>Customer</th>
        <th>Status</th><th class="num">Progress</th><th class="num">Budget (₱)</th>
        <th>Manager</th><th>Start Date</th><th>End Date</th><th>Description</th>
    </tr></thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr class="empty"><td colspan="11">No projects found for this period.</td></tr>
    <?php else: foreach ($rows as $i => $r): ?>
        <tr>
            <td style="color:#9CA3AF"><?php echo $i+1; ?></td>
            <td style="font-weight:700"><?php echo fmt($r['project_code']); ?></td>
            <td><?php echo fmt($r['project_name']); ?></td>
            <td><?php echo fmt($r['customer_name']); ?></td>
            <td><?php
                $sc=['completed'=>'b-green','in_progress'=>'b-blue','planning'=>'b-gray','on_hold'=>'b-red','installation'=>'b-yellow'];
                echo '<span class="badge '.($sc[$r['status']]??'b-gray').'">'.status($r['status']).'</span>';
            ?></td>
            <td class="num"><?php echo pct($r['progress']??0); ?></td>
            <td class="num"><?php echo number_format($r['estimated_cost']??0,2); ?></td>
            <td><?php echo fmt($r['manager']); ?></td>
            <td><?php echo shortDate($r['start_date']); ?></td>
            <td><?php echo shortDate($r['expected_end_date']); ?></td>
            <td><?php echo fmt($r['description']); ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>

<?php /* ═══ CUSTOMERS ═══ */ elseif ($type === 'Customers'): ?>

<div class="section-title">
    👥 Customers Report
    <span class="section-count"><?php echo $count; ?> customers</span>
</div>

<?php
    $active   = count(array_filter($rows, fn($r) => ($r['status']??'')==='active'));
    $inactive = count(array_filter($rows, fn($r) => ($r['status']??'')==='inactive'));
    $residential = count(array_filter($rows, fn($r) => ($r['customer_type']??'')==='residential'));
    $commercial  = count(array_filter($rows, fn($r) => ($r['customer_type']??'')==='commercial'));
?>
<div class="stat-chips">
    <div class="stat-chip"><strong><?php echo $active; ?></strong>Active</div>
    <div class="stat-chip"><strong><?php echo $inactive; ?></strong>Inactive</div>
    <div class="stat-chip"><strong><?php echo $residential; ?></strong>Residential</div>
    <div class="stat-chip"><strong><?php echo $commercial; ?></strong>Commercial</div>
</div>

<table class="dt">
    <thead><tr>
        <th>#</th><th>Customer Code</th><th>Name</th><th>Email</th><th>Phone</th>
        <th>Type</th><th>Address</th><th>Status</th><th>Created</th>
    </tr></thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr class="empty"><td colspan="9">No customers found for this period.</td></tr>
    <?php else: foreach ($rows as $i => $r): ?>
        <tr>
            <td style="color:#9CA3AF"><?php echo $i+1; ?></td>
            <td style="font-weight:700"><?php echo fmt($r['customer_code']); ?></td>
            <td><?php echo fmt($r['name']); ?></td>
            <td><?php echo fmt($r['email']); ?></td>
            <td><?php echo fmt($r['phone']); ?></td>
            <td><?php echo fmt(ucfirst($r['customer_type']??'')); ?></td>
            <td><?php echo fmt($r['address']); ?></td>
            <td><?php
                $active = ($r['status']??'')==='active';
                echo '<span class="badge '.($active?'b-green':'b-gray').'">'.ucfirst($r['status']??'').'</span>';
            ?></td>
            <td><?php echo shortDate($r['created_at']); ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>

<?php /* ═══ INSTALLATIONS ═══ */ elseif ($type === 'Installations'): ?>

<div class="section-title">
    🔧 Installations Report
    <span class="section-count"><?php echo $count; ?> installations</span>
</div>

<?php
    $completed  = count(array_filter($rows, fn($r) => $r['status']==='completed'));
    $inProgress = count(array_filter($rows, fn($r) => $r['status']==='in_progress'));
    $scheduled  = count(array_filter($rows, fn($r) => $r['status']==='scheduled'));
?>
<div class="stat-chips">
    <div class="stat-chip"><strong><?php echo $completed; ?></strong>Completed</div>
    <div class="stat-chip"><strong><?php echo $inProgress; ?></strong>In Progress</div>
    <div class="stat-chip"><strong><?php echo $scheduled; ?></strong>Scheduled</div>
</div>

<table class="dt">
    <thead><tr>
        <th>#</th><th>Code</th><th>Customer</th><th>Project</th>
        <th>Install Date</th><th>Completion Date</th><th>Status</th>
        <th class="num">Progress</th><th>Technician</th><th>Team</th><th>Notes</th>
    </tr></thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr class="empty"><td colspan="11">No installations found for this period.</td></tr>
    <?php else: foreach ($rows as $i => $r): ?>
        <tr>
            <td style="color:#9CA3AF"><?php echo $i+1; ?></td>
            <td style="font-weight:700"><?php echo fmt($r['installation_code']); ?></td>
            <td><?php echo fmt($r['customer_name']); ?></td>
            <td><?php echo fmt($r['project_name']); ?></td>
            <td><?php echo shortDate($r['installation_date'] ?? $r['scheduled_date']); ?></td>
            <td><?php echo shortDate($r['completion_date']); ?></td>
            <td><?php
                $sc=['completed'=>'b-green','in_progress'=>'b-blue','scheduled'=>'b-yellow','cancelled'=>'b-red'];
                echo '<span class="badge '.($sc[$r['status']]??'b-gray').'">'.status($r['status']).'</span>';
            ?></td>
            <td class="num"><?php echo pct($r['progress']??0); ?></td>
            <td><?php echo fmt($r['technician']); ?></td>
            <td><?php echo fmt($r['team']); ?></td>
            <td><?php echo fmt($r['notes']); ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>

<?php /* ═══ TASKS ═══ */ elseif ($type === 'Tasks'): ?>

<div class="section-title">
    ✅ Tasks Report
    <span class="section-count"><?php echo $count; ?> tasks</span>
</div>

<?php
    $completed = count(array_filter($rows, fn($r) => $r['status']==='completed'));
    $pending   = count(array_filter($rows, fn($r) => $r['status']==='pending'));
    $inProgress= count(array_filter($rows, fn($r) => $r['status']==='in_progress'));
    $overdue   = count(array_filter($rows, fn($r) => !empty($r['due_date']) && $r['status']!=='completed' && strtotime($r['due_date']) < time()));
?>
<div class="stat-chips">
    <div class="stat-chip"><strong><?php echo $completed; ?></strong>Completed</div>
    <div class="stat-chip"><strong><?php echo $inProgress; ?></strong>In Progress</div>
    <div class="stat-chip"><strong><?php echo $pending; ?></strong>Pending</div>
    <div class="stat-chip"><strong><?php echo $overdue; ?></strong>Overdue</div>
</div>

<table class="dt">
    <thead><tr>
        <th>#</th><th>Task Title</th><th>Project</th><th>Priority</th>
        <th>Status</th><th>Assigned To</th><th>Due Date</th><th>Completed Date</th><th>Description</th>
    </tr></thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr class="empty"><td colspan="9">No tasks found for this period.</td></tr>
    <?php else: foreach ($rows as $i => $r): ?>
        <tr>
            <td style="color:#9CA3AF"><?php echo $i+1; ?></td>
            <td style="font-weight:700"><?php echo fmt($r['task_title']); ?></td>
            <td><?php echo fmt($r['project_name']); ?></td>
            <td><?php
                $pc=['urgent'=>'b-red','high'=>'b-yellow','medium'=>'b-blue','low'=>'b-gray'];
                echo '<span class="badge '.($pc[$r['priority']]??'b-gray').'">'.ucfirst($r['priority']??'').'</span>';
            ?></td>
            <td><?php
                $sc=['completed'=>'b-green','in_progress'=>'b-blue','pending'=>'b-yellow','cancelled'=>'b-gray'];
                echo '<span class="badge '.($sc[$r['status']]??'b-gray').'">'.status($r['status']).'</span>';
            ?></td>
            <td><?php echo fmt($r['assigned_to']); ?></td>
            <td><?php
                $due = $r['due_date'] ?? null;
                $overdue = $due && $r['status']!=='completed' && strtotime($due) < time();
                echo $due ? '<span'.($overdue?' style="color:#EF4444;font-weight:700"':'').'>'.shortDate($due).($overdue?' ⚠':'').'</span>' : '—';
            ?></td>
            <td><?php echo shortDate($r['completed_date']); ?></td>
            <td><?php echo fmt($r['description']); ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>

<?php endif; ?>

<?php if (count($sections) > 1): ?></div><?php endif; ?>

<?php endforeach; ?>

<div class="rpt-footer">
    <span>Sunder Solar Energy MIS — Confidential</span>
    <span>Generated on <?php echo $generatedAt; ?> by <?php echo fmt($generatedBy); ?></span>
    <span>Total sections: <?php echo count($sections); ?></span>
</div>

<script>
if (new URLSearchParams(window.location.search).get('autoprint') === '1') {
    window.addEventListener('load', () => setTimeout(() => window.print(), 400));
}
</script>
</body>
</html>
