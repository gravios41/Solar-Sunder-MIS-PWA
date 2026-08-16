<?php
// modules/reports.php
// Reports page

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

checkAuthentication();

$pageTitle = 'Reports';
$pageSubtitle = 'Generate and download business reports';

include_once __DIR__ . '/../includes/header.php';
?>

<div class="grid-cols-4 mb-6">
    <div class="stat-card" onclick="generateQuickReport('monthly')" style="cursor: pointer;">
        <div class="stat-icon blue">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-value">Monthly</div>
        <div class="stat-label">Sales Summary</div>
    </div>
    <div class="stat-card" onclick="generateQuickReport('financial')" style="cursor: pointer;">
        <div class="stat-icon green">
            <i class="fas fa-dollar-sign"></i>
        </div>
        <div class="stat-value">Financial</div>
        <div class="stat-label">Overview</div>
    </div>
    <div class="stat-card" onclick="generateQuickReport('stock')" style="cursor: pointer;">
        <div class="stat-icon orange">
            <i class="fas fa-boxes"></i>
        </div>
        <div class="stat-value">Stock</div>
        <div class="stat-label">Status</div>
    </div>
    <div class="stat-card" onclick="generateQuickReport('projects')" style="cursor: pointer;">
        <div class="stat-icon purple">
            <i class="fas fa-folder-open"></i>
        </div>
        <div class="stat-value">Project</div>
        <div class="stat-label">Status</div>
    </div>
</div>

<div class="stats-grid mb-6">
    <div class="stat-card">
        <div class="stat-value" id="salesCount">0</div>
        <div class="stat-label">Sales Reports</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" id="inventoryCount">0</div>
        <div class="stat-label">Inventory Reports</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" id="customerCount">0</div>
        <div class="stat-label">Customer Reports</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" id="projectCount">0</div>
        <div class="stat-label">Project Reports</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Generated Reports</h3>
        <button onclick="openGenerateModal()" class="btn btn-primary">
            <i class="fas fa-plus"></i> Generate Report
        </button>
    </div>
    <div class="card-body">
        <div class="filters-bar">
            <div class="filter-group">
                <label class="filter-label">Report Type</label>
                <select id="typeFilter" class="filter-select">
                    <option value="all">All Types</option>
                    <option value="Sales">Sales</option>
                    <option value="Inventory">Inventory</option>
                    <option value="Projects">Projects</option>
                    <option value="Customers">Customers</option>
                    <option value="Installations">Installations</option>
                    <option value="Financial">Financial</option>
                    <option value="Tasks">Tasks</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Period</label>
                <select id="periodFilter" class="filter-select">
                    <option value="all">All Periods</option>
                    <option value="March 2026">March 2026</option>
                    <option value="February 2026">February 2026</option>
                    <option value="January 2026">January 2026</option>
                    <option value="Q1 2026">Q1 2026</option>
                </select>
            </div>
            <div class="filter-group flex-1">
                <label class="filter-label">Search</label>
                <input type="text" id="searchInput" placeholder="Search reports..." class="form-control">
            </div>
        </div>
        
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Report Name</th>
                        <th>Type</th>
                        <th>Period</th>
                        <th>Generated Date</th>
                        <th>Format</th>
                        <th>Size</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="reportsTableBody">
                    <tr><td colspan="7" class="text-center">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Generate Reports Modal -->
