<?php
// modules/quotations.php
// Quotations management page

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

checkAuthentication();

$pageTitle = 'Quotations';
$pageSubtitle = 'Create and manage quotations';

include_once __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-file-invoice-dollar"></i>
        </div>
        <div class="stat-value" id="totalValue">₱0</div>
        <div class="stat-label">Total Value</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-value" id="pendingCount">0</div>
        <div class="stat-label">Pending</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-value" id="approvedCount">0</div>
        <div class="stat-label">Approved</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-value" id="conversionRate">0%</div>
        <div class="stat-label">Conversion Rate</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">All Quotations</h3>
        <?php if (checkPermission('quotations', 'create')): ?>
        <button onclick="openQuotationModal()" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Quotation
        </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="filters-bar">
            <div class="filter-group">
                <label class="filter-label">Status</label>
                <select id="statusFilter" class="filter-select">
                    <option value="all">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="under_review">Under Review</option>
                </select>
            </div>
            <div class="filter-group flex-1">
                <label class="filter-label">Search</label>
                <input type="text" id="searchInput" placeholder="Search quotations..." class="form-control">
            </div>
        </div>
        
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Quotation ID</th>
                        <th>Customer / Project</th>
                        <th>Amount</th>
                        <th>Items</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="quotationsTableBody">
                    <tr><td colspan="7" class="text-center">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Quotation Modal -->
<div id="quotationModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3 id="modalTitle" class="modal-title">Add New Quotation</h3>
            <button class="modal-close" onclick="closeQuotationModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="quotationForm">
                <input type="hidden" id="quotationId">

                <!-- Section: Customer & Project -->
                <div style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #F3F4F6">
                    <div class="grid-cols-2">
                        <div class="form-group">
                            <label class="form-label">Customer *</label>
                            <select id="customerId" class="form-select" required onchange="loadProjectsForCustomer()">
                                <option value="">Select Customer</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Project</label>
                            <select id="projectId" class="form-select">
                                <option value="">Select Project (Optional)</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid-cols-2">
                        <div class="form-group" style="margin-bottom:0">
                            <label class="form-label">Quotation Date</label>
                            <input type="date" id="quotationDate" class="form-control">
                        </div>
                        <div class="form-group" style="margin-bottom:0">
                            <label class="form-label">Valid Until</label>
                            <input type="date" id="validUntil" class="form-control">
                        </div>
                    </div>
                </div>

                <!-- Section: Items & Services -->
                <div style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #F3F4F6">
                    <label class="form-label" style="margin-bottom:8px">Items & Services</label>
                    <div style="display:flex;gap:8px;padding:0 2px;margin-bottom:4px">
                        <label class="form-label" style="flex:1;margin-bottom:0;font-size:0.75rem">Description</label>
                        <label class="form-label" style="width:72px;flex-shrink:0;margin-bottom:0;font-size:0.75rem">Qty</label>
                        <label class="form-label" style="width:110px;flex-shrink:0;margin-bottom:0;font-size:0.75rem">Unit Price</label>
                        <label class="form-label" style="width:110px;flex-shrink:0;margin-bottom:0;font-size:0.75rem">Amount</label>
                        <span style="width:28px;flex-shrink:0"></span>
                    </div>
                    <div id="itemsContainer" class="space-y-2"></div>
                    <button type="button" onclick="addItemRow()" class="btn btn-secondary btn-sm mt-2">
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </div>

                <!-- Section: Totals -->
                <div style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #F3F4F6">
                    <div class="grid-cols-3">
                        <div class="form-group" style="margin-bottom:0">
                            <label class="form-label">Subtotal</label>
                            <input type="text" id="subtotal" class="form-control" readonly>
                        </div>
                        <div class="form-group" style="margin-bottom:0">
                            <label class="form-label">Tax (18%)</label>
                            <input type="text" id="taxAmount" class="form-control" readonly>
                        </div>
                        <div class="form-group" style="margin-bottom:0">
                            <label class="form-label">Total Amount</label>
                            <input type="text" id="totalAmount" class="form-control" readonly>
                        </div>
                    </div>
                </div>

                <!-- Section: Status & Notes -->
                <div class="grid-cols-2">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select id="status" class="form-select">
                            <option value="draft">Draft</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="under_review">Under Review</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea id="notes" class="form-textarea" style="min-height:38px"></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeQuotationModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveQuotation()">Save Quotation</button>
        </div>
    </div>
</div>

<script>
let quotations = [];
let customers = [];
let projects = [];
let inventoryItems = [];

