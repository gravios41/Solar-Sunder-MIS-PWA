<?php
// modules/energy-assessments.php
// Bill-based solar sizing

require_once __DIR__ . '/../config/config.php';
requireAuth();
checkPageAccess('energy-assessments');

$pageTitle = 'Energy Assessments';
$pageSubtitle = 'Verify a bill and size a solar system';
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
            <p style="margin:0 0 16px;font-size:12px;color:#64748b"><i class="fas fa-circle-info"></i> No Client record is created yet — the details below carry over to the quotation automatically and become the real Client record once that quotation is approved.</p>
            <div class="form-group"><label class="form-label">Client Name *</label><input id="clientName" class="form-control" type="text" placeholder="Full name of the client" required></div>
            <div class="form-group">
                <label class="form-label">Contact Person</label>
                <input type="text" id="clientContactPerson" class="form-control">
            </div>
            <div class="grid-cols-2">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" id="clientEmail" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone *</label>
                    <input type="tel" id="clientPhone" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Address</label>
                <textarea id="clientAddress" class="form-textarea"></textarea>
            </div>
            <div class="grid-cols-2">
                <div class="form-group">
                    <label class="form-label">City</label>
                    <input type="text" id="clientCity" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Province</label>
                    <input type="text" id="clientState" class="form-control">
                </div>
            </div>
            <div class="grid-cols-2">
                <div class="form-group">
                    <label class="form-label">Postal Code</label>
                    <input type="text" id="clientPincode" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">GSTIN</label>
                    <input type="text" id="clientGstin" class="form-control">
                </div>
            </div>
            <div class="grid-cols-2">
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select id="clientType" class="form-select">
                        <option value="commercial">Commercial</option>
                        <option value="residential" selected>Residential</option>
                        <option value="industrial">Industrial</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select id="clientStatus" class="form-select">
                        <option value="active">Active</option>
                        <option value="pending">Pending</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <!-- OCR Bill Upload Section -->
            <div style="border-top:1px solid #e2e8f0;margin-top:20px;padding-top:20px">
                <h4 style="margin:0 0 15px">Upload Bill Image for OCR</h4>

                <div class="form-group" style="max-width:360px;margin-bottom:18px">
                    <label class="form-label">Solar System Type</label>
                    <select id="systemType" class="form-control">
                        <option value="hybrid">Hybrid Systems</option>
                        <option value="grid_tied">Grid-Tied Systems</option>
                        <option value="off_grid">Off-Grid Systems</option>
                    </select>
                    <small style="color:#64748b;display:block;margin-top:4px">Changes battery sizing in the recommendation — Grid-Tied needs none, Off-Grid needs much more.</small>
                </div>

                <div id="billUploadHint" style="margin:-8px 0 15px;font-size:12.5px;color:#F97316"><i class="fas fa-circle-info"></i> Enter the client's name above before uploading a bill.</div>
                <div id="billUploadSection">
                    <div style="max-width:280px;gap:15px">
                        <div class="form-group" style="border:2px dashed #cbd5e1;padding:15px;border-radius:8px;text-align:center">
                            <input type="file" id="billFile1" class="form-control bill-file-upload" accept="image/jpeg,image/png,image/webp,application/pdf" style="display:none" disabled>
                            <label for="billFile1" style="cursor:pointer;display:block">
                                <div class="form-label" style="margin-bottom:8px">Bill</div>
                                <i class="fas fa-cloud-upload-alt" style="font-size:24px;color:#94a3b8;margin-bottom:8px;display:block"></i>
                                <small style="color:#64748b">JPG, PNG, WEBP, or PDF</small>
                                <div id="billUploadStatus1" style="margin-top:8px;font-size:12px"></div>
                            </label>
                            <button type="button" class="btn btn-sm btn-secondary" style="margin-top:8px;display:none" id="billUploadBtn1" onclick="uploadBill(1)"><i class="fas fa-upload"></i> Upload</button>
                        </div>
                    </div>
                </div>
                <div id="ocrResultsPanel" style="margin-top:15px;display:none">
                    <h5>OCR Extracted Results</h5>
                    <div class="table-container"><table class="table"><thead><tr><th>Bill</th><th>Billing Period</th><th>Consumption (kWh)</th><th>Status</th></tr></thead><tbody id="ocrResultsTable"></tbody></table></div>
                </div>
            </div>

            <h4 style="margin:20px 0 10px">Or Enter Manually</h4>
            <div class="form-group" style="max-width:220px"><label class="form-label">Peak Sun Hours</label><input id="peakSunHours" class="form-control" type="number" value="5" min="1" max="10" step="0.1"></div>
            <div class="table-container"><table class="table manual-bills-table"><thead><tr><th>Billing Month</th><th>Monthly Consumption (kWh) *</th><th>Monthly Bill (₱)</th></tr></thead><tbody>
                <tr><td><input class="form-control bill-period" type="month" required></td><td><input class="form-control bill-kwh" type="number" min="0.01" step="0.01" required></td><td><input class="form-control bill-amount" type="number" min="0" step="0.01"></td></tr>
            </tbody></table></div>
            <div class="card" style="margin-top:20px;background:#f8fafc"><div class="card-body"><strong>Recommendation preview</strong><div id="sizingAssumptions" style="margin-top:2px;font-size:12px;color:#64748b"></div><div id="recommendation" style="margin-top:8px;color:#475569">Enter the bill's kWh reading.</div><div id="recommendationItems"></div></div></div>
            <div style="margin-top:20px"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Assessment</button></div>
        </form>
    </div>
