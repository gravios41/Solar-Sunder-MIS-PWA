<?php
/**
 * AJAX Content Loader
 * Returns module content without header/footer for dynamic loading
 * Used for mobile-friendly AJAX navigation
 */

require_once __DIR__ . '/../config/config.php';
requireAuth();

header('Content-Type: text/html; charset=utf-8');

$module = isset($_GET['module']) ? basename($_GET['module']) : '';
$action = isset($_GET['action']) ? $_GET['action'] : 'view';

// Allowed modules
$allowedModules = [
    'dashboard', 'customers', 'quotations', 'energy-assessments', 'projects', 'installations',
    'inventory', 'inventory-orders', 'tasks', 'reports', 'archives', 'settings', 'user-management'
];

if (!in_array($module, $allowedModules)) {
    http_response_code(404);
    echo json_encode(['error' => 'Invalid module']);
    exit;
}

// Check access
checkPageAccess($module);

// Set base path for included files
$appBasePath = defined('APP_BASE_PATH') ? APP_BASE_PATH : '/';

// Start output buffering
ob_start();

try {
    // Include the module file, but we'll only output the content
    $modulePath = __DIR__ . "/../modules/{$module}.php";
    
    if (!file_exists($modulePath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Module not found']);
        exit;
    }

    // Load module without header/footer
    include $modulePath;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

$content = ob_get_clean();

// The module file always includes the full header.php/footer.php chrome
// (doctype, <title>, sidebar, topbar, global modals, core <script> tags),
// so pull out just what's inside .page-body — anything else here gets
// dumped raw into pageBody.innerHTML by ajax-loader.js, and things like
// <title>/<script> leak onto the page as visible plain text since they
// aren't valid content in that position.
$startMarker = '<div class="page-body">';
$endMarker   = '</div><!-- /.page-body -->';
$start = strpos($content, $startMarker);
$end   = strrpos($content, $endMarker);
if ($start !== false && $end !== false && $end > $start) {
    $start += strlen($startMarker);
    $content = substr($content, $start, $end - $start);
}

// For AJAX requests that expect JSON
if (isset($_GET['json'])) {
    echo json_encode([
        'success' => true,
        'content' => $content,
        'module' => $module
    ]);
} else {
    // For regular AJAX requests, return HTML
    echo $content;
}
?>