async function loadInventoryItems() {
    try {
        const res = await fetch('../api/inventory-api.php');
        const result = await res.json();
        if (result.success) inventoryItems = result.data || [];
    } catch (e) {
        console.error('Error loading inventory:', e);
    }
}

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
        }
    } catch (error) {
        console.error('Error loading projects:', error);
    }
}

function loadProjectsForCustomer() {
    const customerId = parseInt(document.getElementById('customerId').value);
    const projectSelect = document.getElementById('projectId');
    const filteredProjects = projects.filter(p => p.customer_id === customerId);
    projectSelect.innerHTML = '<option value="">Select Project (Optional)</option>' + 
        filteredProjects.map(p => `<option value="${p.id}">${escapeHtml(p.project_name)}</option>`).join('');
}

async function loadQuotations() {
    try {
        const response = await fetch('../api/quotations-api.php');
        const result = await response.json();
        if (result.success) {
            quotations = result.data;
            renderQuotations();
            updateStats();
        }
    } catch (error) {
        console.error('Error loading quotations:', error);
    }
}

function updateStats() {
    const totalValue = quotations.reduce((sum, q) => sum + (q.total_amount || 0), 0);
    const pending = quotations.filter(q => q.status === 'pending').length;
    const approved = quotations.filter(q => q.status === 'approved').length;
    const conversionRate = quotations.length > 0 ? Math.round((approved / quotations.length) * 100) : 0;
    
    document.getElementById('totalValue').innerHTML = formatCurrency(totalValue);
    document.getElementById('pendingCount').textContent = pending;
    document.getElementById('approvedCount').textContent = approved;
    document.getElementById('conversionRate').textContent = conversionRate + '%';
}

function renderQuotations() {
    const search = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const status = document.getElementById('statusFilter')?.value || 'all';
    
    let filtered = quotations.filter(q => {
        const customerName = (q.customer_name || '').toLowerCase();
        if (search && !q.quotation_number.toLowerCase().includes(search) && !customerName.includes(search)) return false;
        if (status !== 'all' && q.status !== status) return false;
        return true;
    });
    
    const tbody = document.getElementById('quotationsTableBody');
    if (!tbody) return;
    
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No quotations found</td></tr>';
        return;
    }
    
    tbody.innerHTML = filtered.map(q => `
        <tr>
            <td class="font-medium">${escapeHtml(q.quotation_number)}</td>
            <td>
                <div class="font-medium">${escapeHtml(q.customer_name)}</div>
                <div class="text-sm text-gray-500">${escapeHtml(q.project_name || '-')}</div>
            </td>
            <td class="font-medium">${formatCurrency(q.total_amount)}</td>
            <td>${q.items_count || 0} items</td>
            <td>
                <div>${formatDate(q.quotation_date)}</div>
                <div class="text-xs text-gray-500">Valid: ${formatDate(q.valid_until)}</div>
            </td>
            <td>${getStatusBadgeHtml(q.status)}</td>
            <td>
                <div style="display:flex;align-items:center;gap:6px">
                    <button onclick="viewQuotation(${q.id})" class="btn-icon" title="View">
                        <i class="fas fa-eye" style="color:#3B82F6"></i>
                    </button>
                    ${(USER_ROLE === 'super_admin' || USER_ROLE === 'owner') ? `
                    <button onclick="editQuotation(${q.id})" class="btn-icon" title="Edit">
                        <i class="fas fa-edit" style="color:#F97316"></i>
                    </button>` : ''}
                    ${(USER_ROLE === 'super_admin' || USER_ROLE === 'owner') ? `
                    <button onclick="archiveQuotation(${q.id})" class="btn-icon" title="Archive">
                        <i class="fas fa-archive" style="color:#6B7280"></i>
                    </button>` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

function getStatusBadgeHtml(status) {
    const badges = {
        draft: 'badge-secondary',
        pending: 'badge-warning',
        approved: 'badge-success',
        rejected: 'badge-danger',
        under_review: 'badge-info'
    };
    const labels = {
        draft: 'Draft',
        pending: 'Pending',
        approved: 'Approved',
        rejected: 'Rejected',
        under_review: 'Under Review'
    };
    return `<span class="badge ${badges[status]}">${labels[status]}</span>`;
}

let itemCounter = 0;

function buildItemOptions(selectedDesc) {
    let html = '<option value="">— Select item —</option>';
    inventoryItems.forEach(inv => {
        const sel = selectedDesc === inv.item_name ? 'selected' : '';
        html += `<option value="${escapeHtml(inv.item_name)}" data-price="${inv.unit_price}" ${sel}>${escapeHtml(inv.item_name)}</option>`;
    });
    return html;
}

function addItemRow(item = null) {
    const container = document.getElementById('itemsContainer');
    const itemId = ++itemCounter;
    const desc  = item?.description || '';
    const qty   = item?.quantity   || 1;
    const price = item?.unit_price || 0;
    const html = `
        <div class="item-row" style="display:flex;gap:8px;margin-bottom:6px" data-id="${itemId}">
            <select class="item-desc form-select" style="flex:1;min-width:0" onchange="onItemSelect(this)">
                ${buildItemOptions(desc)}
            </select>
            <input type="number" class="item-qty form-control" style="width:72px;flex-shrink:0" value="${qty}" min="1" onchange="calculateTotal()">
            <input type="number" class="item-price form-control" style="width:110px;flex-shrink:0" step="0.01" value="${price}" onchange="calculateTotal()">
            <input type="text" class="item-amount form-control" style="width:110px;flex-shrink:0;background:#F9FAFB" readonly value="${formatCurrency(qty * price)}">
            <button type="button" onclick="removeItemRow(this)" style="width:28px;flex-shrink:0;background:none;border:none;cursor:pointer;color:#EF4444">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
    calculateTotal();
}