</div>

<div class="card"><div class="card-header"><h3 class="card-title">Saved Assessments</h3></div><div class="card-body"><div class="table-container"><table class="table"><thead><tr><th>Client</th><th>Average monthly</th><th>Recommended system</th><th>Panels</th><th>Status</th><th>Approval</th><th>Actions</th></tr></thead><tbody id="assessmentRows"><tr><td colspan="7" class="text-center">Loading...</td></tr></tbody></table></div></div></div>

<!-- Recommendation Approval Modal -->
<div id="recommendationModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;padding:20px;overflow-y:auto">
    <div style="background:white;border-radius:8px;max-width:600px;margin:40px auto;padding:24px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h3>Review Recommendation</h3>
            <button type="button" onclick="closeRecommendationModal()" class="btn btn-sm btn-secondary"><i class="fas fa-times"></i></button>
        </div>
        <div id="modalContent" style="margin-bottom:20px"></div>
        <div style="display:flex;gap:10px;justify-content:flex-end">
            <button type="button" onclick="closeRecommendationModal()" class="btn btn-secondary" id="modalCloseBtn">Cancel</button>
            <button type="button" onclick="approveRecommendation()" class="btn btn-success" id="modalSaveBtn"><i class="fas fa-check"></i> Save &amp; Create Quotation</button>
            <button type="button" onclick="rejectRecommendation()" class="btn btn-danger" id="modalRejectBtn"><i class="fas fa-times"></i> Reject</button>
        </div>
    </div>
</div>

<script>
let ocrBillsData = {};
let currentAssessmentId = null;

// Which tier the user has clicked on, per context — the live preview
// (informational only, nothing to persist yet since the assessment isn't
// even saved) and the Review Recommendation modal, whose choice actually
// gets sent to create-approved-project.php on approval. Cached alongside
// the last-fetched tier data so clicking a card just re-renders instead
// of re-fetching from the server.
let previewSelectedTier = 'standard';
let lastPreviewTiers = null, lastPreviewSunHours = null, lastPreviewEfficiency = null;
let reviewSelectedTier = 'standard';
let lastReviewTiers = null, lastReviewSunHours = null, lastReviewEfficiency = null;

function selectPreviewTier(key) {
    previewSelectedTier = key;
    if (lastPreviewTiers) {
        document.getElementById('recommendationItems').innerHTML =
            renderTierContainers(lastPreviewTiers, lastPreviewSunHours, lastPreviewEfficiency, previewSelectedTier, 'selectPreviewTier');
    }
}

