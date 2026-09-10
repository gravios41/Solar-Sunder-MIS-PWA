<?php
// auth/reset-password.php
// Handles forgot-password email delivery and password reset

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$step = $data['step'] ?? '';

// ── Step 1: Send a one-time reset code ─────────────────────────────────────
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

        $user = !empty($result) ? $result[0] : null;
        if ($user && !empty($user['is_active']) && strtolower((string)$user['email']) === strtolower($email)) {
            $resetCode = strtoupper(bin2hex(random_bytes(4)));
            $supabase->insert('password_reset_tokens', [
                'user_id'    => $user['id'],
                'token_hash' => hash('sha256', $resetCode),
                'expires_at' => date('c', time() + 900),
            ]);

            $recipientName = htmlspecialchars($user['full_name'] ?: $user['username'], ENT_QUOTES, 'UTF-8');
            $resetLink = SITE_URL . 'auth/login.php?reset_token=' . urlencode($resetCode);
            $safeLink  = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
            $html = '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#1f2937">'
                . '<h2>Password Reset</h2><p>Hello ' . $recipientName . ',</p>'
                . '<p>Click the button below to set a new password for your Sunder Solar MIS account:</p>'
                . '<p style="margin:24px 0"><a href="' . $safeLink . '" style="background:#F97316;color:#fff;text-decoration:none;padding:12px 28px;border-radius:8px;font-weight:bold;display:inline-block">Reset Password</a></p>'
                . '<p style="font-size:13px;color:#6b7280">Or paste this link into your browser:<br>' . $safeLink . '</p>'
                . '<p>This link expires in 15 minutes. If you did not request this, you can ignore this email.</p></div>';
            sendAppEmail($user['email'], 'Reset your Sunder Solar MIS password', $html);
        }

        echo json_encode(['success' => true, 'message' => 'If the account details match, a password reset link has been sent to the registered email.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'System error. Please try again.']);
    }
    exit();
}

// ── Step 2: Validate code and save new password ────────────────────────────
if ($step === 'reset') {
    $resetCode   = strtoupper(trim($data['reset_code'] ?? ''));
    $newPassword = $data['new_password']        ?? '';
    $confirmPw   = $data['confirm_password']    ?? '';

    if (!$resetCode || !$newPassword) {
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
        $tokens = $supabase->from('password_reset_tokens')
            ->select('id,user_id,expires_at,used_at')
            ->eq('token_hash', hash('sha256', $resetCode))
            ->limit(1)
            ->execute();
        $token = $tokens[0] ?? null;
        if (!$token || !empty($token['used_at']) || strtotime($token['expires_at']) < time()) {
            echo json_encode(['success' => false, 'error' => 'That reset code is invalid or has expired.']);
            exit();
        }

        $supabase->update('users', $token['user_id'], [
            'password'   => password_hash($newPassword, PASSWORD_DEFAULT),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $supabase->update('password_reset_tokens', $token['id'], ['used_at' => date('c')]);

        echo json_encode(['success' => true, 'message' => 'Password reset successfully. You can now sign in.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to reset password. Please try again.']);
    }
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid step.']);
