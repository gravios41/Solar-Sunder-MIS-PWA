<?php
// modules/dashboard.php
require_once __DIR__ . '/../config/config.php';
requireAuth();

$pageTitle    = 'Dashboard';
$pageSubtitle = 'Overview of your solar business';

try {
    $customers   = $supabase->from('customers')->select('*')->execute() ?? [];
    $projects    = $supabase->from('projects')->select('*')->execute() ?? [];
    $inventory   = $supabase->from('inventory')->select('*')->execute() ?? [];
    $quotations  = $supabase->from('quotations')->select('*')->execute() ?? [];
    $activities  = $supabase->from('activity_logs')
                    ->select('*')
                    ->order('created_at', ['ascending' => false])
                    ->execute() ?? [];
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $customers = $projects = $inventory = $quotations = $activities = [];
}

$totalCustomers    = count($customers);
$activeProjects    = count(array_filter($projects, fn($p) => in_array($p['status'] ?? '', ['in_progress', 'installation'])));
$totalInventory    = array_sum(array_column($inventory, 'quantity'));
$pendingQuotations = count(array_filter($quotations, fn($q) => ($q['status'] ?? '') === 'pending'));
$approvedQuotations= count(array_filter($quotations, fn($q) => ($q['status'] ?? '') === 'approved'));
$totalQuotValue    = array_sum(array_column($quotations, 'total_amount'));
$conversionRate    = count($quotations) > 0 ? round(($approvedQuotations / count($quotations)) * 100) : 0;
$recentProjects    = array_slice($projects, 0, 3);
$lowStockCount     = count(array_filter($inventory, fn($i) => ($i['quantity'] ?? 0) <= ($i['reorder_point'] ?? 5)));

include_once __DIR__ . '/../includes/header.php';
?>

<!-- ── KPI Stats Row ─────────────────────── -->
<div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card orange">
        <div class="stat-header">
            <div class="stat-icon orange"><i class="fas fa-users"></i></div>
            <span class="stat-trend neutral"><i class="fas fa-minus"></i> All time</span>
        </div>
        <div class="stat-value" data-counter="<?php echo $totalCustomers; ?>"><?php echo $totalCustomers; ?></div>
        <div class="stat-label">Total Customers</div>
    </div>

    <div class="stat-card blue">
        <div class="stat-header">
            <div class="stat-icon blue"><i class="fas fa-solar-panel"></i></div>
            <span class="stat-trend up"><i class="fas fa-arrow-up"></i> Active</span>
        </div>
        <div class="stat-value" data-counter="<?php echo $activeProjects; ?>"><?php echo $activeProjects; ?></div>
        <div class="stat-label">Active Projects</div>
    </div>

    <div class="stat-card green">
        <div class="stat-header">
            <div class="stat-icon green"><i class="fas fa-boxes"></i></div>
            <?php if ($lowStockCount > 0): ?>
            <span class="stat-trend down"><i class="fas fa-exclamation-triangle"></i> <?php echo $lowStockCount; ?> low</span>
            <?php else: ?>
            <span class="stat-trend up"><i class="fas fa-check"></i> In stock</span>
            <?php endif; ?>
        </div>
        <div class="stat-value" data-counter="<?php echo $totalInventory; ?>"><?php echo number_format($totalInventory); ?></div>
        <div class="stat-label">Inventory Units</div>
    </div>

    <div class="stat-card purple">
        <div class="stat-header">
            <div class="stat-icon purple"><i class="fas fa-file-invoice-dollar"></i></div>
            <span class="stat-trend <?php echo $pendingQuotations > 0 ? 'down' : 'neutral'; ?>">
                <?php echo $pendingQuotations > 0 ? '<i class="fas fa-clock"></i> Pending' : '<i class="fas fa-check"></i> All clear'; ?>
            </span>
        </div>
        <div class="stat-value" data-counter="<?php echo $pendingQuotations; ?>"><?php echo $pendingQuotations; ?></div>
        <div class="stat-label">Pending Quotations</div>
    </div>
