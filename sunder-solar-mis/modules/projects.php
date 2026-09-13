<?php
// modules/projects.php
// Projects management page

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

checkAuthentication();

$pageTitle = 'Projects';
$pageSubtitle = 'Track and manage all projects';

// The owner is always the project manager at this company — the field is
// pre-filled and readonly rather than asking anyone to type it in.
$ownerFullName = getOwnerFullName($supabase);

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
                    <label class="form-label">Client *</label>
                    <select id="customerId" class="form-select" required onchange="onProjectCustomerChange()">
                        <option value="">Select Client</option>
                    </select>
                </div>

                <!-- Upgrade of an existing project — recommends compatible items
                     (matched against what the customer already has) instead of
                     sizing a whole new system from scratch. -->
                <div class="form-group" id="upgradeToggleGroup" style="display:none">
                    <label class="checkbox-wrap" style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" id="isUpgrade" onchange="onUpgradeToggle()">
                        <span>This is an upgrade to an existing project for this client</span>
                    </label>
                </div>
                <div class="form-group" id="upgradeProjectGroup" style="display:none">
                    <label class="form-label">Upgrade of Project *</label>
                    <select id="upgradeOfProjectId" class="form-select" onchange="loadUpgradeRecommendations()">
                        <option value="">Select Past Project</option>
                    </select>
                </div>
                <div id="upgradeRecommendations" style="display:none;margin-bottom:16px;padding:14px;border:1px solid #F97316;border-radius:8px;background:#FFF7ED">
                    <p style="font-weight:600;margin-bottom:8px;font-size:0.85rem">Compatible items to add</p>
                    <div id="upgradeRecommendationsList" style="font-size:0.82rem;color:#64748b">Select a past project above to see what's compatible with it.</div>
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
                        <input type="number" id="progress" class="form-control" min="0" max="100" value="0" readonly title="Auto-calculated from this project's task progress">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Project Manager</label>
                        <input type="text" id="manager" class="form-control" value="<?php echo escape($ownerFullName); ?>" readonly title="Always the owner at this company">
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
                select.innerHTML = '<option value="">Select Client</option>' + 
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
                        ${(p.status === 'completed' && (USER_ROLE === 'super_admin' || USER_ROLE === 'owner')) ? `<button onclick="archiveProject(${p.id})" class="btn btn-secondary btn-sm flex-1"><i class="fas fa-archive"></i> Archive</button>` : ''}
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

let upgradeRecommendationGroups = [];

