<?php
// modules/user-management.php
// User management page (Super Admin and Owner only)

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

checkAuthentication();

// Check permission
if (!checkPermission('users', 'view')) {
    setToast('You do not have permission to access User Management', 'error');
    header('Location: dashboard.php');
    exit();
}

$pageTitle = 'User Management';
$pageSubtitle = 'Manage system users and roles';

include_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">System Users</h3>
        <?php if (checkPermission('users', 'create')): ?>
        <button onclick="openUserModal()" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New User
        </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <tr><td colspan="6" class="text-center">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- User Modal -->
<div id="userModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle" class="modal-title">Add New User</h3>
            <button class="modal-close" onclick="closeUserModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="userForm">
                <input type="hidden" id="userId">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" id="fullName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Username *</label>
                    <input type="text" id="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" id="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" id="password" class="form-control" placeholder="Leave blank to keep current">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" id="phone" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Role *</label>
                    <select id="userRole" class="form-select" required>
                        <option value="">Select Role</option>
                        <?php if ($_SESSION['user_role'] === 'super_admin'): ?>
                        <option value="super_admin">Super Admin</option>
                        <?php endif; ?>
                        <option value="admin">Admin</option>
                        <?php if ($_SESSION['user_role'] === 'super_admin'): ?>
                        <option value="owner">Owner</option>
                        <?php endif; ?>
                        <option value="employee">Employee</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select id="userStatus" class="form-select">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeUserModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveUser()">Save User</button>
        </div>
    </div>
</div>

<script>
let users = [];

async function loadUsers() {
    try {
        const response = await fetch('../api/users-api.php');
        const result = await response.json();
        if (result.success) {
            users = result.data;
            renderUsers();
        }
    } catch (error) {
        console.error('Error loading users:', error);
    }
}

