// assets/js/main.js — Sunder Solar MIS

/* ── Toast Notification System ─────────────── */
(function initToastContainer() {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createContainer);
    } else {
        createContainer();
    }
    function createContainer() {
        if (document.getElementById('toast-container')) return;
        const c = document.createElement('div');
        c.id = 'toast-container';
        document.body.appendChild(c);
    }
})();

const TOAST_ICONS = {
    success: 'fa-check-circle',
    error:   'fa-exclamation-circle',
    warning: 'fa-exclamation-triangle',
    info:    'fa-info-circle',
};
const TOAST_TITLES = {
    success: 'Success',
    error:   'Error',
    warning: 'Warning',
    info:    'Info',
};

function showToast(message, type = 'success', duration = 3500) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <i class="fas ${TOAST_ICONS[type] || 'fa-info-circle'} toast-icon"></i>
        <div class="toast-content">
            <div class="toast-title">${TOAST_TITLES[type] || 'Notice'}</div>
            <div class="toast-msg">${message}</div>
        </div>
        <button class="toast-close" onclick="dismissToast(this.parentElement)">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(toast);

    const timer = setTimeout(() => dismissToast(toast), duration);
    toast._timer = timer;
}

function dismissToast(toast) {
    if (!toast || !toast.parentElement) return;
    clearTimeout(toast._timer);
    toast.style.animation = 'slideOutRight 0.35s ease forwards';
    setTimeout(() => toast.remove(), 340);
}

/* ── Animated Number Counter ────────────────── */
function animateCounter(el, target, duration = 1200, prefix = '', suffix = '') {
    const start     = 0;
    const startTime = performance.now();
    const isFloat   = String(target).includes('.');
    const decimals  = isFloat ? String(target).split('.')[1].length : 0;

    function ease(t) { return t < 0.5 ? 2*t*t : -1+(4-2*t)*t; }

    function tick(now) {
        const elapsed  = now - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const value    = start + (target - start) * ease(progress);
        el.textContent = prefix + value.toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + suffix;
        if (progress < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
}

function initCounters() {
    const els = document.querySelectorAll('[data-counter]');
    if (!els.length) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !entry.target._counted) {
                entry.target._counted = true;
                const raw     = entry.target.dataset.counter;
                const prefix  = entry.target.dataset.prefix  || '';
                const suffix  = entry.target.dataset.suffix  || '';
                const target  = parseFloat(raw.replace(/[^0-9.]/g, '')) || 0;
                animateCounter(entry.target, target, 1200, prefix, suffix);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.2 });

    els.forEach(el => observer.observe(el));
}

/* ── Staggered Card Entrance ────────────────── */
function initStaggerAnimation() {
    const cards = document.querySelectorAll('.stat-card, .project-card, .task-card');
    cards.forEach((card, i) => {
        card.style.animationDelay = `${i * 0.06}s`;
    });
}

/* ── Progress Bars Animate on Scroll ────────── */
function initProgressBars() {
    const bars = document.querySelectorAll('.progress-bar');
    if (!bars.length) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const bar = entry.target;
                const width = bar.style.width || bar.getAttribute('data-width') || '0%';
                bar.style.width = '0';
                bar.getBoundingClientRect(); // force reflow
                bar.style.transition = 'width 1.1s cubic-bezier(0.16,1,0.3,1)';
                bar.style.width = width;
                observer.unobserve(bar);
            }
        });
    }, { threshold: 0.3 });

    bars.forEach(bar => {
        if (bar.style.width) bar.setAttribute('data-width', bar.style.width);
        observer.observe(bar);
    });
}

/* ── Ripple Effect on Buttons ───────────────── */
function initRipple() {
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn');
        if (!btn || btn.disabled) return;

        const circle = document.createElement('span');
        const diameter = Math.max(btn.clientWidth, btn.clientHeight);
        const rect = btn.getBoundingClientRect();

        circle.style.cssText = `
            width: ${diameter}px;
            height: ${diameter}px;
            left: ${e.clientX - rect.left - diameter/2}px;
            top: ${e.clientY - rect.top - diameter/2}px;
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.25);
            transform: scale(0);
            animation: ripple 0.55s ease;
            pointer-events: none;
        `;

        btn.style.position = 'relative';
        btn.style.overflow = 'hidden';
        btn.appendChild(circle);
        setTimeout(() => circle.remove(), 600);
    });

    // Add keyframe if not present
    if (!document.getElementById('ripple-style')) {
        const style = document.createElement('style');
        style.id = 'ripple-style';
        style.textContent = '@keyframes ripple { to { transform: scale(3); opacity: 0; } }';
        document.head.appendChild(style);
    }
}

/* ── Custom Modal System ────────────────────── */

