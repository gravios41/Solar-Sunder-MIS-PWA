<?php
// auth/reset-password.php
// Handles forgot-password verification and password reset

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$step = $data['step'] ?? '';

// ── Step 1: Verify username + email match ──────────────────────────────────
if ($step === 'verify') {
    $username = trim($data['username'] ?? '');
    $email    = trim($data['email']    ?? '');

    if (!$username || !$email) {
        echo json_encode(['success' => false, 'error' => 'Username and email are required.']);
        exit();
    }

    try {
        $result = $supabase->from('users')
            ->select('id,username,email,full_name,is_active')
            ->eq('username', $username)
            ->execute();

        if (empty($result)) {
            echo json_encode(['success' => false, 'error' => 'No account found with that username.']);
            exit();
        }

        $user = $result[0];

        if (strtolower($user['email']) !== strtolower($email)) {
            echo json_encode(['success' => false, 'error' => 'Email does not match our records.']);
            exit();
        }

        if (!$user['is_active']) {
            echo json_encode(['success' => false, 'error' => 'This account is disabled. Contact your administrator.']);
            exit();
        }

        echo json_encode([
            'success'   => true,
            'user_id'   => $user['id'],
            'full_name' => $user['full_name'],
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'System error. Please try again.']);
    }
    exit();
}

// ── Step 2: Save new password ──────────────────────────────────────────────
if ($step === 'reset') {
    $userId      = (int)($data['user_id']      ?? 0);
    $newPassword = $data['new_password']        ?? '';
    $confirmPw   = $data['confirm_password']    ?? '';

    if (!$userId || !$newPassword) {
        echo json_encode(['success' => false, 'error' => 'Invalid request.']);
        exit();
    }

    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters.']);
        exit();
    }

    if ($newPassword !== $confirmPw) {
        echo json_encode(['success' => false, 'error' => 'Passwords do not match.']);
        exit();
    }

    try {
        $supabase->update('users', $userId, [
            'password'   => $newPassword,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        echo json_encode(['success' => true, 'message' => 'Password reset successfully. You can now sign in.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to reset password. Please try again.']);
    }
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid step.']);
