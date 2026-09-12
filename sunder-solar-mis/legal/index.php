<?php
// legal/index.php
// Privacy Policy, Terms of Use, and Acceptable Use & Security Policy.
// Public — no authentication required.
require_once __DIR__ . '/../config/config.php';

$appBasePath = defined('APP_BASE_PATH') ? APP_BASE_PATH : '/';
$lastUpdated = 'September 10, 2026';

// ─────────────────────────────────────────────────────────────
// Fill these in with your real details before going live.
$company   = 'Sunder Solar Energy';
$system    = 'Sunder Solar Management Information System ("Sunder Solar MIS")';
$address   = '[Registered business address, Philippines]';
$contact   = 'privacy@sundersolar.com';           // Data Protection Officer / privacy / security contact
// ─────────────────────────────────────────────────────────────

$section = $_GET['doc'] ?? 'privacy';
$valid   = ['privacy', 'terms', 'aup'];
if (!in_array($section, $valid, true)) $section = 'privacy';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Legal &amp; Privacy — Sunder Solar MIS</title>
    <link rel="icon" type="image/jpg" href="<?php echo htmlspecialchars($appBasePath); ?>assets/images/logo.jpg">
    <style>
        :root { --orange:#F97316; --ink:#1f2937; --muted:#6b7280; --line:#e5e7eb; --bg:#f8fafc; }
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#1f2937;background:#f8fafc;line-height:1.7;-webkit-font-smoothing:antialiased}
        .wrap{max-width:840px;margin:0 auto;padding:32px 20px 80px}
        header.top{display:flex;align-items:center;gap:12px;margin-bottom:8px}
        header.top img{width:40px;height:40px;object-fit:contain;border-radius:8px}
        header.top h1{font-size:1.25rem;font-weight:800}
        .updated{color:#6b7280;font-size:.85rem;margin-bottom:24px}
        nav.docs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:32px;border-bottom:1px solid #e5e7eb;padding-bottom:16px}
        nav.docs a{padding:8px 14px;border-radius:8px;text-decoration:none;color:#1f2937;font-weight:600;font-size:.9rem;border:1px solid #e5e7eb}
        nav.docs a.active{background:#F97316;color:#fff;border-color:#F97316}
        h2{font-size:1.4rem;font-weight:800;margin:0 0 6px}
        h3{font-size:1.05rem;font-weight:700;margin:28px 0 8px}
        p,li{font-size:.95rem;margin-bottom:12px}
        ul,ol{margin:0 0 12px 22px}
        .lede{color:#374151;margin-bottom:20px}
        .box{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px 28px;margin-bottom:20px}
        .callout{background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:16px 20px;font-size:.9rem}
        a.link{color:#c2410c}
        footer.legal{margin-top:40px;font-size:.85rem;color:#6b7280;text-align:center}
        footer.legal a{color:#c2410c;text-decoration:none}
        code{background:#f1f5f9;padding:1px 5px;border-radius:4px;font-size:.85em}
    </style>
</head>
<body>
<div class="wrap">
    <header class="top">
        <img src="<?php echo htmlspecialchars($appBasePath); ?>assets/images/logo.jpg" alt="Sunder Solar">
        <h1>Sunder Solar MIS — Legal &amp; Privacy</h1>
    </header>
    <p class="updated">Last updated: <?php echo $lastUpdated; ?> &nbsp;·&nbsp; Governing law: Republic of the Philippines</p>

    <nav class="docs">
        <a href="?doc=privacy" class="<?php echo $section === 'privacy' ? 'active' : ''; ?>">Privacy Policy</a>
        <a href="?doc=terms" class="<?php echo $section === 'terms' ? 'active' : ''; ?>">Terms of Use</a>
        <a href="?doc=aup" class="<?php echo $section === 'aup' ? 'active' : ''; ?>">Acceptable Use &amp; Security</a>
    </nav>

<?php if ($section === 'privacy'): ?>
    <div class="box">
        <h2>Privacy Policy</h2>
        <p class="lede">This Privacy Policy explains how <?php echo htmlspecialchars($company); ?> ("we", "us", "our")
        collects, uses, stores, and protects personal data processed through the <?php echo htmlspecialchars($system); ?>
        (the "System"), in accordance with the <strong>Data Privacy Act of 2012 (Republic Act No. 10173)</strong>,
        its Implementing Rules and Regulations, and the issuances of the National Privacy Commission (NPC).</p>

        <h3>1. Who we are</h3>
        <p><?php echo htmlspecialchars($company); ?> is the Personal Information Controller for data processed in the System.
        Registered address: <?php echo htmlspecialchars($address); ?>.
        Our Data Protection Officer can be reached at <a class="link" href="mailto:<?php echo htmlspecialchars($contact); ?>"><?php echo htmlspecialchars($contact); ?></a>.</p>

        <h3>2. What we collect</h3>
        <ul>
            <li><strong>Account data:</strong> name, username, email address, phone number, role, hashed password, profile photo.</li>
            <li><strong>Operational data you enter:</strong> client records, energy assessments, quotations, projects, installations, tasks, inventory, reports, and uploaded documents (e.g. electric bills).</li>
            <li><strong>Usage and security data:</strong> login timestamps, IP address, browser/device information, and activity logs (create/update/delete actions).</li>
            <li><strong>Cookies / local storage:</strong> a session cookie for authentication and limited browser storage for interface preferences. No third-party advertising or tracking cookies are used.</li>
        </ul>

        <h3>3. Why we process it (lawful basis)</h3>
        <ul>
            <li>Performance of a contract and legitimate business operations — providing the System to authorised staff of <?php echo htmlspecialchars($company); ?>.</li>
            <li>Compliance with legal obligations (tax, accounting, regulatory record-keeping).</li>
            <li>Legitimate interests — securing the System, preventing fraud and unauthorised access, and maintaining audit trails.</li>
            <li>Consent — where separately requested (e.g. optional notifications).</li>
        </ul>

        <h3>4. Sharing and disclosure</h3>
        <p>We do not sell personal data. We share it only with:</p>
        <ul>
            <li><strong>Sub-processors</strong> that host or support the System (database and hosting providers, and our outbound email provider), bound by confidentiality and data-processing obligations.</li>
            <li><strong>Government authorities</strong> when required by law, subpoena, or lawful order, including under the Cybercrime Prevention Act of 2012 (RA 10175).</li>
        </ul>

        <h3>5. Storage and international transfer</h3>
        <p>Data is stored on managed cloud infrastructure which may be located outside the Philippines. Where data is transferred abroad, we ensure a comparable level of protection through contractual safeguards, consistent with NPC guidance.</p>

        <h3>6. Retention</h3>
        <p>Personal data is retained only for as long as necessary for the purposes above or as required by law, after which it is securely deleted or anonymised. Archived business records are kept for the statutory period applicable to commercial and tax records.</p>

        <h3>7. Security measures</h3>
        <ul>
            <li>Passwords are stored using industry-standard one-way hashing.</li>
            <li>Access is role-based and limited to authorised personnel; sessions expire after inactivity and are protected against fixation and hijacking.</li>
            <li>Transport encryption (HTTPS/TLS) for data in transit.</li>
            <li>Rate limiting on authentication, verified flows for sensitive changes, and audit logging.</li>
        </ul>

        <h3>8. Your rights under the Data Privacy Act</h3>
        <p>Subject to lawful limitations, you have the right to be informed, to access, to rectify, to erase or block, to object, to data portability, to damages, and to lodge a complaint with the NPC. To exercise any right, contact our Data Protection Officer at
        <a class="link" href="mailto:<?php echo htmlspecialchars($contact); ?>"><?php echo htmlspecialchars($contact); ?></a>. You may also complain to the
        <a class="link" href="https://www.privacy.gov.ph" target="_blank" rel="noopener">National Privacy Commission (privacy.gov.ph)</a>.</p>

        <h3>9. Changes</h3>
        <p>We may update this Policy from time to time. Material changes will be notified within the System. Continued use after the effective date constitutes acknowledgement of the updated Policy.</p>

        <div class="callout">
            <strong>Data breach notification:</strong> In the event of a personal data breach likely to result in serious harm,
            we will notify the National Privacy Commission and affected data subjects within 72 hours of knowledge of the breach,
            as required by NPC Circular 16-03.
        </div>
    </div>

<?php elseif ($section === 'terms'): ?>
    <div class="box">
        <h2>Terms of Use</h2>
        <p class="lede">These Terms govern access to and use of the <?php echo htmlspecialchars($system); ?>. By logging in or
        otherwise using the System, you agree to these Terms. If you do not agree, do not access the System.</p>

        <h3>1. Authorised use only</h3>
        <p>The System is a private, internal business application of <?php echo htmlspecialchars($company); ?>. Access is granted
        only to employees, officers, and contractors who have been issued credentials for a legitimate business purpose. Accounts
        are personal and must not be shared. You are responsible for all activity under your account.</p>

        <h3>2. Acceptable conduct</h3>
        <p>You agree to use the System lawfully and in accordance with the <a class="link" href="?doc=aup">Acceptable Use &amp; Security Policy</a>,
        which forms part of these Terms. Prohibited activities include unauthorised access, interference with the System, and misuse of data.</p>

        <h3>3. Data ownership</h3>
        <p>All business records, client data, and content in the System are the property of <?php echo htmlspecialchars($company); ?>.
        You obtain no ownership rights by entering or accessing data. Personal data is handled under our <a class="link" href="?doc=privacy">Privacy Policy</a>.</p>

        <h3>4. Intellectual property</h3>
        <p>The System's software, design, and documentation are protected by the Intellectual Property Code of the Philippines
        (RA 8293) and applicable law. You may not copy, decompile, reverse engineer, resell, or create derivative works except
        as permitted by law or with prior written consent.</p>

        <h3>5. Availability and changes</h3>
        <p>The System is provided on an "as is" and "as available" basis. We may modify, suspend, or discontinue features,
        perform maintenance, or revoke access at any time without liability, subject to applicable law.</p>

        <h3>6. Limitation of liability</h3>
        <p>To the maximum extent permitted by Philippine law, <?php echo htmlspecialchars($company); ?> and the System's
        developers shall not be liable for indirect, incidental, or consequential damages, or for loss arising from misuse,
        unauthorised access caused by the user's failure to safeguard credentials, or force majeure. Nothing in these Terms
        excludes liability that cannot be excluded by law.</p>

        <h3>7. Indemnity</h3>
        <p>You agree to indemnify and hold harmless <?php echo htmlspecialchars($company); ?> and its developers from claims,
        losses, and expenses arising from your breach of these Terms or your unlawful use of the System.</p>

        <h3>8. Termination</h3>
        <p>Access ends automatically when your engagement with <?php echo htmlspecialchars($company); ?> ends, or upon breach
        of these Terms. Provisions on data ownership, IP, liability, and indemnity survive termination.</p>

        <h3>9. Governing law and venue</h3>
        <p>These Terms are governed by the laws of the Republic of the Philippines. Disputes shall be submitted to the exclusive
        jurisdiction of the proper courts of [City], Philippines.</p>

        <h3>10. Contact</h3>
        <p>Questions about these Terms: <a class="link" href="mailto:<?php echo htmlspecialchars($contact); ?>"><?php echo htmlspecialchars($contact); ?></a>.</p>
    </div>

<?php else: ?>
    <div class="box">
        <h2>Acceptable Use &amp; Security Policy</h2>
        <p class="lede">This Policy defines lawful and authorised use of the <?php echo htmlspecialchars($system); ?> and is issued
        for the protection of <?php echo htmlspecialchars($company); ?>, its personnel, its clients, and the developers of the System.
        It is aligned with the <strong>Cybercrime Prevention Act of 2012 (Republic Act No. 10175)</strong> and the
        <strong>Data Privacy Act of 2012 (Republic Act No. 10173)</strong>.</p>

        <h3>1. Authorised access</h3>
        <p>Access to this System is restricted to individuals expressly authorised by <?php echo htmlspecialchars($company); ?>.
        Any access without right, or in excess of authorisation, constitutes <em>Illegal Access</em> under Section 4(a)(1) of
        RA 10175 and may be prosecuted. Continued use of a login screen or credentials by an unauthorised person is not permitted.</p>

        <h3>2. Prohibited activities</h3>
        <p>Users and any other person must not:</p>
        <ul>
            <li>Access, intercept, or attempt to access data or accounts without authorisation (RA 10175 §4(a)(1), §4(a)(3));</li>
            <li>Introduce malware, or delete, deteriorate, alter, or suppress data or system function (Data/System Interference, §4(a)(2)–(3));</li>
            <li>Use or possess devices, passwords, or codes to commit any of the above (Misuse of Devices, §4(a)(5));</li>
            <li>Engage in computer-related forgery, fraud, or identity theft (§4(b));</li>
            <li>Circumvent authentication, rate limits, logging, or access controls;</li>
            <li>Copy, export, or disclose client or company data except as required for authorised work;</li>
            <li>Use the System to harass, defame, or unlawfully process another person's data.</li>
        </ul>

        <h3>3. Monitoring and logging (notice)</h3>
        <p>For security and audit purposes, the System records login times, IP addresses, device information, and create/update/delete
        actions. By using the System you consent to this monitoring. Logs may be used in internal investigations and provided to law
        enforcement pursuant to a lawful order.</p>

        <h3>4. Credential security</h3>
        <ul>
            <li>Keep your password confidential; never share your account.</li>
            <li>Use a strong, unique password and change it if you suspect compromise.</li>
            <li>Log out on shared devices; sessions time out after inactivity.</li>
            <li>Report suspected security incidents immediately to <a class="link" href="mailto:<?php echo htmlspecialchars($contact); ?>"><?php echo htmlspecialchars($contact); ?></a>.</li>
        </ul>

        <h3>5. Responsible disclosure</h3>
        <p>Security researchers who discover a vulnerability may report it in good faith to
        <a class="link" href="mailto:<?php echo htmlspecialchars($contact); ?>"><?php echo htmlspecialchars($contact); ?></a>.
        We will not pursue legal action against researchers who: act in good faith, do not access or modify data beyond the minimum
        necessary to demonstrate the issue, do not disrupt services, and give us reasonable time to remediate before public disclosure.
        This does not authorise access to third-party or client data.</p>

        <h3>6. Developer protection</h3>
        <p>The System is provided by its developers to <?php echo htmlspecialchars($company); ?> for authorised internal use.
        The developers are not liable for damage resulting from unauthorised access, misuse, modification of the software by third
        parties, or use outside the intended scope. Any attempt to attribute unlawful acts of users to the developers is disclaimed.</p>

        <h3>7. Enforcement</h3>
        <p>Violations may result in suspension of access, disciplinary action, civil liability, and referral to the National Bureau
        of Investigation Cybercrime Division or the PNP Anti-Cybercrime Group for prosecution under RA 10175, RA 10173, the Revised
        Penal Code, and other applicable laws.</p>

        <div class="callout">
            <strong>Warning:</strong> Unauthorised access to this computer system is prohibited and punishable under Republic Act
            No. 10175. Disconnect now if you are not an authorised user.
        </div>
    </div>
<?php endif; ?>

    <footer class="legal">
        <p>
            <a href="?doc=privacy">Privacy Policy</a> &nbsp;·&nbsp;
            <a href="?doc=terms">Terms of Use</a> &nbsp;·&nbsp;
            <a href="?doc=aup">Acceptable Use &amp; Security</a>
        </p>
        <p style="margin-top:8px">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($company); ?>. All rights reserved.
        &nbsp;·&nbsp; <a href="<?php echo htmlspecialchars($appBasePath); ?>auth/login.php">Back to sign in</a></p>
    </footer>
</div>
</body>
</html>
