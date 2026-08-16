<?php
// modules/projects.php
// Projects management page

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

checkAuthentication();

$pageTitle = 'Projects';
$pageSubtitle = 'Track and manage all projects';

include_once __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-folder-open"></i>
        </div>
        <div class="stat-value" id="totalProjects">0</div>
        <div class="stat-label">Total Projects</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-value" id="inProgress">0</div>
        <div class="stat-label">In Progress</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-value" id="completed">0</div>
        <div class="stat-label">Completed</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-value" id="planning">0</div>
        <div class="stat-label">Planning</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">All Projects</h3>
        <?php if (checkPermission('projects', 'create')): ?>
        <button onclick="openProjectModal()" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Project
        </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="filters-bar">
            <div class="filter-group">
                <label class="filter-label">Status</label>
                <select id="statusFilter" class="filter-select">
                    <option value="all">All Status</option>
                    <option value="planning">Planning</option>
                    <option value="in_progress">In Progress</option>
                    <option value="installation">Installation</option>
                    <option value="on_hold">On Hold</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
            <div class="filter-group flex-1">
                <label class="filter-label">Search</label>
                <input type="text" id="searchInput" placeholder="Search projects..." class="form-control">
            </div>
        </div>
        
        <div id="projectsGrid" class="projects-grid"></div>
    </div>
</div>

<!-- Project Modal -->
<div id="projectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle" class="modal-title">Add New Project</h3>
            <button class="modal-close" onclick="closeProjectModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="projectForm">
                <input type="hidden" id="projectId">
                <div class="form-group">
                    <label class="form-label">Project Name *</label>
                    <input type="text" id="projectName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Customer *</label>
                    <select id="customerId" class="form-select" required>
                        <option value="">Select Customer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea id="description" class="form-textarea"></textarea>
                </div>
                <div class="grid-cols-2">
                    <div class="form-group">
                        <label class="form-label">Budget</label>
                        <input type="number" id="budget" class="form-control" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select id="status" class="form-select">
                            <option value="planning">Planning</option>
                            <option value="in_progress">In Progress</option>
                            <option value="installation">Installation</option>
                            <option value="on_hold">On Hold</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>
                <div class="grid-cols-2">
                    <div class="form-group">
                        <label class="form-label">Progress (%)</label>
                        <input type="number" id="progress" class="form-control" min="0" max="100" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Project Manager</label>
                        <input type="text" id="manager" class="form-control">
                    </div>
                </div>
                <div class="grid-cols-2">
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" id="startDate" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expected End Date</label>
                        <input type="date" id="expectedEndDate" class="form-control">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeProjectModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveProject()">Save Project</button>
        </div>
    </div>
</div>

<script>
let projects = [];
let customers = [];

async function loadCustomers() {
    try {
        const response = await fetch('../api/customers-api.php');
        const result = await response.json();
        if (result.success) {
            customers = result.data;
            const select = document.getElementById('customerId');
            if (select) {
                select.innerHTML = '<option value="">Select Customer</option>' + 
                    customers.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
            }
        }
    } catch (error) {
        console.error('Error loading customers:', error);
    }
}

async function loadProjects() {
    try {
        const response = await fetch('../api/projects-api.php');
        const result = await response.json();
        if (result.success) {
            projects = result.data;
            renderProjects();
            updateStats();
        }
    } catch (error) {
        console.error('Error loading projects:', error);
    }
}

function updateStats() {
    const total = projects.length;
    const inProgress = projects.filter(p => p.status === 'in_progress').length;
    const completed = projects.filter(p => p.status === 'completed').length;
    const planning = projects.filter(p => p.status === 'planning').length;
    
    document.getElementById('totalProjects').textContent = total;
    document.getElementById('inProgress').textContent = inProgress;
    document.getElementById('completed').textContent = completed;
    document.getElementById('planning').textContent = planning;
}

