<?php
// modules/inventory.php
// Inventory management page

require_once __DIR__ . '/../config/config.php';
requireAuth();
checkPageAccess('inventory');

$pageTitle = 'Inventory';
$pageSubtitle = 'Manage your inventory items';

include_once __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-boxes"></i>
        </div>
        <div class="stat-value" id="totalItems">0</div>
        <div class="stat-label">Total Items</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-value" id="inStock">0</div>
        <div class="stat-label">In Stock</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-value" id="lowStock">0</div>
        <div class="stat-label">Low Stock</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-value" id="criticalStock">0</div>
        <div class="stat-label">Critical</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Inventory Items</h3>
        <?php if (hasPermission('inventory', 'create')): ?>
        <button onclick="openItemModal()" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Item
        </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="filters-bar">
            <div class="filter-group">
                <label class="filter-label">Category</label>
                <select id="categoryFilter" class="filter-select">
                    <option value="all">All Categories</option>
                    <option value="solar_panel">Solar Panels</option>
                    <option value="inverter">Inverters</option>
                    <option value="battery">Batteries</option>
                    <option value="mounting">Mounting</option>
                    <option value="cable">Cables</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Status</label>
                <select id="statusFilter" class="filter-select">
                    <option value="all">All Status</option>
                    <option value="In Stock">In Stock</option>
                    <option value="Low Stock">Low Stock</option>
                    <option value="Critical">Critical</option>
                </select>
            </div>
            <div class="filter-group flex-1">
                <label class="filter-label">Search</label>
                <input type="text" id="searchInput" placeholder="Search items..." class="form-control">
            </div>
        </div>
        
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Unit Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="inventoryTableBody">
                    <tr><td colspan="8" class="text-center">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Purchase Orders Card -->
<div class="card" style="margin-top:24px">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-truck" style="color:var(--solar-orange);margin-right:8px"></i>Purchase Orders</h3>
        <?php if (hasPermission('inventory', 'create')): ?>
        <button onclick="openOrderModal()" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Order
        </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="filters-bar" style="margin-bottom:16px">
            <div class="filter-group">
                <label class="filter-label">Status</label>
                <select id="orderStatusFilter" class="filter-select" onchange="renderOrders()">
                    <option value="all">All Orders</option>
                    <option value="pending">Pending</option>
                    <option value="ordered">Ordered</option>
                    <option value="in_transit">In Transit</option>
                    <option value="arrived">Arrived</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="filter-group flex-1">
                <label class="filter-label">Search</label>
                <input type="text" id="orderSearchInput" placeholder="Search orders..." class="form-control" oninput="renderOrders()">
            </div>
            <button onclick="loadOrders()" class="btn btn-secondary btn-sm" title="Refresh">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty Ordered</th>
                        <th>Supplier</th>
                        <th>Order Date</th>
                        <th>Expected Delivery</th>
                        <th>Actual Delivery</th>
                        <th>Status</th>
                        <th>Ordered By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="ordersTableBody">
                    <tr><td colspan="9" class="text-center">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Order Modal -->
<div id="orderModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="orderModalTitle" class="modal-title">New Purchase Order</h3>
            <button class="modal-close" onclick="closeOrderModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="orderForm">
                <input type="hidden" id="orderId">
                <div class="form-group">
                    <label class="form-label">Item *</label>
                    <select id="orderItemSelect" class="form-select" onchange="fillOrderItem(this)">
                        <option value="">— Select from inventory or type manually —</option>
                    </select>
                </div>
                <div class="grid-cols-2">
                    <div class="form-group">
                        <label class="form-label">Item Name *</label>
                        <input type="text" id="orderItemName" class="form-control" required placeholder="e.g. 400W Solar Panel">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quantity *</label>
                        <input type="number" id="orderQty" class="form-control" min="1" value="1" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Supplier</label>
                    <input type="text" id="orderSupplier" class="form-control" placeholder="Supplier name">
                </div>
                <div class="grid-cols-2">
                    <div class="form-group">
                        <label class="form-label">Order Date *</label>
                        <input type="date" id="orderDate" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expected Delivery</label>
                        <input type="date" id="orderExpected" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select id="orderStatus" class="form-select">
                        <option value="pending">Pending</option>
                        <option value="ordered">Ordered</option>
                        <option value="in_transit">In Transit</option>
                        <option value="arrived">Arrived</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="form-group" id="actualDeliveryGroup" style="display:none">
                    <label class="form-label">Actual Delivery Date</label>
                    <input type="date" id="orderActual" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea id="orderNotes" class="form-textarea" placeholder="Optional notes..."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeOrderModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveOrder()">Save Order</button>
        </div>
    </div>
