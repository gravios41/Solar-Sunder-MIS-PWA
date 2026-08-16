<?php
// includes/sidebar.php
$currentPage = basename($_SERVER['PHP_SELF']);
$userRole    = $_SESSION['user_role'] ?? 'guest';

function isActive($page) {
    global $currentPage;
    return $currentPage === $page ? 'active' : '';
}
?>

<aside id="sidebar" class="sidebar">
    <!-- Brand -->
    <div class="sidebar-header">
        <a href="<?php echo htmlspecialchars($appBasePath); ?>modules/dashboard.php" class="logo">
            <img src="<?php echo htmlspecialchars($appBasePath); ?>assets/images/logo.jpg"
                 alt="Sunder Solar Energy"
                 style="width:38px;height:38px;object-fit:contain;border-radius:8px;flex-shrink:0;">
            <div class="logo-text">
                <h1>Sunder Solar</h1>
                <p>Management System</p>
            </div>
        </a>
    </div>

    <!-- Toggle button -->
    <button id="sidebarToggle" class="sidebar-toggle" aria-label="Toggle sidebar">
        <i class="fas fa-chevron-left"></i>
    </button>

    <!-- Navigation -->
    <nav class="sidebar-nav">

        <!-- Main -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Main</div>

            <div class="nav-item">
                <a href="<?php echo htmlspecialchars($appBasePath); ?>modules/dashboard.php"
                   class="nav-link <?= isActive('dashboard.php') ?>"
                   data-tooltip="Dashboard">
                    <i class="fas fa-chart-pie nav-icon"></i>
                    <span>Dashboard</span>
                </a>
            </div>
        </div>

        <div class="sidebar-divider"></div>

        <!-- CRM & Sales -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">CRM &amp; Sales</div>

            <?php if ($userRole !== 'employee'): ?>
            <div class="nav-item">
                <a href="<?php echo htmlspecialchars($appBasePath); ?>modules/customers.php"
                   class="nav-link <?= isActive('customers.php') ?>"
                   data-tooltip="Customers">
                    <i class="fas fa-users nav-icon"></i>
                    <span>Customers</span>
                </a>
            </div>
            <?php endif; ?>

            <div class="nav-item">
                <a href="<?php echo htmlspecialchars($appBasePath); ?>modules/quotations.php"
                   class="nav-link <?= isActive('quotations.php') ?>"
                   data-tooltip="Quotations">
                    <i class="fas fa-file-invoice-dollar nav-icon"></i>
                    <span>Quotations</span>
                </a>
            </div>
        </div>

        <div class="sidebar-divider"></div>

        <!-- Operations -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Operations</div>

            <div class="nav-item">
                <a href="<?php echo htmlspecialchars($appBasePath); ?>modules/projects.php"
                   class="nav-link <?= isActive('projects.php') ?>"
                   data-tooltip="Projects">
                    <i class="fas fa-solar-panel nav-icon"></i>
                    <span>Projects</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="<?php echo htmlspecialchars($appBasePath); ?>modules/installations.php"
                   class="nav-link <?= isActive('installations.php') ?>"
                   data-tooltip="Installations">
                    <i class="fas fa-tools nav-icon"></i>
                    <span>Installations</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="<?php echo htmlspecialchars($appBasePath); ?>modules/tasks.php"
                   class="nav-link <?= isActive('tasks.php') ?>"
                   data-tooltip="Tasks">
                    <i class="fas fa-check-square nav-icon"></i>
                    <span>Tasks</span>
                </a>
            </div>

            <?php if ($userRole !== 'employee'): ?>
            <div class="nav-item">
                <a href="<?php echo htmlspecialchars($appBasePath); ?>modules/inventory.php"
                   class="nav-link <?= isActive('inventory.php') ?>"
                   data-tooltip="Inventory">
                    <i class="fas fa-boxes nav-icon"></i>
                    <span>Inventory</span>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <div class="sidebar-divider"></div>

        <!-- Analytics & Settings -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">Analytics</div>

            <div class="nav-item">
                <a href="<?php echo htmlspecialchars($appBasePath); ?>modules/reports.php"
                   class="nav-link <?= isActive('reports.php') ?>"
                   data-tooltip="Reports">
                    <i class="fas fa-chart-bar nav-icon"></i>
                    <span>Reports</span>
                </a>
            </div>
        </div>

        <div class="sidebar-divider"></div>

        <!-- System -->
        <div class="sidebar-section">
            <div class="sidebar-section-title">System</div>

            <div class="nav-item">
                <a href="<?php echo htmlspecialchars($appBasePath); ?>modules/settings.php"
                   class="nav-link <?= isActive('settings.php') ?>"
                   data-tooltip="Settings">
                    <i class="fas fa-cog nav-icon"></i>
                    <span>Settings</span>
                </a>
            </div>

            <?php if (hasPermission('users', 'view')): ?>
            <div class="nav-item">
                <a href="<?php echo htmlspecialchars($appBasePath); ?>modules/user-management.php"
                   class="nav-link <?= isActive('user-management.php') ?>"
                   data-tooltip="User Management">
                    <i class="fas fa-user-shield nav-icon"></i>
                    <span>User Management</span>
                </a>
            </div>
            <?php endif; ?>

            <?php if (in_array($userRole, ['super_admin', 'admin', 'owner'])): ?>
            <div class="nav-item">
                <a href="<?php echo htmlspecialchars($appBasePath); ?>modules/archives.php"
                   class="nav-link <?= isActive('archives.php') ?>"
                   data-tooltip="Archives">
                    <i class="fas fa-archive nav-icon"></i>
                    <span>Archives</span>
                </a>
            </div>
            <?php endif; ?>
        </div>

    </nav>

    <!-- User section -->
    <div class="user-section">
        <div class="user-profile">
            <div class="user-avatar" style="<?php echo !empty($_SESSION['avatar_url']) ? 'padding:0;overflow:hidden;' : ''; ?>">
                <?php if (!empty($_SESSION['avatar_url'])): ?>
                <img src="<?php echo escape($_SESSION['avatar_url']); ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%">
                <?php else: ?>
                <?php echo getUserInitials($_SESSION['full_name'] ?? 'S'); ?>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo escape($_SESSION['full_name'] ?? 'User'); ?></div>
                <div class="user-role"><?php echo ucwords(str_replace('_', ' ', $_SESSION['user_role'] ?? 'Guest')); ?></div>
            </div>
        </div>
        <a href="<?php echo htmlspecialchars($appBasePath); ?>auth/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Sign Out</span>
        </a>
    </div>
</aside>