</div>

<!-- ── Financial Overview ──────────────── -->
<div class="stats-grid" style="margin-bottom:20px;grid-template-columns:repeat(3,1fr)">
    <div class="stat-card" style="border-top:3px solid var(--solar-orange)">
        <div class="stat-icon orange" style="margin-bottom:12px"><i class="fas fa-peso-sign"></i></div>
        <div class="stat-value" style="font-size:1.5rem" data-counter="<?php echo $totalQuotValue; ?>" data-prefix="₱">
            <?php echo formatCurrency($totalQuotValue); ?>
        </div>
        <div class="stat-label">Total Quotation Value</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--success)">
        <div class="stat-icon green" style="margin-bottom:12px"><i class="fas fa-check-double"></i></div>
        <div class="stat-value" data-counter="<?php echo $approvedQuotations; ?>"><?php echo $approvedQuotations; ?></div>
        <div class="stat-label">Approved Quotations</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--info)">
        <div class="stat-icon blue" style="margin-bottom:12px"><i class="fas fa-percent"></i></div>
        <div class="stat-value" data-counter="<?php echo $conversionRate; ?>" data-suffix="%"><?php echo $conversionRate; ?>%</div>
        <div class="stat-label">Conversion Rate</div>
    </div>
</div>

<?php if (in_array($_SESSION['user_role'] ?? '', ['super_admin', 'admin', 'owner'])): ?>
<div class="card mb-4" style="background:linear-gradient(135deg,#1F2937,#111827);border:1px solid #374151;margin-bottom:20px">
    <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:40px;height:40px;border-radius:12px;background:rgba(249,115,22,0.15);display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-archive" style="color:var(--solar-orange)"></i>
            </div>
            <div>
                <div style="font-weight:700;color:#fff;font-size:1.1rem" id="archiveTotalCount">—</div>
                <div style="font-size:0.75rem;color:#9CA3AF">Total Archived Records</div>
            </div>
        </div>
        <div id="archiveBreakdown" style="display:flex;gap:16px;flex-wrap:wrap;font-size:0.8rem;color:#9CA3AF"></div>
        <a href="<?php echo htmlspecialchars($appBasePath); ?>modules/archives.php" class="btn btn-primary btn-sm">
            <i class="fas fa-external-link-alt"></i> View Archives
        </a>
    </div>
</div>
<script>
(async function loadArchiveSummary() {
    try {
        const res = await fetch('../api/archives-api.php');
        const data = await res.json();
        if (!data.success) return;
        const archives = data.data || [];
        document.getElementById('archiveTotalCount').textContent = archives.length;
        const counts = {};
        archives.forEach(a => { counts[a.entity_type] = (counts[a.entity_type] || 0) + 1; });
        const labels = { tasks:'Tasks', projects:'Projects', customers:'Customers', quotations:'Quotations', installations:'Installations', inventory:'Inventory', reports:'Reports', users:'Users' };
        document.getElementById('archiveBreakdown').innerHTML = Object.entries(counts)
            .map(([type, n]) => `<span><span style="color:#F97316;font-weight:600">${n}</span> ${labels[type]||type}</span>`).join('');
    } catch(e) {}
})();
</script>
<?php endif; ?>

