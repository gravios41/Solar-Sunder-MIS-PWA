<?php
// modules/installations.php
// Installations management page

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

checkAuthentication();

$pageTitle = 'Installations';
$pageSubtitle = 'Schedule and track installations';

include_once __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-tools"></i>
        </div>
        <div class="stat-value" id="totalInstallations">0</div>
        <div class="stat-label">Total Installations</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-calendar"></i>
        </div>
        <div class="stat-value" id="scheduledCount">0</div>
        <div class="stat-label">Scheduled</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-spinner"></i>
        </div>
        <div class="stat-value" id="inProgressCount">0</div>
        <div class="stat-label">In Progress</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-value" id="completedCount">0</div>
        <div class="stat-label">Completed</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Installations</h3>
        <?php if (checkPermission('installations', 'create')): ?>
        <button onclick="openInstallationModal()" class="btn btn-primary">
            <i class="fas fa-plus"></i> Schedule Installation
        </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="filters-bar">
            <div class="filter-group">
                <label class="filter-label">Status</label>
                <select id="statusFilter" class="filter-select">
                    <option value="all">All Status</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Team</label>
                <select id="teamFilter" class="filter-select">
                    <option value="all">All Teams</option>
                    <option value="Team A">Team A</option>
                    <option value="Team B">Team B</option>
                    <option value="Team C">Team C</option>
                </select>
            </div>
            <div class="filter-group flex-1">
                <label class="filter-label">Search</label>
                <input type="text" id="searchInput" placeholder="Search installations..." class="form-control">
            </div>
        </div>
        
        <div id="installationsGrid" class="projects-grid"></div>
    </div>
</div>

<!-- Installation Modal -->
<div id="installationModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle" class="modal-title">Schedule Installation</h3>
            <button class="modal-close" onclick="closeInstallationModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="installationForm">
                <input type="hidden" id="installationId">
                <div class="form-group">
                    <label class="form-label">Project *</label>
                    <select id="projectId" class="form-select" required onchange="loadCustomerFromProject()">
                        <option value="">Select Project</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Customer</label>
                    <input type="text" id="customerName" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input type="text" id="location" class="form-control" placeholder="Installation address">
                </div>
                <div class="grid-cols-2">
                    <div class="form-group">
                        <label class="form-label">Installation Date *</label>
                        <input type="date" id="installationDate" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Team</label>
                        <select id="team" class="form-select">
                            <option value="Team A">Team A</option>
                            <option value="Team B">Team B</option>
                            <option value="Team C">Team C</option>
                        </select>
                    </div>
                </div>
                <div class="grid-cols-2">
                    <div class="form-group">
                        <label class="form-label">Technician</label>
                        <input type="text" id="technician" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select id="status" class="form-select">
                            <option value="scheduled">Scheduled</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea id="notes" class="form-textarea"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeInstallationModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveInstallation()">Save Installation</button>
        </div>
    </div>
</div>

<script>
let installations = [];
let projects = [];
let customers = [];

async function loadProjects() {
    try {
        const response = await fetch('../api/projects-api.php');
        const result = await response.json();
        if (result.success) {
            projects = result.data;
            const select = document.getElementById('projectId');
            if (select) {
                select.innerHTML = '<option value="">Select Project</option>' + 
                    projects.map(p => `<option value="${p.id}" data-customer-id="${p.customer_id}">${escapeHtml(p.project_name)}</option>`).join('');
            }
        }
    } catch (error) {
        console.error('Error loading projects:', error);
    }
}

async function loadCustomers() {
    try {
        const response = await fetch('../api/customers-api.php');
        const result = await response.json();
        if (result.success) {
            customers = result.data;
        }
    } catch (error) {
        console.error('Error loading customers:', error);
    }
}

function loadCustomerFromProject() {
    const projectSelect = document.getElementById('projectId');
    const selectedOption = projectSelect.options[projectSelect.selectedIndex];
    const customerId = selectedOption?.getAttribute('data-customer-id');
    const customer = customers.find(c => c.id == customerId);
    document.getElementById('customerName').value = customer ? customer.name : '';
    if (customer?.address) {
        document.getElementById('location').value = customer.address;
    }
}

async function loadInstallations() {
    try {
        const response = await fetch('../api/installations-api.php');
        const result = await response.json();
        if (result.success) {
            installations = result.data;
            renderInstallations();
            updateStats();
        }
    } catch (error) {
        console.error('Error loading installations:', error);
    }
}

