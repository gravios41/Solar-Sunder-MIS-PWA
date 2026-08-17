<?php
/**
 * AJAX Module Configuration
 * Settings and utilities for AJAX-based module loading
 */

// Detect if request is AJAX
define('IS_AJAX_REQUEST', !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || isset($_GET['ajax']));

// List of modules that support AJAX loading
$AJAX_ENABLED_MODULES = [
    'dashboard' => false,  // Dashboard disabled for AJAX (full page load)
    'customers' => true,
    'quotations' => true,
    'projects' => true,
    'installations' => true,
    'inventory' => true,
    'inventory-orders' => true,
    'tasks' => true,
    'reports' => true,
    'archives' => true,
    'settings' => true,
    'user-management' => true,
];

/**
 * Check if module supports AJAX
 */
function isAjaxModule($moduleName) {
    global $AJAX_ENABLED_MODULES;
    return isset($AJAX_ENABLED_MODULES[$moduleName]) && $AJAX_ENABLED_MODULES[$moduleName];
}

/**
 * Get appropriate response format based on request type
 */
function getResponseFormat() {
    if (IS_AJAX_REQUEST) {
        return json_decode($_GET['format'] ?? 'html', true) === 'json' ? 'json' : 'html';
    }
    return 'html';
}

/**
 * Send AJAX response
 */
function sendAjaxResponse($success, $data, $message = '') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => time()
    ]);
    exit;
}

/**
 * Send HTML response
 */
function sendHtmlResponse($content) {
    header('Content-Type: text/html; charset=utf-8');
    echo $content;
    exit;
}

// Make config available globally
$GLOBALS['AJAX_CONFIG'] = [
    'is_ajax' => IS_AJAX_REQUEST,
    'enabled_modules' => $AJAX_ENABLED_MODULES,
];
?>