/**
 * Styled confirmation dialog — replaces native confirm()
 * options: { confirmText, title, danger }
 */
function showConfirmModal(message, onConfirm, options = {}) {
    const { confirmText = 'Delete', title = 'Are you sure?', danger = true } = options;
    const modal     = document.getElementById('gmConfirm');
    const titleEl   = document.getElementById('gmConfirmTitle');
    const msgEl     = document.getElementById('gmConfirmMsg');
    const okBtn     = document.getElementById('gmConfirmOk');
    const cancelBtn = document.getElementById('gmConfirmCancel');

    if (!modal) { if (confirm(message)) onConfirm(); return; }

    if (window.innerWidth <= 768) {
        modal.classList.add('phone-modal');
    } else {
        modal.classList.remove('phone-modal');
    }

    titleEl.textContent = title;
    msgEl.textContent   = message;
    okBtn.textContent   = confirmText;
    okBtn.className     = `btn ${danger ? 'btn-danger' : 'btn-primary'}`;

    modal.classList.add('active');

    const cleanup = () => modal.classList.remove('active');

    // Clone buttons to clear previous listeners
    const newOk     = okBtn.cloneNode(true);
    const newCancel = cancelBtn.cloneNode(true);
    okBtn.replaceWith(newOk);
    cancelBtn.replaceWith(newCancel);

    newOk.textContent   = confirmText;
    newOk.className     = `btn ${danger ? 'btn-danger' : 'btn-primary'}`;
    newOk.onclick       = () => { cleanup(); onConfirm(); };
    newCancel.onclick   = cleanup;
    modal.onclick       = (e) => { if (e.target === modal) cleanup(); };

    document.addEventListener('keydown', function escKey(e) {
        if (e.key === 'Escape') { cleanup(); document.removeEventListener('keydown', escKey); }
    }, { once: true });
}

/**
 * Styled detail/view popup — replaces native alert() for record details
 * rows: array of { label, value } objects
 */
function showDetailModal(title, rows) {
    const modal    = document.getElementById('gmDetail');
    const titleEl  = document.getElementById('gmDetailTitle');
    const rowsEl   = document.getElementById('gmDetailRows');
    const closeBtn = document.getElementById('gmDetailClose');
    const closeBtn2= document.getElementById('gmDetailCloseBtn');

    if (!modal) return;

    if (window.innerWidth <= 768) {
        modal.classList.add('phone-modal');
    } else {
        modal.classList.remove('phone-modal');
    }

    titleEl.textContent = title;
    rowsEl.innerHTML = rows.map(r => {
        if (r.section) {
            return `<div class="gmDetail-section">${escapeHtml(r.section)}</div>`;
        }
        const val = (r.value !== null && r.value !== undefined && r.value !== '') ? String(r.value) : '—';
        return `
        <div class="gmDetail-row">
            <span class="gmDetail-label">${escapeHtml(r.label)}</span>
            <span class="gmDetail-value">${escapeHtml(val)}</span>
        </div>`;
    }).join('');

    const cleanup = () => {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    };

    modal.classList.add('active');
    if (window.innerWidth <= 768) {
        document.body.style.overflow = 'hidden';
    }

    const newClose  = closeBtn.cloneNode(true);
    const newClose2 = closeBtn2.cloneNode(true);
    closeBtn.replaceWith(newClose);
    closeBtn2.replaceWith(newClose2);

    newClose.innerHTML  = '&times;';
    newClose.onclick    = cleanup;
    newClose2.textContent = 'Close';
    newClose2.onclick   = cleanup;
    modal.onclick       = (e) => { if (e.target === modal) cleanup(); };

    document.addEventListener('keydown', function escKey(e) {
        if (e.key === 'Escape') { cleanup(); document.removeEventListener('keydown', escKey); }
    }, { once: true });
}

/**
 * Styled prompt — replaces native prompt() for numeric/text input
 * options: { title, okText, min, max, step }
 */