function selectReviewTier(key) {
    reviewSelectedTier = key;
    if (lastReviewTiers) {
        document.getElementById('modalMaterials').innerHTML =
            renderTierContainers(lastReviewTiers, lastReviewSunHours, lastReviewEfficiency, reviewSelectedTier, 'selectReviewTier');
    }
}

const billInputs = [...document.querySelectorAll('.bill-kwh')];
const recommendation = document.getElementById('recommendation');

// Panel wattage and system efficiency are no longer user-editable fields —
// the sizing math still needs a value for each, so these are the fixed
// defaults that used to be the inputs' starting values. Not hidden, though:
// shown right under "Recommendation preview" so the assumption is visible.
const DEFAULT_EFFICIENCY = 80;   // percent
const DEFAULT_PANEL_WATTAGE = 550; // watts
document.getElementById('sizingAssumptions').textContent =
    `Sizing assumes ${DEFAULT_PANEL_WATTAGE}W panels at ${DEFAULT_EFFICIENCY}% system efficiency.`;

// The actual sizing/matching math lives server-side in
// buildRecommendationMaterials() (config/functions.php) — the same
// function create-approved-project.php calls on real approval — so this
// preview can never show something different from what approval actually
// creates. Debounced since it hits the database (inventory spec matching)
// on every keystroke otherwise.
let recommendationPreviewTimer = null;
// Bumped on every new preview request, and stamped onto that request when
// it's sent. On a slow/unstable connection, an older request can finish
// AFTER a newer one — without this guard, whichever response happens to
// arrive last wins, even if it's the stale one, silently showing the wrong
// recommendation. Only the response whose stamp still matches the latest
// counter value is allowed to update the UI; everything else is discarded.
let recommendationRequestSeq = 0;

function calculateRecommendation() {
    const values = billInputs.map(input => Number(input.value)).filter(value => value > 0);
    const itemsEl = document.getElementById('recommendationItems');
    if (values.length !== 1) {
        recommendation.textContent = "Enter the bill's kWh reading.";
        if (itemsEl) itemsEl.innerHTML = '';
        clearTimeout(recommendationPreviewTimer);
        return null;
    }

    const averageMonthly = values[0];
    const sunHours = Number(document.getElementById('peakSunHours').value) || 5;
    const efficiency = DEFAULT_EFFICIENCY;
    const panelWattage = DEFAULT_PANEL_WATTAGE;
    const systemType = document.getElementById('systemType')?.value || 'hybrid';

    recommendation.textContent = 'Calculating…';
    clearTimeout(recommendationPreviewTimer);
    recommendationPreviewTimer = setTimeout(
        () => fetchRecommendationPreview(averageMonthly, sunHours, efficiency, panelWattage, systemType),
        400
    );

    // Only used as a "did the user finish entering a valid reading?" gate by
    // the submit handler — the real numbers come from the server both here
    // (debounced, for preview) and on save (energy-assessments-api.php).
    return { averageMonthly };
}

