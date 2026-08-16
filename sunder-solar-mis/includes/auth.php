<?php
// includes/auth.php
// Authentication check for protected pages

function checkAuthentication() {
    // Start session if not started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . SITE_URL . 'auth/login.php');
        exit();
    }
    
    // Check session timeout (30 minutes)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_destroy();
        header('Location: ' . SITE_URL . 'auth/login.php?error=session_expired');
        exit();
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
}

function checkPermission($module, $action = 'view') {
    if (!isset($_SESSION['user_role'])) return false;
    
    $permissions = getPermissions();
    $role = $_SESSION['user_role'];
    
    return isset($permissions[$role][$module][$action]) && $permissions[$role][$module][$action] === true;
}

function getPermissions() {
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
            'projects' => ['view' => true, 'edit' => true, 'delete' => false, 'create' => false],
            'inventory' => ['view' => true, 'edit' => true, 'delete' => false, 'create' => false],
            'quotations' => ['view' => true, 'edit' => true, 'delete' => false, 'create' => false],
            'installations' => ['view' => true, 'edit' => true, 'delete' => false, 'create' => false],
            'tasks' => ['view' => true, 'edit' => true, 'delete' => false, 'create' => false],
            'reports' => ['view' => true, 'edit' => false, 'delete' => false, 'create' => false],
            'settings' => ['view' => true, 'edit' => true, 'delete' => false, 'create' => false],
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
?>