</div>

<!-- Item Modal -->
<div id="itemModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle" class="modal-title">Add Inventory Item</h3>
            <button class="modal-close" onclick="closeItemModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="itemForm">
                <input type="hidden" id="itemId">
                <div class="form-group">
                    <label class="form-label">Item Name *</label>
                    <input type="text" id="itemName" class="form-control" required>
                </div>
                <div class="grid-cols-2">
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select id="category" class="form-select" required>
                            <option value="solar_panel">Solar Panel</option>
                            <option value="inverter">Inverter</option>
                            <option value="battery">Battery</option>
                            <option value="mounting">Mounting</option>
                            <option value="cable">Cable</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Brand</label>
                        <input type="text" id="brand" class="form-control">
                    </div>
                </div>
                <div class="grid-cols-2">
                    <div class="form-group">
                        <label class="form-label">Model</label>
                        <input type="text" id="model" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Unit</label>
                        <select id="unit" class="form-select">
                            <option value="piece">Piece</option>
                            <option value="meter">Meter</option>
                            <option value="set">Set</option>
                            <option value="kg">KG</option>
                        </select>
                    </div>
                </div>
                <div class="grid-cols-2">
                    <div class="form-group">
                        <label class="form-label">Quantity</label>
                        <input type="number" id="quantity" class="form-control" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Unit Price</label>
                        <input type="number" id="unitPrice" class="form-control" step="0.01" value="0">
                    </div>
                </div>
                <div class="grid-cols-2">
                    <div class="form-group">
                        <label class="form-label">Reorder Level</label>
                        <input type="number" id="reorderLevel" class="form-control" value="5">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Location</label>
                        <input type="text" id="location" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Supplier</label>
                    <input type="text" id="supplier" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Specification</label>
                    <textarea id="specification" class="form-textarea"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeItemModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveItem()">Save Item</button>
        </div>
    </div>
</div>

<script>
let inventory = [];

async function loadInventory() {
    try {
        const response = await fetch('../api/inventory-api.php');
        const result = await response.json();
        if (result.success) {
            inventory = result.data;
            renderInventory();
            updateStats();
        }
    } catch (error) {
        console.error('Error loading inventory:', error);
        showToast('Error loading inventory', 'error');
    }
}

function updateStats() {
    const total = inventory.length;
    const inStock = inventory.filter(i => i.status_text === 'In Stock').length;
    const lowStock = inventory.filter(i => i.status_text === 'Low Stock').length;
    const critical = inventory.filter(i => i.status_text === 'Critical').length;
    
    document.getElementById('totalItems').textContent = total;
    document.getElementById('inStock').textContent = inStock;
    document.getElementById('lowStock').textContent = lowStock;
    document.getElementById('criticalStock').textContent = critical;
}

