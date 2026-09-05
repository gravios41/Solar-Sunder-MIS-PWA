<?php
// modules/energy-assessments.php
// Bill-based solar sizing

require_once __DIR__ . '/../config/config.php';
requireAuth();
checkPageAccess('energy-assessments');

$pageTitle = 'Energy Assessments';
$pageSubtitle = 'Verify three months of consumption and size a solar system';
include_once __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-file-invoice"></i></div><div class="stat-value" id="assessmentCount">0</div><div class="stat-label">Assessments</div></div>
    <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-bolt"></i></div><div class="stat-value" id="averageUsage">0 kWh</div><div class="stat-label">Latest Average</div></div>
    <div class="stat-card"><div class="stat-icon green"><i class="fas fa-solar-panel"></i></div><div class="stat-value" id="latestSystem">0 kW</div><div class="stat-label">Latest Recommendation</div></div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">New Energy Assessment</h3></div>
    <div class="card-body">
        <form id="assessmentForm">
            <div class="grid-cols-2">
                <div class="form-group"><label class="form-label">Customer *</label><select id="customerId" class="form-select" required><option value="">Select Customer</option></select></div>
                <div class="form-group"><label class="form-label">Panel wattage</label><input id="panelWattage" class="form-control" type="number" value="550" min="100" max="1000"></div>
            </div>
            <div class="grid-cols-3">
                <div class="form-group"><label class="form-label">Peak sun hours</label><input id="peakSunHours" class="form-control" type="number" value="5" min="1" max="10" step="0.1"></div>
                <div class="form-group"><label class="form-label">System efficiency</label><input id="efficiency" class="form-control" type="number" value="80" min="40" max="100" step="1"><small>Percent</small></div>
                <div class="form-group"><label class="form-label">Bills required</label><input class="form-control" value="3 months" readonly></div>
            </div>

            <!-- OCR Bill Upload Section -->
            <div style="border-top:1px solid #e2e8f0;margin-top:20px;padding-top:20px">
                <h4 style="margin:0 0 15px">Upload Bill Images for OCR</h4>
                <div id="billUploadSection">
                    <div class="grid-cols-3" style="gap:15px">
                        <?php for ($i = 1; $i <= 3; $i++): ?>
                        <div class="form-group" style="border:2px dashed #cbd5e1;padding:15px;border-radius:8px;text-align:center">
                            <input type="file" id="billFile<?php echo $i; ?>" class="form-control bill-file-upload" accept="image/jpeg,image/png,image/webp,application/pdf" style="display:none">
                            <label for="billFile<?php echo $i; ?>" style="cursor:pointer;display:block">
                                <div class="form-label" style="margin-bottom:8px">Bill <?php echo $i; ?></div>
                                <i class="fas fa-cloud-upload-alt" style="font-size:24px;color:#94a3b8;margin-bottom:8px;display:block"></i>
                                <small style="color:#64748b">JPG, PNG, WEBP, or PDF</small>
                                <div id="billUploadStatus<?php echo $i; ?>" style="margin-top:8px;font-size:12px"></div>
                            </label>
                            <button type="button" class="btn btn-sm btn-secondary" style="margin-top:8px;display:none" id="billUploadBtn<?php echo $i; ?>" onclick="uploadBill(<?php echo $i; ?>)"><i class="fas fa-upload"></i> Upload</button>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <div id="ocrResultsPanel" style="margin-top:15px;display:none">
                    <h5>OCR Extracted Results</h5>
                    <div class="table-container"><table class="table"><thead><tr><th>Bill</th><th>Billing Period</th><th>Consumption (kWh)</th><th>Status</th></tr></thead><tbody id="ocrResultsTable"></tbody></table></div>
                </div>
            </div>

            <h4 style="margin:20px 0 10px">Or Enter Manually</h4>
            <div class="table-container"><table class="table"><thead><tr><th>Billing month</th><th>Consumption (kWh) *</th><th>Amount</th></tr></thead><tbody>
                <?php for ($i = 0; $i < 3; $i++): ?><tr><td><input class="form-control bill-period" type="month" required></td><td><input class="form-control bill-kwh" type="number" min="0.01" step="0.01" required></td><td><input class="form-control bill-amount" type="number" min="0" step="0.01"></td></tr><?php endfor; ?>
            </tbody></table></div>
            <div class="card" style="margin-top:20px;background:#f8fafc"><div class="card-body"><strong>Recommendation preview</strong><div id="recommendation" style="margin-top:8px;color:#475569">Enter the three monthly kWh readings.</div></div></div>
            <div style="margin-top:20px"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Assessment</button></div>
        </form>
    </div>
