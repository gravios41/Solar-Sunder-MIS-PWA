<?php
// api/reports-api.php
// REST API for Reports

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Only include config.php (it includes supabase.php and everything else)
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

global $supabase;

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetReports();
        break;
    case 'POST':
        handlePostReport();
        break;
    case 'DELETE':
        handleDeleteReport();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

// ============================================
// GET: Fetch reports
// ============================================
function handleGetReports() {
    global $supabase;
    
    $id = $_GET['id'] ?? null;
    $type = $_GET['type'] ?? '';
    $period = $_GET['period'] ?? '';
    $search = $_GET['search'] ?? '';
    
    try {
        if ($id) {
            // Get single report
            $report = $supabase->getById('reports', $id);
            echo json_encode(['success' => true, 'data' => $report]);
            return;
        }
        
        // Get all reports
        $query = $supabase->from('reports')->select('*');
        
        if ($type && $type !== 'all') {
            $query->eq('report_type', $type);
        }
        
        if ($period && $period !== 'all') {
            $query->eq('period', $period);
        }
        
        if ($search) {
            $query->ilike('report_name', "%$search%");
        }
        
        $query->order('generated_date', false);
        
        $reports = $query->execute();
        
        echo json_encode(['success' => true, 'data' => $reports]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================
// POST: Generate new report
// ============================================
function handlePostReport() {
    global $supabase;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['report_name'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    if (!hasPermission('reports', 'create')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    try {
        $data['created_at'] = date('Y-m-d H:i:s');
        
        // Set default values if not provided
        if (!isset($data['generated_date'])) {
            $data['generated_date'] = date('Y-m-d');
        }
        if (!isset($data['format'])) {
            $data['format'] = 'PDF';
        }
        if (!isset($data['file_size'])) {
            $data['file_size'] = '1.2 MB';
        }
        
        $result = $supabase->insert('reports', $data);
        
        if ($result) {
            logActivity($_SESSION['user_id'], 'create', 'reports', "Generated report: {$data['report_name']}");
            echo json_encode(['success' => true, 'data' => $result, 'message' => 'Report generated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to generate report']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================
// DELETE: Delete report
// ============================================
function handleDeleteReport() {
    global $supabase;
    
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Report ID required']);
        return;
    }
    
    if (!hasPermission('reports', 'delete')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        return;
    }
    
    try {
        $result = archiveRecord('reports', $id);
        if ($result) {
            logActivity($_SESSION['user_id'], 'archive', 'reports', "Archived record ID: $id");
            echo json_encode(['success' => true, 'message' => 'Record archived successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to archive record']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>