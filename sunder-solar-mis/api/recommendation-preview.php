<?php
/**
 * Recommendation Preview
 *
 * Read-only preview of the THREE recommendation tiers (Budget-friendly,
 * Actual Recommendation, Luxury) an Energy Assessment would offer — used
 * by the live "Recommendation preview" panel in modules/energy-assessments.php
 * as the bill's kWh reading is typed in, and by the Review Recommendation
 * modal for an already-saved assessment. All three tiers come from
 * buildAllRecommendationTiers() in config/functions.php — the same
 * function create-approved-project.php's "standard" tier is built from —
 * so the Actual Recommendation shown here always matches what approval
 * will actually create.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
requireAuth();

if (!hasPermission('energy-assessments', 'create')) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$averageMonthlyKwh = (float)($_GET['average_monthly_kwh'] ?? 0);
$peakSunHours = (float)($_GET['peak_sun_hours'] ?? 5);
$efficiency = (float)($_GET['efficiency'] ?? 80) / 100;
$panelWattage = (float)($_GET['panel_wattage'] ?? 550);
$systemType = in_array($_GET['system_type'] ?? '', ['grid_tied', 'off_grid', 'hybrid'], true)
    ? $_GET['system_type']
    : 'hybrid';

if ($averageMonthlyKwh <= 0 || $peakSunHours <= 0 || $efficiency <= 0 || $panelWattage <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

try {
    $averageDailyKwh = $averageMonthlyKwh / 30;
    $systemKw = $averageDailyKwh / $peakSunHours / $efficiency;

    $tiers = buildAllRecommendationTiers($supabase, $panelWattage, $systemKw, $averageDailyKwh, $systemType);

    echo json_encode([
        'success' => true,
        'average_monthly_kwh' => round($averageMonthlyKwh, 2),
        'average_daily_kwh' => round($averageDailyKwh, 2),
        'system_kw' => round($systemKw, 2),
        'tiers' => $tiers,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