function renderInventory() {
    const search = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const category = document.getElementById('categoryFilter')?.value || 'all';
    const status = document.getElementById('statusFilter')?.value || 'all';
    
    let filtered = inventory.filter(item => {
        if (search && !item.item_name.toLowerCase().includes(search) && 
            !item.item_code.toLowerCase().includes(search)) return false;
        if (category !== 'all' && item.category !== category) return false;
        if (status !== 'all' && item.status_text !== status) return false;
        return true;
    });
    
    const tbody = document.getElementById('inventoryTableBody');
    if (!tbody) return;
    
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">No items found</td></tr>';
        return;
    }
    
    tbody.innerHTML = filtered.map(item => `
        <tr>
            <td>${escapeHtml(item.item_code)}</td>
            <td class="font-medium">${escapeHtml(item.item_name)}</td>
            <td>${formatCategory(item.category)}</td>
            <td class="font-medium">${item.quantity}</td>
            <td>${item.unit}</td>
            <td>${formatCurrency(item.unit_price)}</td>
            <td>${getStatusBadgeHtml(item.status_text)}</td>
            <td>
                <div style="display:flex;align-items:center;gap:6px">
                    <button onclick="viewItem(${item.id})" class="btn-icon" title="View">
                        <i class="fas fa-eye" style="color:#3B82F6"></i>
                    </button>
                    ${(USER_ROLE === 'super_admin' || USER_ROLE === 'owner' || USER_ROLE === 'admin') ? `
                    <button onclick="editItem(${item.id})" class="btn-icon" title="Edit">
                        <i class="fas fa-edit" style="color:#F97316"></i>
                    </button>` : ''}
                    ${(USER_ROLE === 'super_admin' || USER_ROLE === 'owner' || USER_ROLE === 'admin') ? `
                    <button onclick="openOrderModal(${item.id})" class="btn-icon" title="Create Order">
                        <i class="fas fa-truck" style="color:#8B5CF6"></i>
                    </button>` : ''}
                    ${(USER_ROLE === 'super_admin' || USER_ROLE === 'owner') ? `
                    <button onclick="archiveItem(${item.id})" class="btn-icon" title="Archive">
                        <i class="fas fa-archive" style="color:#6B7280"></i>
                    </button>` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

function formatCategory(category) {
    const categories = {
        solar_panel: 'Solar Panel',
        inverter: 'Inverter',
        battery: 'Battery',
        mounting: 'Mounting',
        cable: 'Cable'
    };
    return categories[category] || category;
}

function formatCurrency(amount) {
    return '₱' + Number(amount).toLocaleString('en-PH', {minimumFractionDigits: 2});
}

function getStatusBadgeHtml(status) {
    const badges = {
        'In Stock': 'badge-success',
        'Low Stock': 'badge-warning',
        'Critical': 'badge-danger'
    };
    return `<span class="badge ${badges[status]}">${status}</span>`;
}

function openItemModal(item = null) {
    const modal = document.getElementById('itemModal');
    const title = document.getElementById('modalTitle');
    
    if (item) {
        title.textContent = 'Edit Inventory Item';
        document.getElementById('itemId').value = item.id;
        document.getElementById('itemName').value = item.item_name;
        document.getElementById('category').value = item.category;
        document.getElementById('brand').value = item.brand || '';
        document.getElementById('model').value = item.model || '';
        document.getElementById('unit').value = item.unit;
        document.getElementById('quantity').value = item.quantity;
        document.getElementById('unitPrice').value = item.unit_price;
        document.getElementById('reorderLevel').value = item.reorder_level;
        document.getElementById('supplier').value = item.supplier || '';
        document.getElementById('location').value = item.location || '';
        document.getElementById('specification').value = item.specification || '';
    } else {
        title.textContent = 'Add Inventory Item';
        document.getElementById('itemForm').reset();
        document.getElementById('itemId').value = '';
        document.getElementById('unit').value = 'piece';
        document.getElementById('reorderLevel').value = 5;
    }
    
    modal.classList.add('active');
}

function closeItemModal() {
    const modal = document.getElementById('itemModal');
    modal.classList.remove('active');
}

async function saveItem() {
    const id = document.getElementById('itemId').value;
    const data = {
        item_name: document.getElementById('itemName').value,
        category: document.getElementById('category').value,
        brand: document.getElementById('brand').value,
        model: document.getElementById('model').value,
        unit: document.getElementById('unit').value,
        quantity: parseInt(document.getElementById('quantity').value) || 0,
        unit_price: parseFloat(document.getElementById('unitPrice').value) || 0,
        reorder_level: parseInt(document.getElementById('reorderLevel').value) || 5,
        supplier: document.getElementById('supplier').value,
        location: document.getElementById('location').value,
        specification: document.getElementById('specification').value
    };
    
    if (!data.item_name) {
        showToast('Please enter item name', 'error');
        return;
    }
    
    const url = id ? `../api/inventory-api.php?id=${id}` : '../api/inventory-api.php';
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
            closeItemModal();
            loadInventory();
        } else {
            showToast(result.error, 'error');
        }
    } catch (error) {
        showToast('Error saving item', 'error');
    }
}

function viewItem(id) {
    const item = inventory.find(i => i.id === id);
    if (!item) return;
    showDetailModal(item.item_name, [
        { label: 'Item Code',    value: item.item_code },
        { label: 'Category',     value: formatCategory(item.category) },
        { label: 'Brand',        value: item.brand },
        { label: 'Model',        value: item.model },
        { label: 'Unit',         value: item.unit },
        { label: 'Quantity',     value: item.quantity },
        { label: 'Unit Price',   value: formatCurrency(item.unit_price) },
        { label: 'Reorder Level',value: item.reorder_level },
        { label: 'Supplier',     value: item.supplier },
        { label: 'Location',     value: item.location },
        { label: 'Status',       value: item.status_text },
        { label: 'Specification',value: item.specification },
    ]);
}

async function editItem(id) {
    const item = inventory.find(i => i.id === id);
    if (item) openItemModal(item);
}

async function archiveItem(id) {
    const item = inventory.find(i => i.id === id);
    const name = item?.item_name || 'this item';
    showConfirmModal(
        `"${name}" will be removed from inventory. This action cannot be undone.`,
        async () => {
            try {
                const response = await fetch(`../api/inventory-api.php?id=${id}`, {
                    method: 'DELETE'
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    loadInventory();
                } else {
                    showToast(result.error, 'error');
                }
            } catch (error) {
                showToast('Error removing item', 'error');
            }
        },
        { title: 'Archive Inventory Item', confirmText: 'Archive' }
    );
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i><span>${message}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// Event listeners
document.getElementById('searchInput')?.addEventListener('input', renderInventory);
document.getElementById('categoryFilter')?.addEventListener('change', renderInventory);
document.getElementById('statusFilter')?.addEventListener('change', renderInventory);

// ── Purchase Orders ────────────────────────────────────────────
let orders = [];

async function loadOrders() {
    try {
        const res    = await fetch('../api/inventory-orders-api.php');
        const result = await res.json();
        if (result.success) { orders = result.data || []; renderOrders(); }
    } catch (e) { console.error('Error loading orders:', e); }
}

const ORDER_STATUS_BADGE = {
    pending:    'badge-warning',
    ordered:    'badge-info',
    in_transit: 'badge-secondary',
    arrived:    'badge-success',
    cancelled:  'badge-danger'
};
const ORDER_STATUS_LABEL = {
    pending:'Pending', ordered:'Ordered', in_transit:'In Transit', arrived:'Arrived', cancelled:'Cancelled'
};

function renderOrders() {
    const search = document.getElementById('orderSearchInput')?.value.toLowerCase() || '';
    const status = document.getElementById('orderStatusFilter')?.value || 'all';

    let filtered = orders.filter(o => {
        if (status !== 'all' && o.status !== status) return false;
        if (search && !(o.item_name || '').toLowerCase().includes(search) &&
                      !(o.supplier  || '').toLowerCase().includes(search)) return false;
        return true;
    });

    const tbody = document.getElementById('ordersTableBody');
    if (!tbody) return;

    if (!filtered.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center" style="color:var(--text-muted);padding:32px">No orders found</td></tr>';
        return;
    }

    tbody.innerHTML = filtered.map(o => {
        const badgeClass = ORDER_STATUS_BADGE[o.status] || 'badge-secondary';
        const label      = ORDER_STATUS_LABEL[o.status] || o.status;
        const isActive   = !['arrived','cancelled'].includes(o.status);

        // Delivery date highlight
        let expectedHtml = o.expected_delivery ? formatOrderDate(o.expected_delivery) : '—';
        if (o.expected_delivery && o.status !== 'arrived' && o.status !== 'cancelled') {
            const diff = Math.ceil((new Date(o.expected_delivery) - new Date()) / 86400000);
            if (diff < 0)       expectedHtml = `<span style="color:#EF4444;font-weight:600">${formatOrderDate(o.expected_delivery)} <small>(overdue)</small></span>`;
            else if (diff <= 2) expectedHtml = `<span style="color:#F97316;font-weight:600">${formatOrderDate(o.expected_delivery)} <small>(${diff}d)</small></span>`;
        }

        return `<tr>
            <td class="font-medium">${escapeHtml(o.item_name)}${o.item_code ? `<br><small style="color:var(--text-muted)">${escapeHtml(o.item_code)}</small>` : ''}</td>
            <td>${o.quantity_ordered}</td>
            <td>${escapeHtml(o.supplier || '—')}</td>
            <td>${formatOrderDate(o.order_date)}</td>
            <td>${expectedHtml}</td>
            <td>${o.actual_delivery ? `<span style="color:#10B981;font-weight:600">${formatOrderDate(o.actual_delivery)}</span>` : '—'}</td>
            <td><span class="badge ${badgeClass}">${label}</span></td>
            <td style="font-size:0.82rem">${escapeHtml(o.ordered_by_name || '—')}</td>
            <td>
                <div style="display:flex;align-items:center;gap:6px">
                    ${isActive ? `
                    <button onclick="quickStatus(${o.id}, '${nextStatus(o.status)}')" class="btn-icon" title="Advance status">
                        <i class="fas fa-arrow-right" style="color:#3B82F6"></i>
                    </button>` : ''}
                    ${(USER_ROLE === 'super_admin' || USER_ROLE === 'owner') ? `
                    <button onclick="editOrder(${o.id})" class="btn-icon" title="Edit">
                        <i class="fas fa-edit" style="color:#F97316"></i>
                    </button>` : ''}
                    ${(USER_ROLE === 'super_admin' || USER_ROLE === 'owner') && o.status !== 'cancelled' && o.status !== 'arrived' ? `
                    <button onclick="cancelOrder(${o.id})" class="btn-icon" title="Cancel">
                        <i class="fas fa-times" style="color:#EF4444"></i>
                    </button>` : ''}
                </div>
            </td>
        </tr>`;
    }).join('');
}

function nextStatus(status) {
    const flow = { pending:'ordered', ordered:'in_transit', in_transit:'arrived' };
    return flow[status] || status;
}

function formatOrderDate(d) {
    if (!d) return '—';
    return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' });
}

async function quickStatus(id, status) {
    const labels = { ordered:'Ordered', in_transit:'In Transit', arrived:'Arrived ✓' };
    const confirmed = await new Promise(resolve => {
        showConfirmModal(`Mark this order as "${labels[status] || status}"?`, () => resolve(true), {
            title: 'Update Order Status',
            confirmText: 'Update'
        });
        // resolve false if cancelled — handled by confirmAction closing without calling callback
    });
    try {
        const body = { status };
        if (status === 'arrived') body.actual_delivery = new Date().toISOString().slice(0,10);
        const res    = await fetch(`../api/inventory-orders-api.php?id=${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const result = await res.json();
        if (result.success) {
            showToast(result.message, 'success');
            loadOrders();
            if (status === 'arrived') loadInventory(); // refresh stock counts
        } else { showToast(result.error, 'error'); }
    } catch (e) { showToast('Error updating order', 'error'); }
}