<!-- ── Content Grid ────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

    <!-- Recent Projects -->
    <div class="card" style="margin-bottom:0">
        <div class="card-header">
            <div>
                <h3 class="card-title">Recent Projects</h3>
                <p class="card-subtitle">Latest solar installations in progress</p>
            </div>
            <a href="projects.php" class="btn btn-primary btn-sm">
                <i class="fas fa-arrow-right"></i> View All
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($recentProjects)): ?>
            <div class="empty-state" style="padding:30px 20px">
                <div class="empty-icon"><i class="fas fa-solar-panel"></i></div>
                <div class="empty-title">No projects yet</div>
                <div class="empty-desc">Create your first solar project to get started.</div>
                <a href="projects.php" class="btn btn-primary btn-sm">Add Project</a>
            </div>
            <?php else: ?>
            <div class="projects-grid" style="grid-template-columns:1fr">
                <?php foreach ($recentProjects as $i => $project): ?>
                <div class="project-card" style="animation-delay:<?php echo $i * 0.08; ?>s">
                    <div class="project-card-header">
                        <div style="min-width:0">
                            <div class="project-name truncate"><?php echo escape($project['project_name'] ?? 'Untitled'); ?></div>
                            <div class="project-meta">
                                <i class="fas fa-calendar-alt"></i>
                                Due <?php echo formatDate($project['expected_end_date'] ?? null); ?>
                            </div>
                        </div>
                        <?php echo getStatusBadge($project['status'] ?? 'planning'); ?>
                    </div>

                    <?php $progress = (int)($project['progress'] ?? 0); ?>
                    <div>
                        <div class="flex-between text-sm mb-2">
                            <span style="color:var(--text-light);font-size:0.78rem">Progress</span>
                            <span style="font-weight:700;color:var(--solar-orange)"><?php echo $progress; ?>%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" style="width:<?php echo $progress; ?>%"></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="card" style="margin-bottom:0">
        <div class="card-header">
            <div>
                <h3 class="card-title">
                    Recent Activity
                    <?php if (!empty($activities)): ?>
                    <span style="font-size:0.75rem;font-weight:600;background:var(--solar-orange);color:white;padding:2px 8px;border-radius:999px;margin-left:6px;vertical-align:middle"><?php echo count($activities); ?></span>
                    <?php endif; ?>
                </h3>
                <p class="card-subtitle">All system events</p>
            </div>
        </div>
        <div class="card-body" style="padding:0">
            <?php if (empty($activities)): ?>
            <div class="empty-state" style="padding:30px 16px">
                <div class="empty-icon" style="width:52px;height:52px;font-size:1.4rem"><i class="fas fa-history"></i></div>
                <div class="empty-title">No activity yet</div>
                <div class="empty-desc">Activity will appear here as the system is used.</div>
            </div>
            <?php else: ?>
            <div class="activity-feed" style="padding:4px 20px;max-height:480px;overflow-y:auto">
                <?php foreach ($activities as $i => $activity): ?>
                <div class="activity-item" style="animation-delay:<?php echo min($i * 0.06, 0.6); ?>s">
                    <div class="activity-dot-wrap">
                        <div class="activity-dot <?php echo $i === 0 ? '' : 'success'; ?>"></div>
                        <?php if ($i < count($activities) - 1): ?>
                        <div class="activity-line"></div>
                        <?php endif; ?>
                    </div>
                    <div class="activity-content">
                        <div class="activity-action"><?php echo escape($activity['action'] ?? ''); ?></div>
                        <div class="activity-desc"><?php echo escape($activity['description'] ?? ''); ?></div>
                        <div class="activity-time">
                            <i class="fas fa-clock"></i>
                            <?php echo formatDateTime($activity['created_at'] ?? null); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ── Quick Actions ───────────────────── -->
<div class="card" style="margin-top:20px">
    <div class="card-header">
        <h3 class="card-title">Quick Actions</h3>
    </div>
    <div class="card-body">
        <div style="display:flex;gap:12px;flex-wrap:wrap">
            <a href="customers.php" class="btn btn-secondary">
                <i class="fas fa-user-plus"></i> Add Customer
            </a>
            <a href="projects.php" class="btn btn-secondary">
                <i class="fas fa-solar-panel"></i> New Project
            </a>
            <a href="quotations.php" class="btn btn-secondary">
                <i class="fas fa-file-invoice"></i> Create Quote
            </a>
            <a href="installations.php" class="btn btn-secondary">
                <i class="fas fa-calendar-plus"></i> Schedule Installation
            </a>
            <a href="inventory.php" class="btn btn-secondary">
                <i class="fas fa-plus-square"></i> Add Inventory
            </a>
            <a href="reports.php" class="btn btn-primary">
                <i class="fas fa-chart-bar"></i> View Reports
            </a>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
