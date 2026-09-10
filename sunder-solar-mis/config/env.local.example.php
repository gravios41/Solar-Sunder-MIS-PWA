<?php
// Copy to env.local.php and fill in real values for local development.
// env.local.php is git-ignored. In production set these as real env vars.

$localEnv = [
    'SMTP_HOST'      => 'smtp.gmail.com',
    'SMTP_PORT'      => '587',
    'SMTP_SECURE'    => 'tls',
    'SMTP_USER'      => 'you@gmail.com',
    'SMTP_PASS'      => 'your-16-char-app-password',
    'MAIL_FROM'      => 'you@gmail.com',
    'MAIL_FROM_NAME' => 'Sunder Solar MIS',
];

foreach ($localEnv as $k => $v) {
    if (getenv($k) === false || getenv($k) === '') {
        putenv("$k=$v");
        $_ENV[$k] = $v;
    }
}
