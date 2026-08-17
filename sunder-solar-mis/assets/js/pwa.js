(function initPwa() {
    if (!('serviceWorker' in navigator)) {
        console.warn('Service Workers not supported');
        return;
    }

    const scriptUrl = new URL(document.currentScript.src);
    const appBaseUrl = new URL('../../', scriptUrl);
    const installBanner = document.getElementById('pwaInstallBanner');
    let deferredPrompt = null;

    const showInstallBanner = () => {
        if (installBanner) {
            installBanner.style.display = 'flex';
        }
    };

    const hideInstallBanner = () => {
        if (installBanner) {
            installBanner.style.display = 'none';
        }
    };

    console.log('PWA init - Banner found:', !!installBanner);

    window.installPWA = async function() {
        console.log('installPWA() called - deferredPrompt available:', !!deferredPrompt);

        if (!deferredPrompt) {
            console.warn('deferredPrompt not available yet. The browser may suppress the install prompt until the app is installable again.');
            showInstallBanner();
            return;
        }

        try {
            hideInstallBanner();
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            console.log('User response to install prompt:', outcome);

            if (outcome === 'accepted') {
                deferredPrompt = null;
                hideInstallBanner();
                return;
            }

            deferredPrompt = null;
            showInstallBanner();
        } catch (err) {
            console.error('Error during install prompt:', err);
            showInstallBanner();
        }
    };

    window.addEventListener('beforeinstallprompt', function(event) {
        event.preventDefault();
        deferredPrompt = event;
        showInstallBanner();
        console.log('beforeinstallprompt event fired - PWA is installable');
    });

    window.addEventListener('appinstalled', function() {
        console.log('App installed successfully');
        deferredPrompt = null;
        hideInstallBanner();
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