function onItemSelect(select) {
    const row = select.closest('.item-row');
    const opt = select.options[select.selectedIndex];
    const price = parseFloat(opt.getAttribute('data-price')) || 0;
    if (price > 0) row.querySelector('.item-price').value = price;
    calculateTotal();
}

function removeItemRow(btn) {
    btn.closest('.item-row').remove();
    calculateTotal();
}

function calculateTotal() {
    let subtotal = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const qty   = parseFloat(row.querySelector('.item-qty')?.value)   || 0;
        const price = parseFloat(row.querySelector('.item-price')?.value) || 0;
        const amount = qty * price;
        const amountInput = row.querySelector('.item-amount');
        if (amountInput) amountInput.value = formatCurrency(amount);
        subtotal += amount;
    });
    const tax   = subtotal * 0.18;
    const total = subtotal + tax;
    document.getElementById('subtotal').value    = formatCurrency(subtotal);
    document.getElementById('taxAmount').value   = formatCurrency(tax);
    document.getElementById('totalAmount').value = formatCurrency(total);
}

function openQuotationModal(quotation = null) {
    document.getElementById('itemsContainer').innerHTML = '';
    itemCounter = 0;
    
    if (quotation) {
        document.getElementById('modalTitle').textContent = 'Edit Quotation';
        document.getElementById('quotationId').value = quotation.id;
        document.getElementById('customerId').value = quotation.customer_id;
        loadProjectsForCustomer();
        document.getElementById('projectId').value = quotation.project_id || '';
        document.getElementById('quotationDate').value = quotation.quotation_date;
        document.getElementById('validUntil').value = quotation.valid_until;
        document.getElementById('status').value = quotation.status;
        document.getElementById('notes').value = quotation.notes || '';
        if (quotation.items) {
            quotation.items.forEach(item => addItemRow(item));
        } else {
            for(let i = 0; i < 3; i++) addItemRow();
        }
    } else {
        document.getElementById('modalTitle').textContent = 'Add New Quotation';
        document.getElementById('quotationForm').reset();
        document.getElementById('quotationId').value = '';
        document.getElementById('quotationDate').value = new Date().toISOString().split('T')[0];
        const validUntil = new Date();
        validUntil.setDate(validUntil.getDate() + 30);
        document.getElementById('validUntil').value = validUntil.toISOString().split('T')[0];
        document.getElementById('status').value = 'draft';
        for(let i = 0; i < 3; i++) addItemRow();
    }
    
    document.getElementById('quotationModal').classList.add('active');
}

function closeQuotationModal() {
    document.getElementById('quotationModal').classList.remove('active');
}

