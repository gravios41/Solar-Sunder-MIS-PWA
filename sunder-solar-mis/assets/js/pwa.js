(function initPwa() {
    if (!('serviceWorker' in navigator)) return;

    const scriptUrl = new URL(document.currentScript.src);
    const appBaseUrl = new URL('../../', scriptUrl);
    const installBtn = document.getElementById('pwaInstallBtn');
    const installBtnBanner = document.getElementById('pwaInstallBtnBanner');
    const installBanner = document.getElementById('pwaInstallBanner');
    let deferredPrompt = null;

    window.addEventListener('beforeinstallprompt', function(event) {
        event.preventDefault();
        deferredPrompt = event;
        if (installBtn) installBtn.hidden = false;
        if (installBanner) installBanner.style.display = 'flex';
    });

    if (installBtn) {
        installBtn.addEventListener('click', async function() {
            if (!deferredPrompt) return;
            installBtn.hidden = true;
            if (installBanner) installBanner.style.display = 'none';
            deferredPrompt.prompt();
            await deferredPrompt.userChoice;
            deferredPrompt = null;
        });
    }

    if (installBtnBanner) {
        installBtnBanner.addEventListener('click', async function() {
            if (!deferredPrompt) return;
            installBtn.hidden = true;
            if (installBanner) installBanner.style.display = 'none';
            deferredPrompt.prompt();
            await deferredPrompt.userChoice;
            deferredPrompt = null;
        });
    }

    window.addEventListener('appinstalled', function() {
        deferredPrompt = null;
        if (installBtn) installBtn.hidden = true;
        if (installBanner) installBanner.style.display = 'none';
    });

    window.addEventListener('load', function() {
        navigator.serviceWorker.register(new URL('sw.js', appBaseUrl), {
            scope: appBaseUrl.pathname
        }).catch(function(error) {
            console.warn('PWA registration failed:', error);
        });
    });
})();
