<?php
// api/dashboard-api.php
// Dashboard statistics API

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? 'stats';

switch ($action) {
    case 'stats':
        getStats($supabase);
        break;
    case 'recent-projects':
        getRecentProjects($supabase);
        break;
    case 'recent-activities':
        getRecentActivities($supabase);
        break;
    case 'chart-data':
        getChartData($supabase);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function getStats($supabase) {
    try {
        $customers = $supabase->getAll('customers');
        $projects = $supabase->getAll('projects');
        $inventory = $supabase->getAll('inventory');
        $quotations = $supabase->getAll('quotations');
        
        $stats = [
            'total_customers' => count($customers),
            'active_projects' => count(array_filter($projects, function($p) {
                return in_array($p['status'], ['in_progress', 'installation']);
            })),
            'total_inventory' => array_sum(array_column($inventory, 'quantity')),
            'pending_quotations' => count(array_filter($quotations, function($q) {
                return $q['status'] == 'pending';
            })),
            'approved_quotations' => count(array_filter($quotations, function($q) {
                return $q['status'] == 'approved';
            })),
            'total_quotation_value' => array_sum(array_column($quotations, 'total_amount')),
            'conversion_rate' => count($quotations) > 0 ? 
                round((count(array_filter($quotations, function($q) {
                    return $q['status'] == 'approved';
                })) / count($quotations)) * 100) : 0
        ];
        
        echo json_encode(['success' => true, 'data' => $stats]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getRecentProjects($supabase) {
    try {
        $limit = $_GET['limit'] ?? 5;
        
        $projects = $supabase->from('projects')
            ->select('*')
            ->order('created_at', false)
            ->limit($limit)
            ->execute();
        
        // Get customer names
        foreach ($projects as &$project) {
            $customer = $supabase->getById('customers', $project['customer_id']);
            $project['customer_name'] = $customer ? $customer['name'] : 'Unknown';
        }
        
        echo json_encode(['success' => true, 'data' => $projects]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getRecentActivities($supabase) {
    try {
        $activities = $supabase->from('activity_logs')
            ->select('*')
            ->order('created_at', false)
            ->execute();

        echo json_encode(['success' => true, 'data' => $activities ?? []]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getChartData($supabase) {
    try {
        $period = $_GET['period'] ?? 'month';
        
        // Get quotations by month
        $quotations = $supabase->getAll('quotations');
        
        $monthlyData = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $monthlyData[$month] = [
                'month' => date('M', strtotime("-$i months")),
                'quotations' => 0,
                'amount' => 0
            ];
        }
        
        foreach ($quotations as $q) {
            $month = substr($q['quotation_date'], 0, 7);
            if (isset($monthlyData[$month])) {
                $monthlyData[$month]['quotations']++;
                $monthlyData[$month]['amount'] += $q['total_amount'];
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'labels' => array_values(array_column($monthlyData, 'month')),
                'quotations' => array_values(array_column($monthlyData, 'quotations')),
                'amounts' => array_values(array_column($monthlyData, 'amount'))
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>