function showPromptModal(message, defaultValue, onConfirm, options = {}) {
    const { title = 'Update Value', okText = 'Update', min = 0, max = 100, step = 1 } = options;
    const modal     = document.getElementById('gmPrompt');
    const titleEl   = document.getElementById('gmPromptTitle');
    const msgEl     = document.getElementById('gmPromptMsg');
    const input     = document.getElementById('gmPromptInput');
    const okBtn     = document.getElementById('gmPromptOk');
    const cancelBtn = document.getElementById('gmPromptCancel');

    if (!modal) {
        const val = prompt(message, defaultValue);
        if (val !== null) onConfirm(val);
        return;
    }

    if (window.innerWidth <= 768) {
        modal.classList.add('phone-modal');
    } else {
        modal.classList.remove('phone-modal');
    }

    titleEl.textContent = title;
    msgEl.textContent   = message;
    input.value         = defaultValue;
    input.min           = min;
    input.max           = max;
    input.step          = step;

    modal.classList.add('active');
    setTimeout(() => { input.focus(); input.select(); }, 80);

    const cleanup = () => modal.classList.remove('active');

    const newOk     = okBtn.cloneNode(true);
    const newCancel = cancelBtn.cloneNode(true);
    okBtn.replaceWith(newOk);
    cancelBtn.replaceWith(newCancel);

    newOk.textContent   = okText;
    newOk.onclick       = () => { cleanup(); onConfirm(input.value); };
    newCancel.onclick   = cleanup;
    modal.onclick       = (e) => { if (e.target === modal) cleanup(); };

    input.onkeydown = (e) => {
        if (e.key === 'Enter') { cleanup(); onConfirm(input.value); }
        if (e.key === 'Escape') cleanup();
    };
}

/** Backward-compat wrapper */
function confirmAction(message, callback) {
    showConfirmModal(message, callback, { confirmText: 'Confirm', danger: false });
}

/* ── Global Loading States ──────────────────── */
function showLoading(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return;
    el.dataset.originalContent = el.innerHTML;
    el.innerHTML = `
        <div style="display:flex;align-items:center;justify-content:center;padding:40px;">
            <div class="spinner"></div>
        </div>`;
}

function hideLoading(elementId, content) {
    const el = document.getElementById(elementId);
    if (!el) return;
    el.innerHTML = content !== undefined ? content : (el.dataset.originalContent || '');
}

/* ── Currency & Date Formatters ─────────────── */
function formatCurrency(amount, compact = false) {
    const num = Number(amount) || 0;
    if (compact && num >= 1_000_000) return '₱' + (num / 1_000_000).toFixed(1) + 'M';
    if (compact && num >= 1_000)    return '₱' + (num / 1_000).toFixed(1) + 'K';
    return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(dateString, format = 'short') {
    if (!dateString) return '—';
    const date = new Date(dateString);
    if (isNaN(date)) return '—';
    const opts = {
        short:    { year: 'numeric', month: 'short', day: 'numeric' },
        long:     { year: 'numeric', month: 'long',  day: 'numeric' },
        datetime: { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' },
        time:     { hour: '2-digit', minute: '2-digit' },
    };
    return date.toLocaleDateString('en-US', opts[format] || opts.short);
}

function escapeHtml(text) {
    if (!text) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}

/* ── Debounce ───────────────────────────────── */
function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

/* ── Throttle ───────────────────────────────── */
function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

/* ── Global Search Dispatcher ───────────────── */
function initGlobalSearch() {
    const input = document.getElementById('globalSearch');
    if (!input) return;
    input.addEventListener('input', debounce(function(e) {
        window.dispatchEvent(new CustomEvent('globalSearch', {
            detail: { search: e.target.value.toLowerCase(), page: location.pathname.split('/').pop() }
        }));
    }, 300));

    // Keyboard shortcut: / to focus search
    document.addEventListener('keydown', function(e) {
        if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
            e.preventDefault();
            input.focus();
        }
    });
}

/* ── User Dropdown ──────────────────────────── */
function initUserDropdown() {
    const wrap = document.getElementById('userProfileDropdown');
    if (!wrap) return;

    wrap.addEventListener('click', function(e) {
        e.stopPropagation();
        const d = wrap.querySelector('.user-dropdown');
        if (d) d.classList.toggle('show');
    });

    document.addEventListener('click', function() {
        document.querySelectorAll('.user-dropdown.show').forEach(d => d.classList.remove('show'));
    });
}

/* ── Notification Bell ──────────────────────── */
const NOTIF_LS_KEY = 'sunder_notif_last_ts';

async function initNotifications() {
    const btn   = document.getElementById('notifBtn');
    const panel = document.getElementById('notifPanel');
    const badge = document.getElementById('notifCount');
    if (!btn || !panel) return;

    let activities = [];

    async function fetchActivities() {
        try {
            const appBasePath = window.APP_BASE_PATH || '/';
            const res  = await fetch(`${appBasePath}api/dashboard-api.php?action=recent-activities`);
            const data = await res.json();
            if (data.success) activities = data.data || [];
        } catch(e) {}
        renderBadge();
        renderList();
    }

    function renderBadge() {
        const lastTs  = localStorage.getItem(NOTIF_LS_KEY) || '0';
        const newOnes = activities.filter(a => (a.created_at || '') > lastTs).length;
        const tot     = document.getElementById('notifTotal');
        if (tot) { tot.textContent = activities.length; tot.style.display = activities.length ? 'inline-flex' : 'none'; }
        if (newOnes > 0) {
            badge.textContent = newOnes > 99 ? '99+' : newOnes;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }

    function renderList() {
        const list = document.getElementById('notifList');
        if (!list) return;
        if (!activities.length) {
            list.innerHTML = '<div style="text-align:center;padding:32px 16px;color:var(--text-muted)"><i class="fas fa-bell-slash" style="font-size:2rem;opacity:.3;display:block;margin-bottom:10px"></i>No activity yet</div>';
            return;
        }
        const icons  = { create:'fa-plus-circle', update:'fa-edit', archive:'fa-archive', restore:'fa-undo', delete:'fa-trash', login:'fa-sign-in-alt', logout:'fa-sign-out-alt' };
        const colors = { create:'#10B981',        update:'#3B82F6', archive:'#6B7280',    restore:'#10B981', delete:'#EF4444',  login:'#F97316',         logout:'#9CA3AF' };
        const lastTs = localStorage.getItem(NOTIF_LS_KEY) || '0';
        list.innerHTML = activities.map(a => {
            const icon    = icons[a.action]  || 'fa-info-circle';
            const color   = colors[a.action] || '#6B7280';
            const isNew   = (a.created_at || '') > lastTs;
            const timeStr = formatDate(a.created_at, 'datetime');
            return `<div class="notif-item${isNew ? ' notif-item-new' : ''}">
                <div class="notif-icon" style="background:${color};color:#fff">
                    <i class="fas ${icon}"></i>
                </div>
                <div class="notif-body">
                    <div class="notif-desc">${escapeHtml(a.description || a.action || '')}</div>
                    <div class="notif-meta">
                        <span class="notif-action-badge" style="background:${color};color:#fff">${escapeHtml(a.action||'')}</span>
                        <span class="notif-time"><i class="fas fa-clock"></i> ${timeStr}</span>
                    </div>
                </div>
                ${isNew ? '<div class="notif-dot"></div>' : ''}
            </div>`;
        }).join('');
    }

    // Toggle panel
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const isOpen = panel.classList.toggle('show');
        if (isOpen) {
            const latest = activities[0]?.created_at || new Date().toISOString();
            localStorage.setItem(NOTIF_LS_KEY, latest);
            badge.style.display = 'none';
            renderList();
        }
    });

    // Close on outside click
    document.addEventListener('click', function(e) {
        if (!panel.contains(e.target) && e.target !== btn) {
            panel.classList.remove('show');
        }
    });

    // Expose mark-all-read so the inline onclick can reach the inner render closures
    window._notifMarkAllRead = function() {
        localStorage.setItem(NOTIF_LS_KEY, new Date().toISOString());
        renderBadge();
        renderList();
    };

    await fetchActivities();
}

