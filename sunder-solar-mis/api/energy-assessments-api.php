<?php
// api/energy-assessments-api.php
// Energy assessments and verified bill readings

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
requireAuth();

if (!hasPermission('energy-assessments', 'view')) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

global $supabase;
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Get single assessment by ID or all assessments
        $assessmentId = $_GET['id'] ?? null;
        
        if ($assessmentId) {
            $assessments = $supabase->from('energy_assessments')
                ->select('*')
                ->eq('id', $assessmentId)
                ->execute() ?: [];
        } else {
            $assessments = $supabase->from('energy_assessments')
                ->select('*')
                ->order('created_at', false)
                ->execute() ?: [];
        }

        if (!empty($assessments)) {
            // A brand-new client has no Customer row yet (customer_id is
            // null) — filter those out before building the IN clause.
            $customerIds = array_values(array_unique(array_filter(array_column($assessments, 'customer_id'))));
            $customersById = [];
            if ($customerIds) {
                $customerRows = $supabase->getAll('customers', ['id' => 'in.(' . implode(',', $customerIds) . ')']) ?: [];
                foreach ($customerRows as $c) {
                    $customersById[$c['id']] = $c;
                }
            }

            $assessmentIds = array_column($assessments, 'id');
            $billsByAssessment = [];
            if ($assessmentIds) {
                $billRows = $supabase->getAll('energy_bill_readings', [
                    'assessment_id' => 'in.(' . implode(',', $assessmentIds) . ')',
                    'order' => 'billing_period.asc'
                ]) ?: [];
                foreach ($billRows as $bill) {
                    $billsByAssessment[$bill['assessment_id']][] = $bill;
                }
            }

            foreach ($assessments as &$assessment) {
                $assessment['customer_name'] = $assessment['customer_id']
                    ? ($customersById[$assessment['customer_id']]['name'] ?? 'Unknown')
                    : ($assessment['client_name'] ?: 'Unknown');
                $assessment['bills'] = $billsByAssessment[$assessment['id']] ?? [];
            }
            unset($assessment);
        }

        echo json_encode(['success' => true, 'data' => $assessments]);
        exit;
    }

    if ($method === 'PUT') {
        if (!hasPermission('energy-assessments', 'edit')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $assessmentId = $data['id'] ?? '';
        
        if (!$assessmentId) {
            echo json_encode(['success' => false, 'error' => 'Assessment ID required']);
            exit;
        }

        $updateData = [];

        if (isset($data['approval_status'])) {
            $updateData['approval_status'] = $data['approval_status'];
            $updateData['approved_by'] = $_SESSION['user_id'];
            $updateData['approved_at'] = date('Y-m-d H:i:s');
            $updateData['status'] = $data['approval_status'] === 'approved' ? 'approved' : 'draft';
        }

        if (isset($data['approval_notes'])) {
            $updateData['approval_notes'] = $data['approval_notes'];
        }

        // The client's full details (same fields as the Quotation's
        // client-only form) can be filled in — or corrected — right here on
        // the assessment, including when finalizing a placeholder created
        // for an OCR upload. Only meaningful while there's still no real
        // Customer record (customer_id null); once approved this assessment
        // is done being edited anyway.
        $clientFieldKeys = [
            'client_name', 'client_contact_person', 'client_phone', 'client_email',
            'client_address', 'client_city', 'client_state', 'client_pincode',
            'client_gstin', 'client_type', 'client_status',
        ];
        foreach ($clientFieldKeys as $key) {
            if (isset($data[$key])) {
                $updateData[$key] = trim($data[$key]) !== '' ? trim($data[$key]) : null;
            }
        }

        // Finalizing a placeholder assessment (created to attach OCR bill
        // uploads to) with real bill data — replaces its bills rather than
        // leaving the placeholder behind and creating a second record.
        if (isset($data['bills'])) {
            $peakSunHours = max(1, min(10, (float)($data['peak_sun_hours'] ?? 5)));
            $efficiency = max(0.4, min(1, (float)($data['system_efficiency'] ?? 0.8)));
            $panelWattage = max(100, min(1000, (int)($data['panel_wattage'] ?? 550)));

            $sizing = computeAssessmentSizing($data['bills'], $peakSunHours, $efficiency, $panelWattage, false);
            if (isset($sizing['error'])) {
                echo json_encode(['success' => false, 'error' => $sizing['error']]);
                exit;
            }

            $updateData = array_merge($updateData, $sizing, ['status' => 'verified']);

            $supabase->request('DELETE', 'energy_bill_readings', ['assessment_id' => 'eq.' . $assessmentId]);
            foreach ($data['bills'] as $bill) {
                $supabase->insert('energy_bill_readings', [
                    'assessment_id' => $assessmentId,
                    'billing_period' => $bill['billing_period'],
                    'consumption_kwh' => (float)$bill['consumption_kwh'],
                    'amount' => isset($bill['amount']) && $bill['amount'] !== '' ? (float)$bill['amount'] : null,
                    'is_verified' => true
                ]);
            }
        }

        if (empty($updateData)) {
            echo json_encode(['success' => false, 'error' => 'No fields to update']);
            exit;
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');
        $supabase->update('energy_assessments', $assessmentId, $updateData);

        logActivity($_SESSION['user_id'], 'update', 'energy-assessments', "Updated energy assessment $assessmentId");
        echo json_encode(['success' => true, 'message' => 'Assessment updated']);
        exit;
    }

    if ($method !== 'POST' || !hasPermission('energy-assessments', 'create')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied or method not allowed']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    // No Customer record needs to exist yet — a brand-new client is just a
    // name at this point. customer_id is used when the assessment is for
    // someone already in the Customers module; otherwise client_name is
    // carried on the assessment (and later the quotation) until the
    // quotation is approved, at which point the real Customer row is
    // created and these get linked via customer_id (see approve-quotation.php).
    $customerId = $data['customer_id'] ?? null;
    $clientName = trim($data['client_name'] ?? '');
    $bills = $data['bills'] ?? [];
    $peakSunHours = max(1, min(10, (float)($data['peak_sun_hours'] ?? 5)));
    $efficiency = max(0.4, min(1, (float)($data['system_efficiency'] ?? 0.8)));
    $panelWattage = max(100, min(1000, (int)($data['panel_wattage'] ?? 550)));

    if (!$customerId && !$clientName) {
        echo json_encode(['success' => false, 'error' => 'Enter the client\'s name']);
        exit;
    }

    // A placeholder assessment only exists to give bill-upload-ocr.php an
    // ID to attach scanned images to before real numbers exist yet — it's
    // finalized with real data later via PUT, so zero-kWh bills are allowed
    // here only when the caller explicitly marks this as a placeholder.
    $isPlaceholder = !empty($data['is_placeholder']);

    $sizing = computeAssessmentSizing($bills, $peakSunHours, $efficiency, $panelWattage, $isPlaceholder);
    if (isset($sizing['error'])) {
        echo json_encode(['success' => false, 'error' => $sizing['error']]);
        exit;
    }

    // The full client record (mirrors the Quotation's client-only form) only
    // matters when there's no existing Customer to fall back on — an
    // already-existing customer_id already has all of this in Customers.
    $clientFieldKeys = [
        'client_contact_person', 'client_phone', 'client_email', 'client_address',
        'client_city', 'client_state', 'client_pincode', 'client_gstin',
    ];
    $clientFields = [];
    foreach ($clientFieldKeys as $key) {
        $value = trim($data[$key] ?? '');
        $clientFields[$key] = ($customerId || $value === '') ? null : $value;
    }
    $clientFields['client_type'] = $customerId ? null : ($data['client_type'] ?? 'residential');
    $clientFields['client_status'] = $customerId ? null : ($data['client_status'] ?? 'active');

    $assessmentData = array_merge($sizing, $clientFields, [
        'customer_id' => $customerId ?: null,
        'client_name' => $customerId ? null : $clientName,
        'project_id' => $data['project_id'] ?? null,
        'status' => $isPlaceholder ? 'draft' : 'verified',
        'created_by' => $_SESSION['user_id'],
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    $inserted = $supabase->insert('energy_assessments', $assessmentData);
    $assessment = $inserted[0] ?? $inserted;
    if (!$assessment || empty($assessment['id'])) {
        throw new Exception('Unable to save energy assessment');
    }

    foreach ($bills as $bill) {
        $supabase->insert('energy_bill_readings', [
            'assessment_id' => $assessment['id'],
            'billing_period' => $bill['billing_period'],
            'consumption_kwh' => (float)$bill['consumption_kwh'],
            'amount' => isset($bill['amount']) && $bill['amount'] !== '' ? (float)$bill['amount'] : null,
            'is_verified' => true
        ]);
    }

    logActivity($_SESSION['user_id'], 'create', 'energy-assessments', 'Created energy assessment');
    echo json_encode([
        'success' => true, 
        'data' => $assessment, 
        'assessment_id' => $assessment['id'],
        'message' => 'Energy assessment saved successfully'
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Validates exactly 1 bill and computes the recommended system sizing from
 * its consumption. Returns ['error' => string] on invalid input, or the
 * computed assessment fields.
 *
 * $allowZeroKwh exists only for the OCR-upload placeholder bootstrap,
 * which needs a real assessment row to attach scanned images to before
 * any consumption numbers are known yet — every other caller must pass
 * false so a genuine zero-kWh bill is rejected.
 */
function computeAssessmentSizing($bills, $peakSunHours, $efficiency, $panelWattage, $allowZeroKwh) {
    if (count($bills) !== 1) {
        return ['error' => 'Exactly one bill is required'];
    }

    if (count(array_filter($bills, static fn($bill) => empty($bill['billing_period']))) > 0) {
        return ['error' => 'Each bill needs a billing period'];
    }

    $consumption = array_map(static fn($bill) => (float)($bill['consumption_kwh'] ?? 0), $bills);

    if (!$allowZeroKwh && count(array_filter($consumption, static fn($v) => $v <= 0)) > 0) {
        return ['error' => 'Each bill needs a kWh value greater than zero'];
    }

    $averageMonthly = array_sum($consumption) / count($consumption);
    $averageDaily = $averageMonthly / 30;
    $systemKw = $averageDaily / $peakSunHours / $efficiency;
    $panelCount = (int)ceil(($systemKw * 1000) / $panelWattage);

    return [
        'average_monthly_kwh' => round($averageMonthly, 2),
        'average_daily_kwh' => round($averageDaily, 2),
        'peak_sun_hours' => $peakSunHours,
        'system_efficiency' => $efficiency,
        'recommended_system_kw' => round($systemKw, 2),
        'recommended_panel_count' => $panelCount,
        'panel_wattage' => $panelWattage,
    ];
}
