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
 * Wrap message content in the Sunder Solar MIS branded email shell
 * (orange gradient header, logo, card body, footer). Table-based layout
 * with inline styles for broad email-client compatibility.
 *
 * @param string $heading   Big title shown under the logo
 * @param string $bodyHtml   Inner HTML (paragraphs, buttons, tables…)
 * @param string $preheader  Hidden preview text shown in the inbox list
 */
function emailShell($heading, $bodyHtml, $preheader = '') {
    $siteUrl = defined('SITE_URL') ? SITE_URL : '';
    $logo    = $siteUrl ? $siteUrl . 'assets/images/logo.jpg' : '';
    $year    = date('Y');
    $h       = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
    $pre     = htmlspecialchars($preheader, ENT_QUOTES, 'UTF-8');

    $logoTag = $logo
        ? '<img src="' . htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') . '" width="46" height="46" alt="Sunder Solar" style="display:block;border-radius:10px;background:#ffffff">'
        : '';

    return '<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased">
<div style="display:none;max-height:0;overflow:hidden;opacity:0">' . $pre . '</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 12px">
<tr><td align="center">
  <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(15,23,42,0.08)">

    <!-- Header -->
    <tr><td style="background:linear-gradient(135deg,#F97316,#F59E0B);padding:28px 32px">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
        <td width="46" style="vertical-align:middle">' . $logoTag . '</td>
        <td style="vertical-align:middle;padding-left:14px">
          <div style="color:#ffffff;font-size:17px;font-weight:700;letter-spacing:-0.01em">Sunder Solar MIS</div>
          <div style="color:rgba(255,255,255,0.85);font-size:12px">Management Information System</div>
        </td>
      </tr></table>
    </td></tr>

    <!-- Body -->
    <tr><td style="padding:36px 32px 28px">
      <h1 style="margin:0 0 18px;font-size:22px;font-weight:800;color:#0F172A;letter-spacing:-0.02em">' . $h . '</h1>
      <div style="font-size:15px;line-height:1.65;color:#334155">' . $bodyHtml . '</div>
    </td></tr>

    <!-- Footer -->
    <tr><td style="padding:20px 32px;background:#0F172A">
      <div style="color:#94A3B8;font-size:12px;line-height:1.6">
        This is an automated message from Sunder Solar MIS. Please do not reply.<br>
        &copy; ' . $year . ' Sunder Solar Energy. All rights reserved.
      </div>
    </td></tr>

  </table>
</td></tr>
</table>
</body></html>';
}

/**
 * Build the standard orange call-to-action button (bulletproof table button).
 */
function emailButton($label, $url) {
    $u = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $l = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0"><tr>
        <td style="border-radius:10px;background:linear-gradient(135deg,#F97316,#F59E0B)">
          <a href="' . $u . '" style="display:inline-block;padding:13px 30px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:10px">' . $l . '</a>
        </td></tr></table>';
}

/**
 * Send an HTML email. Throws Exception on failure.
 *
 * Transport is chosen automatically:
 *   - RESEND_API_KEY set  -> HTTPS call to Resend's API (works even when the
 *     host blocks outbound SMTP, e.g. Render's free tier — port 443 only).
 *   - otherwise            -> PHPMailer over SMTP (needs an unblocked SMTP
 *     egress path — fine locally / on hosts that allow it).
 * Every caller keeps using sendAppEmail($to, $subject, $html) unchanged;
 * nothing but this function needs to know which transport is active.
 *
 * @param int $timeoutSeconds  Connection/response timeout. Lower this for a
 *   "best effort, don't make the user wait" send (e.g. a courtesy copy that
 *   isn't required for the feature to work) — a broken transport then fails
 *   fast instead of holding the HTTP response open for the full default.
 */
function sendAppEmail($recipient, $subject, $html, $timeoutSeconds = 15) {
    $resendKey = mailerEnv('RESEND_API_KEY');
    if ($resendKey) {
        sendViaResend($recipient, $subject, $html, $resendKey, $timeoutSeconds);
        return;
    }
    sendViaSmtp($recipient, $subject, $html, $timeoutSeconds);
}

/**
 * Resend (https://resend.com) HTTPS API — one JSON POST, no SMTP port needed.
 * Free tier: 100/day, 3000/month, no card. Sender defaults to Resend's
 * shared testing address (works with zero setup); set RESEND_FROM once a
 * custom domain is verified with Resend.
 */
function sendViaResend($recipient, $subject, $html, $apiKey, $timeoutSeconds = 15) {
    $fromAddr = mailerEnv('RESEND_FROM', 'onboarding@resend.dev');
    $fromName = mailerEnv('MAIL_FROM_NAME', 'Sunder Solar MIS');

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeoutSeconds,
        CURLOPT_CONNECTTIMEOUT => min(5, $timeoutSeconds),
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode([
            'from'    => $fromName . ' <' . $fromAddr . '>',
            'to'      => [$recipient],
            'subject' => $subject,
            'html'    => $html,
        ]),
    ]);
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($response === false || $error || $status < 200 || $status >= 300) {
        error_log('sendViaResend failed: HTTP ' . $status . ' ' . $error . ' body=' . $response);
        throw new Exception('Email delivery failed.');
    }
}

/**
 * PHPMailer over SMTP — the fallback/local-dev transport.
 */
function sendViaSmtp($recipient, $subject, $html, $timeoutSeconds = 15) {
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
        $mail->Timeout    = $timeoutSeconds;

        $mail->setFrom($fromAddr, $fromName);
        $mail->addAddress($recipient);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $html)));

        $mail->send();
    } catch (Exception $e) {
        error_log('sendViaSmtp failed: ' . $mail->ErrorInfo);
        throw new Exception('Email delivery failed.');
    }
}