async function cancelOrder(id) {
    showConfirmModal('Cancel this order?', async () => {
        try {
            const res    = await fetch(`../api/inventory-orders-api.php?id=${id}`, { method: 'DELETE' });
            const result = await res.json();
            if (result.success) { showToast(result.message, 'success'); loadOrders(); }
            else showToast(result.error, 'error');
        } catch (e) { showToast('Error cancelling order', 'error'); }
    }, { title: 'Cancel Order', confirmText: 'Yes, Cancel' });
}

function openOrderModal(inventoryId = null) {
    document.getElementById('orderModalTitle').textContent = 'New Purchase Order';
    document.getElementById('orderId').value = '';
    document.getElementById('orderForm').reset();
    document.getElementById('orderDate').value = new Date().toISOString().slice(0,10);
    document.getElementById('actualDeliveryGroup').style.display = 'none';

    // Pre-fill from inventory row if truck icon clicked
    const select = document.getElementById('orderItemSelect');
    select.innerHTML = '<option value="">— Select from inventory or type manually —</option>' +
        inventory.map(i => `<option value="${i.id}" data-name="${escapeHtml(i.item_name)}" data-code="${escapeHtml(i.item_code || '')}" data-supplier="${escapeHtml(i.supplier || '')}">${i.item_name} (${i.item_code})</option>`).join('');

    if (inventoryId) {
        select.value = inventoryId;
        fillOrderItem(select);
    }

    document.getElementById('orderStatus').addEventListener('change', toggleActualDelivery);
    document.getElementById('orderModal').classList.add('active');
}