function openProjectModal(project = null) {
    const modal = document.getElementById('projectModal');
    const title = document.getElementById('modalTitle');

    // The upgrade flow only makes sense when creating a brand-new project —
    // hidden and reset every time the modal opens either way.
    document.getElementById('isUpgrade').checked = false;
    document.getElementById('upgradeToggleGroup').style.display = 'none';
    document.getElementById('upgradeProjectGroup').style.display = 'none';
    document.getElementById('upgradeRecommendations').style.display = 'none';
    document.getElementById('upgradeOfProjectId').innerHTML = '<option value="">Select Past Project</option>';
    upgradeRecommendationGroups = [];

    if (project) {
        title.textContent = 'Edit Project';
        document.getElementById('projectId').value = project.id;
        document.getElementById('projectName').value = project.project_name;
        document.getElementById('customerId').value = project.customer_id;
        document.getElementById('description').value = project.description || '';
        document.getElementById('budget').value = project.estimated_cost;
        document.getElementById('status').value = project.status;
        document.getElementById('progress').value = project.progress;
        // manager field is always the owner (readonly, pre-filled server-side) —
        // never overwritten from a project's possibly-stale stored value.
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

// Only offer the upgrade option once a customer with at least one existing
// project is picked — nothing to upgrade otherwise. Only relevant when
// adding a new project (projectId is empty); editing hides it entirely.
function onProjectCustomerChange() {
    const isEditing = !!document.getElementById('projectId').value;
    const customerId = parseInt(document.getElementById('customerId').value);
    const pastProjects = isEditing ? [] : projects.filter(p => p.customer_id === customerId);

    const toggleGroup = document.getElementById('upgradeToggleGroup');
    if (pastProjects.length > 0) {
        toggleGroup.style.display = '';
        document.getElementById('upgradeOfProjectId').innerHTML = '<option value="">Select Past Project</option>' +
            pastProjects.map(p => `<option value="${p.id}">${escapeHtml(p.project_name)} (${escapeHtml(p.project_code)})</option>`).join('');
    } else {
        toggleGroup.style.display = 'none';
        document.getElementById('isUpgrade').checked = false;
        onUpgradeToggle();
    }
}

function onUpgradeToggle() {
    const isUpgrade = document.getElementById('isUpgrade').checked;
    document.getElementById('upgradeProjectGroup').style.display = isUpgrade ? '' : 'none';
    document.getElementById('upgradeRecommendations').style.display = isUpgrade ? 'block' : 'none';
    if (!isUpgrade) {
        upgradeRecommendationGroups = [];
        document.getElementById('upgradeRecommendationsList').innerHTML = "Select a past project above to see what's compatible with it.";
    }
}

async function loadUpgradeRecommendations() {
    const pastProjectId = document.getElementById('upgradeOfProjectId').value;
    const listEl = document.getElementById('upgradeRecommendationsList');
    if (!pastProjectId) { listEl.innerHTML = "Select a past project above to see what's compatible with it."; return; }

    listEl.innerHTML = 'Loading compatible items…';
    try {
        const res = await fetch(`../api/project-upgrade-recommendations.php?project_id=${pastProjectId}`);
        const result = await res.json();
        if (!result.success) { listEl.innerHTML = escapeHtml(result.error || 'Could not load recommendations.'); return; }

        upgradeRecommendationGroups = result.data || [];
        if (upgradeRecommendationGroups.length === 0) {
            listEl.innerHTML = escapeHtml(result.message || 'No compatible items found for that project.');
            return;
        }

        listEl.innerHTML = upgradeRecommendationGroups.map((group, gi) => `
            <div style="margin-bottom:10px">
                <div style="font-size:0.78rem;color:#94a3b8;margin-bottom:4px">They currently have: ${escapeHtml(group.existing_item)} &times; ${group.existing_quantity}</div>
                ${group.compatible.map((c, ci) => `
                    <label style="display:flex;align-items:center;gap:8px;padding:4px 0;cursor:pointer">
                        <input type="checkbox" class="upgrade-item-check" data-group="${gi}" data-candidate="${ci}">
                        <span style="flex:1">${escapeHtml(c.item_name)}${c.is_exact_match ? ' <span style="color:#16a34a;font-size:0.72rem">(exact match)</span>' : ''} — ${formatCurrency(c.unit_price)}</span>
                        <input type="number" class="form-control upgrade-item-qty" data-group="${gi}" data-candidate="${ci}" value="1" min="1" style="width:60px;padding:4px" disabled>
                    </label>
                `).join('')}
            </div>
        `).join('');

        document.querySelectorAll('.upgrade-item-check').forEach(cb => {
            cb.addEventListener('change', function() {
                const qtyInput = document.querySelector(`.upgrade-item-qty[data-group="${this.dataset.group}"][data-candidate="${this.dataset.candidate}"]`);
                if (qtyInput) qtyInput.disabled = !this.checked;
            });
        });
    } catch (e) {
        listEl.innerHTML = 'Could not load recommendations.';
    }
}

function collectSelectedUpgradeItems() {
    const items = [];
    document.querySelectorAll('.upgrade-item-check:checked').forEach(cb => {
        const group = upgradeRecommendationGroups[cb.dataset.group];
        const candidate = group?.compatible?.[cb.dataset.candidate];
        if (!candidate) return;
        const qtyInput = document.querySelector(`.upgrade-item-qty[data-group="${cb.dataset.group}"][data-candidate="${cb.dataset.candidate}"]`);
        items.push({ inventory_id: candidate.id, quantity: parseFloat(qtyInput?.value) || 1 });
    });
    return items;
}

function closeProjectModal() {
    const modal = document.getElementById('projectModal');
    modal.classList.remove('active');
}

async function saveProject() {
    const id = document.getElementById('projectId').value;
    const isUpgrade = !id && document.getElementById('isUpgrade').checked;
    const upgradeOfProjectId = document.getElementById('upgradeOfProjectId').value;

    if (isUpgrade && !upgradeOfProjectId) {
        showToast('Select which past project this upgrades', 'error');
        return;
    }
    const upgradeItems = isUpgrade ? collectSelectedUpgradeItems() : [];
    if (isUpgrade && upgradeItems.length === 0) {
        showToast('Select at least one item to add for this upgrade', 'error');
        return;
    }

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
    if (isUpgrade) {
        data.upgrade_of_project_id = parseInt(upgradeOfProjectId);
    }

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

        if (!result.success) {
            showToast(result.error, 'error');
            return;
        }

        // Upgrade mode also needs a draft quotation for the chosen items —
        // same approve-to-deduct-inventory pipeline as any other quotation.
        if (isUpgrade) {
            const newProjectId = result.data?.id ?? (Array.isArray(result.data) ? result.data[0]?.id : null);
            try {
                const qRes = await fetch('../api/create-upgrade-quotation.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ project_id: newProjectId, items: upgradeItems })
                });
                const qResult = await qRes.json();
                showToast(qResult.success
                    ? `Project created — upgrade quotation ${qResult.quotation_number} is ready in Quotations.`
                    : `Project created, but the upgrade quotation failed: ${qResult.error}`,
                    qResult.success ? 'success' : 'error');
            } catch (e) {
                showToast('Project created, but the upgrade quotation could not be generated.', 'error');
            }
        } else {
            showToast(result.message, 'success');
        }

        closeProjectModal();
        loadProjects();
    } catch (error) {
        showToast('Error saving project', 'error');
    }
}

