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
                    <!-- Shown for quotations tied to an existing Customer record -->
                    <div id="customerFieldsSection" class="grid-cols-2">
                        <div class="form-group">
                            <label class="form-label">Customer *</label>
                            <select id="customerId" class="form-select" onchange="loadProjectsForCustomer()">
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
                    <!-- Shown instead, for a draft quotation from Energy Assessments whose
                         client has no Customer record yet — one is created automatically
                         when this quotation is approved. -->
                    <div id="clientNameFieldsSection" style="display:none">
                        <div class="grid-cols-2">
                            <div class="form-group">
                                <label class="form-label">Client Name *</label>
                                <input type="text" id="clientNameField" class="form-control" placeholder="Full name">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="tel" id="clientPhoneField" class="form-control">
                            </div>
                        </div>
                        <div class="grid-cols-2">
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" id="clientEmailField" class="form-control">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Address</label>
                                <input type="text" id="clientAddressField" class="form-control">
                            </div>
                        </div>
                        <p style="font-size:11px;color:#94a3b8;margin:-4px 0 12px"><i class="fas fa-circle-info"></i> No Customer record exists yet for this client — one is created automatically when this quotation is approved.</p>
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
                        <label class="form-label" style="width:64px;flex-shrink:0;margin-bottom:0;font-size:0.75rem">Qty</label>
                        <label class="form-label" style="width:100px;flex-shrink:0;margin-bottom:0;font-size:0.75rem">Unit Price</label>
                        <label class="form-label" style="width:100px;flex-shrink:0;margin-bottom:0;font-size:0.75rem">Amount</label>
                        <span style="width:24px;flex-shrink:0"></span>
                    </div>
                    <div id="itemsContainer"></div>
                    <p style="font-size:11px;color:#94a3b8;margin:6px 0 0">Each category can hold as many items as needed — use "+ Add" to add another, or leave a category empty to skip it.</p>
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

