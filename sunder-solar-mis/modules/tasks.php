<?php
// modules/tasks.php
// Tasks management page

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

checkAuthentication();

$pageTitle = 'Tasks';
$pageSubtitle = 'Manage and track all tasks';

include_once __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-value" id="todoCount">0</div>
        <div class="stat-label">To Do</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-spinner"></i>
        </div>
        <div class="stat-value" id="progressCount">0</div>
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
        <h3 class="card-title">Task Board</h3>
        <?php if (checkPermission('tasks', 'create')): ?>
        <button onclick="openTaskModal()" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Task
        </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="filters-bar">
            <div class="filter-group">
                <label class="filter-label">Priority</label>
                <select id="priorityFilter" class="filter-select">
                    <option value="all">All Priority</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
            <div class="filter-group flex-1">
                <label class="filter-label">Search</label>
                <input type="text" id="searchInput" placeholder="Search tasks..." class="form-control">
            </div>
        </div>
        
        <div class="tasks-board">
            <div class="task-column">
                <h4 class="task-column-header">To Do</h4>
                <div id="todoTasks" class="space-y-3"></div>
            </div>
            <div class="task-column">
                <h4 class="task-column-header">In Progress</h4>
                <div id="progressTasks" class="space-y-3"></div>
            </div>
            <div class="task-column">
                <h4 class="task-column-header">Completed</h4>
                <div id="completedTasks" class="space-y-3"></div>
            </div>
        </div>
    </div>
</div>

<!-- Project Tasks Modal — clicking a project name on any task card pops
     up every task belonging to that project, in one go -->
<div id="projectTasksModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3 id="projectTasksModalTitle" class="modal-title">Project Tasks</h3>
            <button class="modal-close" onclick="closeProjectTasksModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="projectTasksModalSummary" style="margin-bottom:16px"></div>
            <div id="projectTasksModalGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px"></div>
        </div>
    </div>
</div>

<!-- Task Modal -->
<div id="taskModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle" class="modal-title">Add New Task</h3>
            <button class="modal-close" onclick="closeTaskModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="taskForm">
                <input type="hidden" id="taskId">
                <div class="form-group">
                    <label class="form-label">Task Title *</label>
                    <input type="text" id="taskTitle" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea id="taskDescription" class="form-textarea"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Project</label>
                    <select id="taskProjectId" class="form-select">
                        <option value="">Select Project (Optional)</option>
                    </select>
                </div>
                <div class="grid-cols-2">
                    <div class="form-group">
                        <label class="form-label">Assigned To</label>
                        <select id="assignedTo" class="form-select">
                            <option value="">Select User</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select id="priority" class="form-select">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Due Date</label>
                    <input type="date" id="dueDate" class="form-control">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeTaskModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveTask()">Save Task</button>
        </div>
    </div>
</div>

<script>
let tasks = [];
let projects = [];

async function loadProjects() {
    try {
        const response = await fetch('../api/projects-api.php');
        const result = await response.json();
        if (result.success) {
            projects = result.data;
            const select = document.getElementById('taskProjectId');
            if (select) {
                select.innerHTML = '<option value="">Select Project (Optional)</option>' +
                    projects.map(p => `<option value="${p.id}">${escapeHtml(p.project_name)}</option>`).join('');
            }
        }
    } catch (error) {
        console.error('Error loading projects:', error);
    }
}

let assignableUsers = [];

async function loadAssignableUsers() {
    try {
        const response = await fetch('../api/tasks-api.php?assignable_users=1');
        const result = await response.json();
        if (result.success) {
            assignableUsers = result.data;
            const select = document.getElementById('assignedTo');
            if (select) {
                select.innerHTML = '<option value="">Select User</option>' +
                    assignableUsers.map(u => `<option value="${escapeHtml(u.full_name)}">${escapeHtml(u.full_name)} (${escapeHtml(u.role.replace(/_/g, ' '))})</option>`).join('');
            }
        }
    } catch (error) {
        console.error('Error loading assignable users:', error);
    }
}