async function saveQuotation() {
    const id = document.getElementById('quotationId').value;
    const items = [];
    
    document.querySelectorAll('.item-row').forEach(row => {
        const desc = row.querySelector('.item-desc')?.value;
        if (desc) {
            items.push({
                description: desc,
                quantity: parseFloat(row.querySelector('.item-qty')?.value) || 0,
                unit_price: parseFloat(row.querySelector('.item-price')?.value) || 0,
                amount: (parseFloat(row.querySelector('.item-qty')?.value) || 0) * (parseFloat(row.querySelector('.item-price')?.value) || 0)
            });
        }
    });
    
    const totalAmountStr = document.getElementById('totalAmount').value;
    const totalAmount = parseFloat(totalAmountStr.replace(/[^0-9.-]+/g, '')) || 0;
    
    const data = {
        customer_id: parseInt(document.getElementById('customerId').value),
        project_id: document.getElementById('projectId').value ? parseInt(document.getElementById('projectId').value) : null,
        quotation_date: document.getElementById('quotationDate').value,
        valid_until: document.getElementById('validUntil').value,
        total_amount: totalAmount,
        items_count: items.length,
        status: document.getElementById('status').value,
        notes: document.getElementById('notes').value,
        items: items
    };
    
    if (!data.customer_id) {
        showToast('Please select a customer', 'error');
        return;
    }
    
    const url = id ? `../api/quotations-api.php?id=${id}` : '../api/quotations-api.php';
    const method = id ? 'PUT' : 'POST';
    
    console.log('[Quotation] Saving', method, url, data);
    try {
        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const rawText = await response.text();
        console.log('[Quotation] Raw response:', rawText);
        let result;
        try {
            result = JSON.parse(rawText);
        } catch (parseErr) {
            console.error('[Quotation] JSON parse failed:', parseErr, rawText);
            showToast('Server returned invalid response — check console', 'error');
            return;
        }
        console.log('[Quotation] Parsed result:', result);
        if (result.success) {
            showToast(result.message, 'success');
            closeQuotationModal();
            loadQuotations();
        } else {
            console.error('[Quotation] Server error:', result.error);
            showToast(result.error || 'Failed to save quotation', 'error');
        }
    } catch (error) {
        console.error('[Quotation] Fetch error:', error);
        showToast('Network error saving quotation', 'error');
    }
}

async function viewQuotation(id) {
    try {
        const response = await fetch(`../api/quotations-api.php?id=${id}`);
        const result = await response.json();
        if (!result.success || !result.data) {
            showToast('Error loading quotation details', 'error');
            return;
        }
        const q = result.data;
        const rows = [
            { label: 'Quotation No.',  value: q.quotation_number },
            { label: 'Customer',       value: q.customer_name },
            { label: 'Project',        value: q.project_name || '-' },
            { label: 'Quotation Date', value: formatDate(q.quotation_date) },
            { label: 'Valid Until',    value: formatDate(q.valid_until) },
            { label: 'Status',         value: (q.status || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) },
        ];

        if (q.items && q.items.length > 0) {
            rows.push({ section: 'Items & Services' });
            q.items.forEach((item, i) => {
                const amt = (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0);
                rows.push({
                    label: `Item ${i + 1}`,
                    value: `${item.description}  ×${item.quantity}  @  ${formatCurrency(item.unit_price)}  =  ${formatCurrency(amt)}`
                });
            });
        }

        const subtotal = (q.items || []).reduce((s, item) =>
            s + (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0), 0);
        const tax = subtotal * 0.18;

        rows.push(
            { section: 'Totals' },
            { label: 'Subtotal',     value: formatCurrency(subtotal) },
            { label: 'Tax (18%)',    value: formatCurrency(tax) },
            { label: 'Total Amount', value: formatCurrency(q.total_amount) },
        );

        if (q.notes) rows.push({ section: 'Notes' }, { label: 'Notes', value: q.notes });

        showDetailModal(q.quotation_number, rows);
    } catch (error) {
        showToast('Error loading quotation', 'error');
    }
}

async function editQuotation(id) {
    try {
        const response = await fetch(`../api/quotations-api.php?id=${id}`);
        const result = await response.json();
        if (result.success && result.data) {
            openQuotationModal(result.data);
        }
    } catch (error) {
        showToast('Error loading quotation', 'error');
    }
}

async function archiveQuotation(id) {
    const quotation = quotations.find(q => q.id === id);
    const name = quotation?.quotation_number || 'this quotation';
    showConfirmModal(
        `"${name}" will be archived and hidden from the quotations list.`,
        async () => {
            try {
                const response = await fetch(`../api/quotations-api.php?id=${id}`, {
                    method: 'DELETE'
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    loadQuotations();
                } else {
                    showToast(result.error, 'error');
                }
            } catch (error) {
                showToast('Error archiving quotation', 'error');
            }
        },
        { title: 'Archive Quotation', confirmText: 'Archive' }
    );
}

// Event listeners
document.getElementById('searchInput')?.addEventListener('input', renderQuotations);
document.getElementById('statusFilter')?.addEventListener('change', renderQuotations);

// Initialize
loadInventoryItems();
loadCustomers();
loadProjects();
loadQuotations();
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>