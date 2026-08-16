<?php
// modules/customers.php
// Customers management page

require_once __DIR__ . '/../config/config.php';
requireAuth();
checkPageAccess('customers');

$pageTitle = 'Customers';
$pageSubtitle = 'Manage your customer relationships';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;
    
    switch ($action) {
        case 'get':
            $customers = $supabase->from('customers')
                ->select('*')
                ->order('created_at', false)
                ->execute();
            echo json_encode(['success' => true, 'data' => $customers ?? []]);
            break;

        case 'save':
            $data = [
                'customer_code' => !empty($_POST['customer_code']) ? $_POST['customer_code'] : generateCode('CUST'),
                'name'           => trim($_POST['name'] ?? ''),
                'contact_person' => trim($_POST['contact_person'] ?? ''),
                'email'          => trim($_POST['email'] ?? ''),
                'phone'          => trim($_POST['phone'] ?? ''),
                'address'        => trim($_POST['address'] ?? ''),
                'city'           => trim($_POST['city'] ?? ''),
                'state'          => trim($_POST['state'] ?? ''),
                'pincode'        => trim($_POST['pincode'] ?? ''),
                'gstin'          => trim($_POST['gstin'] ?? ''),
                'type'           => $_POST['type'] ?? 'commercial',
                'status'         => $_POST['status'] ?? 'active',
                'updated_at'     => date('Y-m-d H:i:s'),
            ];

            if (empty($data['name'])) {
                echo json_encode(['success' => false, 'message' => 'Customer name is required']);
                break;
            }

            if ($id) {
                if (hasPermission('customers', 'edit')) {
                    $supabase->update('customers', $id, $data);
                    logActivity($_SESSION['user_id'], 'update', 'customers', "Updated customer ID: $id");
                    echo json_encode(['success' => true, 'message' => 'Customer updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Permission denied']);
                }
            } else {
                if (hasPermission('customers', 'create')) {
                    $data['created_at'] = date('Y-m-d H:i:s');
                    $supabase->insert('customers', $data);
                    logActivity($_SESSION['user_id'], 'create', 'customers', "Created customer: {$data['name']}");
                    echo json_encode(['success' => true, 'message' => 'Customer created successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Permission denied']);
                }
            }
            break;

        case 'delete':
            if (hasPermission('customers', 'delete')) {
                try {
                    archiveRecord('customers', $id);
                    logActivity($_SESSION['user_id'], 'archive', 'customers', "Archived customer ID: $id");
                    echo json_encode(['success' => true, 'message' => 'Customer archived successfully']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
            }
            break;
    }
    exit();
}

include_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">All Customers</h3>
        <?php if (hasPermission('customers', 'create')): ?>
        <button onclick="openCustomerModal()" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Customer
        </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="filters-bar">
            <div class="filter-group">
                <label class="filter-label">Status</label>
                <select id="statusFilter" class="filter-select">
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Type</label>
                <select id="typeFilter" class="filter-select">
                    <option value="all">All Types</option>
                    <option value="commercial">Commercial</option>
                    <option value="residential">Residential</option>
                    <option value="industrial">Industrial</option>
                </select>
            </div>
            <div class="filter-group flex-1">
                <label class="filter-label">Search</label>
                <input type="text" id="searchInput" placeholder="Search customers..." class="form-control">
            </div>
        </div>
        
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Contact Person</th>
                        <th>Email & Phone</th>
                        <th>Projects</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="customersTableBody">
                    <tr><td colspan="6" class="text-center">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Customer Modal -->
<div id="customerModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle" class="modal-title">Add Customer</h3>
            <button class="modal-close" onclick="closeCustomerModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="customerForm">
                <input type="hidden" id="customerId">
                <div class="form-group">
                    <label class="form-label">Company Name *</label>
                    <input type="text" id="customerName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Contact Person</label>
                    <input type="text" id="contactPerson" class="form-control">
                </div>
                <div class="grid-cols-2">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" id="customerEmail" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone *</label>
                        <input type="tel" id="customerPhone" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea id="customerAddress" class="form-textarea"></textarea>
                </div>
                <div class="grid-cols-2">
                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input type="text" id="customerCity" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">State</label>
                        <input type="text" id="customerState" class="form-control">
                    </div>
                </div>
                <div class="grid-cols-2">
                    <div class="form-group">
                        <label class="form-label">Pincode</label>
                        <input type="text" id="customerPincode" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">GSTIN</label>
                        <input type="text" id="customerGstin" class="form-control">
                    </div>
                </div>
                <div class="grid-cols-2">
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select id="customerType" class="form-select">
                            <option value="commercial">Commercial</option>
                            <option value="residential">Residential</option>
                            <option value="industrial">Industrial</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select id="customerStatus" class="form-select">
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeCustomerModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveCustomer()">Save Customer</button>
        </div>
    </div>
</div>

<script>
let customers = [];

async function loadCustomers() {
    try {
        const response = await fetch('customers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get'
        });
        const result = await response.json();
        if (result.success) {
            customers = result.data;
            renderCustomers();
        }
    } catch (error) {
        console.error('Error loading customers:', error);
    }
}