function fillOrderItem(select) {
    const opt = select.options[select.selectedIndex];
    if (!opt || !opt.value) return;
    document.getElementById('orderItemName').value    = opt.dataset.name || '';
    document.getElementById('orderSupplier').value   = opt.dataset.supplier || '';
}

function toggleActualDelivery() {
    const show = document.getElementById('orderStatus').value === 'arrived';
    document.getElementById('actualDeliveryGroup').style.display = show ? '' : 'none';
}

function editOrder(id) {
    const o = orders.find(x => x.id === id);
    if (!o) return;
    openOrderModal();
    document.getElementById('orderModalTitle').textContent = 'Edit Purchase Order';
    document.getElementById('orderId').value       = o.id;
    document.getElementById('orderItemName').value = o.item_name;
    document.getElementById('orderQty').value      = o.quantity_ordered;
    document.getElementById('orderSupplier').value = o.supplier || '';
    document.getElementById('orderDate').value     = o.order_date || '';
    document.getElementById('orderExpected').value = o.expected_delivery || '';
    document.getElementById('orderActual').value   = o.actual_delivery || '';
    document.getElementById('orderStatus').value   = o.status;
    document.getElementById('orderNotes').value    = o.notes || '';
    document.getElementById('actualDeliveryGroup').style.display = o.status === 'arrived' ? '' : 'none';
}