async function fetchRecommendationPreview(averageMonthly, sunHours, efficiency, panelWattage, systemType) {
    const requestId = ++recommendationRequestSeq;
    try {
        const params = new URLSearchParams({
            average_monthly_kwh: averageMonthly,
            peak_sun_hours: sunHours,
            efficiency: efficiency,
            panel_wattage: panelWattage,
            system_type: systemType || 'hybrid',
        });
        const response = await fetch(`../api/recommendation-preview.php?${params}`);
        const result = await response.json();

        // A newer request was sent while this one was in flight — its
        // response (or the "Calculating…" state it left behind) is the
        // current truth, so this now-stale response is dropped rather than
        // overwriting it.
        if (requestId !== recommendationRequestSeq) return;

        if (!result.success) {
            recommendation.textContent = result.error || 'Could not calculate a recommendation.';
            return;
        }

        const std = result.tiers.standard;
        recommendation.textContent = `Average ${result.average_monthly_kwh.toFixed(2)} kWh/month · ${result.average_daily_kwh.toFixed(2)} kWh/day · approximately ${result.system_kw.toFixed(2)} kW · ${std.panel_count} panels at ${std.panel_wattage_selected}W`;

        lastPreviewTiers = result.tiers;
        lastPreviewSunHours = sunHours;
        lastPreviewEfficiency = efficiency;

        const itemsEl = document.getElementById('recommendationItems');
        if (itemsEl) {
            itemsEl.innerHTML = renderTierContainers(result.tiers, sunHours, efficiency, previewSelectedTier, 'selectPreviewTier');
        }
    } catch (e) {
        if (requestId !== recommendationRequestSeq) return; // stale request — a newer one is already in flight or resolved
        console.error('Error loading recommendation preview:', e);
        recommendation.textContent = 'Could not calculate a recommendation.';
    }
}

// Renders the three recommendation tiers (Budget-friendly / Actual
// Recommendation / Luxury) as side-by-side containers — shared by the
// live pre-save preview above and the Review Recommendation modal for an
// already-saved assessment, so the two never drift apart visually.
const TIER_DISPLAY = {
    budget:   { label: 'Budget-Friendly',      accent: '#0EA5E9', bg: '#F0F9FF' },
    standard: { label: 'Actual Recommendation', accent: '#F97316', bg: '#FFF7ED' },
    luxury:   { label: 'Luxury',                accent: '#8B5CF6', bg: '#F5F3FF' },
};

const SYSTEM_TYPE_NOTE = {
    grid_tied: 'Grid-Tied: no battery shown — excess power exports to the grid via net metering instead of being stored.',
    off_grid:  'Off-Grid: battery sized for 3&times; the normal backup autonomy, since there\'s no grid to fall back on.',
    hybrid:    null,
};

function renderTierContainers(tiers, peakSunHours, efficiencyPercent, selectedTier, onSelectFn) {
    const cards = ['budget', 'standard', 'luxury'].map(key => {
        const tier = tiers[key];
        const display = TIER_DISPLAY[key];
        const total = tier.items.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
        const rows = tier.items.map(item => `
            <li style="display:flex;justify-content:space-between;gap:8px;padding:5px 0;border-bottom:1px solid rgba(0,0,0,0.06);font-size:12.5px">
                <span style="color:#475569">${escapeHtml(item.item_name)} &times; ${item.quantity}</span>
                <span style="color:#334155;white-space:nowrap;font-variant-numeric:tabular-nums">${money(item.unit_price * item.quantity)}</span>
            </li>`).join('');

        // Each tier picks its own panel count/wattage, so its actual
        // expected generation differs too — computed the same way system
        // sizing works in reverse: kW of panels x sun hours x efficiency.
        let harvestHtml = '';
        if (peakSunHours && efficiencyPercent) {
            const panelKw = (tier.panel_count * tier.panel_wattage_selected) / 1000;
            const dailyKwh = panelKw * peakSunHours * (efficiencyPercent / 100);
            harvestHtml = `<div style="font-size:12px;color:#475569;margin-bottom:10px"><i class="fas fa-sun" style="color:${display.accent};margin-right:4px"></i>~${dailyKwh.toFixed(2)} kWh/day harvest</div>`;
        }

        const isSelected = onSelectFn && key === selectedTier;
        // A single merged style attribute — a duplicate `style="..."` on
        // the same element is invalid HTML, and the browser silently
        // keeps only the first one and drops the rest, which is exactly
        // what was quietly discarding every card's background/border/
        // position:relative (and, with that gone, the "Selected" badge's
        // position:absolute fell back to the nearest positioned ancestor
        // instead of this card, so it floated up to the outer panel).
        const onClickAttr = onSelectFn ? `onclick="${onSelectFn}('${key}')"` : '';
        const cardStyle = [
            'position:relative',
            'flex:1',
            'min-width:230px',
            `background:${display.bg}`,
            `border:${isSelected ? '2px' : '1px'} solid ${isSelected ? display.accent : display.accent + '33'}`,
            `border-top:3px solid ${display.accent}`,
            'border-radius:10px',
            'padding:14px 16px',
            isSelected ? `box-shadow:0 2px 10px ${display.accent}40` : '',
            onSelectFn ? 'cursor:pointer' : '',
        ].filter(Boolean).join(';');

        const selectedBadge = isSelected
            ? `<div style="position:absolute;top:8px;right:8px;background:${display.accent};color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:99px;white-space:nowrap"><i class="fas fa-check"></i> Selected</div>`
            : '';

        return `
            <div class="tier-card" ${onClickAttr} style="${cardStyle}">
                ${selectedBadge}
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:${display.accent};padding-right:${isSelected ? '70px' : '0'}">${display.label}</div>
                <div style="font-size:19px;font-weight:700;color:#1e293b;margin:4px 0 6px;font-variant-numeric:tabular-nums">${money(total)}</div>
                ${harvestHtml}
                <ul style="list-style:none;margin:0;padding:0">${rows}</ul>
                ${onSelectFn && !isSelected ? `<div style="margin-top:10px;text-align:center;font-size:11.5px;color:${display.accent};font-weight:600">Click to choose this option</div>` : ''}
            </div>`;
    }).join('');

    const note = SYSTEM_TYPE_NOTE[tiers.standard?.system_type] || null;
    const noteHtml = note ? `<div style="margin-top:8px;font-size:12px;color:#64748b"><i class="fas fa-circle-info"></i> ${note}</div>` : '';

    return `
        <div style="margin-top:12px;font-size:12px;font-weight:600;color:#334155">Recommended materials (from inventory) — three options:</div>
        <div class="tier-container" style="display:flex;gap:12px;flex-wrap:wrap;margin-top:8px">${cards}</div>
        ${noteHtml}`;
}

