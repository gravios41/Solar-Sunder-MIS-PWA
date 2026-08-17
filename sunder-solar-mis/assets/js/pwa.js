(function initPwa() {
    if (!('serviceWorker' in navigator)) {
        console.warn('Service Workers not supported');
        return;
    }

    const scriptUrl = new URL(document.currentScript.src);
    const appBaseUrl = new URL('../../', scriptUrl);
    let deferredPrompt = null;

    window.installPWA = async function() {
        console.log('installPWA() called - deferredPrompt available:', !!deferredPrompt);

        if (!deferredPrompt) {
            console.warn('deferredPrompt not available. The browser will show the install prompt automatically.');
            alert('Please use your browser menu to install the app.');
            return;
        }

        try {
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            console.log('User response to install prompt:', outcome);

            if (outcome === 'accepted') {
                console.log('PWA install accepted');
                deferredPrompt = null;
                return;
            }

            console.log('PWA install dismissed');
            deferredPrompt = null;
        } catch (err) {
            console.error('Error during install prompt:', err);
        }
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
