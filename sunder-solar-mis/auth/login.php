<?php
// auth/login.php
require_once __DIR__ . '/../config/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'modules/dashboard.php');
    exit();
}

$appBasePath = defined('APP_BASE_PATH') ? APP_BASE_PATH : '/';

$error = $_GET['error'] ?? '';
$msgs  = [
    'empty_fields'       => 'Please enter your username and password.',
    'invalid_credentials'=> 'Incorrect username or password. Please try again.',
    'account_disabled'   => 'Your account is disabled. Contact the administrator.',
    'session_expired'    => 'Your session has expired. Please sign in again.',
    'system_error'       => 'A system error occurred. Please try again later.',
];
$errorMessage = $msgs[$error] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#F97316">
    <meta name="application-name" content="Sunder Solar MIS">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Sunder MIS">
    <title>Sign In — Sunder Solar MIS</title>
    <script>window.APP_BASE_PATH = <?php echo json_encode($appBasePath); ?>;</script>
    <link rel="manifest" href="<?php echo htmlspecialchars($appBasePath); ?>manifest.json">
    <link rel="icon" type="image/jpg" sizes="192x192" href="<?php echo htmlspecialchars($appBasePath); ?>assets/images/logo.jpg">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($appBasePath); ?>assets/images/icons/icon-192.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --orange:  #F97316;
            --amber:   #F59E0B;
            --yellow:  #FCD34D;
            --dark:    #0F172A;
            --mid:     #1E293B;
            --light:   #94A3B8;
            --border:  rgba(255,255,255,0.12);
            --success: #10B981;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: var(--dark);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: auto;
            -webkit-overflow-scrolling: touch;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Animated Background ── */
        .bg-scene {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
        }
        .bg-gradient {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% 10%, rgba(249,115,22,0.22) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 80% 80%, rgba(245,158,11,0.16) 0%, transparent 60%),
                radial-gradient(ellipse 40% 40% at 60% 20%, rgba(252,211,77,0.10) 0%, transparent 50%),
                linear-gradient(160deg, #0F172A 0%, #1a2744 50%, #0F172A 100%);
        }
        /* Grid lines */
        .bg-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 60px 60px;
        }
        /* Floating orbs */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            animation: orbFloat linear infinite;
            opacity: 0.4;
        }
        .orb-1 { width: 400px; height: 400px; background: rgba(249,115,22,0.25); top: -100px; left: -100px; animation-duration: 18s; }
        .orb-2 { width: 300px; height: 300px; background: rgba(245,158,11,0.2);  bottom: -80px; right: -80px;  animation-duration: 22s; animation-direction: reverse; }
        .orb-3 { width: 200px; height: 200px; background: rgba(252,211,77,0.15); top: 50%; left: 50%;  animation-duration: 15s; animation-delay: -8s; }

        @keyframes orbFloat {
            0%   { transform: translate(0, 0) rotate(0deg); }
            33%  { transform: translate(40px, -30px) rotate(120deg); }
            66%  { transform: translate(-20px, 40px) rotate(240deg); }
            100% { transform: translate(0, 0) rotate(360deg); }
        }

        /* Floating particles */
        .particles { position: absolute; inset: 0; overflow: hidden; }
        .particle {
            position: absolute;
            width: 3px; height: 3px;
            background: rgba(249,115,22,0.5);
            border-radius: 50%;
            animation: particleRise linear infinite;
        }
        @keyframes particleRise {
            0%   { transform: translateY(100vh) scale(0); opacity: 0; }
            10%  { opacity: 1; }
            90%  { opacity: 0.5; }
            100% { transform: translateY(-20px) scale(1); opacity: 0; }
        }

        /* ── Login Card ── */
        .login-wrap {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1000px;
            animation: cardEntry 0.7s cubic-bezier(0.16,1,0.3,1) both;
        }
        @keyframes cardEntry {
            from { opacity: 0; transform: translateY(30px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Top logo */
        .top-brand {
            text-align: center;
            margin-bottom: 28px;
            animation: fadeInDown 0.6s ease 0.1s both;
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .brand-logo-img {
            width: 120px;
            height: 120px;
            object-fit: contain;
            border-radius: 20px;
            margin-bottom: 10px;
            filter: drop-shadow(0 8px 24px rgba(249,115,22,0.45));
            animation: pulse-ring 3s ease infinite;
        }
        @keyframes pulse-ring {
            0%, 100% { filter: drop-shadow(0 8px 24px rgba(249,115,22,0.45)); }
            50%       { filter: drop-shadow(0 8px 32px rgba(249,115,22,0.75)); }
        }
        .top-brand h1 { font-size: 1.75rem; font-weight: 800; color: #fff; letter-spacing: -0.03em; }
        .top-brand p  { color: var(--light); font-size: 0.875rem; margin-top: 4px; }

        /* Card container */
        .card {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-radius: 24px;
            border: 1px solid var(--border);
            box-shadow: 0 32px 80px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.06);
            overflow: hidden;
        }

        /* Left panel */
        .panel-left {
            background: linear-gradient(145deg, rgba(249,115,22,0.15) 0%, rgba(245,158,11,0.08) 100%);
            border-right: 1px solid var(--border);
            padding: 40px;
            display: flex;
            flex-direction: column;
            animation: slideInLeft 0.6s ease 0.2s both;
        }
        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-24px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        .panel-left h2 { font-size: 1.25rem; font-weight: 700; color: #fff; margin-bottom: 6px; }
        .panel-left p  { font-size: 0.82rem; color: var(--light); margin-bottom: 28px; }

        /* Role cards */
        .role-cards { display: flex; flex-direction: column; gap: 10px; flex: 1; }
        .role-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            border-radius: 14px;
            border: 1.5px solid rgba(255,255,255,0.08);
            cursor: pointer;
            transition: all 0.25s ease;
            background: rgba(255,255,255,0.04);
            position: relative;
            overflow: hidden;
        }
        .role-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(249,115,22,0.12), rgba(245,158,11,0.06));
            opacity: 0;
            transition: opacity 0.2s;
        }
        .role-card:hover { border-color: rgba(249,115,22,0.4); transform: translateX(4px); }
        .role-card:hover::before { opacity: 1; }
        .role-card.active {
            border-color: var(--orange);
            background: rgba(249,115,22,0.12);
            transform: translateX(4px);
        }
        .role-card.active::before { opacity: 1; }

        .role-icon-wrap {
            width: 40px; height: 40px;
            border-radius: 10px;
            background: rgba(255,255,255,0.08);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            font-size: 1rem;
            color: var(--light);
            transition: all 0.2s ease;
        }
        .role-card.active .role-icon-wrap,
        .role-card:hover .role-icon-wrap {
            background: rgba(249,115,22,0.2);
            color: var(--orange);
        }

        .role-info { flex: 1; }
        .role-info strong { display: block; font-size: 0.875rem; font-weight: 600; color: #fff; line-height: 1.3; }
        .role-info span   { font-size: 0.75rem; color: var(--light); }

        .role-check {
            width: 22px; height: 22px;
            border-radius: 50%;
            background: var(--success);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            opacity: 0;
            transform: scale(0);
            transition: all 0.2s var(--ease-spring, cubic-bezier(0.34,1.56,0.64,1));
            font-size: 0.65rem;
            color: #fff;
        }
        .role-card.active .role-check { opacity: 1; transform: scale(1); }

        /* Right panel */
        .panel-right {
            padding: 40px;
            background: rgba(15,23,42,0.6);
            animation: slideInRight 0.6s ease 0.3s both;
        }
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(24px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        .panel-right h2 { font-size: 1.5rem; font-weight: 800; color: #fff; letter-spacing: -0.02em; margin-bottom: 4px; }
        .panel-right p  { font-size: 0.82rem; color: var(--light); margin-bottom: 32px; }

        /* Error alert */
        .alert-error {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 13px 16px;
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.25);
            border-radius: 12px;
            margin-bottom: 22px;
            animation: shake 0.5s ease;
            color: #FCA5A5;
            font-size: 0.85rem;
        }
        .alert-error i { color: #EF4444; margin-top: 1px; }

        @keyframes shake {
            0%,100% { transform: translateX(0); }
            20%      { transform: translateX(-8px); }
            40%      { transform: translateX(8px); }
            60%      { transform: translateX(-4px); }
            80%      { transform: translateX(4px); }
        }

        /* Form */
        .form-group { margin-bottom: 20px; }
        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: rgba(255,255,255,0.75);
            margin-bottom: 8px;
        }
        .input-wrap { position: relative; }
        .input-icon {
            position: absolute;
            left: 14px; top: 50%;
            transform: translateY(-50%);
            color: var(--light);
            font-size: 0.85rem;
            pointer-events: none;
            transition: color 0.2s;
        }
        .form-input {
            width: 100%;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 12px 14px 12px 40px;
            color: #fff;
            font-size: 0.875rem;
            font-family: inherit;
            transition: all 0.2s ease;
            outline: none;
        }
        .form-input::placeholder { color: rgba(255,255,255,0.25); }
        .form-input:hover  { border-color: rgba(255,255,255,0.18); }
        .form-input:focus  {
            border-color: var(--orange);
            background: rgba(249,115,22,0.06);
            box-shadow: 0 0 0 3px rgba(249,115,22,0.12);
        }
        .form-input:focus + .input-icon,
        .input-wrap:focus-within .input-icon { color: var(--orange); }

        /* Password toggle */
        .pw-toggle {
            position: absolute;
            right: 14px; top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--light);
            cursor: pointer;
            font-size: 0.85rem;
            transition: color 0.2s;
            padding: 0;
        }
        .pw-toggle:hover { color: #fff; }

        /* Remember / forgot */
        .form-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }
        .checkbox-wrap { display: flex; align-items: center; gap: 8px; cursor: pointer; }
        .checkbox-wrap input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--orange); cursor: pointer; }
        .checkbox-wrap span { font-size: 0.82rem; color: var(--light); }
        .forgot-link { font-size: 0.82rem; color: var(--orange); text-decoration: none; transition: opacity 0.2s; }
        .forgot-link:hover { opacity: 0.7; }

        /* Submit button */
        .btn-submit {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #F97316, #F59E0B);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 0.925rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 4px 16px rgba(249,115,22,0.35);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-submit::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, transparent 60%);
            opacity: 0;
            transition: opacity 0.2s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(249,115,22,0.45);
        }
        .btn-submit:hover::after { opacity: 1; }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit.loading { opacity: 0.8; cursor: wait; }

        .btn-submit .spinner {
            width: 18px; height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            display: none;
        }
        .btn-submit.loading .spinner { display: block; }
        .btn-submit.loading .btn-text { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Footer */
        .card-footer {
            padding: 16px 40px;
            border-top: 1px solid var(--border);
            text-align: center;
            color: rgba(255,255,255,0.25);
            font-size: 0.75rem;
            grid-column: 1 / -1;
        }

        /* Responsive */
        @media (max-width: 700px) {
            /* Allow scrolling on mobile */
            body {
                align-items: flex-start;
                padding-top: 60px;
                padding-bottom: 60px;
            }

            .login-wrap {
                width: 100%;
                max-width: 100%;
            }

            .card { 
                grid-template-columns: 1fr;
                max-height: none;
            }
            
            .panel-left { 
                border-right: none; 
                border-bottom: 1px solid var(--border); 
                padding: 28px;
                max-height: 400px;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .panel-right { 
                padding: 28px;
            }

            .card-footer { 
                padding: 14px 28px;
            }

            /* Make role cards scrollable on small screens */
            .role-cards {
                max-height: 280px;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
            }

            /* Modal scrolling on mobile */
            #forgotModal {
                display: flex !important;
                align-items: flex-start;
                padding-top: 60px;
                padding-bottom: 60px;
            }

            /* Prevent iOS auto-zoom on input focus */
            .form-input,
            input[type="text"],
            input[type="email"],
            input[type="password"],
            textarea {
                font-size: 16px !important;
                padding: 14px 16px 14px 44px !important;
            }

            .form-label {
                font-size: 0.85rem !important;
            }

            .checkbox-wrap {
                font-size: 0.875rem !important;
            }
        }
    </style>
</head>
<body>

<!-- Animated background -->
<div class="bg-scene">
    <div class="bg-gradient"></div>
    <div class="bg-grid"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
    <div class="particles" id="particles"></div>
</div>

<!-- ── Forgot Password Modal ── -->
<div id="forgotModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:999;align-items:center;justify-content:center;padding:20px;overflow-y:auto;-webkit-overflow-scrolling:touch">
    <div style="background:#1E293B;border:1px solid rgba(255,255,255,0.12);border-radius:20px;padding:36px 32px;width:100%;max-width:420px;position:relative;animation:cardEntry 0.3s ease">
        <button onclick="closeForgotModal()" style="position:absolute;top:16px;right:18px;background:none;border:none;color:rgba(255,255,255,0.4);font-size:1.4rem;cursor:pointer;line-height:1">&times;</button>

        <div style="text-align:center;margin-bottom:24px">
            <div style="width:52px;height:52px;background:linear-gradient(135deg,#F97316,#F59E0B);border-radius:14px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px">
                <i class="fas fa-lock-open" style="color:#fff;font-size:1.3rem"></i>
            </div>
            <h3 style="color:#fff;font-size:1.2rem;font-weight:700;margin:0 0 4px">Reset Password</h3>
            <p id="fpSubtitle" style="color:#94A3B8;font-size:0.82rem;margin:0">Enter your username and registered email</p>
        </div>

        <!-- Alert box -->
        <div id="fpAlert" style="display:none;padding:11px 14px;border-radius:10px;margin-bottom:18px;font-size:0.84rem;display:flex;align-items:center;gap:8px"></div>

        <!-- Step 1: Verify identity -->
        <div id="fpStep1">
            <div style="margin-bottom:16px">
                <label style="display:block;font-size:0.8rem;font-weight:600;color:rgba(255,255,255,0.75);margin-bottom:7px">Username</label>
                <div style="position:relative">
                    <input type="text" id="fpUsername" placeholder="Enter your username"
                           style="width:100%;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.1);border-radius:11px;padding:11px 14px 11px 38px;color:#fff;font-size:0.875rem;outline:none;font-family:inherit"
                           onfocus="this.style.borderColor='#F97316'" onblur="this.style.borderColor='rgba(255,255,255,0.1)'">
                    <i class="fas fa-user" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:0.82rem"></i>
                </div>
            </div>
            <div style="margin-bottom:22px">
                <label style="display:block;font-size:0.8rem;font-weight:600;color:rgba(255,255,255,0.75);margin-bottom:7px">Registered Email</label>
                <div style="position:relative">
                    <input type="email" id="fpEmail" placeholder="Enter your email address"
                           style="width:100%;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.1);border-radius:11px;padding:11px 14px 11px 38px;color:#fff;font-size:0.875rem;outline:none;font-family:inherit"
                           onfocus="this.style.borderColor='#F97316'" onblur="this.style.borderColor='rgba(255,255,255,0.1)'"
                           onkeydown="if(event.key==='Enter') verifyIdentity()">
                    <i class="fas fa-envelope" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:0.82rem"></i>
                </div>
            </div>
            <button onclick="verifyIdentity()" id="fpVerifyBtn"
                    style="width:100%;padding:12px;background:linear-gradient(135deg,#F97316,#F59E0B);color:#fff;border:none;border-radius:11px;font-size:0.9rem;font-weight:700;cursor:pointer;font-family:inherit">
                <span id="fpVerifyText"><i class="fas fa-arrow-right"></i> Continue</span>
            </button>
        </div>

        <!-- Step 2: New password -->
        <div id="fpStep2" style="display:none">
            <div style="margin-bottom:16px">
                <label style="display:block;font-size:0.8rem;font-weight:600;color:rgba(255,255,255,0.75);margin-bottom:7px">New Password</label>
                <div style="position:relative">
                    <input type="password" id="fpNewPw" placeholder="At least 6 characters"
                           style="width:100%;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.1);border-radius:11px;padding:11px 38px 11px 38px;color:#fff;font-size:0.875rem;outline:none;font-family:inherit"
                           onfocus="this.style.borderColor='#F97316'" onblur="this.style.borderColor='rgba(255,255,255,0.1)'">
                    <i class="fas fa-lock" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:0.82rem"></i>
                    <button type="button" onclick="toggleFpPw('fpNewPw','fpNewPwIcon')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94A3B8;cursor:pointer;font-size:0.82rem;padding:0">
                        <i id="fpNewPwIcon" class="fas fa-eye-slash"></i>
                    </button>
                </div>
            </div>
            <div style="margin-bottom:22px">
                <label style="display:block;font-size:0.8rem;font-weight:600;color:rgba(255,255,255,0.75);margin-bottom:7px">Confirm Password</label>
                <div style="position:relative">
                    <input type="password" id="fpConfirmPw" placeholder="Repeat new password"
                           style="width:100%;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.1);border-radius:11px;padding:11px 38px 11px 38px;color:#fff;font-size:0.875rem;outline:none;font-family:inherit"
                           onfocus="this.style.borderColor='#F97316'" onblur="this.style.borderColor='rgba(255,255,255,0.1)'"
                           onkeydown="if(event.key==='Enter') resetPassword()">
                    <i class="fas fa-lock" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:0.82rem"></i>
                    <button type="button" onclick="toggleFpPw('fpConfirmPw','fpConfirmPwIcon')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94A3B8;cursor:pointer;font-size:0.82rem;padding:0">
                        <i id="fpConfirmPwIcon" class="fas fa-eye-slash"></i>
                    </button>
                </div>
            </div>
            <button onclick="resetPassword()" id="fpResetBtn"
                    style="width:100%;padding:12px;background:linear-gradient(135deg,#F97316,#F59E0B);color:#fff;border:none;border-radius:11px;font-size:0.9rem;font-weight:700;cursor:pointer;font-family:inherit">
                <span id="fpResetText"><i class="fas fa-key"></i> Reset Password</span>
            </button>
            <button onclick="goBackStep1()" style="width:100%;padding:10px;background:none;border:none;color:#94A3B8;font-size:0.82rem;cursor:pointer;margin-top:10px;font-family:inherit">
                <i class="fas fa-arrow-left"></i> Back
            </button>
        </div>
    </div>
</div>

<!-- Login card -->
<div class="login-wrap">
    <!-- Brand header -->
    <div class="top-brand">
        <img src="<?php echo htmlspecialchars($appBasePath); ?>assets/images/logo.jpg"
             alt="Sunder Solar Energy" class="brand-logo-img">
        <h1>Sunder Solar Energy</h1>
        <p>Management Information System</p>
    </div>

    <div class="card">
        <!-- Left: role selection -->
        <div class="panel-left">
            <h2>Select Your Role</h2>
            <p>Choose your access level to continue</p>

            <div class="role-cards">
                <div class="role-card" data-role="super_admin" onclick="selectRole('super_admin')">
                    <div class="role-icon-wrap">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="role-info">
                        <strong>Super Admin</strong>
                        <span>Full system access &amp; user management</span>
                    </div>
                    <div class="role-check"><i class="fas fa-check"></i></div>
                </div>

                <div class="role-card" data-role="admin" onclick="selectRole('admin')">
                    <div class="role-icon-wrap">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <div class="role-info">
                        <strong>Admin</strong>
                        <span>Edit &amp; view existing records</span>
                    </div>
                    <div class="role-check"><i class="fas fa-check"></i></div>
                </div>

                <div class="role-card" data-role="owner" onclick="selectRole('owner')">
                    <div class="role-icon-wrap">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="role-info">
                        <strong>Owner</strong>
                        <span>Full business access &amp; oversight</span>
                    </div>
                    <div class="role-check"><i class="fas fa-check"></i></div>
                </div>

                <div class="role-card" data-role="employee" onclick="selectRole('employee')">
                    <div class="role-icon-wrap">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="role-info">
                        <strong>Employee</strong>
                        <span>Quotations, projects &amp; task access</span>
                    </div>
                    <div class="role-check"><i class="fas fa-check"></i></div>
                </div>
            </div>
        </div>

        <!-- Right: login form -->
        <div class="panel-right">
            <h2>Welcome Back</h2>
            <p>Enter your credentials to access the system</p>

            <?php if ($errorMessage): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($errorMessage); ?></span>
            </div>
            <?php endif; ?>

            <form id="loginForm" method="POST" action="authenticate.php">
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <div class="input-wrap">
                        <input type="text" id="username" name="username"
                               class="form-input" placeholder="Enter username" required autocomplete="username">
                        <i class="fas fa-user input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrap">
                        <input type="password" id="password" name="password"
                               class="form-input" placeholder="Enter password" required autocomplete="current-password">
                        <i class="fas fa-lock input-icon"></i>
                        <button type="button" class="pw-toggle" id="pwToggle" aria-label="Toggle password">
                            <i class="fas fa-eye-slash" id="pwToggleIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-meta">
                    <label class="checkbox-wrap">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="forgot-link" onclick="openForgotModal(); return false;">Forgot password?</a>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    <span class="spinner"></span>
                    <span class="btn-text"><i class="fas fa-sign-in-alt"></i> Sign In</span>
                </button>
            </form>
        </div>

        <!-- Footer -->
        <div class="card-footer">
            Powered by Sunder Solar &nbsp;•&nbsp; &copy; <?php echo date('Y'); ?> All rights reserved
        </div>
    </div>
</div>

<script>
/* ── Particles ── */
(function() {
    const container = document.getElementById('particles');
    for (let i = 0; i < 18; i++) {
        const p = document.createElement('div');
        p.className = 'particle';
        p.style.cssText = `
            left: ${Math.random() * 100}%;
            width: ${Math.random() * 3 + 1}px;
            height: ${Math.random() * 3 + 1}px;
            animation-duration: ${Math.random() * 12 + 8}s;
            animation-delay: ${Math.random() * 10}s;
            opacity: ${Math.random() * 0.6 + 0.2};
        `;
        container.appendChild(p);
    }
})();

/* ── Role selection (visual only) ── */
function selectRole(role) {
    document.querySelectorAll('.role-card').forEach(c => c.classList.remove('active'));
    document.querySelector(`[data-role="${role}"]`).classList.add('active');
}

/* ── Password toggle ── */
const pwToggle  = document.getElementById('pwToggle');
const pwInput   = document.getElementById('password');
const pwIcon    = document.getElementById('pwToggleIcon');
if (pwToggle) {
    pwToggle.addEventListener('click', () => {
        const isText = pwInput.type === 'text';
        pwInput.type = isText ? 'password' : 'text';
        pwIcon.className = isText ? 'fas fa-eye-slash' : 'fas fa-eye';
    });
}

/* ── Form submit spinner ── */
document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.classList.add('loading');
});