function renderCustomers() {
    const search = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const status = document.getElementById('statusFilter')?.value || 'all';
    const type = document.getElementById('typeFilter')?.value || 'all';
    
    let filtered = customers.filter(c => {
        if (search && !c.name.toLowerCase().includes(search) && 
            !(c.email && c.email.toLowerCase().includes(search))) return false;
        if (status !== 'all' && c.status !== status) return false;
        if (type !== 'all' && c.type !== type) return false;
        return true;
    });
    
    const tbody = document.getElementById('customersTableBody');
    if (!tbody) return;
    
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center">No customers found</td></tr>';
        return;
    }
    
    tbody.innerHTML = filtered.map(c => `
        <tr>
            <td>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-building text-orange-600"></i>
                    </div>
                    <div>
                        <div class="font-medium">${escapeHtml(c.name)}</div>
                        <div class="text-sm text-gray-500">${c.customer_code}</div>
                    </div>
                </div>
            </td>
            <td>${escapeHtml(c.contact_person || '-')}</td>
            <td>
                <div class="space-y-1">
                    <div class="flex items-center gap-2 text-sm">
                        <i class="fas fa-envelope text-xs text-gray-400"></i>
                        ${escapeHtml(c.email || '-')}
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <i class="fas fa-phone text-xs text-gray-400"></i>
                        ${escapeHtml(c.phone)}
                    </div>
                </div>
            </td>
            <td>${c.projects_count || 0}</td>
            <td>${getStatusBadgeHtml(c.status)}</td>
            <td>
                <div style="display:flex;align-items:center;gap:6px">
                    <button onclick="viewCustomer(${c.id})" class="btn-icon" title="View">
                        <i class="fas fa-eye" style="color:#3B82F6"></i>
                    </button>
                    <button onclick="editCustomer(${c.id})" class="btn-icon" title="Edit">
                        <i class="fas fa-edit" style="color:#F97316"></i>
                    </button>
                    ${(USER_ROLE === 'super_admin' || USER_ROLE === 'owner') ? `
                    <button onclick="archiveCustomer(${c.id})" class="btn-icon" title="Archive">
                        <i class="fas fa-archive" style="color:#6B7280"></i>
                    </button>` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

function getStatusBadgeHtml(status) {
    const badges = {
        active: 'badge-success',
        pending: 'badge-warning',
        inactive: 'badge-danger'
    };
    const labels = {
        active: 'Active',
        pending: 'Pending',
        inactive: 'Inactive'
    };
    return `<span class="badge ${badges[status]}">${labels[status]}</span>`;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function openCustomerModal(customer = null) {
    const modal = document.getElementById('customerModal');
    const title = document.getElementById('modalTitle');
    
    if (customer) {
        title.textContent = 'Edit Customer';
        document.getElementById('customerId').value = customer.id;
        document.getElementById('customerName').value = customer.name;
        document.getElementById('contactPerson').value = customer.contact_person || '';
        document.getElementById('customerEmail').value = customer.email || '';
        document.getElementById('customerPhone').value = customer.phone;
        document.getElementById('customerAddress').value = customer.address || '';
        document.getElementById('customerCity').value = customer.city || '';
        document.getElementById('customerState').value = customer.state || '';
        document.getElementById('customerPincode').value = customer.pincode || '';
        document.getElementById('customerGstin').value = customer.gstin || '';
        document.getElementById('customerType').value = customer.type || 'commercial';
        document.getElementById('customerStatus').value = customer.status || 'active';
    } else {
        title.textContent = 'Add New Customer';
        document.getElementById('customerForm').reset();
        document.getElementById('customerId').value = '';
    }
    
    modal.classList.add('active');
}

function closeCustomerModal() {
    const modal = document.getElementById('customerModal');
    modal.classList.remove('active');
}

async function saveCustomer() {
    const id = document.getElementById('customerId').value;
    const formData = new URLSearchParams();
    formData.append('action', 'save');
    if (id) formData.append('id', id);
    formData.append('name', document.getElementById('customerName').value);
    formData.append('contact_person', document.getElementById('contactPerson').value);
    formData.append('email', document.getElementById('customerEmail').value);
    formData.append('phone', document.getElementById('customerPhone').value);
    formData.append('address', document.getElementById('customerAddress').value);
    formData.append('city', document.getElementById('customerCity').value);
    formData.append('state', document.getElementById('customerState').value);
    formData.append('pincode', document.getElementById('customerPincode').value);
    formData.append('gstin', document.getElementById('customerGstin').value);
    formData.append('type', document.getElementById('customerType').value);
    formData.append('status', document.getElementById('customerStatus').value);
    
    try {
        const response = await fetch('customers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showToast(result.message, 'success');
            closeCustomerModal();
            loadCustomers();
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        showToast('Error saving customer', 'error');
    }
}

function viewCustomer(id) {
    const c = customers.find(c => c.id === id);
    if (!c) return;
    showDetailModal(c.name, [
        { label: 'Customer Code',  value: c.customer_code },
        { label: 'Contact Person', value: c.contact_person },
        { label: 'Email',          value: c.email },
        { label: 'Phone',          value: c.phone },
        { label: 'Address',        value: c.address },
        { label: 'City',           value: c.city },
        { label: 'State',          value: c.state },
        { label: 'Pincode',        value: c.pincode },
        { label: 'GSTIN',          value: c.gstin },
        { label: 'Type',           value: c.type },
        { label: 'Status',         value: c.status },
    ]);
}

async function editCustomer(id) {
    const customer = customers.find(c => c.id === id);
    if (customer) openCustomerModal(customer);
}

async function archiveCustomer(id) {
    const customer = customers.find(c => c.id === id);
    const name = customer?.name || 'this customer';
    showConfirmModal(
        `"${name}" will be archived and hidden from the customer list.`,
        async () => {
            const formData = new URLSearchParams();
            formData.append('action', 'delete');
            formData.append('id', id);
            try {
                const response = await fetch('customers.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    loadCustomers();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                showToast('Error archiving customer', 'error');
            }
        },
        { title: 'Archive Customer', confirmText: 'Archive' }
    );
}

// Event listeners
document.getElementById('searchInput')?.addEventListener('input', renderCustomers);
document.getElementById('statusFilter')?.addEventListener('change', renderCustomers);
document.getElementById('typeFilter')?.addEventListener('change', renderCustomers);

// Initialize
loadCustomers();
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>