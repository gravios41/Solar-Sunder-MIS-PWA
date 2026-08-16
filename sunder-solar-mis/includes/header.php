<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$appBasePath = defined('APP_BASE_PATH') ? APP_BASE_PATH : '/';
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
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : ''; ?>Sunder Solar MIS</title>

    <link rel="manifest" href="<?php echo htmlspecialchars($appBasePath); ?>manifest.json">
    <link rel="icon" type="image/jpg" sizes="192x192" href="<?php echo htmlspecialchars($appBasePath); ?>assets/images/logo.jpg">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($appBasePath); ?>assets/images/icons/icon-192.png">

    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars($appBasePath); ?>assets/css/style.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Supabase SDK -->
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2" defer></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>

    <script>
        const USER_ROLE = <?php echo json_encode($_SESSION['user_role'] ?? ''); ?>;
        window.APP_BASE_PATH = <?php echo json_encode($appBasePath); ?>;
    </script>
</head>
<body>

<!-- Mobile overlay -->
<div id="mobileOverlay" class="mobile-overlay"></div>

<!-- Sidebar -->
<?php include_once __DIR__ . '/sidebar.php'; ?>

<!-- Main content -->
<main class="main-content" id="mainContent">

    <!-- Top header bar -->
    <header class="app-header">
        <div class="header-left">
            <!-- Mobile menu button -->
            <button id="mobileMenuBtn" class="mobile-menu-btn" aria-label="Open menu">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Page title -->
            <div class="header-title">
                <h1><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard'; ?></h1>
                <p><?php echo isset($pageSubtitle) ? htmlspecialchars($pageSubtitle) : 'Welcome back, ' . escape($_SESSION['full_name'] ?? 'User'); ?></p>
            </div>
        </div>

        <div class="header-actions">
            <button class="header-icon-btn pwa-install-btn" id="pwaInstallBtn" aria-label="Install app" data-tip="Install app" hidden>
                <i class="fas fa-download"></i>
            </button>

            <!-- Search -->
            <div class="search-box hide-mobile">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="globalSearch" placeholder="Search… (press /)" class="search-input" autocomplete="off">
            </div>

            <!-- Notifications -->
            <div class="notif-wrap hide-mobile">
                <button class="header-icon-btn" id="notifBtn" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    <span class="header-badge" id="notifCount" style="display:none">0</span>
                </button>

                <div id="notifPanel" class="notif-panel">
                    <div class="notif-panel-header">
                        <span class="notif-panel-title">Notifications</span>
                        <span id="notifTotal" class="notif-total-badge" style="display:none">0</span>
                        <button id="markAllRead" onclick="markNotifRead()" style="margin-left:auto;background:none;border:none;color:var(--solar-orange);font-size:0.75rem;font-weight:600;cursor:pointer;padding:0">Mark all read</button>
                    </div>
                    <div id="notifList" class="notif-list">
                        <div style="text-align:center;padding:24px;color:var(--text-muted)"><i class="fas fa-spinner fa-spin"></i></div>
                    </div>
                    <div class="notif-footer">
                        <a href="<?php echo htmlspecialchars($appBasePath); ?>modules/dashboard.php">View all activity on Dashboard &rarr;</a>
                    </div>
                </div>
            </div>

            <!-- User dropdown -->
            <div class="user-dropdown-wrap hide-mobile" id="userProfileDropdown" style="cursor:pointer">
                <div class="header-avatar" id="headerUserInitial" style="<?php echo !empty($_SESSION['avatar_url']) ? 'padding:0;overflow:hidden;' : ''; ?>">
                    <?php if (!empty($_SESSION['avatar_url'])): ?>
                    <img src="<?php echo escape($_SESSION['avatar_url']); ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%">
                    <?php else: ?>
                    <?php echo getUserInitials($_SESSION['full_name'] ?? 'S'); ?>
                    <?php endif; ?>
                </div>
                <div class="header-user-info">
                    <div class="user-name"><?php echo escape($_SESSION['full_name'] ?? 'User'); ?></div>
                    <div class="user-role"><?php echo ucwords(str_replace('_', ' ', $_SESSION['user_role'] ?? 'Guest')); ?></div>
                </div>
                <i class="fas fa-chevron-down" style="font-size:0.7rem;color:var(--text-light)"></i>

                <div class="user-dropdown" id="userDropdownMenu">
                    <div class="dropdown-header">
                        <div class="user-name"><?php echo escape($_SESSION['full_name'] ?? 'User'); ?></div>
                        <div class="user-role"><?php echo escape($_SESSION['username'] ?? ''); ?></div>
                    </div>
                    <a href="<?php echo htmlspecialchars($appBasePath); ?>modules/settings.php" class="dropdown-item">
                        <i class="fas fa-user-circle"></i> Profile &amp; Settings
                    </a>
                    <a href="<?php echo htmlspecialchars($appBasePath); ?>modules/dashboard.php" class="dropdown-item">
                        <i class="fas fa-chart-pie"></i> Dashboard
                    </a>
                    <a href="<?php echo htmlspecialchars($appBasePath); ?>auth/logout.php" class="dropdown-item danger">
                        <i class="fas fa-sign-out-alt"></i> Sign Out
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Page content wrapper -->
    <div class="page-body">