<div id="generateModal" class="modal">
    <div class="modal-content" style="max-width:560px">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-file-chart-bar" style="color:var(--solar-orange);margin-right:8px"></i>Generate Reports</h3>
            <button class="modal-close" onclick="closeGenerateModal()">&times;</button>
        </div>
        <div class="modal-body">

            <!-- Select All -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--border)">
                <span style="font-weight:600;font-size:0.95rem">Select Report Types</span>
                <div style="display:flex;gap:8px">
                    <button type="button" onclick="selectAllReports(true)"  class="btn btn-secondary btn-sm">Select All</button>
                    <button type="button" onclick="selectAllReports(false)" class="btn btn-secondary btn-sm">Clear</button>
                </div>
            </div>

            <!-- Report type checkboxes -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px">
                <?php
                $reportTypes = [
                    ['key'=>'Sales',         'icon'=>'fa-chart-line',    'color'=>'#10B981', 'desc'=>'Quotations & revenue'],
                    ['key'=>'Financial',     'icon'=>'fa-coins',         'color'=>'#3B82F6', 'desc'=>'Financial overview'],
                    ['key'=>'Inventory',     'icon'=>'fa-boxes',         'color'=>'#F97316', 'desc'=>'Stock & items'],
                    ['key'=>'Projects',      'icon'=>'fa-solar-panel',   'color'=>'#8B5CF6', 'desc'=>'Project status'],
                    ['key'=>'Customers',     'icon'=>'fa-users',         'color'=>'#06B6D4', 'desc'=>'Customer list'],
                    ['key'=>'Installations', 'icon'=>'fa-tools',         'color'=>'#EF4444', 'desc'=>'Installation records'],
                    ['key'=>'Tasks',         'icon'=>'fa-check-square',  'color'=>'#10B981', 'desc'=>'Tasks & assignments'],
                ];
                foreach ($reportTypes as $rt): ?>
                <label class="report-type-card" data-type="<?php echo $rt['key']; ?>">
                    <input type="checkbox" class="report-type-check" value="<?php echo $rt['key']; ?>" onchange="updateTypeCard(this)">
                    <div class="report-type-icon" style="background:<?php echo $rt['color']; ?>20;color:<?php echo $rt['color']; ?>">
                        <i class="fas <?php echo $rt['icon']; ?>"></i>
                    </div>
                    <div>
                        <div style="font-weight:600;font-size:0.88rem"><?php echo $rt['key']; ?></div>
                        <div style="font-size:0.76rem;color:var(--text-muted)"><?php echo $rt['desc']; ?></div>
                    </div>
                    <div class="report-type-check-indicator"><i class="fas fa-check"></i></div>
                </label>
                <?php endforeach; ?>
            </div>

            <!-- Period -->
            <div class="form-group">
                <label class="form-label">Period</label>
                <select id="genPeriod" class="form-select" onchange="toggleCustomDates()">
                    <option value="current_month">Current Month (<?php echo date('F Y'); ?>)</option>
                    <option value="last_month">Last Month (<?php echo date('F Y', strtotime('-1 month')); ?>)</option>
                    <option value="current_quarter">Current Quarter (Q<?php echo ceil(date('n')/3) . ' ' . date('Y'); ?>)</option>
                    <option value="current_year">Current Year (<?php echo date('Y'); ?>)</option>
                    <option value="all_time">All Time</option>
                    <option value="custom">Custom Range</option>
                </select>
            </div>
            <div id="customDateRow" style="display:none;gap:12px" class="grid-cols-2">
                <div class="form-group">
                    <label class="form-label">From</label>
                    <input type="date" id="genDateFrom" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">To</label>
                    <input type="date" id="genDateTo" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>

            <!-- Progress bar (shown while generating) -->
            <div id="genProgress" style="display:none;margin-top:12px">
                <div style="display:flex;justify-content:space-between;font-size:0.82rem;margin-bottom:6px">
                    <span id="genProgressLabel">Generating...</span>
                    <span id="genProgressCount">0 / 0</span>
                </div>
                <div style="background:var(--border);border-radius:99px;height:8px;overflow:hidden">
                    <div id="genProgressBar" style="height:100%;background:var(--solar-orange);border-radius:99px;width:0%;transition:width 0.3s"></div>
                </div>
            </div>

        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeGenerateModal()">Cancel</button>
            <button type="button" class="btn btn-primary" id="genBtn" onclick="generateSelectedReports()">
                <i class="fas fa-cog"></i> <span id="genBtnLabel">Generate</span>
            </button>
        </div>
    </div>
</div>

