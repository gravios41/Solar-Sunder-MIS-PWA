<?php
// modules/archives.php
// Archived records management page

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
checkAuthentication();

if (!in_array($_SESSION['user_role'] ?? '', ['super_admin', 'admin', 'owner'])) {
    header('Location: ../modules/dashboard.php');
    exit();
}

$pageTitle    = 'Archives';
$pageSubtitle = 'Archived records — recoverable';

include_once __DIR__ . '/../includes/header.php';
?>

<style>
.archive-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:4px; }
.archive-tab { padding:6px 14px; border-radius:999px; border:1px solid var(--border); background:transparent; cursor:pointer; font-size:0.8rem; font-weight:500; color:var(--text-muted); transition:var(--t); }
.archive-tab.active { background:var(--solar-orange); color:white; border-color:var(--solar-orange); }
.archive-tab:hover:not(.active) { background:var(--bg-hover); color:var(--text); }
.entity-dot { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
</style>

<!-- Stat Cards -->
<div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card orange">
        <div class="stat-header">
            <div class="stat-icon orange"><i class="fas fa-archive"></i></div>
        </div>
        <div class="stat-value" id="totalCount">0</div>
        <div class="stat-label">Total Archived</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-header">
            <div class="stat-icon blue"><i class="fas fa-solar-panel"></i></div>
        </div>
        <div class="stat-value" id="projectsCount">0</div>
        <div class="stat-label">Projects</div>
    </div>
    <div class="stat-card purple">
        <div class="stat-header">
            <div class="stat-icon purple"><i class="fas fa-tasks"></i></div>
        </div>
        <div class="stat-value" id="tasksCount">0</div>
        <div class="stat-label">Tasks</div>
    </div>
    <div class="stat-card green">
        <div class="stat-header">
            <div class="stat-icon green"><i class="fas fa-users"></i></div>
        </div>
        <div class="stat-value" id="customersCount">0</div>
        <div class="stat-label">Customers</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Archived Records</h3>
        <input type="text" id="searchInput" placeholder="Search archives..." class="form-control" style="max-width:260px">
    </div>
    <div class="card-body">
        <div class="archive-tabs">
            <button class="archive-tab active" onclick="setTab('all', this)">All</button>
            <button class="archive-tab" onclick="setTab('customers', this)">Customers</button>
            <button class="archive-tab" onclick="setTab('projects', this)">Projects</button>
            <button class="archive-tab" onclick="setTab('quotations', this)">Quotations</button>
            <button class="archive-tab" onclick="setTab('installations', this)">Installations</button>
            <button class="archive-tab" onclick="setTab('tasks', this)">Tasks</button>
            <button class="archive-tab" onclick="setTab('inventory', this)">Inventory</button>
            <button class="archive-tab" onclick="setTab('reports', this)">Reports</button>
            <button class="archive-tab" onclick="setTab('users', this)">Users</button>
        </div>

        <div class="table-container" style="margin-top:16px">
            <table class="table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Record</th>
                        <th>Archived By</th>
                        <th>Archived Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="archivesTableBody">
                    <tr><td colspan="5" class="text-center">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const USER_CAN_RESTORE = (USER_ROLE === 'super_admin' || USER_ROLE === 'owner');

let archives = [];
let activeTab = 'all';

const entityConfig = {
    tasks:         { color: '#3B82F6', icon: 'fa-tasks',           label: 'Task' },
    projects:      { color: '#F97316', icon: 'fa-solar-panel',     label: 'Project' },
    customers:     { color: '#10B981', icon: 'fa-users',           label: 'Customer' },
    quotations:    { color: '#8B5CF6', icon: 'fa-file-invoice-dollar', label: 'Quotation' },
    installations: { color: '#14B8A6', icon: 'fa-tools',           label: 'Installation' },
    inventory:     { color: '#6B7280', icon: 'fa-boxes',           label: 'Inventory' },
    reports:       { color: '#6366F1', icon: 'fa-chart-bar',       label: 'Report' },
    users:         { color: '#EF4444', icon: 'fa-user-shield',     label: 'User' },
};

async function loadArchives() {
    try {
        const res = await fetch('../api/archives-api.php');
        const data = await res.json();
        if (!data.success) {
            const msg = data.error || 'Failed to load archives';
            document.getElementById('archivesTableBody').innerHTML =
                `<tr><td colspan="5" class="text-center" style="color:var(--danger)">${msg}.<br><small style="color:var(--text-muted)">Please ensure the <strong>archives</strong> table exists in Supabase.</small></td></tr>`;
            return;
        }
        archives = data.data || [];
        updateStats();
        renderArchives();
    } catch (e) {
        document.getElementById('archivesTableBody').innerHTML =
            '<tr><td colspan="5" class="text-center" style="color:var(--danger)">Error loading archives. Please create the archives table in Supabase first.</td></tr>';
    }
}

function updateStats() {
    document.getElementById('totalCount').textContent = archives.length;
    document.getElementById('projectsCount').textContent = archives.filter(a => a.entity_type === 'projects').length;
    document.getElementById('tasksCount').textContent = archives.filter(a => a.entity_type === 'tasks').length;
    document.getElementById('customersCount').textContent = archives.filter(a => a.entity_type === 'customers').length;
}

function setTab(tab, el) {
    activeTab = tab;
    document.querySelectorAll('.archive-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    renderArchives();
}

function renderArchives() {
    const search = (document.getElementById('searchInput')?.value || '').toLowerCase();
    let filtered = archives.filter(a => {
        if (activeTab !== 'all' && a.entity_type !== activeTab) return false;
        if (search && !(a.entity_code || '').toLowerCase().includes(search) &&
            !(a.archived_by_name || '').toLowerCase().includes(search)) return false;
        return true;
    });

    const tbody = document.getElementById('archivesTableBody');
    if (!tbody) return;

    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">No archived records found</td></tr>';
        return;
    }

    tbody.innerHTML = filtered.map(a => {
        const cfg = entityConfig[a.entity_type] || { color: '#6B7280', icon: 'fa-archive', label: a.entity_type };
        const dot = `<div class="entity-dot" style="background:${cfg.color}"><i class="fas ${cfg.icon}" style="color:white;font-size:0.7rem"></i></div>`;
        const archivedAt = a.archived_at ? new Date(a.archived_at).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' }) : '-';

        const restoreBtn = USER_CAN_RESTORE
            ? `<button onclick="restoreRecord(${a.id}, '${escapeJs(a.entity_code)}')" class="btn-icon" title="Restore"><i class="fas fa-undo" style="color:#10B981"></i></button>`
            : '';

        return `<tr>
            <td>
                <div style="display:flex;align-items:center;gap:8px">
                    ${dot}
                    <span style="font-size:0.8rem;color:var(--text-muted)">${cfg.label}</span>
                </div>
            </td>
            <td>
                <div style="font-weight:600">${escapeHtml(a.entity_code || '-')}</div>
                <div style="font-size:0.75rem;color:var(--text-muted)">ID: ${a.entity_id}</div>
            </td>
            <td>${escapeHtml(a.archived_by_name || '-')}</td>
            <td style="font-size:0.85rem;color:var(--text-muted)">${archivedAt}</td>
            <td>
                <div style="display:flex;align-items:center;gap:6px">
                    <button onclick="viewArchive(${a.id})" class="btn-icon" title="View Details">
                        <i class="fas fa-eye" style="color:#3B82F6"></i>
                    </button>
                    ${restoreBtn}
                </div>
            </td>
        </tr>`;
    }).join('');
}

function viewArchive(id) {
    const archive = archives.find(a => a.id === id);
    if (!archive) return;
    let recordData = {};
    try { recordData = JSON.parse(archive.record_data || '{}'); } catch(e) {}

    const rows = Object.entries(recordData)
        .filter(([k, v]) => v !== null && v !== '' && String(v).length < 200)
        .map(([k, v]) => ({
            label: k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()),
            value: String(v)
        }));

    if (typeof showDetailModal === 'function') {
        showDetailModal(archive.entity_code || 'Archive Detail', rows);
    } else {
        alert(JSON.stringify(recordData, null, 2));
    }
}

async function restoreRecord(id, code) {
    if (typeof showConfirmModal === 'function') {
        showConfirmModal(
            `Restore "${code}" back to its original module?`,
            async () => {
                try {
                    const res = await fetch(`../api/archives-api.php?id=${id}`, { method: 'DELETE' });
                    const data = await res.json();
                    if (data.success) {
                        showToast(data.message || 'Record restored successfully', 'success');
                        loadArchives();
                    } else {
                        showToast(data.error || 'Failed to restore record', 'error');
                    }
                } catch(e) {
                    showToast('Error restoring record', 'error');
                }
            },
            { title: 'Restore Record', confirmText: 'Restore' }
        );
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}

function escapeJs(text) {
    return (text || '').replace(/'/g, "\\'").replace(/"/g, '\\"');
}

document.getElementById('searchInput')?.addEventListener('input', renderArchives);

loadArchives();
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