/* ── Forgot Password ── */
let fpUserId = null;

function openForgotModal() {
    document.getElementById('forgotModal').style.display = 'flex';
    document.getElementById('fpStep1').style.display = 'block';
    document.getElementById('fpStep2').style.display = 'none';
    document.getElementById('fpUsername').value = '';
    document.getElementById('fpEmail').value = '';
    fpShowAlert('', '');
    document.getElementById('fpSubtitle').textContent = 'Enter your username and registered email';
    setTimeout(() => document.getElementById('fpUsername').focus(), 100);
}

function closeForgotModal() {
    document.getElementById('forgotModal').style.display = 'none';
}

function fpShowAlert(msg, type) {
    const el = document.getElementById('fpAlert');
    if (!msg) { el.style.display = 'none'; return; }
    const isError = type === 'error';
    el.style.cssText = `display:flex;align-items:center;gap:8px;padding:11px 14px;border-radius:10px;margin-bottom:18px;font-size:0.84rem;
        background:${isError ? 'rgba(239,68,68,0.12)' : 'rgba(16,185,129,0.12)'};
        border:1px solid ${isError ? 'rgba(239,68,68,0.3)' : 'rgba(16,185,129,0.3)'};
        color:${isError ? '#FCA5A5' : '#6EE7B7'}`;
    el.innerHTML = `<i class="fas ${isError ? 'fa-exclamation-circle' : 'fa-check-circle'}"></i><span>${msg}</span>`;
}