document.querySelectorAll('#assessmentForm input').forEach(input => input.addEventListener('input', calculateRecommendation));
document.getElementById('systemType')?.addEventListener('change', calculateRecommendation);

// The OCR upload needs a customer picked first (it bootstraps a
// placeholder assessment tied to that customer) — disable the bill
// dropzones until one is selected instead of letting people hit a
// confusing failure after already choosing a file.
function updateBillUploadAvailability() {
    const hasClientName = !!document.getElementById('clientName').value.trim();
    document.querySelectorAll('.bill-file-upload').forEach(input => { input.disabled = !hasClientName; });
    const hint = document.getElementById('billUploadHint');
    if (hint) hint.style.display = hasClientName ? 'none' : 'block';
}
document.getElementById('clientName').addEventListener('input', updateBillUploadAvailability);
updateBillUploadAvailability();

// OCR Upload Handler — scans immediately on file selection instead of
// waiting for a separate "Upload" click, which was easy to miss (the
// filename turning green already looked like a completed action, so
// people moved on to the manual fields without ever triggering OCR).
// The button stays as a manual retry if the automatic scan fails.
document.querySelectorAll('.bill-file-upload').forEach((input, index) => {
    input.addEventListener('change', function() {
        const billNum = index + 1;
        const fileName = this.files[0]?.name || '';
        const statusEl = document.getElementById(`billUploadStatus${billNum}`);
        const uploadBtn = document.getElementById(`billUploadBtn${billNum}`);

        if (this.files.length > 0) {
            statusEl.innerHTML = `<span style="color:#64748b">${fileName} — scanning…</span>`;
            uploadBtn.style.display = 'inline-block';
            uploadBill(billNum);
        } else {
            statusEl.innerHTML = '';
            uploadBtn.style.display = 'none';
        }
    });
});