function renderProjects() {
    const search = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const status = document.getElementById('statusFilter')?.value || 'all';
    
    let filtered = projects.filter(p => {
        if (search && !p.project_name.toLowerCase().includes(search) && 
            !p.project_code.toLowerCase().includes(search)) return false;
        if (status !== 'all' && p.status !== status) return false;
        return true;
    });
    
    const grid = document.getElementById('projectsGrid');
    if (!grid) return;
    
    if (filtered.length === 0) {
        grid.innerHTML = '<div class="text-center py-8 text-gray-500">No projects found</div>';
        return;
    }
    
    grid.innerHTML = filtered.map(p => `
        <div class="project-card">
            <div class="card">
                <div class="card-body">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-sm text-gray-500">${escapeHtml(p.project_code)}</span>
                                ${getStatusBadgeHtml(p.status)}
                            </div>
                            <h4 class="font-semibold text-lg">${escapeHtml(p.project_name)}</h4>
                            <p class="text-sm text-gray-600 mt-1">${escapeHtml(p.customer_name || 'Unknown')}</p>
                        </div>
                    </div>
                    <div class="space-y-2 mt-3">
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <i class="fas fa-calendar"></i>
                            <span>${formatDate(p.start_date)} → ${formatDate(p.expected_end_date)}</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <i class="fas fa-dollar-sign"></i>
                            <span>Budget: ${formatCurrency(p.estimated_cost)}</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <i class="fas fa-user"></i>
                            <span>Manager: ${escapeHtml(p.manager || 'Unassigned')}</span>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="flex justify-between text-sm mb-1">
                            <span>Progress</span>
                            <span>${p.progress}%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" style="width: ${p.progress}%"></div>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t flex gap-2">
                        <button onclick="viewProject(${p.id})" class="btn btn-secondary btn-sm flex-1">
                            <i class="fas fa-eye"></i> View
                        </button>
                        ${(USER_ROLE === 'super_admin' || USER_ROLE === 'owner') ? `<button onclick="editProject(${p.id})" class="btn btn-primary btn-sm flex-1"><i class="fas fa-edit"></i> Edit</button>` : ''}
                        ${(USER_ROLE === 'super_admin' || USER_ROLE === 'owner') ? `<button onclick="archiveProject(${p.id})" class="btn btn-secondary btn-sm flex-1"><i class="fas fa-archive"></i> Archive</button>` : ''}
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function getStatusBadgeHtml(status) {
    const badges = {
        planning: 'badge-secondary',
        in_progress: 'badge-info',
        installation: 'badge-warning',
        on_hold: 'badge-danger',
        completed: 'badge-success'
    };
    const labels = {
        planning: 'Planning',
        in_progress: 'In Progress',
        installation: 'Installation',
        on_hold: 'On Hold',
        completed: 'Completed'
    };
    return `<span class="badge ${badges[status]}">${labels[status]}</span>`;
}

function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatCurrency(amount) {
    return '₱' + Number(amount).toLocaleString();
}

function openProjectModal(project = null) {
    const modal = document.getElementById('projectModal');
    const title = document.getElementById('modalTitle');
    
    if (project) {
        title.textContent = 'Edit Project';
        document.getElementById('projectId').value = project.id;
        document.getElementById('projectName').value = project.project_name;
        document.getElementById('customerId').value = project.customer_id;
        document.getElementById('description').value = project.description || '';
        document.getElementById('budget').value = project.estimated_cost;
        document.getElementById('status').value = project.status;
        document.getElementById('progress').value = project.progress;
        document.getElementById('manager').value = project.manager || '';
        document.getElementById('startDate').value = project.start_date;
        document.getElementById('expectedEndDate').value = project.expected_end_date;
    } else {
        title.textContent = 'Add New Project';
        document.getElementById('projectForm').reset();
        document.getElementById('projectId').value = '';
        document.getElementById('progress').value = 0;
        document.getElementById('status').value = 'planning';
    }
    
    modal.classList.add('active');
}

function closeProjectModal() {
    const modal = document.getElementById('projectModal');
    modal.classList.remove('active');
}

async function saveProject() {
    const id = document.getElementById('projectId').value;
    const data = {
        project_name: document.getElementById('projectName').value,
        customer_id: parseInt(document.getElementById('customerId').value),
        description: document.getElementById('description').value,
        estimated_cost: parseFloat(document.getElementById('budget').value) || 0,
        status: document.getElementById('status').value,
        progress: parseInt(document.getElementById('progress').value) || 0,
        manager: document.getElementById('manager').value,
        start_date: document.getElementById('startDate').value,
        expected_end_date: document.getElementById('expectedEndDate').value
    };
    
    if (!data.project_name || !data.customer_id) {
        showToast('Please fill all required fields', 'error');
        return;
    }
    
    const url = id ? `../api/projects-api.php?id=${id}` : '../api/projects-api.php';
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
            closeProjectModal();
            loadProjects();
        } else {
            showToast(result.error, 'error');
        }
    } catch (error) {
        showToast('Error saving project', 'error');
    }
}

async function viewProject(id) {
    const project = projects.find(p => p.id === id);
    if (project) {
        showDetailModal(project.project_name, [
            { label: 'Project Code', value: project.project_code },
            { label: 'Customer',     value: project.customer_name },
            { label: 'Status',       value: project.status?.replace(/_/g, ' ') },
            { label: 'Progress',     value: project.progress + '%' },
            { label: 'Budget',       value: formatCurrency(project.estimated_cost) },
            { label: 'Manager',      value: project.manager },
            { label: 'Start Date',   value: formatDate(project.start_date) },
            { label: 'End Date',     value: formatDate(project.end_date) },
        ]);
    }
}

async function editProject(id) {
    const project = projects.find(p => p.id === id);
    if (project) openProjectModal(project);
}

async function archiveProject(id) {
    const project = projects.find(p => p.id === id);
    const name = project?.project_name || 'this project';
    showConfirmModal(
        `"${name}" will be archived and hidden from the projects list.`,
        async () => {
        try {
            const response = await fetch(`../api/projects-api.php?id=${id}`, {
                method: 'DELETE'
            });
            const result = await response.json();

            if (result.success) {
                showToast(result.message, 'success');
                loadProjects();
            } else {
                showToast(result.error, 'error');
            }
        } catch (error) {
            showToast('Error archiving project', 'error');
        }
        },
        { title: 'Archive Project', confirmText: 'Archive', danger: false }
    );
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Event listeners
document.getElementById('searchInput')?.addEventListener('input', renderProjects);
document.getElementById('statusFilter')?.addEventListener('change', renderProjects);

// Initialize
loadCustomers();
loadProjects();
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>