<?php
// config/config.php
// Main configuration file for Sunder Solar MIS

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (disable in production but keep logs)
$isProduction = getenv('APP_ENV') === 'production' || getenv('RENDER') === 'true';
error_reporting(E_ALL);
ini_set('display_errors', $isProduction ? '0' : '1');
ini_set('log_errors', '1');

// Timezone
date_default_timezone_set('Asia/Manila');

// Site configuration
define('SITE_NAME', 'Sunder Solar MIS');
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$appBasePath = preg_replace('#/(auth|modules|api)$#', '', $scriptDir);
$appBasePath = rtrim($appBasePath, '/') . '/';
$forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['HTTPS'] ?? '';
$forwardedHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
$scheme = (!empty($forwardedProto) && $forwardedProto !== 'off') ? strtolower($forwardedProto) : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
$host = $forwardedHost ?: 'localhost';
define('APP_BASE_PATH', $appBasePath);
define('SITE_URL', $scheme . '://' . $host . APP_BASE_PATH);
define('SITE_VERSION', '1.0.0');

// Supabase Configuration
define('SUPABASE_URL',          getenv('SUPABASE_URL') ?: 'https://lnkeyzzskapoyvgofmyc.supabase.co');
define('SUPABASE_ANON_KEY',     getenv('SUPABASE_ANON_KEY') ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imxua2V5enpza2Fwb3l2Z29mbXljIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzYyNzAxOTEsImV4cCI6MjA5MTg0NjE5MX0.8Zg1BRbgBn4lC-sAoyOd-CjpZSp5LlQS185V4BZFmCs');
// Service role bypasses Row Level Security — used only server-side (never exposed to browser)
define('SUPABASE_SERVICE_KEY',  getenv('SUPABASE_SERVICE_KEY') ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imxua2V5enpza2Fwb3l2Z29mbXljIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3NjI3MDE5MSwiZXhwIjoyMDkxODQ2MTkxfQ.2G7qIeusEwVDIPZgrPhwloVi_w-vqAPmt0nkwDaWzLU');

// Session timeout (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Include required files
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/functions.php';

// PHP backend uses the service role key — bypasses RLS, full table access
$supabase = new SupabaseClient(SUPABASE_URL, SUPABASE_SERVICE_KEY);

// Check authentication for protected pages
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . SITE_URL . 'auth/login.php');
        exit();
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_destroy();
        header('Location: ' . SITE_URL . 'auth/login.php?error=session_expired');
        exit();
    }
    $_SESSION['last_activity'] = time();
}

// Check if user has permission
function hasPermission($module, $action = 'view') {
    if (!isset($_SESSION['user_role'])) return false;
    
    $role = $_SESSION['user_role'];
    $permissions = getRolePermissions();
    
    return isset($permissions[$role][$module][$action]) && $permissions[$role][$module][$action] === true;
}

// Get role permissions
function getRolePermissions() {
    return [
        'super_admin' => [
            'dashboard' => ['view' => true, 'edit' => true, 'delete' => true, 'create' => true],
            'customers' => ['view' => true, 'edit' => true, 'delete' => true, 'create' => true],
            'projects' => ['view' => true, 'edit' => true, 'delete' => true, 'create' => true],
            'inventory' => ['view' => true, 'edit' => true, 'delete' => true, 'create' => true],
            'quotations' => ['view' => true, 'edit' => true, 'delete' => true, 'create' => true],
            'installations' => ['view' => true, 'edit' => true, 'delete' => true, 'create' => true],
            'tasks' => ['view' => true, 'edit' => true, 'delete' => true, 'create' => true],
            'reports' => ['view' => true, 'edit' => true, 'delete' => true, 'create' => true],
            'settings' => ['view' => true, 'edit' => true, 'delete' => true, 'create' => true],
            'users' => ['view' => true, 'edit' => true, 'delete' => true, 'create' => true]
        ],
        'admin' => [
            'dashboard' => ['view' => true, 'edit' => false, 'delete' => false, 'create' => false],
            'customers' => ['view' => true, 'edit' => true, 'delete' => false, 'create' => false],
            'projects' => ['view' => true, 'edit' => false, 'delete' => false, 'create' => false],
            'inventory' => ['view' => true, 'edit' => true, 'delete' => false, 'create' => false],
            'quotations' => ['view' => true, 'edit' => false, 'delete' => false, 'create' => false],
            'installations' => ['view' => true, 'edit' => false, 'delete' => false, 'create' => false],
            'tasks' => ['view' => true, 'edit' => true, 'delete' => false, 'create' => false],
            'reports' => ['view' => true, 'edit' => false, 'delete' => false, 'create' => false],
            'settings' => ['view' => true, 'edit' => false, 'delete' => false, 'create' => false],
            'users' => ['view' => false, 'edit' => false, 'delete' => false, 'create' => false]
        ],
        'owner' => [
            'dashboard' => ['view' => true, 'edit' => true, 'delete' => true, 'create' => true],
            'customers' => ['view' => true, 'edit' => true, 'delete' => true, 'create' => true],
            'projects' => ['view' => true, 'edit' => true, 'delete' => true, 'create' => true],
            'inventory' => ['view' => true, 'edit' => true, 'delete' => true, 'create' => true],
            'quotations' => ['view' => true, 'edit' => true, 'delete' => true, 'create' => true],
            'installations' => ['view' => true, 'edit' => true, 'delete' => true, 'create' => true],
            'tasks' => ['view' => true, 'edit' => true, 'delete' => true, 'create' => true],
            'reports' => ['view' => true, 'edit' => true, 'delete' => true, 'create' => true],
            'settings' => ['view' => true, 'edit' => true, 'delete' => false, 'create' => false],
            'users' => ['view' => true, 'edit' => true, 'delete' => true, 'create' => true]
        ],
        'employee' => [
            'dashboard'     => ['view' => true,  'edit' => false, 'delete' => false, 'create' => false],
            'quotations'    => ['view' => true,  'edit' => false, 'delete' => false, 'create' => false],
            'projects'      => ['view' => true,  'edit' => false, 'delete' => false, 'create' => false],
            'installations' => ['view' => true,  'edit' => false, 'delete' => false, 'create' => false],
            'tasks'         => ['view' => true,  'edit' => true,  'delete' => false, 'create' => true],
            'reports'       => ['view' => true,  'edit' => false, 'delete' => false, 'create' => false],
            'settings'      => ['view' => true,  'edit' => true,  'delete' => false, 'create' => false],
            'customers'     => ['view' => false, 'edit' => false, 'delete' => false, 'create' => false],
            'inventory'     => ['view' => false, 'edit' => false, 'delete' => false, 'create' => false],
            'users'         => ['view' => false, 'edit' => false, 'delete' => false, 'create' => false],
        ]
    ];
}

// Get current user
function getCurrentUser() {
    if (isset($_SESSION['user_id'])) {
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'],
            'role' => $_SESSION['user_role'],
            'email' => $_SESSION['email']
        ];
    }
    return null;
}
?>
