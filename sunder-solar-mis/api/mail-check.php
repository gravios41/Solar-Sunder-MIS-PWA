<?php
// api/mail-check.php
// Super-admin-only diagnostic: shows which SMTP settings the server can see
// and (optionally) sends a test email. Visit:
//   /api/mail-check.php           -> shows config status
//   /api/mail-check.php?send=you@example.com  -> also sends a test message

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/mailer.php';

if (($_SESSION['user_role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Super Admin only']);
    exit();
}

$keys = ['RESEND_API_KEY', 'RESEND_FROM', 'SMTP_HOST', 'SMTP_PORT', 'SMTP_SECURE', 'SMTP_USER', 'SMTP_PASS', 'MAIL_FROM', 'MAIL_FROM_NAME'];
$status = [];
foreach ($keys as $k) {
    $val = mailerEnv($k);
    $seenIn = [];
    if (getenv($k) !== false && getenv($k) !== '') $seenIn[] = 'getenv';
    if (($_SERVER[$k] ?? '') !== '')                $seenIn[] = '$_SERVER';
    if (($_ENV[$k] ?? '') !== '')                   $seenIn[] = '$_ENV';

    if ($val === '') {
        $status[$k] = 'MISSING';
    } elseif (in_array($k, ['SMTP_PASS', 'RESEND_API_KEY'], true)) {
        $status[$k] = 'set (' . strlen($val) . ' chars) via ' . implode('+', $seenIn);
    } elseif ($k === 'SMTP_USER' || $k === 'MAIL_FROM' || $k === 'RESEND_FROM') {
        $parts = explode('@', $val);
        $status[$k] = (isset($parts[1]) ? substr($parts[0], 0, 2) . '***@' . $parts[1] : $val) . ' via ' . implode('+', $seenIn);
    } else {
        $status[$k] = $val . ' via ' . implode('+', $seenIn);
    }
}

$usingResend = (bool) mailerEnv('RESEND_API_KEY');
$configured  = $usingResend
    ? true
    : (mailerEnv('SMTP_USER') && mailerEnv('SMTP_PASS') && (mailerEnv('MAIL_FROM') || mailerEnv('SMTP_USER')));

$runtimeFile = __DIR__ . '/../../env.runtime';
$result = [
    'transport'         => $usingResend ? 'resend (HTTPS)' : 'smtp',
    'configured'        => (bool) $configured,
    'runtime_env_file'  => is_file($runtimeFile) ? 'present' : 'absent (using getenv/dashboard only)',
    'settings'          => $status,
];
if (!$usingResend) {
    $result['note'] = 'Using raw SMTP — this times out on Render (outbound SMTP is blocked). Set RESEND_API_KEY to switch to the HTTPS transport.';
}

$send = $_GET['send'] ?? '';
if ($send && filter_var($send, FILTER_VALIDATE_EMAIL)) {
    try {
        sendAppEmail($send, 'Sunder Solar MIS — mail test', '<p>SMTP is working. Sent ' . date('c') . '.</p>');
        $result['test_send'] = 'OK — sent to ' . $send;
    } catch (Exception $e) {
        $result['test_send'] = 'FAILED — ' . $e->getMessage();
    }
}

echo json_encode($result, JSON_PRETTY_PRINT);