async function viewProject(id) {
    const base = projects.find(p => p.id === id);
    if (!base) return;

    // The list payload only has customer_name — fetch the single-project
    // endpoint for the full client record, the approved quotation's line
    // items, and the selected package, so this view is a complete picture
    // of the project (not just the board-card summary).
    let project = base;
    try {
        const response = await fetch(`../api/projects-api.php?id=${id}`);
        const result = await response.json();
        if (result.success && result.data) project = result.data;
    } catch (error) {
        console.error('Error loading project detail:', error);
    }

    const customer = project.customer || null;
    const rows = [
        { section: 'Client Info' },
        { label: 'Client',         value: project.customer_name },
        { label: 'Contact Person', value: customer?.contact_person },
        { label: 'Phone',          value: customer?.phone },
        { label: 'Email',          value: customer?.email },
        { label: 'Address',        value: [customer?.address, customer?.city, customer?.state].filter(Boolean).join(', ') },

        { section: 'Project Overview' },
        { label: 'Project Code', value: project.project_code },
        { label: 'Status',       value: project.status?.replace(/_/g, ' ') },
        { label: 'Progress',     value: project.progress + '%' },
        { label: 'Manager',      value: project.manager },
        { label: 'Selected Package', value: project.selected_package },
        { label: 'Quotation',    value: project.quotation_number },

        { section: 'Timeline & Budget' },
        { label: 'Start Date',       value: formatDate(project.start_date) },
        { label: 'Expected Finish',  value: formatDate(project.expected_end_date) },
        { label: 'Budget',           value: formatCurrency(project.estimated_cost) },
    ];

    const items = project.quotation_items || [];
    if (items.length > 0) {
        rows.push({ section: 'Items Used In This Project' });
        items.forEach(item => {
            rows.push({
                label: item.description,
                value: `${item.quantity} × ${formatCurrency(item.unit_price)} = ${formatCurrency(item.amount)}`
            });
        });
    }

    showDetailModal(project.project_name, rows);
}

async function editProject(id) {
    const project = projects.find(p => p.id === id);
    if (project) openProjectModal(project);
}

// Entry point for the "Upgrade" button on an existing project's card —
// jumps straight into the same upgrade flow the "Add New Project" modal
// already has (compatible-item recommendations + upgrade quotation), just
// pre-filled for THIS project instead of requiring the customer to be
// picked and the past project found manually.
function openUpgradeModal(projectId) {
    const project = projects.find(p => p.id === projectId);
    if (!project) return;

    openProjectModal(); // blank "Add New Project" state, upgrade UI reset
    document.getElementById('modalTitle').textContent = `Upgrade — ${project.project_name}`;
    document.getElementById('projectName').value = `${project.project_name} - Upgrade`;
    document.getElementById('customerId').value = project.customer_id;

    onProjectCustomerChange(); // populates the "Upgrade of Project" list for this client, shows the toggle
    document.getElementById('isUpgrade').checked = true;
    onUpgradeToggle();
    document.getElementById('upgradeOfProjectId').value = projectId;
    loadUpgradeRecommendations();
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
Promise.all([loadCustomers(), loadProjects()]).then(() => {
    // Arriving from the "Upgrade" button on an Installation's card
    // (?upgrade_project=ID) — jump straight into the upgrade flow for that
    // project once its data (and the client dropdown) is actually loaded.
    const upgradeProjectId = parseInt(new URLSearchParams(window.location.search).get('upgrade_project'));
    if (upgradeProjectId) {
        openUpgradeModal(upgradeProjectId);
        // Clean the URL so refreshing/reopening doesn't re-trigger this.
        window.history.replaceState({}, '', window.location.pathname);
    }
});

// Progress is driven by task completion elsewhere in the app (see
// updateProjectProgressFromTasks() server-side) — poll so a project's
// progress bar here stays current even if it was updated from the Tasks
// module in another tab, instead of only refreshing on a manual reload.
setInterval(loadProjects, 15000);
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>