</div>

<div class="card"><div class="card-header"><h3 class="card-title">Saved Assessments</h3></div><div class="card-body"><div class="table-container"><table class="table"><thead><tr><th>Customer</th><th>Average monthly</th><th>Recommended system</th><th>Panels</th><th>Status</th><th>Approval</th><th>Actions</th></tr></thead><tbody id="assessmentRows"><tr><td colspan="7" class="text-center">Loading...</td></tr></tbody></table></div></div></div>

<!-- Recommendation Approval Modal -->
<div id="recommendationModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;padding:20px;overflow-y:auto">
    <div style="background:white;border-radius:8px;max-width:600px;margin:40px auto;padding:24px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h3>Review Recommendation</h3>
            <button type="button" onclick="closeRecommendationModal()" class="btn btn-sm btn-secondary"><i class="fas fa-times"></i></button>
        </div>
        <div id="modalContent" style="margin-bottom:20px"></div>
        <div style="display:flex;gap:10px;justify-content:flex-end">
            <button type="button" onclick="closeRecommendationModal()" class="btn btn-secondary">Cancel</button>
            <button type="button" onclick="approveRecommendation()" class="btn btn-success"><i class="fas fa-check"></i> Approve & Create Project</button>
            <button type="button" onclick="rejectRecommendation()" class="btn btn-danger"><i class="fas fa-times"></i> Reject</button>
        </div>
    </div>
</div>

<script>
let ocrBillsData = {};
let currentAssessmentId = null;

const billInputs = [...document.querySelectorAll('.bill-kwh')];
const recommendation = document.getElementById('recommendation');

function calculateRecommendation() {
    const values = billInputs.map(input => Number(input.value)).filter(value => value > 0);
    if (values.length !== 3) { recommendation.textContent = 'Enter the three monthly kWh readings.'; return null; }
    const averageMonthly = values.reduce((sum, value) => sum + value, 0) / 3;
    const daily = averageMonthly / 30;
    const sunHours = Number(document.getElementById('peakSunHours').value) || 5;
    const efficiency = (Number(document.getElementById('efficiency').value) || 80) / 100;
    const panelWattage = Number(document.getElementById('panelWattage').value) || 550;
    const systemKw = daily / sunHours / efficiency;
    const panels = Math.ceil(systemKw * 1000 / panelWattage);
    recommendation.textContent = `Average ${averageMonthly.toFixed(2)} kWh/month · ${daily.toFixed(2)} kWh/day · approximately ${systemKw.toFixed(2)} kW · ${panels} panels at ${panelWattage} W`;
    return { averageMonthly, systemKw, panels };
}

document.querySelectorAll('#assessmentForm input').forEach(input => input.addEventListener('input', calculateRecommendation));

// OCR Upload Handler
document.querySelectorAll('.bill-file-upload').forEach((input, index) => {
    input.addEventListener('change', function() {
        const billNum = index + 1;
        const fileName = this.files[0]?.name || '';
        const statusEl = document.getElementById(`billUploadStatus${billNum}`);
        const uploadBtn = document.getElementById(`billUploadBtn${billNum}`);
        
        if (this.files.length > 0) {
            statusEl.innerHTML = `<span style="color:#16a34a">${fileName}</span>`;
            uploadBtn.style.display = 'inline-block';
        } else {
            statusEl.innerHTML = '';
            uploadBtn.style.display = 'none';
        }
    });
});