// Mirrors the backend's canEditTaskChecklist(): an employee can edit a
// checklist unless the task is assigned (by name) to a different real user
// account. Tasks assigned to a name that matches no account (legacy/demo
// data) stay editable by any employee rather than locking everyone out.
function canEditChecklistFor(task) {
    if (USER_ROLE !== 'employee') return false;
    const assignedName = (task.assigned_to || '').trim();
    if (!assignedName) return true;
    const matchesRealUser = assignableUsers.some(u => u.full_name.toLowerCase() === assignedName.toLowerCase());
    if (!matchesRealUser) return true;
    return assignedName.toLowerCase() === (USER_FULL_NAME || '').trim().toLowerCase();
}

async function loadTasks() {
    try {
        const response = await fetch('../api/tasks-api.php');
        const result = await response.json();
        if (result.success) {
            tasks = result.data;
            renderTasks();
            updateStats();
        }
    } catch (error) {
        console.error('Error loading tasks:', error);
    }
}

function updateStats() {
    const todo = tasks.filter(t => t.status === 'pending').length;
    const inProgress = tasks.filter(t => t.status === 'in_progress').length;
    const completed = tasks.filter(t => t.status === 'completed').length;
    
    document.getElementById('todoCount').textContent = todo;
    document.getElementById('progressCount').textContent = inProgress;
    document.getElementById('completedCount').textContent = completed;
}

function renderTaskList(taskList) {
    if (taskList.length === 0) {
        return '<p class="text-center text-gray-500 py-4">No tasks</p>';
    }
    return taskList.map(t => {
            const project = projects.find(p => p.id === t.project_id);
            // Quick Start/Complete bypasses the checklist entirely, so it's
            // limited to the same roles that can edit a task outright — for
            // an employee, task progress is driven only by the checklist,
            // and admin/owner-without-edit view tasks read-only.
            let statusBtn = '';
            if (USER_ROLE === 'super_admin' || USER_ROLE === 'owner') {
                if (t.status === 'pending') {
                    statusBtn = `
                        <div class="flex gap-2 pt-2 border-t">
                            <button onclick="updateTaskStatus(${t.id}, 'in_progress')" class="btn btn-secondary btn-sm flex-1">
                                <i class="fas fa-play"></i> Start
                            </button>
                        </div>`;
                } else if (t.status === 'in_progress') {
                    statusBtn = `
                        <div class="flex gap-2 pt-2 border-t">
                            <button onclick="updateTaskStatus(${t.id}, 'completed')" class="btn btn-success btn-sm flex-1">
                                <i class="fas fa-check"></i> Complete
                            </button>
                        </div>`;
                }
            }
            const editBtn = (USER_ROLE === 'super_admin' || USER_ROLE === 'owner')
                ? `<button onclick="editTask(${t.id})" class="btn-icon" title="Edit"><i class="fas fa-edit" style="color:#F97316"></i></button>`
                : '';
            // Only offer archiving once a task is actually done — keeps
            // anyone from accidentally archiving still-active work, and is
            // the whole point of archiving: clearing out completed items
            // so the active board (and the underlying table) don't just
            // keep growing forever.
            const archiveBtn = (t.status === 'completed' && (USER_ROLE === 'super_admin' || USER_ROLE === 'owner'))
                ? `<button onclick="archiveTask(${t.id})" class="btn-icon" title="Archive"><i class="fas fa-archive" style="color:#6B7280"></i></button>`
                : '';
            return `
                <div class="task-card">
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-xs text-gray-500">#${t.id}</span>
                        ${getPriorityBadgeHtml(t.priority)}
                    </div>
                    <h5 class="font-medium mb-1">${escapeHtml(t.task_title)}</h5>
                    <p class="text-xs mb-2">${project
                        ? `<span onclick="event.stopPropagation();openProjectTasksModal(${project.id})" style="color:#F97316;cursor:pointer;font-weight:600" title="View all tasks for this project">${escapeHtml(project.project_name)}</span>`
                        : `<span class="text-gray-600">No Project</span>`}</p>
                    <p class="text-xs text-gray-500 mb-2">${escapeHtml(t.description || '')}</p>
                    <div class="flex justify-between items-center text-xs mb-3">
                        <span><i class="fas fa-user mr-1"></i>${escapeHtml(t.assigned_to || 'Unassigned')}</span>
                        <span><i class="fas fa-calendar mr-1"></i>Due: ${formatDate(t.due_date)}</span>
                    </div>
                    ${(t.checklist_count || 0) > 0 ? `
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px;font-size:11px;color:#64748b">
                        <div style="flex:1;height:5px;background:#e2e8f0;border-radius:3px;overflow:hidden">
                            <div style="height:100%;width:${t.progress_percent || 0}%;background:${(t.progress_percent || 0) >= 100 ? '#16a34a' : '#3B82F6'};border-radius:3px"></div>
                        </div>
                        <span>${t.checklist_completed || 0}/${t.checklist_count} · ${t.progress_percent || 0}%</span>
                    </div>` : ''}
                    ${statusBtn}
                    <div style="display:flex;align-items:center;gap:6px;margin-top:8px">
                        <button onclick="viewTask(${t.id})" class="btn-icon" title="View"><i class="fas fa-eye" style="color:#3B82F6"></i></button>
                        ${editBtn}
                        ${archiveBtn}
                    </div>
                </div>
            `;
        }).join('');
}

