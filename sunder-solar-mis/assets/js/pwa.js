(function initPwa() {
    if (!('serviceWorker' in navigator)) {
        console.warn('Service Workers not supported');
        return;
    }

    const scriptUrl = new URL(document.currentScript.src);
    const appBaseUrl = new URL('../../', scriptUrl);
    let deferredPrompt = null;

    function isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    }

    function detectPlatform() {
        const ua = navigator.userAgent || '';
        const isIOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        if (isIOS) return 'ios';
        if (/Android/.test(ua)) return 'android';
        return 'desktop';
    }

    // Accurate, per-platform steps — used only when the browser won't show
    // its own native install prompt (deferredPrompt not available). iOS
    // never fires beforeinstallprompt at all, so it always lands here.
    const INSTRUCTIONS = {
        ios: [
            ['fa-share-square', 'Tap the <strong>Share</strong> icon in Safari’s toolbar.'],
            ['fa-list', 'Scroll down and tap <strong>Add to Home Screen</strong>.'],
            ['fa-check', 'Tap <strong>Add</strong> in the top-right corner.']
        ],
        android: [
            ['fa-ellipsis-vertical', 'Tap the <strong>⋮</strong> menu icon (top-right of Chrome).'],
            ['fa-download', 'Tap <strong>Install app</strong> (or <strong>Add to Home screen</strong>).'],
            ['fa-check', 'Confirm by tapping <strong>Install</strong>.']
        ],
        desktop: [
            ['fa-plus', 'Look for an install icon in the address bar (usually on the right).'],
            ['fa-bars', 'Or open the browser menu and choose <strong>Install Sunder Solar MIS…</strong>'],
            ['fa-check', 'Confirm to add it as an app.']
        ]
    };

    function showInstructionsModal(message, steps) {
        const modal = document.getElementById('gmPwaInstall');
        const stepsEl = document.getElementById('gmPwaInstallSteps');
        if (!modal || !stepsEl) {
            alert(message || 'Please use your browser menu to install the app.');
            return;
        }

        if (message) {
            stepsEl.innerHTML = `<p style="margin:0;color:var(--text-light)">${message}</p>`;
        } else {
            stepsEl.innerHTML = steps.map((step, i) => `
                <div style="display:flex;align-items:flex-start;gap:12px">
                    <div style="flex-shrink:0;width:28px;height:28px;border-radius:50%;background:rgba(249,115,22,.12);color:var(--solar-orange);display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:700">${i + 1}</div>
                    <div style="padding-top:4px;font-size:0.9rem;line-height:1.4"><i class="fas ${step[0]}" style="width:18px;color:var(--solar-orange);margin-right:4px"></i>${step[1]}</div>
                </div>
            `).join('');
        }

        modal.classList.add('active');
    }

    document.addEventListener('DOMContentLoaded', function() {
        const closeBtn = document.getElementById('gmPwaInstallClose');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                document.getElementById('gmPwaInstall')?.classList.remove('active');
            });
        }
        document.getElementById('gmPwaInstall')?.addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('active');
        });
    });

    window.installPWA = async function() {
        if (isStandalone()) {
            showInstructionsModal('Sunder Solar MIS is already installed on this device.');
            return;
        }

        if (deferredPrompt) {
            try {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                console.log('User response to install prompt:', outcome);
            } catch (err) {
                console.error('Error during install prompt:', err);
            } finally {
                deferredPrompt = null;
            }
            return;
        }

        // No native prompt available (always true on iOS; sometimes true
        // on Android/desktop too) — show accurate manual steps instead.
        const platform = detectPlatform();
        showInstructionsModal(null, INSTRUCTIONS[platform] || INSTRUCTIONS.desktop);
    };

    window.addEventListener('beforeinstallprompt', function(event) {
        event.preventDefault();
        deferredPrompt = event;
        console.log('beforeinstallprompt event fired - PWA is installable');
    });

    window.addEventListener('appinstalled', function() {
        console.log('App installed successfully');
        deferredPrompt = null;
    });

    window.addEventListener('load', function() {
        navigator.serviceWorker.register(new URL('sw.js', appBaseUrl), {
            scope: appBaseUrl.pathname
        }).then(function(registration) {
            console.log('Service Worker registered successfully', registration);
        }).catch(function(error) {
            console.warn('PWA Service Worker registration failed:', error);
        });
    });
})();