<!-- One-click PDF download for the quotation — client-side generation, no server changes needed -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

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
                    <button onclick="downloadQuotationPdf(${q.id})" class="btn-icon" title="Download PDF">
                        <i class="fas fa-file-pdf" style="color:#DC2626"></i>
                    </button>
                    ${(USER_ROLE === 'super_admin' || USER_ROLE === 'owner') && q.status !== 'approved' ? `
                    <button onclick="approveQuotation(${q.id})" class="btn-icon" title="Approve — deducts inventory and creates the installation and tasks">
                        <i class="fas fa-check-circle" style="color:#16a34a"></i>
                    </button>` : ''}
                    ${(USER_ROLE === 'super_admin' || USER_ROLE === 'owner') && q.status !== 'approved' ? `
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

// One fixed row per category, in a natural solar system build sequence —
// Each category gets its own section, grouping the dropdown to just that
// category's items — but a category like Accessories is naturally a basket
// of several distinct things (connectors, tape, lugs, breakers...) needed
// together, not a single choice, so every section can hold multiple rows
// via its own "+ Add" button, not just one fixed row.
const QUOTATION_ROW_CATEGORIES = [
    { key: 'solar_panel', label: 'Solar Panel' },
    { key: 'inverter', label: 'Inverter' },
    { key: 'battery', label: 'Battery' },
    { key: 'mounting', label: 'Mounting' },
    { key: 'cable', label: 'Cable' },
    { key: 'accessories', label: 'Accessories' },
    { key: 'services', label: 'Services' }
];

// Accessories is naturally a basket of several distinct small items
// (connectors, tape, lugs, breakers...) needed together, and a quotation
// can just as easily need several DIFFERENT services at once (e.g.
// Installation & Labor plus a System Upgrade) — every other category is
// a single physical choice per quotation, so only these two get a button
// to add more rows, and their rows can be removed outright since another
// can always be added back. A single-row category's row can only be
// cleared back to "None", never removed, since there'd be no way to bring
// it back without an "Add" button.
const MULTI_ITEM_CATEGORIES = ['accessories', 'services'];

// Services aren't physical stock, so they don't belong in Inventory — this
// is a fixed list rather than a category pulled from inventoryItems.
// Priced at 0 by default: labor/service cost is quoted per job, not a
// fixed catalog price, so staff fill in the actual amount per quotation.
const SERVICE_OPTIONS = [
    'Site Survey & Assessment',
    'System Design & Engineering',
    'Installation & Labor',
    'Permit Processing & Documentation',
    'Grid Connection & System Testing',
    'Customer Training & Handover',
    'System Upgrade',
    'Maintenance Service',
    'Annual Maintenance Service'
];

function categoryItemSource(categoryKey) {
    return categoryKey === 'services'
        ? SERVICE_OPTIONS.map(name => ({ item_name: name, unit_price: 0 }))
        : inventoryItems.filter(inv => inv.category === categoryKey);
}

function buildCategoryOptions(categoryKey, selectedDesc) {
    let html = '<option value="">— None —</option>';
    categoryItemSource(categoryKey).forEach(inv => {
        const sel = selectedDesc === inv.item_name ? 'selected' : '';
        html += `<option value="${escapeHtml(inv.item_name)}" data-price="${inv.unit_price}" ${sel}>${escapeHtml(inv.item_name)}</option>`;
    });
    return html;
}

// Matches saved quotation line items back to the section for their
// category, so editing an existing quotation re-populates every row.
function findExistingItemsForCategory(categoryKey, existingItems) {
    if (!existingItems || !existingItems.length) return [];
    if (categoryKey === 'services') {
        return existingItems.filter(it => SERVICE_OPTIONS.includes(it.description));
    }
    return existingItems.filter(it => {
        const inv = inventoryItems.find(i => i.item_name === it.description);
        return inv && inv.category === categoryKey;
    });
}

function buildItemRowHtml(categoryKey, existingItem) {
    const desc  = existingItem?.description || '';
    const qty   = existingItem?.quantity || 1;
    const price = existingItem?.unit_price || 0;
    // Unit price always comes from the selected inventory item, so it stays
    // locked to that value — no manual override that could drift from
    // actual stock cost. Services have no catalog price (quoted per job),
    // so that one category keeps its price field editable.
    const priceIsEditable = categoryKey === 'services';
    return `
        <div class="item-row" style="display:flex;gap:8px;margin-bottom:6px;align-items:center">
            <select class="item-desc form-select" style="flex:1;min-width:0" onchange="onItemSelect(this)">
                ${buildCategoryOptions(categoryKey, desc)}
            </select>
            <input type="number" class="item-qty form-control" style="width:64px;flex-shrink:0" value="${qty}" min="1" onchange="calculateTotal()">
            <input type="number" class="item-price form-control" style="width:100px;flex-shrink:0;${priceIsEditable ? '' : 'background:#F1F5F9;cursor:not-allowed'}" step="0.01" value="${price}" ${priceIsEditable ? '' : 'disabled'} onchange="calculateTotal()">
            <input type="text" class="item-amount form-control" style="width:100px;flex-shrink:0;background:#F1F5F9;cursor:not-allowed" disabled value="${formatCurrency(qty * price)}">
            ${MULTI_ITEM_CATEGORIES.includes(categoryKey) ? `
            <button type="button" onclick="removeItemRow(this)" title="Remove this row" style="width:24px;flex-shrink:0;background:none;border:none;cursor:pointer;color:#EF4444">
                <i class="fas fa-trash"></i>
            </button>` : `
            <button type="button" onclick="clearItemRow(this)" title="Clear selection" style="width:24px;flex-shrink:0;background:none;border:none;cursor:pointer;color:#94A3B8">
                <i class="fas fa-times"></i>
            </button>`}
        </div>
    `;
}

function renderItemRows(existingItems) {
    const container = document.getElementById('itemsContainer');
    container.innerHTML = QUOTATION_ROW_CATEGORIES.map(cat => {
        const existingForCat = findExistingItemsForCategory(cat.key, existingItems);
        const rowsHtml = (existingForCat.length ? existingForCat : [null])
            .map(item => buildItemRowHtml(cat.key, item))
            .join('');
        const canAddMore = MULTI_ITEM_CATEGORIES.includes(cat.key);
        return `
            <div class="category-section" data-category="${cat.key}" style="margin-bottom:12px">
                <div style="font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:4px">${escapeHtml(cat.label)}</div>
                <div class="category-rows">${rowsHtml}</div>
                ${canAddMore ? `
                <button type="button" onclick="addCategoryRow('${cat.key}')" class="btn btn-secondary btn-sm" style="margin-top:2px;font-size:0.72rem;padding:3px 10px">
                    <i class="fas fa-plus"></i> Add ${escapeHtml(cat.label)}
                </button>` : ''}
            </div>
        `;
    }).join('');
    calculateTotal();
}

function addCategoryRow(categoryKey) {
    const section = document.querySelector(`.category-section[data-category="${categoryKey}"] .category-rows`);
    if (section) section.insertAdjacentHTML('beforeend', buildItemRowHtml(categoryKey, null));
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

function clearItemRow(btn) {
    const row = btn.closest('.item-row');
    row.querySelector('.item-desc').value = '';
    row.querySelector('.item-qty').value = 1;
    row.querySelector('.item-price').value = 0;
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

// True while the modal is editing a quotation that has no Customer record
// yet (created from an Energy Assessment for a not-yet-existing client) —
// toggles which of the two field sections above is active/validated.
let editingClientOnly = false;

function setQuotationCustomerMode(isClientOnly) {
    editingClientOnly = isClientOnly;
    document.getElementById('customerFieldsSection').style.display = isClientOnly ? 'none' : '';
    document.getElementById('clientNameFieldsSection').style.display = isClientOnly ? 'block' : 'none';
    document.getElementById('customerId').required = !isClientOnly;
}

function openQuotationModal(quotation = null) {
    if (quotation) {
        document.getElementById('modalTitle').textContent = 'Edit Quotation';
        document.getElementById('quotationId').value = quotation.id;

        if (!quotation.customer_id && quotation.client_name) {
            setQuotationCustomerMode(true);
            document.getElementById('clientNameField').value = quotation.client_name || '';
            document.getElementById('clientPhoneField').value = quotation.client_phone || '';
            document.getElementById('clientEmailField').value = quotation.client_email || '';
            document.getElementById('clientAddressField').value = quotation.client_address || '';
        } else {
            setQuotationCustomerMode(false);
            document.getElementById('customerId').value = quotation.customer_id;
            loadProjectsForCustomer();
            document.getElementById('projectId').value = quotation.project_id || '';
        }

        document.getElementById('quotationDate').value = quotation.quotation_date;
        document.getElementById('validUntil').value = quotation.valid_until;
        document.getElementById('status').value = quotation.status;
        document.getElementById('notes').value = quotation.notes || '';
        renderItemRows(quotation.items || []);
    } else {
        document.getElementById('modalTitle').textContent = 'Add New Quotation';
        document.getElementById('quotationForm').reset();
        document.getElementById('quotationId').value = '';
        setQuotationCustomerMode(false);
        document.getElementById('quotationDate').value = new Date().toISOString().split('T')[0];
        const validUntil = new Date();
        validUntil.setDate(validUntil.getDate() + 30);
        document.getElementById('validUntil').value = validUntil.toISOString().split('T')[0];
        document.getElementById('status').value = 'draft';
        renderItemRows(null);
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
        quotation_date: document.getElementById('quotationDate').value,
        valid_until: document.getElementById('validUntil').value,
        total_amount: totalAmount,
        items_count: items.length,
        status: document.getElementById('status').value,
        notes: document.getElementById('notes').value,
        items: items
    };

    if (editingClientOnly) {
        data.client_name = document.getElementById('clientNameField').value.trim();
        data.client_phone = document.getElementById('clientPhoneField').value.trim();
        data.client_email = document.getElementById('clientEmailField').value.trim();
        data.client_address = document.getElementById('clientAddressField').value.trim();
        if (!data.client_name) {
            showToast('Please enter the client\'s name', 'error');
            return;
        }
    } else {
        data.customer_id = parseInt(document.getElementById('customerId').value);
        data.project_id = document.getElementById('projectId').value ? parseInt(document.getElementById('projectId').value) : null;
        if (!data.customer_id) {
            showToast('Please select a customer', 'error');
            return;
        }
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

// One-click PDF — built entirely client-side (jsPDF + autotable) from the
// same data the View/Edit modals use, so what the client receives always
// matches what's on screen. No server-side PDF library needed.
async function downloadQuotationPdf(id) {
    try {
        const response = await fetch(`../api/quotations-api.php?id=${id}`);
        const result = await response.json();
        if (!result.success || !result.data) {
            showToast('Error loading quotation for PDF', 'error');
            return;
        }
        const q = result.data;
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();

        // Header band
        doc.setFillColor(249, 115, 22);
        doc.rect(0, 0, 210, 28, 'F');
        doc.setTextColor(255, 255, 255);
        doc.setFontSize(16);
        doc.setFont(undefined, 'bold');
        doc.text('Sunder Solar Energy', 14, 13);
        doc.setFontSize(10);
        doc.setFont(undefined, 'normal');
        doc.text('Solar PV Quotation', 14, 20);
        doc.setFontSize(13);
        doc.setFont(undefined, 'bold');
        doc.text(q.quotation_number || '', 196, 16, { align: 'right' });

        doc.setTextColor(30, 41, 59);
        let y = 38;
        doc.setFontSize(11);
        doc.setFont(undefined, 'bold');
        doc.text('Prepared for:', 14, y);
        doc.setFont(undefined, 'normal');
        doc.text(q.customer_name || q.client_name || 'Client', 45, y);
        y += 6;
        if (q.client_phone) { doc.text(`Phone: ${q.client_phone}`, 14, y); y += 6; }
        if (q.client_email) { doc.text(`Email: ${q.client_email}`, 14, y); y += 6; }
        if (q.client_address) { doc.text(`Address: ${q.client_address}`, 14, y); y += 6; }

        doc.setFont(undefined, 'bold');
        doc.text('Quotation Date:', 130, 38);
        doc.setFont(undefined, 'normal');
        doc.text(q.quotation_date ? formatDate(q.quotation_date) : '-', 168, 38);
        doc.setFont(undefined, 'bold');
        doc.text('Valid Until:', 130, 44);
        doc.setFont(undefined, 'normal');
        doc.text(q.valid_until ? formatDate(q.valid_until) : '-', 168, 44);

        y = Math.max(y, 50) + 4;

        const items = q.items || [];
        const rows = items.map(item => [
            item.description || '',
            String(item.quantity ?? ''),
            formatCurrency(item.unit_price),
            formatCurrency((parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0))
        ]);
        doc.autoTable({
            startY: y,
            head: [['Description', 'Qty', 'Unit Price', 'Amount']],
            body: rows,
            theme: 'grid',
            headStyles: { fillColor: [249, 115, 22], textColor: 255 },
            styles: { fontSize: 9 },
            columnStyles: { 1: { halign: 'right' }, 2: { halign: 'right' }, 3: { halign: 'right' } }
        });

        const subtotal = items.reduce((s, item) => s + (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0), 0);
        const tax = subtotal * 0.18;
        let ty = doc.lastAutoTable.finalY + 8;
        doc.setFontSize(10);
        const totalsLine = (label, value, bold) => {
            doc.setFont(undefined, bold ? 'bold' : 'normal');
            doc.text(label, 140, ty);
            doc.text(value, 196, ty, { align: 'right' });
            ty += 6;
        };
        totalsLine('Subtotal', formatCurrency(subtotal), false);
        totalsLine('Tax (18%)', formatCurrency(tax), false);
        totalsLine('Total Amount', formatCurrency(q.total_amount), true);

        if (q.notes) {
            ty += 6;
            doc.setFont(undefined, 'bold');
            doc.setFontSize(10);
            doc.text('Notes', 14, ty);
            ty += 5;
            doc.setFont(undefined, 'normal');
            const noteLines = doc.splitTextToSize(q.notes, 180);
            doc.text(noteLines, 14, ty);
        }

        doc.setFontSize(8);
        doc.setTextColor(148, 163, 184);
        doc.text('This quotation is an estimate and subject to final site assessment.', 14, 287);

        doc.save(`${q.quotation_number || 'Quotation'}.pdf`);
    } catch (error) {
        console.error('PDF generation error:', error);
        showToast('Error generating PDF', 'error');
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

// Two separate confirmations, since this is an irreversible action that
// deducts real stock and creates an installation + task list: the first
// is the general warning; the second names exactly which real inventory
// items will be deducted (fetched fresh, matched against inventoryItems
// the same way approve-quotation.php itself matches them — a service
// line simply won't match anything and is correctly left out), so the
// second click is an informed one, not just re-confirming blind.
async function approveQuotation(id) {
    const quotation = quotations.find(q => q.id === id);
    const name = quotation?.quotation_number || 'this quotation';

    showConfirmModal(
        `Approve "${name}"? This deducts its line items from inventory and creates the installation and task list — it can't be undone from here.`,
        async () => {
            let deductionSummary = 'No matching inventory items were found to deduct — only services on this quotation.';
            try {
                const res = await fetch(`../api/quotations-api.php?id=${id}`);
                const result = await res.json();
                const items = (result.success && result.data?.items) || [];
                const deductions = items
                    .map(item => ({ ...item, inv: inventoryItems.find(inv => inv.item_name === item.description) }))
                    .filter(item => item.inv);
                if (deductions.length > 0) {
                    deductionSummary = 'This will deduct: ' + deductions.map(d => `${d.description} ×${d.quantity}`).join(', ');
                }
            } catch (e) {
                deductionSummary = 'Could not verify exact deductions ahead of time — approving will still deduct whatever matches inventory.';
            }

            showConfirmModal(
                `Final confirmation for "${name}". ${deductionSummary}. This cannot be undone. Approve now?`,
                async () => {
                    try {
                        const response = await fetch('../api/approve-quotation.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ quotation_id: id })
                        });
                        const result = await response.json();
                        if (result.success) {
                            showToast(result.message, 'success');
                            loadQuotations();
                        } else {
                            showToast(result.error, 'error');
                        }
                    } catch (error) {
                        showToast('Error approving quotation', 'error');
                    }
                },
                { title: 'Confirm Approval', confirmText: 'Yes, Approve' }
            );
        },
        { title: 'Approve Quotation', confirmText: 'Continue' }
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