async function uploadBill(billNum) {
    const clientName = document.getElementById('clientName').value.trim();
    const statusEl = document.getElementById(`billUploadStatus${billNum}`);

    if (!clientName) {
        statusEl.innerHTML = '<span style="color:#dc2626">Enter the client\'s name above first</span>';
        showToast('Enter the client\'s name before uploading a bill', 'error');
        return;
    }

    // First save a temporary assessment if not yet created
    if (!currentAssessmentId) {
        const tempBills = [{billing_period: '2025-01-01', consumption_kwh: 0}];
        const response = await fetch('../api/energy-assessments-api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                client_name: clientName,
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
            // Show the server's actual reason (e.g. "Enter the client's name")
            // instead of a generic message that hides what to fix — this
            // was previously leaving the status stuck on "scanning…"
            // forever with no indication of why.
            statusEl.innerHTML = `<span style="color:#dc2626">${escapeHtml(result.error || 'Failed to create assessment')}</span>`;
            showToast(result.error || 'Failed to create assessment', 'error');
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

    statusEl.innerHTML = '<span style="color:#3b82f6">Processing...</span>';

    try {
        const response = await fetch('../api/bill-upload-ocr.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            ocrBillsData[billNum] = result.extracted;
            const lowConfidence = result.extracted.confidence === 'low';
            const color = lowConfidence ? '#d97706' : '#16a34a';
            const icon = lowConfidence ? '⚠' : '✓';
            statusEl.innerHTML = `<span style="color:${color}">${icon} ${result.extracted.consumption_kwh} kWh${lowConfidence ? ' — please verify against the bill' : ''}</span>`;
            updateOCRResults();
            fillManualRowFromOcr(billNum, result.extracted);
            showToast(lowConfidence ? 'Scanned, but not fully confident — please double-check the amount' : 'Bill uploaded and OCR completed', lowConfidence ? 'warning' : 'success');
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
    reviewSelectedTier = 'standard'; // reset each time — a prior assessment's choice shouldn't carry over
    lastReviewTiers = null;
    const modal = document.getElementById('recommendationModal');
    const content = document.getElementById('modalContent');
    content.innerHTML = `<div style="text-align:center">Loading recommendation details...</div>`;
    modal.style.display = 'block';

    // Fetch assessment details, then the same accurate materials matching
    // used everywhere else (buildRecommendationMaterials via
    // recommendation-preview.php) so this modal shows exactly what
    // approving it will actually create — full detail, grouped by category.
    fetch(`../api/energy-assessments-api.php?id=${assessmentId}`)
        .then(r => r.json())
        .then(async result => {
            if (!result.success || !result.data.length) return;
            const assessment = result.data[0];
            const alreadyQuoted = !!assessment.quotation_id;

            // Once a tier's been picked and a quotation exists, re-showing
            // all three tiers for re-comparison is pointless (and picking
            // again would just error, since one quotation per assessment
            // already exists) — show only what was actually chosen.
            document.getElementById('modalSaveBtn').style.display = alreadyQuoted ? 'none' : '';
            document.getElementById('modalRejectBtn').style.display = alreadyQuoted ? 'none' : '';
            document.getElementById('modalCloseBtn').textContent = alreadyQuoted ? 'Close' : 'Cancel';

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
                <div id="modalMaterials"><div style="text-align:center;color:#94a3b8;font-size:13px">Loading…</div></div>
                ${alreadyQuoted
                    ? `<p style="color:#64748b;font-size:13px;margin-top:12px">This is the recommendation already saved as a quotation. Edit line items or approve it in the <strong>Quotations</strong> module.</p>`
                    : `<p style="color:#64748b;font-size:13px;margin-top:12px">Click a tier below to choose it, then save — a draft quotation will be built from whichever one is selected (<strong>Actual Recommendation</strong> by default). No Project or Client record is created yet — that, along with the inventory deduction and installation/task list, only happens once the quotation itself is approved in the Quotations module.</p>`}
            `;

            const materialsEl = document.getElementById('modalMaterials');

            if (alreadyQuoted) {
                // Show the real, already-saved quotation line items — not a
                // recomputed preview — since that's what's actually on file.
                try {
                    const res = await fetch(`../api/quotations-api.php?id=${assessment.quotation_id}`);
                    const q = await res.json();
                    if (!q.success || !q.data) {
                        materialsEl.innerHTML = `<p style="color:#dc2626;font-size:13px">Could not load the saved quotation.</p>`;
                        return;
                    }
                    const items = q.data.items || [];
                    const rows = items.map(item => `
                        <li style="display:flex;justify-content:space-between;gap:8px;padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:13px">
                            <span style="color:#475569">${escapeHtml(item.description)} &times; ${item.quantity}</span>
                            <span style="color:#334155;white-space:nowrap;font-variant-numeric:tabular-nums">${money((parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0))}</span>
                        </li>`).join('');
                    materialsEl.innerHTML = `
                        <div style="font-size:12px;font-weight:600;color:#334155;margin-bottom:4px">Selected recommendation — ${escapeHtml(q.data.quotation_number)} (${escapeHtml(q.data.status)})</div>
                        <ul style="list-style:none;margin:0;padding:0">${rows}</ul>
                        <div style="display:flex;justify-content:space-between;margin-top:10px;padding-top:8px;border-top:1px solid #e2e8f0;font-weight:700;font-size:14px">
                            <span>Total</span><span>${money(q.data.total_amount)}</span>
                        </div>`;
                } catch (e) {
                    materialsEl.innerHTML = `<p style="color:#dc2626;font-size:13px">Could not load the saved quotation.</p>`;
                }
                return;
            }

            const params = new URLSearchParams({
                average_monthly_kwh: assessment.average_monthly_kwh,
                peak_sun_hours: assessment.peak_sun_hours,
                efficiency: assessment.system_efficiency * 100,
                panel_wattage: assessment.panel_wattage,
            });
            try {
                const res = await fetch(`../api/recommendation-preview.php?${params}`);
                const preview = await res.json();
                if (!preview.success) {
                    materialsEl.innerHTML = `<p style="color:#dc2626;font-size:13px">${escapeHtml(preview.error || 'Could not load materials.')}</p>`;
                    return;
                }
                lastReviewTiers = preview.tiers;
                lastReviewSunHours = assessment.peak_sun_hours;
                lastReviewEfficiency = assessment.system_efficiency * 100;
                materialsEl.innerHTML = renderTierContainers(preview.tiers, lastReviewSunHours, lastReviewEfficiency, reviewSelectedTier, 'selectReviewTier');
            } catch (e) {
                materialsEl.innerHTML = `<p style="color:#dc2626;font-size:13px">Could not load materials.</p>`;
            }
        });
}

function money(value) {
    return '₱' + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
        body: JSON.stringify({assessment_id: currentAssessmentId, tier: reviewSelectedTier})
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
        client_name: document.getElementById('clientName').value.trim(),
        client_contact_person: document.getElementById('clientContactPerson').value.trim(),
        client_phone: document.getElementById('clientPhone').value.trim(),
        client_email: document.getElementById('clientEmail').value.trim(),
        client_address: document.getElementById('clientAddress').value.trim(),
        client_city: document.getElementById('clientCity').value.trim(),
        client_state: document.getElementById('clientState').value.trim(),
        client_pincode: document.getElementById('clientPincode').value.trim(),
        client_gstin: document.getElementById('clientGstin').value.trim(),
        client_type: document.getElementById('clientType').value,
        client_status: document.getElementById('clientStatus').value,
        bills,
        peak_sun_hours: document.getElementById('peakSunHours').value,
        system_efficiency: DEFAULT_EFFICIENCY / 100,
        panel_wattage: DEFAULT_PANEL_WATTAGE
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
        document.getElementById('peakSunHours').value = 5;
        currentAssessmentId = null;
        ocrBillsData = {};
        document.getElementById('ocrResultsPanel').style.display = 'none';
        loadAssessments();
    } else showToast(result.error || 'Unable to save assessment', 'error');
});

loadAssessments();
</script>
<?php include_once __DIR__ . '/../includes/footer.php'; ?>
