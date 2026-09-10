<?php
// config/mailer.php
// Shared outgoing email via bundled PHPMailer (open source, MIT — see lib/PHPMailer/).
// Reads SMTP settings from environment variables (see env.local.example.php).

require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';

/**
 * Read a config value from the environment. Apache/Render can place vars in
 * any of getenv() / $_SERVER / $_ENV depending on how they were injected,
 * so check all three.
 */
function mailerEnv($key, $default = '') {
    $v = getenv($key);
    if ($v === false || $v === '') $v = $_SERVER[$key] ?? '';
    if ($v === '')                 $v = $_ENV[$key] ?? '';
    if ($v === '' && function_exists('apache_getenv')) {
        $a = @apache_getenv($key);
        if ($a !== false && $a !== null && $a !== '') $v = $a;
    }
    return $v !== '' && $v !== false ? $v : $default;
}

/**
 * Send an HTML email. Throws Exception on failure.
 */
function sendAppEmail($recipient, $subject, $html) {
    $host     = mailerEnv('SMTP_HOST', 'smtp.gmail.com');
    $port     = (int) mailerEnv('SMTP_PORT', '587');
    $username = mailerEnv('SMTP_USER');
    $password = mailerEnv('SMTP_PASS');
    $secure   = strtolower(mailerEnv('SMTP_SECURE', 'tls')); // 'tls' or 'ssl'
    $fromAddr = mailerEnv('MAIL_FROM') ?: $username;
    $fromName = mailerEnv('MAIL_FROM_NAME', 'Sunder Solar MIS');

    if (!$username || !$password || !$fromAddr) {
        throw new Exception('Email service is not configured.');
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $username;
        $mail->Password   = $password;
        $mail->Port       = $port;
        $mail->SMTPSecure = ($secure === 'ssl')
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 20;

        $mail->setFrom($fromAddr, $fromName);
        $mail->addAddress($recipient);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $html)));

        $mail->send();
    } catch (Exception $e) {
        error_log('sendAppEmail failed: ' . $mail->ErrorInfo);
        throw new Exception('Email delivery failed.');
    }
}