// A project's standard installation tasks (7 of them) would otherwise
// repeat a near-identical card once per task in whichever column they
// share — same project name, same "click to see everything" link. Groups
// same-project tasks within one column into a single summary card that
// opens the project popup; a task that's the only one of its project in
// this column keeps its normal full card (with all its usual actions),
// since there's nothing to collapse.
function renderTaskColumn(taskList) {
    if (taskList.length === 0) {
        return '<p class="text-center text-gray-500 py-4">No tasks</p>';
    }

    const byProjectId = new Map();
    taskList.forEach(t => {
        const key = t.project_id || 'none';
        if (!byProjectId.has(key)) byProjectId.set(key, []);
        byProjectId.get(key).push(t);
    });

    return [...byProjectId.values()].map(group => {
        if (group.length === 1) {
            return renderTaskList(group);
        }
        const project = projects.find(p => p.id === group[0].project_id);
        const title = project ? escapeHtml(project.project_name) : 'No Project';
        const progress = project?.progress ?? 0;
        return `
            <div class="task-card" onclick="openProjectTasksModal(${group[0].project_id})" style="cursor:pointer">
                <h5 class="font-medium mb-1" style="color:#F97316">${title}</h5>
                <p class="text-xs text-gray-500 mb-2">${group.length} tasks in this stage</p>
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px;font-size:11px;color:#64748b">
                    <div style="flex:1;height:5px;background:#e2e8f0;border-radius:3px;overflow:hidden">
                        <div style="height:100%;width:${progress}%;background:${progress >= 100 ? '#16a34a' : '#3B82F6'};border-radius:3px"></div>
                    </div>
                    <span>${progress}% overall</span>
                </div>
                <div style="display:flex;align-items:center;gap:6px;color:#3B82F6;font-size:12px">
                    <i class="fas fa-eye"></i> View all ${group.length}
                </div>
            </div>`;
    }).join('');
}

function renderTasks() {
    const search = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const priority = document.getElementById('priorityFilter')?.value || 'all';

    let filtered = tasks.filter(t => {
        if (search && !t.task_title.toLowerCase().includes(search) &&
            !(t.assigned_to || '').toLowerCase().includes(search)) return false;
        if (priority !== 'all' && t.priority !== priority) return false;
        return true;
    });

    // Grouped by project_id within each column — same-project tasks sit
    // next to each other (a stable sort keeps their existing due-date
    // order within that group). No layout/markup change: still the same
    // three columns, just this ordering within each one.
    const byProject = (a, b) => (a.project_id || 0) - (b.project_id || 0);

    const todo = filtered.filter(t => t.status === 'pending').sort(byProject);
    const inProgress = filtered.filter(t => t.status === 'in_progress').sort(byProject);
    const completed = filtered.filter(t => t.status === 'completed').sort(byProject);

    document.getElementById('todoTasks').innerHTML = renderTaskColumn(todo);
    document.getElementById('progressTasks').innerHTML = renderTaskColumn(inProgress);
    document.getElementById('completedTasks').innerHTML = renderTaskColumn(completed);
}