async function uploadBill(billNum) {
    const customerId = document.getElementById('customerId').value;
    
    // First save a temporary assessment if not yet created
    if (!currentAssessmentId) {
        const tempBills = [{billing_period: '2025-01-01', consumption_kwh: 0}, {billing_period: '2025-01-01', consumption_kwh: 0}, {billing_period: '2025-01-01', consumption_kwh: 0}];
        const response = await fetch('../api/energy-assessments-api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                customer_id: customerId,
                bills: tempBills,
                peak_sun_hours: 5,
                system_efficiency: 0.80,
                panel_wattage: 550,
                is_placeholder: true
            })
        });
        const result = await response.json();
        if (result.success) {
            currentAssessmentId = result.assessment_id;
        } else {
            showToast('Failed to create assessment', 'error');
            return;
        }
    }

    const fileInput = document.getElementById(`billFile${billNum}`);
    const file = fileInput.files[0];
    
    if (!file) {
        showToast('Please select a file', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('assessment_id', currentAssessmentId);
    formData.append('bill_image', file);

    const statusEl = document.getElementById(`billUploadStatus${billNum}`);
    statusEl.innerHTML = '<span style="color:#3b82f6">Processing...</span>';

    try {
        const response = await fetch('../api/bill-upload-ocr.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            ocrBillsData[billNum] = result.extracted;
            statusEl.innerHTML = `<span style="color:#16a34a">✓ ${result.extracted.consumption_kwh} kWh</span>`;
            updateOCRResults();
            fillManualRowFromOcr(billNum, result.extracted);
            showToast('Bill uploaded and OCR completed');
        } else {
            statusEl.innerHTML = `<span style="color:#dc2626">Error: ${result.error}</span>`;
            showToast(result.error || 'OCR failed', 'error');
        }
    } catch (error) {
        statusEl.innerHTML = `<span style="color:#dc2626">Error: ${error.message}</span>`;
        showToast(error.message, 'error');
    }
}

function fillManualRowFromOcr(billNum, extracted) {
    // Carries the OCR result into the same manual-entry row so "Save
    // Assessment" uses the scanned values instead of requiring retyping.
    const periodInput = document.querySelectorAll('.bill-period')[billNum - 1];
    const kwhInput = document.querySelectorAll('.bill-kwh')[billNum - 1];

    if (kwhInput && extracted.consumption_kwh > 0) {
        kwhInput.value = extracted.consumption_kwh;
    }
    if (periodInput && extracted.billing_period) {
        // <input type="month"> expects YYYY-MM; OCR returns YYYY-MM-DD
        periodInput.value = extracted.billing_period.slice(0, 7);
    }
    calculateRecommendation();
}

function updateOCRResults() {
    if (Object.keys(ocrBillsData).length > 0) {
        document.getElementById('ocrResultsPanel').style.display = 'block';
        const rows = Object.entries(ocrBillsData)
            .map(([billNum, data]) => `<tr><td>Bill ${billNum}</td><td>${data.billing_period || 'N/A'}</td><td>${data.consumption_kwh.toFixed(2)} kWh</td><td><span style="color:#16a34a">✓ Verified</span></td></tr>`)
            .join('');
        document.getElementById('ocrResultsTable').innerHTML = rows;
    }
}

async function loadCustomers() { 
    const response = await fetch('../api/customers-api.php'); 
    const result = await response.json(); 
    if (result.success) document.getElementById('customerId').innerHTML += (result.data || []).map(customer => `<option value="${customer.id}">${escapeHtml(customer.name)}</option>`).join(''); 
}

async function loadAssessments() { 
    const response = await fetch('../api/energy-assessments-api.php'); 
    const result = await response.json(); 
    if (!result.success) return; 
    const rows = result.data || []; 
    document.getElementById('assessmentCount').textContent = rows.length; 
    if (rows[0]) { 
        document.getElementById('averageUsage').textContent = `${Number(rows[0].average_monthly_kwh).toFixed(0)} kWh`; 
        document.getElementById('latestSystem').textContent = `${Number(rows[0].recommended_system_kw).toFixed(2)} kW`; 
    } 
    document.getElementById('assessmentRows').innerHTML = rows.length ? rows.map(row => {
        const approvalStatus = row.approval_status ? `<span style="color:${row.approval_status === 'approved' ? '#16a34a' : '#f59e0b'}">${row.approval_status}</span>` : '—';
        const actions = !row.approval_status || row.approval_status === 'pending' ? `<button type="button" class="btn btn-sm btn-primary" onclick="showRecommendationModal('${row.id}')">Review</button>` : '';
        return `<tr>
            <td>${escapeHtml(row.customer_name)}</td>
            <td>${Number(row.average_monthly_kwh).toFixed(2)} kWh</td>
            <td>${Number(row.recommended_system_kw).toFixed(2)} kW</td>
            <td>${row.recommended_panel_count}</td>
            <td>${escapeHtml(row.status)}</td>
            <td>${approvalStatus}</td>
            <td>${actions}</td>
        </tr>`;
    }).join('') : '<tr><td colspan="7" class="text-center">No assessments yet</td></tr>'; 
}

function showRecommendationModal(assessmentId) {
    currentAssessmentId = assessmentId;
    const modal = document.getElementById('recommendationModal');
    const content = document.getElementById('modalContent');
    content.innerHTML = `<div style="text-align:center">Loading recommendation details...</div>`;
    modal.style.display = 'block';

    // Fetch assessment details
    fetch(`../api/energy-assessments-api.php?id=${assessmentId}`)
        .then(r => r.json())
        .then(result => {
            if (result.success && result.data.length > 0) {
                const assessment = result.data[0];
                content.innerHTML = `
                    <h4>${assessment.customer_name}</h4>
                    <div style="background:#f8fafc;padding:12px;border-radius:6px;margin-bottom:15px">
                        <p><strong>Average consumption:</strong> ${Number(assessment.average_monthly_kwh).toFixed(2)} kWh/month</p>
                        <p><strong>Daily average:</strong> ${Number(assessment.average_daily_kwh).toFixed(2)} kWh/day</p>
                        <p><strong>Recommended system size:</strong> ${Number(assessment.recommended_system_kw).toFixed(2)} kW</p>
                        <p><strong>Panel count (${assessment.panel_wattage}W):</strong> ${assessment.recommended_panel_count} panels</p>
                        <p><strong>Peak sun hours:</strong> ${assessment.peak_sun_hours}</p>
                        <p><strong>System efficiency:</strong> ${(assessment.system_efficiency * 100).toFixed(0)}%</p>
                    </div>
                    <p style="color:#64748b;font-size:13px">Approving this recommendation creates a project and a draft quotation for review. Inventory is only deducted, and the installation and task list only created, once that quotation is approved in the Quotations module.</p>
                `;
            }
        });
}

function closeRecommendationModal() {
    document.getElementById('recommendationModal').style.display = 'none';
    currentAssessmentId = null;
}

async function approveRecommendation() {
    if (!currentAssessmentId) return;
    
    const response = await fetch('../api/create-approved-project.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({assessment_id: currentAssessmentId})
    });
    const result = await response.json();
    
    if (result.success) {
        showToast(result.message || 'Recommendation approved');
        closeRecommendationModal();
        loadAssessments();
    } else {
        showToast(result.error || 'Failed to approve', 'error');
    }
}