function renderUsers() {
    const tbody = document.getElementById('usersTableBody');
    if (!tbody) return;
    
    const currentUserRole = '<?php echo $_SESSION['user_role']; ?>';
    const currentUserId = <?php echo $_SESSION['user_id']; ?>;
    
    let displayUsers = users;
    
    // Owner cannot see Super Admin users; employee cannot access this page at all
    if (currentUserRole === 'owner') {
        displayUsers = users.filter(u => u.role !== 'super_admin');
    }
    
    if (displayUsers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center">No users found</td></tr>';
        return;
    }
    
    tbody.innerHTML = displayUsers.map(u => `
        <tr>
            <td>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-orange-600"></i>
                    </div>
                    <div>
                        <div class="font-medium">${escapeHtml(u.full_name)}</div>
                        <div class="text-sm text-gray-500">@${escapeHtml(u.username)}</div>
                    </div>
                </div>
            </td>
            <td>${escapeHtml(u.email)}</td>
            <td>${getRoleBadge(u.role)}</td>
            <td>${u.is_active ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'}</td>
            <td class="text-sm text-gray-500">${formatDate(u.last_login, 'datetime')}</td>
            <td>
                <div style="display:flex;align-items:center;gap:6px">
                    <button onclick="viewUser(${u.id})" class="btn-icon" title="View">
                        <i class="fas fa-eye" style="color:#3B82F6"></i>
                    </button>
                    ${canEditUser(u) ? `
                    <button onclick="editUser(${u.id})" class="btn-icon" title="Edit">
                        <i class="fas fa-edit" style="color:#F97316"></i>
                    </button>` : ''}
                    ${canDeleteUser(u) ? `
                    <button onclick="archiveUser(${u.id})" class="btn-icon" title="Archive">
                        <i class="fas fa-archive" style="color:#6B7280"></i>
                    </button>` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

function getRoleBadge(role) {
    const badges = {
        super_admin: 'badge-danger',
        admin: 'badge-info',
        owner: 'badge-success',
        employee: 'badge-secondary'
    };
    const labels = {
        super_admin: 'Super Admin',
        admin: 'Admin',
        owner: 'Owner',
        employee: 'Employee'
    };
    return `<span class="badge ${badges[role] || 'badge-secondary'}">${labels[role] || role}</span>`;
}

function canEditUser(user) {
    const currentUserRole = '<?php echo $_SESSION['user_role']; ?>';
    const currentUserId = <?php echo $_SESSION['user_id']; ?>;

    if (user.id === currentUserId) return false;
    if (currentUserRole === 'super_admin') return true;
    if (currentUserRole === 'owner') {
        return user.role === 'admin' || user.role === 'employee';
    }
    return false;
}

function canDeleteUser(user) {
    const currentUserRole = '<?php echo $_SESSION['user_role']; ?>';
    const currentUserId = <?php echo $_SESSION['user_id']; ?>;

    if (user.id === currentUserId) return false;
    if (currentUserRole === 'super_admin') return true;
    if (currentUserRole === 'owner') {
        return user.role === 'admin' || user.role === 'employee';
    }
    return false;
}

function viewUser(id) {
    const u = users.find(u => u.id === id);
    if (!u) return;
    showDetailModal(u.full_name, [
        { label: 'Username',   value: u.username },
        { label: 'Email',      value: u.email },
        { label: 'Phone',      value: u.phone },
        { label: 'Role',       value: u.role?.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase()) },
        { label: 'Status',     value: u.is_active ? 'Active' : 'Inactive' },
        { label: 'Last Login', value: u.last_login ? new Date(u.last_login).toLocaleString() : 'Never' },
    ]);
}

function openUserModal(user = null) {
    if (user) {
        document.getElementById('modalTitle').textContent = 'Edit User';
        document.getElementById('userId').value = user.id;
        document.getElementById('fullName').value = user.full_name;
        document.getElementById('username').value = user.username;
        document.getElementById('email').value = user.email;
        document.getElementById('phone').value = user.phone || '';
        document.getElementById('userRole').value = user.role;
        document.getElementById('userStatus').value = user.is_active ? '1' : '0';
        document.getElementById('password').placeholder = 'Leave blank to keep current';
        document.getElementById('password').required = false;
    } else {
        document.getElementById('modalTitle').textContent = 'Add New User';
        document.getElementById('userForm').reset();
        document.getElementById('userId').value = '';
        document.getElementById('password').required = true;
        document.getElementById('password').placeholder = 'Enter password';
    }
    document.getElementById('userModal').classList.add('active');
}

function closeUserModal() {
    document.getElementById('userModal').classList.remove('active');
}

async function saveUser() {
    const id = document.getElementById('userId').value;
    const data = {
        full_name: document.getElementById('fullName').value,
        username: document.getElementById('username').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        role: document.getElementById('userRole').value,
        is_active: document.getElementById('userStatus').value === '1' ? 1 : 0
    };
    
    const password = document.getElementById('password').value;
    if (!id && !password) {
        showToast('Password is required for new users', 'error');
        return;
    }
    if (password) {
        data.password = password;
    }
    
    const url = id ? `../api/users-api.php?id=${id}` : '../api/users-api.php';
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
            closeUserModal();
            loadUsers();
        } else {
            showToast(result.error, 'error');
        }
    } catch (error) {
        showToast('Error saving user', 'error');
    }
}

async function editUser(id) {
    const user = users.find(u => u.id === id);
    if (user) openUserModal(user);
}

async function archiveUser(id) {
    const user = users.find(u => u.id === id);
    const name = user?.full_name || user?.username || 'this user';
    showConfirmModal(
        `"${name}" will be archived and deactivated. This action cannot be undone.`,
        async () => {
            try {
                const response = await fetch(`../api/users-api.php?id=${id}`, {
                    method: 'DELETE'
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    loadUsers();
                } else {
                    showToast(result.error, 'error');
                }
            } catch (error) {
                showToast('Error archiving user', 'error');
            }
        },
        { title: 'Archive User', confirmText: 'Archive' }
    );
}

// Initialize
loadUsers();
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>