<style>
.report-type-card {
    display:flex;align-items:center;gap:10px;padding:10px 12px;border:2px solid var(--border);
    border-radius:10px;cursor:pointer;transition:all .2s;position:relative;user-select:none;
}
.report-type-card:hover { border-color:var(--solar-orange);background:var(--solar-orange-light); }
.report-type-card.selected { border-color:var(--solar-orange);background:#FFF7ED; }
.report-type-card input[type=checkbox] { position:absolute;opacity:0;width:0;height:0; }
.report-type-icon { width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0; }
.report-type-check-indicator { margin-left:auto;width:20px;height:20px;border-radius:50%;background:var(--solar-orange);color:#fff;display:none;align-items:center;justify-content:center;font-size:0.65rem; }
.report-type-card.selected .report-type-check-indicator { display:flex; }
</style>

<script>
let reports = [];

async function loadReports() {
    try {
        const response = await fetch('../api/reports-api.php');
        const result = await response.json();
        if (result.success) {
            reports = result.data;
            renderReports();
            updateStats();
        }
    } catch (error) {
        console.error('Error loading reports:', error);
    }
}

function updateStats() {
    const salesCount = reports.filter(r => r.report_type === 'Sales').length;
    const inventoryCount = reports.filter(r => r.report_type === 'Inventory').length;
    const customerCount = reports.filter(r => r.report_type === 'Customers').length;
    const projectCount = reports.filter(r => r.report_type === 'Projects').length;
    
    document.getElementById('salesCount').textContent = salesCount;
    document.getElementById('inventoryCount').textContent = inventoryCount;
    document.getElementById('customerCount').textContent = customerCount;
    document.getElementById('projectCount').textContent = projectCount;
}

function renderReports() {
    const search = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const type = document.getElementById('typeFilter')?.value || 'all';
    const period = document.getElementById('periodFilter')?.value || 'all';
    
    let filtered = reports.filter(r => {
        if (search && !r.report_name.toLowerCase().includes(search) && 
            !r.report_type.toLowerCase().includes(search)) return false;
        if (type !== 'all' && r.report_type !== type) return false;
        if (period !== 'all' && r.period !== period) return false;
        return true;
    });
    
    filtered.sort((a, b) => new Date(b.generated_date) - new Date(a.generated_date));
    
    const tbody = document.getElementById('reportsTableBody');
    if (!tbody) return;
    
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No reports found</td></tr>';
        return;
    }
    
    tbody.innerHTML = filtered.map(r => `
        <tr>
            <td class="font-medium">${escapeHtml(r.report_name)}</td>
            <td>${getReportTypeBadge(r.report_type)}</td>
            <td>${escapeHtml(r.period)}</td>
            <td>${formatDate(r.generated_date)}</td>
            <td><span class="badge badge-secondary">${r.format}</span></td>
            <td>${r.file_size}</td>
            <td>
                <div style="display:flex;align-items:center;gap:6px">
                    <button onclick="downloadExcel(${r.id})" class="btn-icon" title="Download Excel (.csv)">
                        <i class="fas fa-file-excel" style="color:#10B981;font-size:1.1rem"></i>
                    </button>
                    <button onclick="downloadPDF(${r.id})" class="btn-icon" title="View/Print as PDF">
                        <i class="fas fa-file-pdf" style="color:#EF4444;font-size:1.1rem"></i>
                    </button>
                    ${(USER_ROLE === 'super_admin' || USER_ROLE === 'owner') ? `
                    <button onclick="archiveReport(${r.id})" class="btn-icon" title="Archive">
                        <i class="fas fa-archive" style="color:#6B7280"></i>
                    </button>` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

function getReportTypeBadge(type) {
    const colors = {
        'Sales': 'badge-success',
        'Inventory': 'badge-info',
        'Projects': 'badge-warning',
        'Customers': 'badge-info',
        'Installations': 'badge-secondary',
        'Financial': 'badge-danger'
    };
    return `<span class="badge ${colors[type]}">${type}</span>`;
}

// ── Generate Reports Modal ─────────────────────────────────────
function openGenerateModal() {
    selectAllReports(false);
    document.getElementById('genPeriod').value = 'current_month';
    toggleCustomDates();
    document.getElementById('genProgress').style.display = 'none';
    document.getElementById('genBtn').disabled = false;
    document.getElementById('genBtnLabel').textContent = 'Generate';
    document.getElementById('generateModal').classList.add('active');
}

function closeGenerateModal() {
    document.getElementById('generateModal').classList.remove('active');
}

function selectAllReports(select) {
    document.querySelectorAll('.report-type-check').forEach(cb => {
        cb.checked = select;
        cb.closest('.report-type-card').classList.toggle('selected', select);
    });
}

function updateTypeCard(checkbox) {
    checkbox.closest('.report-type-card').classList.toggle('selected', checkbox.checked);
}

function toggleCustomDates() {
    const isCustom = document.getElementById('genPeriod').value === 'custom';
    document.getElementById('customDateRow').style.display = isCustom ? 'grid' : 'none';
}

function getPeriodLabel() {
    const val = document.getElementById('genPeriod').value;
    const now = new Date();
    const labels = {
        current_month:   now.toLocaleString('default', { month:'long', year:'numeric' }),
        last_month:      new Date(now.getFullYear(), now.getMonth()-1, 1).toLocaleString('default', { month:'long', year:'numeric' }),
        current_quarter: `Q${Math.ceil((now.getMonth()+1)/3)} ${now.getFullYear()}`,
        current_year:    String(now.getFullYear()),
        all_time:        'All Time',
    };
    if (val === 'custom') {
        const from = document.getElementById('genDateFrom').value;
        const to   = document.getElementById('genDateTo').value;
        return (from && to) ? `${from} to ${to}` : 'Custom Range';
    }
    return labels[val] || val;
}

function getDateRange() {
    const val = document.getElementById('genPeriod').value;
    const now = new Date();
    const fmt = d => d.toISOString().slice(0, 10);

    if (val === 'current_month') {
        return { from: fmt(new Date(now.getFullYear(), now.getMonth(), 1)), to: fmt(now) };
    } else if (val === 'last_month') {
        return { from: fmt(new Date(now.getFullYear(), now.getMonth()-1, 1)),
                 to:   fmt(new Date(now.getFullYear(), now.getMonth(), 0)) };
    } else if (val === 'current_quarter') {
        const q = Math.floor(now.getMonth() / 3);
        return { from: fmt(new Date(now.getFullYear(), q*3, 1)), to: fmt(now) };
    } else if (val === 'current_year') {
        return { from: fmt(new Date(now.getFullYear(), 0, 1)), to: fmt(now) };
    } else if (val === 'custom') {
        return { from: document.getElementById('genDateFrom').value || null,
                 to:   document.getElementById('genDateTo').value   || null };
    }
    return { from: null, to: null }; // all_time
}

async function generateSelectedReports() {
    const checked = [...document.querySelectorAll('.report-type-check:checked')].map(c => c.value);
    if (!checked.length) {
        showToast('Please select at least one report type', 'error');
        return;
    }

    const period    = getPeriodLabel();
    const dateRange = getDateRange();
    const today     = new Date().toISOString().split('T')[0];
    const btn    = document.getElementById('genBtn');
    const label  = document.getElementById('genBtnLabel');
    const prog   = document.getElementById('genProgress');
    const bar    = document.getElementById('genProgressBar');
    const count  = document.getElementById('genProgressCount');
    const lbl    = document.getElementById('genProgressLabel');

    btn.disabled = true;
    prog.style.display = 'block';

    let done = 0;
    const total = checked.length;
    const failed = [];

    for (const type of checked) {
        lbl.textContent  = `Generating ${type} report…`;
        count.textContent = `${done} / ${total}`;
        bar.style.width  = `${Math.round((done/total)*100)}%`;

        const reportName = `${type} Report — ${period}`;
        try {
            const res = await fetch('../api/reports-api.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    report_name:    reportName,
                    report_type:    type,
                    period:         period,
                    date_from:      dateRange.from,
                    date_to:        dateRange.to,
                    generated_date: today,
                    format:         'PDF',
                    file_size:      '—'
                })
            });
            const result = await res.json();
            if (!result.success) failed.push(type);
        } catch (e) {
            failed.push(type);
        }
        done++;
    }

    bar.style.width  = '100%';
    count.textContent = `${done} / ${total}`;
    lbl.textContent  = 'Done!';

    if (failed.length) {
        showToast(`${done - failed.length} of ${total} reports generated. Failed: ${failed.join(', ')}`, 'error');
    } else {
        showToast(`${total} report${total > 1 ? 's' : ''} generated successfully`, 'success');
    }

    await loadReports();
    setTimeout(closeGenerateModal, 800);
}

// Keep quick-generate stat cards working
async function generateQuickReport(type) {
    const typeMap  = { monthly:'Sales', financial:'Financial', stock:'Inventory', projects:'Projects' };
    const nameMap  = { monthly:'Monthly Sales Summary', financial:'Financial Overview', stock:'Stock Status', projects:'Project Status' };
    const period   = new Date().toLocaleString('default', { month:'long', year:'numeric' });
    const today    = new Date().toISOString().split('T')[0];
    try {
        const res = await fetch('../api/reports-api.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ report_name: `${nameMap[type]} — ${period}`, report_type: typeMap[type], period, generated_date: today, format:'PDF', file_size:'—' })
        });
        const result = await res.json();
        if (result.success) { showToast('Report generated', 'success'); loadReports(); }
        else showToast(result.error, 'error');
    } catch (e) { showToast('Error generating report', 'error'); }
}

function downloadExcel(id) {
    window.location.href = `../api/report-download.php?id=${id}`;
}

function downloadPDF(id) {
    window.open(`../modules/report-print.php?id=${id}`, '_blank');
}

async function archiveReport(id) {
    const report = reports.find(r => r.id === id);
    const name = report?.report_name || 'this report';
    showConfirmModal(
        `"${name}" will be permanently deleted. This action cannot be undone.`,
        async () => {
            try {
                const response = await fetch(`../api/reports-api.php?id=${id}`, {
                    method: 'DELETE'
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    loadReports();
                } else {
                    showToast(result.error, 'error');
                }
            } catch (error) {
                showToast('Error deleting report', 'error');
            }
        },
        { title: 'Delete Report', confirmText: 'Delete' }
    );
}

// Event listeners
document.getElementById('searchInput')?.addEventListener('input', renderReports);
document.getElementById('typeFilter')?.addEventListener('change', renderReports);
document.getElementById('periodFilter')?.addEventListener('change', renderReports);

// Initialize
loadReports();
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>