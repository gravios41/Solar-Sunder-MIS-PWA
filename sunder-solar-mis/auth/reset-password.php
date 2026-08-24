<?php
// auth/reset-password.php
// Handles forgot-password email delivery and password reset

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

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
            $html = '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#1f2937">'
                . '<h2>Password Reset</h2><p>Hello ' . $recipientName . ',</p>'
                . '<p>Use this code to reset your Sunder Solar MIS password:</p>'
                . '<p style="font-size:28px;font-weight:bold;letter-spacing:5px;color:#F97316">' . $resetCode . '</p>'
                . '<p>This code expires in 15 minutes. If you did not request this, you can ignore this email.</p></div>';
            sendResetEmail($user['email'], 'Your Sunder Solar MIS password reset code', $html);
        }

        echo json_encode(['success' => true, 'message' => 'If the account details match, a reset code has been sent to the registered email.']);
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

function sendResetEmail($recipient, $subject, $html) {
    $apiKey = getenv('RESEND_API_KEY');
    $sender = getenv('MAIL_FROM') ?: 'Sunder Solar MIS <onboarding@resend.dev>';
    if (!$apiKey) {
        throw new Exception('Email service is not configured.');
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode(['from' => $sender, 'to' => [$recipient], 'subject' => $subject, 'html' => $html]),
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $error || $status < 200 || $status >= 300) {
        throw new Exception('Email delivery failed.');
    }
}