function markNotifRead() {
    if (typeof window._notifMarkAllRead === 'function') {
        window._notifMarkAllRead();
    } else {
        // Fallback if initNotifications hasn't run yet
        localStorage.setItem(NOTIF_LS_KEY, new Date().toISOString());
        const badge = document.getElementById('notifCount');
        if (badge) badge.style.display = 'none';
        document.querySelectorAll('.notif-item-new').forEach(el => {
            el.classList.remove('notif-item-new');
            const dot = el.querySelector('.notif-dot');
            if (dot) dot.remove();
        });
    }
}

/* ── DOM Ready Init ─────────────────────────── */
document.addEventListener('DOMContentLoaded', function() {
    initGlobalSearch();
    initUserDropdown();
    initStaggerAnimation();
    initProgressBars();
    initRipple();
    initNotifications();

    // Small delay so numbers render first, then animate
    setTimeout(initCounters, 200);
});

/* ── Session timeout warning ────────────────── */
(function() {
    const WARN_AT = 25 * 60 * 1000; // 25 minutes
    let warned = false;
    setTimeout(function() {
        if (!warned) {
            warned = true;
            showToast('Your session will expire in 5 minutes. Please save your work.', 'warning', 8000);
        }
    }, WARN_AT);
})();

/* ── Exports ────────────────────────────────── */
window.showToast        = showToast;
window.markNotifRead    = markNotifRead;
window.dismissToast     = dismissToast;
window.confirmAction    = confirmAction;
window.showConfirmModal = showConfirmModal;
window.showDetailModal  = showDetailModal;
window.showPromptModal  = showPromptModal;
window.showLoading      = showLoading;
window.hideLoading      = hideLoading;
window.formatCurrency   = formatCurrency;
window.formatDate       = formatDate;
window.escapeHtml       = escapeHtml;
window.debounce         = debounce;
window.throttle         = throttle;
window.animateCounter   = animateCounter;
