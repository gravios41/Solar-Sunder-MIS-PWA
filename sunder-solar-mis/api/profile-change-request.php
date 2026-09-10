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
    $rows = '';
    if ($wantsEmail) {
        $rows .= '<tr><td style="padding:6px 12px;border:1px solid #e5e7eb">Email</td>'
              . '<td style="padding:6px 12px;border:1px solid #e5e7eb">' . $e($me['email']) . '</td>'
              . '<td style="padding:6px 12px;border:1px solid #e5e7eb"><strong>' . $e($newEmail) . '</strong></td></tr>';
    }
    if ($wantsPhone) {
        $rows .= '<tr><td style="padding:6px 12px;border:1px solid #e5e7eb">Phone</td>'
              . '<td style="padding:6px 12px;border:1px solid #e5e7eb">' . $e($me['phone'] ?? '—') . '</td>'
              . '<td style="padding:6px 12px;border:1px solid #e5e7eb"><strong>' . $e($newPhone) . '</strong></td></tr>';
    }

    $html = '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#1f2937">'
        . '<h2>Profile change request</h2>'
        . '<p><strong>' . $e($me['full_name'] ?: $me['username']) . '</strong> '
        . '(username: ' . $e($me['username']) . ', role: ' . $e($me['role']) . ', ID: ' . $e($me['id']) . ') '
        . 'has requested the following change(s):</p>'
        . '<table style="border-collapse:collapse;margin:12px 0">'
        . '<tr><th style="padding:6px 12px;border:1px solid #e5e7eb;text-align:left">Field</th>'
        . '<th style="padding:6px 12px;border:1px solid #e5e7eb;text-align:left">Current</th>'
        . '<th style="padding:6px 12px;border:1px solid #e5e7eb;text-align:left">Requested</th></tr>'
        . $rows . '</table>'
        . ($reason !== '' ? '<p><strong>Reason:</strong> ' . $e($reason) . '</p>' : '')
        . '<p style="color:#6b7280;font-size:13px">Requested ' . date('M j, Y g:i A') . ' from IP ' . $e($_SERVER['REMOTE_ADDR'] ?? '') . '. '
        . 'Apply the change in User Management if it looks legitimate.</p></div>';

    $sent = 0;
    foreach ($admins as $admin) {
        try {
            sendAppEmail($admin['email'], 'Profile change request from ' . ($me['full_name'] ?: $me['username']), $html);
            $sent++;
        } catch (Exception $ex) {
            error_log('profile-change-request: failed to notify ' . $admin['email'] . ' — ' . $ex->getMessage());
        }
    }

    if ($sent === 0) {
        echo json_encode(['success' => false, 'error' => 'Could not send the request. Please try again later.']);
        exit();
    }

    $_SESSION['profile_change_request_at'] = time();
    if (function_exists('logActivity')) {
        logActivity($_SESSION['user_id'], 'request', 'users',
            'Requested profile change' . ($wantsEmail ? ' (email)' : '') . ($wantsPhone ? ' (phone)' : ''));
    }

    echo json_encode(['success' => true, 'message' => 'Your request was sent to the administrators. They will update your details.']);
} catch (Exception $e) {
    error_log('profile-change-request error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'System error. Please try again.']);
}