function updateStats() {
    const total = installations.length;
    const scheduled = installations.filter(i => i.status === 'scheduled').length;
    const inProgress = installations.filter(i => i.status === 'in_progress').length;
    const completed = installations.filter(i => i.status === 'completed').length;
    
    document.getElementById('totalInstallations').textContent = total;
    document.getElementById('scheduledCount').textContent = scheduled;
    document.getElementById('inProgressCount').textContent = inProgress;
    document.getElementById('completedCount').textContent = completed;
}

function renderInstallations() {
    const search = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const status = document.getElementById('statusFilter')?.value || 'all';
    const team = document.getElementById('teamFilter')?.value || 'all';
    
    let filtered = installations.filter(i => {
        const project = projects.find(p => p.id === i.project_id);
        if (search && !(project?.project_name || '').toLowerCase().includes(search) && 
            !i.installation_code.toLowerCase().includes(search)) return false;
        if (status !== 'all' && i.status !== status) return false;
        if (team !== 'all' && i.team !== team) return false;
        return true;
    });
    
    const grid = document.getElementById('installationsGrid');
    if (!grid) return;
    
    if (filtered.length === 0) {
        grid.innerHTML = '<div class="text-center py-8 text-gray-500">No installations found</div>';
        return;
    }
    
    grid.innerHTML = filtered.map(i => {
        const project = projects.find(p => p.id === i.project_id);
        const customer = customers.find(c => c.id === i.customer_id);
        return `
            <div class="project-card">
                <div class="card">
                    <div class="card-body">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-sm text-gray-500">${escapeHtml(i.installation_code)}</span>
                                    ${getStatusBadgeHtml(i.status)}
                                </div>
                                <h4 class="font-semibold">${escapeHtml(project?.project_name || 'Unknown Project')}</h4>
                                <p class="text-sm text-gray-600 mt-1">${escapeHtml(customer?.name || 'Unknown Customer')}</p>
                            </div>
                        </div>
                        <div class="space-y-2 mb-3">
                            <div class="flex items-center gap-2 text-sm text-gray-600">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>${escapeHtml(i.location || customer?.address || 'No address')}</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-gray-600">
                                <i class="fas fa-calendar"></i>
                                <span>Scheduled: ${formatDate(i.installation_date)}</span>
                            </div>
                            <div class="grid-cols-2">
                                <div>
                                    <p class="text-xs text-gray-500">Technician</p>
                                    <p class="text-sm font-medium">${escapeHtml(i.technician || 'Unassigned')}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Team</p>
                                    <p class="text-sm font-medium">${escapeHtml(i.team)}</p>
                                </div>
                            </div>
                        </div>
                        ${i.status !== 'scheduled' ? `
                        <div class="mt-3">
                            <div class="flex justify-between text-sm mb-1">
                                <span>Progress</span>
                                <span>${i.progress}%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width: ${i.progress}%"></div>
                            </div>
                            <button onclick="updateProgress(${i.id})" class="btn btn-secondary btn-sm mt-2 w-full">
                                <i class="fas fa-chart-line"></i> Update Progress
                            </button>
                        </div>
                        ` : ''}
                        <div class="mt-4 pt-3 border-t flex gap-2">
                            <button onclick="viewInstallation(${i.id})" class="btn btn-secondary btn-sm flex-1">
                                <i class="fas fa-eye"></i> View
                            </button>
                            ${(USER_ROLE === 'super_admin' || USER_ROLE === 'owner') ? `<button onclick="editInstallation(${i.id})" class="btn btn-primary btn-sm flex-1"><i class="fas fa-edit"></i> Edit</button>` : ''}
                            ${(USER_ROLE === 'super_admin' || USER_ROLE === 'owner') ? `<button onclick="archiveInstallation(${i.id})" class="btn btn-secondary btn-sm flex-1"><i class="fas fa-archive"></i> Archive</button>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function getStatusBadgeHtml(status) {
    const badges = {
        scheduled: 'badge-warning',
        in_progress: 'badge-info',
        completed: 'badge-success'
    };
    const labels = {
        scheduled: 'Scheduled',
        in_progress: 'In Progress',
        completed: 'Completed'
    };
    return `<span class="badge ${badges[status]}">${labels[status]}</span>`;
}

function openInstallationModal(installation = null) {
    if (installation) {
        document.getElementById('modalTitle').textContent = 'Edit Installation';
        document.getElementById('installationId').value = installation.id;
        document.getElementById('projectId').value = installation.project_id;
        loadCustomerFromProject();
        document.getElementById('location').value = installation.location || '';
        document.getElementById('installationDate').value = installation.installation_date;
        document.getElementById('technician').value = installation.technician || '';
        document.getElementById('team').value = installation.team;
        document.getElementById('status').value = installation.status;
        document.getElementById('notes').value = installation.notes || '';
    } else {
        document.getElementById('modalTitle').textContent = 'Schedule Installation';
        document.getElementById('installationForm').reset();
        document.getElementById('installationId').value = '';
        document.getElementById('installationDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('status').value = 'scheduled';
    }
    document.getElementById('installationModal').classList.add('active');
}

function closeInstallationModal() {
    document.getElementById('installationModal').classList.remove('active');
}

async function saveInstallation() {
    const id = document.getElementById('installationId').value;
    const projectId = parseInt(document.getElementById('projectId').value);
    const project = projects.find(p => p.id === projectId);
    const customer = customers.find(c => c.id === project?.customer_id);
    
    if (!projectId) {
        showToast('Please select a project', 'error');
        return;
    }
    
    const data = {
        project_id: projectId,
        customer_id: project?.customer_id,
        location: document.getElementById('location').value || customer?.address || '',
        installation_date: document.getElementById('installationDate').value,
        technician: document.getElementById('technician').value,
        team: document.getElementById('team').value,
        status: document.getElementById('status').value,
        notes: document.getElementById('notes').value,
        progress: document.getElementById('status').value === 'completed' ? 100 : 
                  (document.getElementById('status').value === 'in_progress' ? 50 : 0)
    };
    
    const url = id ? `../api/installations-api.php?id=${id}` : '../api/installations-api.php';
    const method = id ? 'PUT' : 'POST';
    
    try {
        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        
        if (result.success) {
            showToast(result.message, 'success');
            closeInstallationModal();
            loadInstallations();
        } else {
            showToast(result.error, 'error');
        }
    } catch (error) {
        showToast('Error saving installation', 'error');
    }
}

async function updateProgress(id) {
    const inst = installations.find(i => i.id === id);
    const current = inst ? inst.progress : 50;
    showPromptModal(
        'Enter the completion percentage for this installation.',
        current,
        async (val) => {
            if (!isNaN(val)) {
                const progress = Math.min(100, Math.max(0, parseInt(val)));
                const status = progress === 100 ? 'completed' : (progress > 0 ? 'in_progress' : 'scheduled');
                try {
                    const response = await fetch(`../api/installations-api.php?id=${id}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ progress, status })
                    });
                    const result = await response.json();
                    if (result.success) {
                        showToast('Progress updated successfully', 'success');
                        loadInstallations();
                    } else {
                        showToast(result.error || 'Failed to update progress', 'error');
                    }
                } catch (error) {
                    showToast('Error updating progress', 'error');
                }
            }
        },
        { title: 'Update Installation Progress', okText: 'Update', min: 0, max: 100, step: 5 }
    );
}

async function viewInstallation(id) {
    const installation = installations.find(i => i.id === id);
    const project = projects.find(p => p.id === installation?.project_id);
    const customer = customers.find(c => c.id === installation?.customer_id);
    if (installation) {
        showDetailModal(installation.installation_code, [
            { label: 'Project',     value: project?.project_name },
            { label: 'Customer',    value: customer?.name },
            { label: 'Location',    value: installation.location || customer?.address },
            { label: 'Sched. Date', value: formatDate(installation.installation_date) },
            { label: 'Status',      value: installation.status?.replace('_', ' ') },
            { label: 'Technician',  value: installation.technician },
            { label: 'Team',        value: installation.team },
            { label: 'Progress',    value: installation.progress + '%' },
            { label: 'Notes',       value: installation.notes },
        ]);
    }
}

async function editInstallation(id) {
    const installation = installations.find(i => i.id === id);
    if (installation) openInstallationModal(installation);
}

async function archiveInstallation(id) {
    showConfirmModal(
        'This installation record will be archived and hidden from the installations list.',
        async () => {
            try {
                const response = await fetch(`../api/installations-api.php?id=${id}`, {
                    method: 'DELETE'
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    loadInstallations();
                } else {
                    showToast(result.error, 'error');
                }
            } catch (error) {
                showToast('Error archiving installation', 'error');
            }
        },
        { title: 'Archive Installation', confirmText: 'Archive' }
    );
}

// Event listeners
document.getElementById('searchInput')?.addEventListener('input', renderInstallations);
document.getElementById('statusFilter')?.addEventListener('change', renderInstallations);
document.getElementById('teamFilter')?.addEventListener('change', renderInstallations);

// Initialize
loadProjects();
loadCustomers();
loadInstallations();
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>