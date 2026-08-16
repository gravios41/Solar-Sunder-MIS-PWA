<?php
// auth/logout.php
// Handle logout

require_once __DIR__ . '/../config/config.php';

// Log activity
if (isset($_SESSION['user_id']) && function_exists('logActivity')) {
    logActivity($_SESSION['user_id'], 'logout', 'auth', "User logged out");
}

// Clear session
$_SESSION = array();

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Clear remember me cookie
setcookie('remember_user', '', time() - 3600, '/');

// Destroy session
session_destroy();

// Redirect to login
header('Location: ' . SITE_URL . 'auth/login.php');
exit();
?>