function closeOrderModal() {
    document.getElementById('orderModal').classList.remove('active');
}

async function saveOrder() {
    const id   = document.getElementById('orderId').value;
    const name = document.getElementById('orderItemName').value.trim();
    const qty  = parseInt(document.getElementById('orderQty').value) || 0;

    if (!name)  { showToast('Item name is required', 'error'); return; }
    if (qty < 1){ showToast('Quantity must be at least 1', 'error'); return; }

    const selectEl = document.getElementById('orderItemSelect');
    const invId    = selectEl.value ? parseInt(selectEl.value) : null;

    const data = {
        inventory_id:     invId,
        item_name:        name,
        item_code:        invId ? (inventory.find(i=>i.id===invId)?.item_code || '') : '',
        quantity_ordered: qty,
        supplier:         document.getElementById('orderSupplier').value.trim(),
        order_date:       document.getElementById('orderDate').value,
        expected_delivery:document.getElementById('orderExpected').value || null,
        actual_delivery:  document.getElementById('orderActual').value   || null,
        status:           document.getElementById('orderStatus').value,
        notes:            document.getElementById('orderNotes').value.trim(),
    };

    const url    = id ? `../api/inventory-orders-api.php?id=${id}` : '../api/inventory-orders-api.php';
    const method = id ? 'PUT' : 'POST';

    try {
        const res    = await fetch(url, { method, headers:{'Content-Type':'application/json'}, body: JSON.stringify(data) });
        const result = await res.json();
        if (result.success) {
            showToast(result.message, 'success');
            closeOrderModal();
            loadOrders();
            if (data.status === 'arrived') loadInventory();
        } else { showToast(result.error, 'error'); }
    } catch (e) { showToast('Error saving order', 'error'); }
}

// Auto-refresh orders every 30 seconds
setInterval(loadOrders, 30000);

// Initialize
loadInventory();
loadOrders();
</script>

<style>
.red { background: #fee2e2; color: #dc2626; }
.btn-icon { background: none; border: none; cursor: pointer; padding: 4px 8px; }
.btn-icon:hover { opacity: 0.7; }
</style>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>