async function rejectRecommendation() {
    if (!currentAssessmentId) return;
    
    const response = await fetch('../api/energy-assessments-api.php', {
        method: 'PUT',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            id: currentAssessmentId,
            approval_status: 'rejected'
        })
    });
    const result = await response.json();
    
    if (result.success) {
        showToast('Recommendation rejected');
        closeRecommendationModal();
        loadAssessments();
    } else {
        showToast(result.error || 'Failed to reject', 'error');
    }
}

document.getElementById('assessmentForm').addEventListener('submit', async event => { 
    event.preventDefault(); 
    const recommendationData = calculateRecommendation(); 
    if (!recommendationData) return; 
    const bills = billInputs.map((input, index) => ({
        billing_period: document.querySelectorAll('.bill-period')[index].value + '-01',
        consumption_kwh: input.value,
        amount: document.querySelectorAll('.bill-amount')[index].value
    }));
    const payload = {
        customer_id: document.getElementById('customerId').value,
        bills,
        peak_sun_hours: document.getElementById('peakSunHours').value,
        system_efficiency: Number(document.getElementById('efficiency').value) / 100,
        panel_wattage: document.getElementById('panelWattage').value
    };
    // If bills were already scanned via OCR, an assessment placeholder
    // exists for them — finalize that same record instead of creating a
    // second, disconnected one and leaving the placeholder orphaned.
    const finalizingPlaceholder = !!currentAssessmentId;
    const response = await fetch('../api/energy-assessments-api.php', {
        method: finalizingPlaceholder ? 'PUT' : 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(finalizingPlaceholder ? { ...payload, id: currentAssessmentId } : payload)
    });
    const result = await response.json();
    if (result.success) {
        showToast(result.message);
        event.target.reset();
        document.getElementById('panelWattage').value = 550;
        document.getElementById('peakSunHours').value = 5;
        document.getElementById('efficiency').value = 80;
        currentAssessmentId = null;
        ocrBillsData = {};
        document.getElementById('ocrResultsPanel').style.display = 'none';
        loadAssessments();
    } else showToast(result.error || 'Unable to save assessment', 'error');
});

loadCustomers(); loadAssessments();
</script>
<?php include_once __DIR__ . '/../includes/footer.php'; ?>
