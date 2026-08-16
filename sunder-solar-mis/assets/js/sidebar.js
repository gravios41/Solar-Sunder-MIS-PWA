// assets/js/sidebar.js — Sunder Solar MIS

document.addEventListener('DOMContentLoaded', function () {
    const sidebar       = document.getElementById('sidebar');
    const toggleBtn     = document.getElementById('sidebarToggle');
    const mainContent   = document.querySelector('.main-content');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileOverlay = document.getElementById('mobileOverlay');

    if (!sidebar) return;

    /* ── Restore collapsed state ── */
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed) {
        sidebar.classList.add('collapsed');
        mainContent?.classList.add('expanded');
        updateToggleIcon(true);
    }

    /* ── Desktop toggle ── */
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            const collapsed = sidebar.classList.toggle('collapsed');
            mainContent?.classList.toggle('expanded', collapsed);
            localStorage.setItem('sidebarCollapsed', collapsed);
            updateToggleIcon(collapsed);
        });
    }

    /* ── Mobile open ── */
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function () {
            openMobileMenu();
        });
    }

    /* ── Mobile overlay close ── */
    if (mobileOverlay) {
        mobileOverlay.addEventListener('click', closeMobileMenu);
    }

    /* ── Close on nav link click (mobile) ── */
    sidebar.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 768) closeMobileMenu();
        });
    });

    /* ── Resize handler ── */
    let resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (window.innerWidth > 768) closeMobileMenu();
        }, 200);
    });

    /* ── Active nav highlight pulse ── */
    const activeLink = sidebar.querySelector('.nav-link.active');
    if (activeLink) {
        const icon = activeLink.querySelector('.nav-icon');
        if (icon) icon.style.animation = 'none'; // ensure clean state
    }

    /* ── Keyboard shortcut: [ to toggle sidebar ── */
    document.addEventListener('keydown', function (e) {
        if (
            e.key === '[' &&
            document.activeElement.tagName !== 'INPUT' &&
            document.activeElement.tagName !== 'TEXTAREA' &&
            window.innerWidth > 768
        ) {
            const collapsed = sidebar.classList.toggle('collapsed');
            mainContent?.classList.toggle('expanded', collapsed);
            localStorage.setItem('sidebarCollapsed', collapsed);
            updateToggleIcon(collapsed);
        }
    });

    /* ── Helpers ── */
    function updateToggleIcon(isCollapsed) {
        if (!toggleBtn) return;
        const icon = toggleBtn.querySelector('i');
        if (!icon) return;
        icon.style.transform = isCollapsed ? 'rotate(180deg)' : '';
    }

    function openMobileMenu() {
        sidebar.classList.add('mobile-open');
        mobileOverlay?.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeMobileMenu() {
        sidebar.classList.remove('mobile-open');
        mobileOverlay?.classList.remove('active');
        document.body.style.overflow = '';
    }
});

/* ── Global helpers (callable from anywhere) ── */
function toggleSidebar() {
    const sidebar     = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    if (!sidebar) return;
    const collapsed = sidebar.classList.toggle('collapsed');
    mainContent?.classList.toggle('expanded', collapsed);
    localStorage.setItem('sidebarCollapsed', collapsed);
    const icon = document.querySelector('#sidebarToggle i');
    if (icon) icon.style.transform = collapsed ? 'rotate(180deg)' : '';
}

function openSidebar() {
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('mobileOverlay');
    sidebar?.classList.add('mobile-open');
    overlay?.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('mobileOverlay');
    sidebar?.classList.remove('mobile-open');
    overlay?.classList.remove('active');
    document.body.style.overflow = '';
}
