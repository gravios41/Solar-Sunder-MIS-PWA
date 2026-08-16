<?php
// auth/authenticate.php
// Handle login authentication

require_once __DIR__ . '/../config/config.php';

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'modules/dashboard.php');
    exit();
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

if (empty($username) || empty($password)) {
    header('Location: login.php?error=empty_fields');
    exit();
}

// Sanitize input
$username = trim(htmlspecialchars(strip_tags($username)));

try {
    // Find user by username using Supabase
    $result = $supabase->from('users')
        ->select('*')
        ->eq('username', $username)
        ->execute();
    
    // Check if result is empty or not an array
    if (!$result || !is_array($result) || count($result) === 0) {
        header('Location: login.php?error=invalid_credentials');
        exit();
    }
    
    $user = $result[0];
    
    // Verify password
    if ($user['password'] !== $password) {
        header('Location: login.php?error=invalid_credentials');
        exit();
    }
    
    // Check if user is active
    if (isset($user['is_active']) && !$user['is_active']) {
        header('Location: login.php?error=account_disabled');
        exit();
    }
    
    // Update last login - FIXED with try-catch
    try {
        $updateData = [
            'last_login' => date('Y-m-d H:i:s'),
            'last_ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ];
        $supabase->update('users', $user['id'], $updateData);
    } catch (Exception $e) {
        // Log but don't stop login if update fails
        error_log("Failed to update last login: " . $e->getMessage());
    }
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
    $_SESSION['user_role'] = $user['role'] ?? 'admin';
    $_SESSION['email'] = $user['email'] ?? '';
    $_SESSION['avatar_url'] = $user['avatar_url'] ?? '';
    $_SESSION['last_activity'] = time();
    
    // Set remember me cookie
    if ($remember) {
        setcookie('remember_user', $user['id'], time() + (86400 * 30), '/');
    }
    
    // Log activity - FIXED with try-catch
    if (function_exists('logActivity')) {
        try {
            logActivity($user['id'], 'login', 'auth', "User {$user['full_name']} logged in");
        } catch (Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
    
    // Redirect to dashboard
    header('Location: ' . SITE_URL . 'modules/dashboard.php');
    exit();
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    error_log("Login error file: " . $e->getFile() . " line: " . $e->getLine());
    header('Location: login.php?error=system_error');
    exit();
}
?>