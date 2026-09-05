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

// The role/module/action matrix lives in one place — config.php's
// getRolePermissions() — since config.php is required before this file
// everywhere it's loaded. This just gives modules that use the
// checkPermission()/getPermissions() naming a way to read the same data,
// without keeping a second copy that has to be hand-edited in step with
// the first every time a permission changes.
function getPermissions() {
    return getRolePermissions();
}
?>