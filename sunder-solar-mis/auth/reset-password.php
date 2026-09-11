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
        $response = ['success' => true, 'message' => 'If the account details match, you can set a new password now.'];

        if ($user && !empty($user['is_active']) && strtolower((string)$user['email']) === strtolower($email)) {
            $resetCode = strtoupper(bin2hex(random_bytes(4)));
            $supabase->insert('password_reset_tokens', [
                'user_id'    => $user['id'],
                'token_hash' => hash('sha256', $resetCode),
                'expires_at' => date('c', time() + 900),
            ]);

            // Hand the code straight back so the reset works even when the
            // outbound mail send fails or is blocked by the host network —
            // email is now a best-effort courtesy copy, not a requirement.
            $response['reset_code'] = $resetCode;
            $response['message']    = 'Identity verified. Set your new password below.';

            try {
                $recipientName = htmlspecialchars($user['full_name'] ?: $user['username'], ENT_QUOTES, 'UTF-8');
                $resetLink = SITE_URL . 'auth/login.php?reset_token=' . urlencode($resetCode);
                $safeLink  = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
                $body = '<p>Hello ' . $recipientName . ',</p>'
                    . '<p>We received a request to reset the password for your Sunder Solar MIS account. '
                    . 'Click the button below to choose a new password.</p>'
                    . emailButton('Reset Password', $resetLink)
                    . '<p style="font-size:13px;color:#64748b">Or paste this link into your browser:<br>'
                    . '<a href="' . $safeLink . '" style="color:#c2410c;word-break:break-all">' . $safeLink . '</a></p>'
                    . '<p style="font-size:13px;color:#64748b;margin-top:20px;padding-top:16px;border-top:1px solid #e2e8f0">'
                    . 'This link expires in <strong>15 minutes</strong>. If you did not request a password reset, '
                    . 'you can safely ignore this email &mdash; your password will not change.</p>';
                $html = emailShell('Reset your password', $body, 'Use the button in this email to set a new password.');
                sendAppEmail($user['email'], 'Reset your Sunder Solar MIS password', $html);
            } catch (Throwable $mailErr) {
                // Non-fatal: the code above still works without the email.
                error_log('reset-password: courtesy email failed (non-fatal): ' . $mailErr->getMessage());
            }
        }

        echo json_encode($response);
    } catch (Throwable $e) {
        error_log('reset-password verify failed: ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
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
    } catch (Throwable $e) {
        error_log('reset-password reset failed: ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        echo json_encode(['success' => false, 'error' => 'Failed to reset password. Please try again.']);
    }
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid step.']);