// Clicking a project name on any task card pops up every task belonging
// to that project (across all three columns) in one go, sorted into the
// actual installation sequence (due date) with an overall-progress summary
// up top — so an employee can immediately see where the project stands
// and what comes next, instead of hunting for the rest of the steps
// scattered across the three status columns.
function openProjectTasksModal(projectId) {
    const project = projects.find(p => p.id === projectId);
    const projectTasks = tasks
        .filter(t => t.project_id === projectId)
        .sort((a, b) => new Date(a.due_date || 0) - new Date(b.due_date || 0));

    const completedCount = projectTasks.filter(t => t.status === 'completed').length;
    const totalCount = projectTasks.length;
    const overallProgress = project?.progress ?? (totalCount ? Math.round((completedCount / totalCount) * 100) : 0);

    document.getElementById('projectTasksModalTitle').textContent = project ? project.project_name : 'Project Tasks';
    document.getElementById('projectTasksModalSummary').innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px;color:#334155;margin-bottom:6px">
            <span>${completedCount} of ${totalCount} steps completed</span>
            <span style="font-weight:700;color:${overallProgress >= 100 ? '#16a34a' : '#F97316'}">${overallProgress}%</span>
        </div>
        <div style="height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden">
            <div style="height:100%;width:${overallProgress}%;background:${overallProgress >= 100 ? '#16a34a' : '#F97316'};border-radius:4px"></div>
        </div>`;
    document.getElementById('projectTasksModalGrid').innerHTML = renderSimpleTaskList(projectTasks);
    document.getElementById('projectTasksModal').classList.add('active');
}

function closeProjectTasksModal() {
    document.getElementById('projectTasksModal').classList.remove('active');
}

// A stripped-down task card for the project popup — since every card here
// already belongs to the same project, repeating the full board card
// (project name, status quick-buttons, View/Edit/Archive all at once)
// is redundant clutter. Just name, description, assigned team, due date,
// progress, and a single button into the real task detail modal (the
// same one "View" on the board opens) for anything further.
function renderSimpleTaskList(taskList) {
    if (taskList.length === 0) {
        return '<p class="text-center text-gray-500 py-4">No tasks</p>';
    }
    return taskList.map(t => `
        <div class="task-card">
            <h5 class="font-medium mb-1">${escapeHtml(t.task_title)}</h5>
            <p class="text-xs text-gray-500 mb-2">${escapeHtml(t.description || '')}</p>
            <div class="flex justify-between items-center text-xs mb-3">
                <span><i class="fas fa-user mr-1"></i>${escapeHtml(t.assigned_to || 'Unassigned')}</span>
                <span><i class="fas fa-calendar mr-1"></i>Due: ${formatDate(t.due_date)}</span>
            </div>
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px;font-size:11px;color:#64748b">
                <div style="flex:1;height:5px;background:#e2e8f0;border-radius:3px;overflow:hidden">
                    <div style="height:100%;width:${t.progress_percent || 0}%;background:${(t.progress_percent || 0) >= 100 ? '#16a34a' : '#3B82F6'};border-radius:3px"></div>
                </div>
                <span>${t.progress_percent || 0}%</span>
            </div>
            <div style="display:flex;gap:6px">
                <button onclick="viewTask(${t.id})" class="btn btn-secondary btn-sm" style="flex:1">
                    <i class="fas fa-eye"></i> View
                </button>
                ${(t.status === 'completed' && (USER_ROLE === 'super_admin' || USER_ROLE === 'owner')) ? `
                <button onclick="archiveTask(${t.id})" class="btn-icon" title="Archive">
                    <i class="fas fa-archive" style="color:#6B7280"></i>
                </button>` : ''}
            </div>
        </div>
    `).join('');
}

function getPriorityBadgeHtml(priority) {
    const badges = {
        low: 'badge-secondary',
        medium: 'badge-info',
        high: 'badge-warning',
        urgent: 'badge-danger'
    };
    const labels = {
        low: 'Low',
        medium: 'Medium',
        high: 'High',
        urgent: 'Urgent'
    };
    return `<span class="badge ${badges[priority]}">${labels[priority]}</span>`;
}

async function updateTaskStatus(id, newStatus) {
    try {
        const response = await fetch(`../api/tasks-api.php?id=${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                status: newStatus,
                completed_date: newStatus === 'completed' ? new Date().toISOString().split('T')[0] : null
            })
        });
        const result = await response.json();
        
        if (result.success) {
            showToast(`Task marked as ${newStatus.replace('_', ' ')}`, 'success');
            loadTasks();
        } else {
            showToast(result.error, 'error');
        }
    } catch (error) {
        showToast('Error updating task', 'error');
    }
}