async function verifyIdentity() {
    const username = document.getElementById('fpUsername').value.trim();
    const email    = document.getElementById('fpEmail').value.trim();
    if (!username || !email) { fpShowAlert('Please fill in both fields.', 'error'); return; }

    const btn = document.getElementById('fpVerifyBtn');
    document.getElementById('fpVerifyText').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
    btn.disabled = true;

    try {
        const res    = await fetch('reset-password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ step: 'verify', username, email })
        });
        const result = await res.json();

        if (result.success) {
            fpUserId = result.user_id;
            fpShowAlert('', '');
            document.getElementById('fpStep1').style.display = 'none';
            document.getElementById('fpStep2').style.display = 'block';
            document.getElementById('fpSubtitle').textContent = `Hi ${result.full_name} — set your new password`;
            setTimeout(() => document.getElementById('fpNewPw').focus(), 100);
        } else {
            fpShowAlert(result.error, 'error');
        }
    } catch (e) {
        fpShowAlert('Connection error. Please try again.', 'error');
    }

    document.getElementById('fpVerifyText').innerHTML = '<i class="fas fa-arrow-right"></i> Continue';
    btn.disabled = false;
}

async function resetPassword() {
    const newPw     = document.getElementById('fpNewPw').value;
    const confirmPw = document.getElementById('fpConfirmPw').value;
    if (!newPw || !confirmPw) { fpShowAlert('Please fill in both password fields.', 'error'); return; }
    if (newPw.length < 6)     { fpShowAlert('Password must be at least 6 characters.', 'error'); return; }
    if (newPw !== confirmPw)  { fpShowAlert('Passwords do not match.', 'error'); return; }

    const btn = document.getElementById('fpResetBtn');
    document.getElementById('fpResetText').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting...';
    btn.disabled = true;

    try {
        const res    = await fetch('reset-password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ step: 'reset', user_id: fpUserId, new_password: newPw, confirm_password: confirmPw })
        });
        const result = await res.json();

        if (result.success) {
            fpShowAlert(result.message, 'success');
            document.getElementById('fpStep2').style.display = 'none';
            document.getElementById('fpSubtitle').textContent = 'Password updated successfully!';
            setTimeout(closeForgotModal, 2500);
        } else {
            fpShowAlert(result.error, 'error');
        }
    } catch (e) {
        fpShowAlert('Connection error. Please try again.', 'error');
    }

    document.getElementById('fpResetText').innerHTML = '<i class="fas fa-key"></i> Reset Password';
    btn.disabled = false;
}

function goBackStep1() {
    document.getElementById('fpStep2').style.display = 'none';
    document.getElementById('fpStep1').style.display = 'block';
    document.getElementById('fpSubtitle').textContent = 'Enter your username and registered email';
    fpShowAlert('', '');
}

function toggleFpPw(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    icon.className = isText ? 'fas fa-eye-slash' : 'fas fa-eye';
}

document.getElementById('forgotModal').addEventListener('click', function(e) {
    if (e.target === this) closeForgotModal();
});
</script>
<script src="<?php echo htmlspecialchars($appBasePath); ?>assets/js/pwa.js"></script>
</body>
</html>
