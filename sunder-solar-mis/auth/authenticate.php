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
$password = trim((string) $password);

try {
    // Find user by username using Supabase.
    // Use exact match first, then a case-insensitive fallback because usernames can be stored with different casing.
    $result = $supabase->from('users')
        ->select('*')
        ->eq('username', $username)
        ->execute();

    if (!$result || !is_array($result) || count($result) === 0) {
        $allUsers = $supabase->from('users')->select('*')->execute();
        if (is_array($allUsers)) {
            $matches = array_values(array_filter($allUsers, function ($u) use ($username) {
                return isset($u['username']) && strtolower((string) $u['username']) === strtolower($username);
            }));
            if (!empty($matches)) {
                $result = $matches;
            }
        }
    }

    // Check if result is empty or not an array
    if (!$result || !is_array($result) || count($result) === 0) {
        header('Location: login.php?error=invalid_credentials');
        exit();
    }

    $user = $result[0];

    // Verify password; support both legacy plain-text and hashed values
    $storedPassword = isset($user['password']) ? (string) $user['password'] : '';
    $passwordValid = false;

    if ($storedPassword !== '') {
        $passwordInfo = password_get_info($storedPassword);

        if (!empty($passwordInfo['algo'])) {
            $passwordValid = password_verify($password, $storedPassword);
        } else {
            $passwordValid = ($storedPassword === $password) || (strtolower($storedPassword) === strtolower($password));

            if ($passwordValid) {
                try {
                    $supabase->update('users', $user['id'], ['password' => password_hash($password, PASSWORD_DEFAULT)]);
                } catch (Exception $e) {
                    error_log('Failed to migrate user password hash: ' . $e->getMessage());
                }
            }
        }
    }

    if (!$passwordValid) {
        header('Location: login.php?error=invalid_credentials');
        exit();
    }

    // Check if user is active
    if (isset($user['is_active']) && !$user['is_active']) {
        header('Location: login.php?error=account_disabled');
        exit();
    }

    // Update last login safely without assuming unsupported columns exist.
    try {
        $updateData = ['last_login' => date('Y-m-d H:i:s')];
        $supabase->update('users', $user['id'], $updateData);
    } catch (Exception $e) {
        error_log('Failed to update last login: ' . $e->getMessage());
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