function viewTask(id) {
    // Compare as strings — protects against any number/string ID mismatch
    // between the tasks array and the onclick-handler argument, which
    // would otherwise fail this lookup silently (no error, modal just
    // never opens).
    const t = tasks.find(t => String(t.id) === String(id));
    if (!t) {
        console.error('viewTask: no task found with id', id, 'in', tasks.map(x => x.id));
        showToast('Could not open this task — please refresh and try again', 'error');
        return;
    }

    try {
        openTaskDetailModal(t);
    } catch (error) {
        console.error('Error opening task detail modal:', error);
        showToast('Error opening task: ' + error.message, 'error');
    }
}

function openTaskModal(task = null) {
    if (task) {
        document.getElementById('modalTitle').textContent = 'Edit Task';
        document.getElementById('taskId').value = task.id;
        document.getElementById('taskTitle').value = task.task_title;
        document.getElementById('taskDescription').value = task.description || '';
        document.getElementById('taskProjectId').value = task.project_id || '';
        document.getElementById('assignedTo').value = task.assigned_to || '';
        document.getElementById('priority').value = task.priority;
        document.getElementById('dueDate').value = task.due_date;
    } else {
        document.getElementById('modalTitle').textContent = 'Add New Task';
        document.getElementById('taskForm').reset();
        document.getElementById('taskId').value = '';
        document.getElementById('priority').value = 'medium';
        const dueDate = new Date();
        dueDate.setDate(dueDate.getDate() + 7);
        document.getElementById('dueDate').value = dueDate.toISOString().split('T')[0];
    }
    document.getElementById('taskModal').classList.add('active');
}

function closeTaskModal() {
    document.getElementById('taskModal').classList.remove('active');
}

async function saveTask() {
    const id = document.getElementById('taskId').value;
    const data = {
        task_title: document.getElementById('taskTitle').value,
        description: document.getElementById('taskDescription').value,
        project_id: document.getElementById('taskProjectId').value ? parseInt(document.getElementById('taskProjectId').value) : null,
        assigned_to: document.getElementById('assignedTo').value,
        priority: document.getElementById('priority').value,
        due_date: document.getElementById('dueDate').value,
        status: 'pending'
    };
    
    if (!data.task_title) {
        showToast('Please enter a task title', 'error');
        return;
    }
    
    const url = id ? `../api/tasks-api.php?id=${id}` : '../api/tasks-api.php';
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
            closeTaskModal();
            loadTasks();
        } else {
            showToast(result.error, 'error');
        }
    } catch (error) {
        showToast('Error saving task', 'error');
    }
}

async function editTask(id) {
    const task = tasks.find(t => t.id === id);
    if (task) openTaskModal(task);
}

