<?php
// api/auth-api.php
// Hybrid: Supabase with additional prepared statement-like security

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Rate limiting to prevent brute force attacks
session_start();
$ip = $_SERVER['REMOTE_ADDR'];
$attempts = $_SESSION['login_attempts'][$ip] ?? 0;

if ($action === 'login' && $attempts >= 5) {
    $timeSinceLastAttempt = time() - ($_SESSION['login_attempts_time'][$ip] ?? 0);
    if ($timeSinceLastAttempt < 900) { // 15 minutes
        echo json_encode(['success' => false, 'error' => 'Too many login attempts. Please try again later.']);
        exit();
    } else {
        // Reset attempts after timeout
        $_SESSION['login_attempts'][$ip] = 0;
    }
}

if ($method !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'check':
        handleCheckAuth();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function handleLogin() {
    global $supabase;
    
    // Rate limiting
    global $ip, $attempts;
    
    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['username']) || !isset($input['password'])) {
        echo json_encode(['success' => false, 'error' => 'Username and password required']);
        return;
    }
    
    // Sanitize inputs - prevent XSS and injection
    $username = trim(htmlspecialchars(strip_tags($input['username']), ENT_QUOTES, 'UTF-8'));
    $password = trim((string) $input['password']);

    // Validate input length
    if (strlen($username) < 3 || strlen($username) > 50) {
        echo json_encode(['success' => false, 'error' => 'Invalid username format']);
        return;
    }

    if (strlen($password) < 4) {
        echo json_encode(['success' => false, 'error' => 'Invalid password format']);
        return;
    }

    // Additional validation - only allow alphanumeric and specific characters
    if (!preg_match('/^[a-zA-Z0-9_@.-]+$/', $username)) {
        echo json_encode(['success' => false, 'error' => 'Invalid username format']);
        return;
    }

    try {
        // Supabase's eq method is already parameterized.
        // Fall back to case-insensitive lookup if exact match fails.
        $users = $supabase->from('users')
            ->select('*')
            ->eq('username', $username)
            ->execute();

        if (empty($users)) {
            $allUsers = $supabase->from('users')->select('*')->execute();
            if (is_array($allUsers)) {
                $matches = array_values(array_filter($allUsers, function ($u) use ($username) {
                    return isset($u['username']) && strtolower((string)$u['username']) === strtolower($username);
                }));
                if (!empty($matches)) {
                    $users = $matches;
                }
            }
        }

        if (empty($users)) {
            // Increment failed attempt counter
            $_SESSION['login_attempts'][$ip] = ($_SESSION['login_attempts'][$ip] ?? 0) + 1;
            $_SESSION['login_attempts_time'][$ip] = time();

            echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
            return;
        }

        $user = $users[0];

        // Use password_verify for better security; support old plain-text passwords too.
        $passwordValid = false;
        if (isset($user['password']) && $user['password'] !== '') {
            $storedPassword = (string) $user['password'];
            if (password_get_info($storedPassword)['algo'] !== false) {
                $passwordValid = password_verify($password, $storedPassword);
            } else {
                if (strtolower($storedPassword) === strtolower($password)) {
                    $passwordValid = true;
                }
            }
        }
        
        if (!$passwordValid) {
            $_SESSION['login_attempts'][$ip] = ($_SESSION['login_attempts'][$ip] ?? 0) + 1;
            $_SESSION['login_attempts_time'][$ip] = time();
            
            echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
            return;
        }
        
        // Reset login attempts on success
        $_SESSION['login_attempts'][$ip] = 0;
        
        // Check if user is active
        if (!$user['is_active']) {
            echo json_encode(['success' => false, 'error' => 'Account is disabled. Please contact administrator.']);
            return;
        }
        
        // Update last login with only columns that exist in the schema.
        try {
            $supabase->update('users', $user['id'], [
                'last_login' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log('Failed to update last login: ' . $e->getMessage());
        }
        
        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);
        
        // Set session variables with fingerprinting
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['last_activity'] = time();
        $_SESSION['fingerprint'] = md5($_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);
        
        // Log activity
        if (function_exists('logActivity')) {
            logActivity($user['id'], 'login', 'auth', "User {$user['full_name']} logged in from IP: {$_SERVER['REMOTE_ADDR']}");
        }
        
        // Remove sensitive data
        unset($user['password']);
        
        echo json_encode([
            'success' => true,
            'data' => $user,
            'message' => 'Login successful'
        ]);
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'An error occurred. Please try again.']);
    }
}

function handleLogout() {
    if (isset($_SESSION['user_id'])) {
        if (function_exists('logActivity')) {
            logActivity($_SESSION['user_id'], 'logout', 'auth', "User logged out from IP: {$_SERVER['REMOTE_ADDR']}");
        }
    }
    
    // Clear all session variables
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
    
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
}

function handleCheckAuth() {
    if (isset($_SESSION['user_id'])) {
        // Verify session fingerprint
        $expectedFingerprint = md5($_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);
        if (($_SESSION['fingerprint'] ?? '') !== $expectedFingerprint) {
            // Possible session hijacking
            handleLogout();
            echo json_encode(['success' => true, 'authenticated' => false, 'error' => 'Session expired']);
            return;
        }
        
        // Check session timeout (30 minutes)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            handleLogout();
            echo json_encode(['success' => true, 'authenticated' => false, 'error' => 'Session expired']);
            return;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'full_name' => $_SESSION['full_name'],
                'role' => $_SESSION['user_role'],
                'email' => $_SESSION['email']
            ]
        ]);
    } else {
        echo json_encode(['success' => true, 'authenticated' => false]);
    }
}

// Helper function to hash passwords (for user creation)
function hashUserPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
}
?>