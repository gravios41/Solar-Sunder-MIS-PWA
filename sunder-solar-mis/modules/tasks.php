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
                            <option value="John Doe">John Doe</option>
                            <option value="Jane Smith">Jane Smith</option>
                            <option value="Mike Johnson">Mike Johnson</option>
                            <option value="Sarah Williams">Sarah Williams</option>
                            <option value="David Brown">David Brown</option>
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

function renderTasks() {
    const search = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const priority = document.getElementById('priorityFilter')?.value || 'all';
    
    let filtered = tasks.filter(t => {
        if (search && !t.task_title.toLowerCase().includes(search) && 
            !(t.assigned_to || '').toLowerCase().includes(search)) return false;
        if (priority !== 'all' && t.priority !== priority) return false;
        return true;
    });
    
    const todo = filtered.filter(t => t.status === 'pending');
    const inProgress = filtered.filter(t => t.status === 'in_progress');
    const completed = filtered.filter(t => t.status === 'completed');
    
    function renderTaskList(taskList) {
        if (taskList.length === 0) {
            return '<p class="text-center text-gray-500 py-4">No tasks</p>';
        }
        return taskList.map(t => {
            const project = projects.find(p => p.id === t.project_id);
            let statusBtn = '';
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
            const editBtn = (USER_ROLE === 'super_admin' || USER_ROLE === 'owner')
                ? `<button onclick="editTask(${t.id})" class="btn-icon" title="Edit"><i class="fas fa-edit" style="color:#F97316"></i></button>`
                : '';
            const archiveBtn = (USER_ROLE === 'super_admin' || USER_ROLE === 'owner')
                ? `<button onclick="archiveTask(${t.id})" class="btn-icon" title="Archive"><i class="fas fa-archive" style="color:#6B7280"></i></button>`
                : '';
            return `
                <div class="task-card">
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-xs text-gray-500">#${t.id}</span>
                        ${getPriorityBadgeHtml(t.priority)}
                    </div>
                    <h5 class="font-medium mb-1">${escapeHtml(t.task_title)}</h5>
                    <p class="text-xs text-gray-600 mb-2">${escapeHtml(project?.project_name || 'No Project')}</p>
                    <p class="text-xs text-gray-500 mb-2">${escapeHtml(t.description || '')}</p>
                    <div class="flex justify-between items-center text-xs mb-3">
                        <span><i class="fas fa-user mr-1"></i>${escapeHtml(t.assigned_to || 'Unassigned')}</span>
                        <span><i class="fas fa-calendar mr-1"></i>Due: ${formatDate(t.due_date)}</span>
                    </div>
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

    document.getElementById('todoTasks').innerHTML = renderTaskList(todo);
    document.getElementById('progressTasks').innerHTML = renderTaskList(inProgress);
    document.getElementById('completedTasks').innerHTML = renderTaskList(completed);
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
    const t = tasks.find(t => t.id === id);
    if (!t) return;
    const project = projects.find(p => p.id === t.project_id);
    showDetailModal(t.task_title, [
        { label: 'Project',     value: project?.project_name || 'No Project' },
        { label: 'Assigned To', value: t.assigned_to || 'Unassigned' },
        { label: 'Priority',    value: t.priority?.charAt(0).toUpperCase() + t.priority?.slice(1) },
        { label: 'Status',      value: t.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) },
        { label: 'Due Date',    value: formatDate(t.due_date) },
        { label: 'Description', value: t.description },
    ]);
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
                    loadTasks();
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

// Initialize
loadProjects();
loadTasks();
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>