async function archiveTask(id) {
    const task = tasks.find(t => t.id === id);
    const name = task?.task_title || 'this task';
    showConfirmModal(
        `"${name}" will be archived and hidden from the task board.`,
        async () => {
            try {
                const response = await fetch(`../api/tasks-api.php?id=${id}`, {
                    method: 'DELETE'
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    await loadTasks();
                    // If this was archived from inside the project popup,
                    // refresh it too — loadTasks()/renderTasks() only
                    // touch the three board columns, not this modal.
                    if (task?.project_id && document.getElementById('projectTasksModal')?.classList.contains('active')) {
                        openProjectTasksModal(task.project_id);
                    }
                } else {
                    showToast(result.error, 'error');
                }
            } catch (error) {
                showToast('Error archiving task', 'error');
            }
        },
        { title: 'Archive Task', confirmText: 'Archive' }
    );
}

// Event listeners
document.getElementById('searchInput')?.addEventListener('input', renderTasks);
document.getElementById('priorityFilter')?.addEventListener('change', renderTasks);

// Task Detail Modal with Checklist
let currentTaskId = null;
let canEditChecklist = false;
let checklistPollInterval = null;

async function openTaskDetailModal(task) {
    currentTaskId = task.id;
    canEditChecklist = canEditChecklistFor(task);
    const project = projects.find(p => p.id === task.project_id);
    
    // Create modal HTML
    const modal = document.createElement('div');
    modal.id = 'taskDetailModal';
    modal.style.cssText = `
        display: block; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5); z-index: 2100; overflow-y: auto; padding: 20px;
    `;
    
    modal.innerHTML = `
        <div style="background: white; border-radius: 8px; max-width: 700px; margin: 40px auto; padding: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
                <div>
                    <h3 style="margin: 0; font-size: 20px; font-weight: 600;">${escapeHtml(task.task_title)}</h3>
                    <p style="margin: 8px 0 0; color: #64748b; font-size: 14px;">${escapeHtml(project?.project_name || 'No Project')}</p>
                </div>
                <button onclick="closeTaskDetailModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            
            <div style="background: #f8fafc; padding: 12px; border-radius: 6px; margin-bottom: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 13px;">
                <div><strong>Status:</strong> ${escapeHtml(task.status?.replace(/_/g, ' '))}</div>
                <div><strong>Assigned:</strong> ${escapeHtml(task.assigned_to || 'Unassigned')}</div>
                <div><strong>Priority:</strong> ${escapeHtml(task.priority)}</div>
                <div><strong>Due:</strong> ${formatDate(task.due_date)}</div>
            </div>
            
            ${task.description ? `<div style="margin-bottom: 20px; padding: 12px; background: #f1f5f9; border-left: 3px solid #F97316;"><strong>Description:</strong><p style="margin: 8px 0 0;">${escapeHtml(task.description)}</p></div>` : ''}
            
            <div style="border-top: 1px solid #e2e8f0; padding-top: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h4 style="margin: 0; font-size: 16px; font-weight: 600;">Task Checklist</h4>
                    <div id="progressBar" style="display: flex; align-items: center; gap: 8px; font-size: 12px;">
                        <div style="width: 100px; height: 6px; background: #e2e8f0; border-radius: 3px;"><div id="progressFill" style="height: 100%; background: #16a34a; border-radius: 3px; width: 0%;"></div></div>
                        <span id="progressText">0%</span>
                    </div>
                </div>
                
                <div id="checklistContainer" style="max-height: 300px; overflow-y: auto; margin-bottom: 15px;">
                    <p style="text-align: center; color: #94a3b8;">Loading checklist...</p>
                </div>
                
                ${canEditChecklist ? `
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="newChecklistInput" placeholder="Add a checklist item..." class="form-control" style="flex: 1; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
                    <button onclick="addChecklistItem()" class="btn btn-primary" style="padding: 8px 16px;"><i class="fas fa-plus"></i></button>
                </div>
                ` : `
                <p style="font-size: 12px; color: #94a3b8; margin: 0;"><i class="fas fa-eye"></i> View only — checklist items are managed by the assigned employee.</p>
                `}
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    loadChecklist();

    // Poll while this task's checklist is open, so progress made by
    // whoever is assigned shows up here without closing and reopening.
    if (checklistPollInterval) clearInterval(checklistPollInterval);
    checklistPollInterval = setInterval(loadChecklist, 8000);
}

function closeTaskDetailModal() {
    const modal = document.getElementById('taskDetailModal');
    if (modal) modal.remove();
    currentTaskId = null;
    if (checklistPollInterval) {
        clearInterval(checklistPollInterval);
        checklistPollInterval = null;
    }
}

async function loadChecklist() {
    if (!currentTaskId) return;
    
    try {
        const response = await fetch(`../api/task-checklist-api.php?task_id=${currentTaskId}`);
        const result = await response.json();
        
        if (result.success) {
            // The modal (and currentTaskId) may have been closed while this
            // fetch was in flight — bail out quietly instead of throwing on
            // elements that no longer exist.
            if (!currentTaskId) return;

            const checklists = result.data || [];
            const summary = result.summary || {};
            const progressFill = document.getElementById('progressFill');
            const progressText = document.getElementById('progressText');
            const container = document.getElementById('checklistContainer');
            if (!progressFill || !progressText || !container) return;

            // Update progress bar
            progressFill.style.width = (summary.progress_percent || 0) + '%';
            progressText.textContent = (summary.progress_percent || 0) + '%';

            // Render checklists
            if (checklists.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #94a3b8;">No checklist items yet</p>';
            } else {
                container.innerHTML = checklists.map(c => `
                    <div style="display: flex; align-items: center; gap: 12px; padding: 12px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 8px; background: ${c.is_completed ? '#f0fdf4' : '#fff'};">
                        <input type="checkbox" ${c.is_completed ? 'checked' : ''} ${canEditChecklist ? `onchange="toggleChecklist('${c.id}', this.checked)"` : 'disabled'} style="width: 18px; height: 18px; cursor: ${canEditChecklist ? 'pointer' : 'not-allowed'};">
                        <span style="flex: 1; ${c.is_completed ? 'text-decoration: line-through; color: #94a3b8;' : ''}">${escapeHtml(c.checklist_item)}</span>
                        ${canEditChecklist ? `<button onclick="deleteChecklistItem('${c.id}')" class="btn-icon" style="color: #dc2626;"><i class="fas fa-trash"></i></button>` : ''}
                    </div>
                `).join('');
            }
        }
    } catch (error) {
        console.error('Error loading checklist:', error);
        const container = document.getElementById('checklistContainer');
        if (container) container.innerHTML = '<p style="color: #dc2626;">Error loading checklist</p>';
    }
}

async function addChecklistItem() {
    const input = document.getElementById('newChecklistInput');
    const item = input.value.trim();
    
    if (!item || !currentTaskId) return;
    
    try {
        const response = await fetch('../api/task-checklist-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                task_id: currentTaskId,
                checklist_item: item
            })
        });
        const result = await response.json();
        
        if (result.success) {
            input.value = '';
            loadChecklist();
            showToast('Checklist item added', 'success');
        } else {
            showToast(result.error, 'error');
        }
    } catch (error) {
        showToast('Error adding checklist item', 'error');
    }
}

async function toggleChecklist(checklistId, isCompleted) {
    try {
        const response = await fetch('../api/task-checklist-api.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: checklistId,
                is_completed: isCompleted
            })
        });
        const result = await response.json();
        
        if (result.success) {
            loadChecklist();
        } else {
            showToast(result.error, 'error');
        }
    } catch (error) {
        showToast('Error updating checklist', 'error');
    }
}

async function deleteChecklistItem(checklistId) {
    if (!confirm('Delete this checklist item?')) return;
    
    try {
        const response = await fetch(`../api/task-checklist-api.php?id=${checklistId}`, {
            method: 'DELETE'
        });
        const result = await response.json();
        
        if (result.success) {
            loadChecklist();
            showToast('Checklist item deleted', 'success');
        } else {
            showToast(result.error, 'error');
        }
    } catch (error) {
        showToast('Error deleting checklist item', 'error');
    }
}

// Initialize
loadProjects();
loadTasks();
loadAssignableUsers();

// Keep the board's progress bars current without a manual refresh
setInterval(loadTasks, 15000);
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>