<?php
// api/profile-change-request.php
// A user asks to change their own email and/or phone. They cannot do it
// themselves — this emails every active owner / super_admin so one of them
// can make the change in User Management.

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/mailer.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

global $supabase;
$data = json_decode(file_get_contents('php://input'), true) ?: [];

$newEmail = strtolower(trim($data['new_email'] ?? ''));
$newPhone = trim($data['new_phone'] ?? '');
$reason   = trim($data['reason'] ?? '');

if ($newEmail === '' && $newPhone === '') {
    echo json_encode(['success' => false, 'error' => 'Enter a new email address or phone number.']);
    exit();
}
if ($newEmail !== '' && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']);
    exit();
}
if ($newPhone !== '') {
    $normalizedPhone = preg_replace('/[^\d+]/', '', $newPhone);
    if (strlen($normalizedPhone) < 7 || strlen($normalizedPhone) > 20) {
        echo json_encode(['success' => false, 'error' => 'Please enter a valid phone number.']);
        exit();
    }
    $newPhone = $normalizedPhone;
}
if (strlen($reason) > 500) {
    $reason = substr($reason, 0, 500);
}

try {
    $me = $supabase->getById('users', $_SESSION['user_id']);
    if (!$me) {
        echo json_encode(['success' => false, 'error' => 'Account not found.']);
        exit();
    }

    // Nothing to do if the values already match
    $wantsEmail = $newEmail !== '' && strtolower((string)$me['email']) !== $newEmail;
    $wantsPhone = $newPhone !== '' && preg_replace('/[^\d+]/', '', (string)($me['phone'] ?? '')) !== $newPhone;
    if (!$wantsEmail && !$wantsPhone) {
        echo json_encode(['success' => false, 'error' => 'Those already match your current details.']);
        exit();
    }

    // Throttle: one request per 5 minutes
    if (isset($_SESSION['profile_change_request_at']) && (time() - $_SESSION['profile_change_request_at']) < 300) {
        echo json_encode(['success' => false, 'error' => 'A request was just submitted. Please wait a few minutes.']);
        exit();
    }

    $allUsers = $supabase->getAll('users') ?: [];
    $admins = array_filter($allUsers, function ($u) {
        return in_array($u['role'] ?? '', ['owner', 'super_admin'], true)
            && !empty($u['is_active'])
            && filter_var($u['email'] ?? '', FILTER_VALIDATE_EMAIL);
    });

    if (empty($admins)) {
        echo json_encode(['success' => false, 'error' => 'No administrator is available to process this request.']);
        exit();
    }

    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $td  = 'padding:9px 14px;border:1px solid #e2e8f0;font-size:14px';
    $tdN = $td . ';color:#94a3b8';
    $rows = '';
    if ($wantsEmail) {
        $rows .= '<tr><td style="' . $td . ';font-weight:600">Email</td>'
              . '<td style="' . $tdN . '">' . $e($me['email']) . '</td>'
              . '<td style="' . $td . ';color:#c2410c;font-weight:700">' . $e($newEmail) . '</td></tr>';
    }
    if ($wantsPhone) {
        $rows .= '<tr><td style="' . $td . ';font-weight:600">Phone</td>'
              . '<td style="' . $tdN . '">' . $e($me['phone'] ?: '—') . '</td>'
              . '<td style="' . $td . ';color:#c2410c;font-weight:700">' . $e($newPhone) . '</td></tr>';
    }

    $body = '<p>An account holder has asked to update their contact details and cannot change '
        . 'these fields themselves. Please review and apply the change in <strong>User Management</strong> '
        . 'if it looks legitimate.</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:8px 0 20px;width:100%">'
        . '<tr>'
        . '<th style="' . $td . ';background:#f8fafc;text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:0.04em;color:#64748b">Field</th>'
        . '<th style="' . $td . ';background:#f8fafc;text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:0.04em;color:#64748b">Current</th>'
        . '<th style="' . $td . ';background:#f8fafc;text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:0.04em;color:#64748b">Requested</th>'
        . '</tr>' . $rows . '</table>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;font-size:14px;color:#334155">'
        . '<tr><td style="padding:3px 0;width:130px;color:#64748b">Requested by</td><td style="padding:3px 0"><strong>' . $e($me['full_name'] ?: $me['username']) . '</strong></td></tr>'
        . '<tr><td style="padding:3px 0;color:#64748b">Username</td><td style="padding:3px 0">' . $e($me['username']) . '</td></tr>'
        . '<tr><td style="padding:3px 0;color:#64748b">Role</td><td style="padding:3px 0">' . $e(ucwords(str_replace('_', ' ', $me['role']))) . '</td></tr>'
        . '<tr><td style="padding:3px 0;color:#64748b">User ID</td><td style="padding:3px 0">' . $e($me['id']) . '</td></tr>'
        . ($reason !== '' ? '<tr><td style="padding:3px 0;color:#64748b">Reason</td><td style="padding:3px 0">' . $e($reason) . '</td></tr>' : '')
        . '<tr><td style="padding:3px 0;color:#64748b">When</td><td style="padding:3px 0">' . $e(date('M j, Y g:i A')) . '</td></tr>'
        . '<tr><td style="padding:3px 0;color:#64748b">IP address</td><td style="padding:3px 0">' . $e($_SERVER['REMOTE_ADDR'] ?? '') . '</td></tr>'
        . '</table>';

    $ctaUrl = (defined('SITE_URL') ? SITE_URL : '') . 'modules/user-management.php';
    $body  .= emailButton('Open User Management', $ctaUrl);

    $html = emailShell('Profile change request', $body,
        ($me['full_name'] ?: $me['username']) . ' requested a contact-detail change.');

    $sent = 0;
    $lastError = '';
    foreach ($admins as $admin) {
        try {
            sendAppEmail($admin['email'], 'Profile change request from ' . ($me['full_name'] ?: $me['username']), $html);
            $sent++;
        } catch (Exception $ex) {
            $lastError = $ex->getMessage();
            error_log('profile-change-request: failed to notify ' . $admin['email'] . ' — ' . $lastError);
        }
    }

    if ($sent === 0) {
        $msg = ($lastError === 'Email service is not configured.')
            ? 'Email is not set up on the server yet. Please contact an administrator.'
            : 'Could not send the request right now. Please try again later.';
        echo json_encode(['success' => false, 'error' => $msg]);
        exit();
    }

    $_SESSION['profile_change_request_at'] = time();
    if (function_exists('logActivity')) {
        logActivity($_SESSION['user_id'], 'request', 'users',
            'Requested profile change' . ($wantsEmail ? ' (email)' : '') . ($wantsPhone ? ' (phone)' : ''));
    }

    echo json_encode(['success' => true, 'message' => 'Your request was sent to the administrators. They will update your details.']);
} catch (Throwable $e) {
    error_log('profile-change-request error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'System error. Please try again.']);
}
