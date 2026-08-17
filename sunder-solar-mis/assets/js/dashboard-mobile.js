/**
 * Dashboard Mobile Configuration
 * Special handling for dashboard on mobile and desktop
 * Prevents AJAX loading for dashboard to maintain full PWA experience
 */

(function initDashboardConfig() {
    const isMobile = () => window.innerWidth <= 768;

    // Override module loader for dashboard
    const originalLoadModule = window.moduleLoader ? window.moduleLoader.loadModule.bind(window.moduleLoader) : null;

    if (originalLoadModule) {
        window.moduleLoader.loadModule = function(moduleName, navElement) {
            // Always use full page load for dashboard
            if (moduleName === 'dashboard') {
                const url = `${window.APP_BASE_PATH}modules/dashboard.php`;
                window.location.href = url;
                return;
            }

            // For other modules on mobile, use AJAX
            if (isMobile()) {
                return originalLoadModule(moduleName, navElement);
            } else {
                // Desktop: use full page load for all modules
                const href = `${window.APP_BASE_PATH}modules/${moduleName}.php`;
                window.location.href = href;
            }
        };

        // Override link click handling
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.removeEventListener('click', () => {}); // Remove old handlers
            link.addEventListener('click', (e) => {
                const href = link.getAttribute('href');
                
                if (href && href.includes('/modules/') && href.endsWith('.php')) {
                    const moduleName = href.match(/\/modules\/([a-z-]+)\.php/i)?.[1];
                    
                    if (moduleName === 'dashboard' || !isMobile()) {
                        // Full page load
                        return; // Allow normal navigation
                    } else {
                        // AJAX load
                        e.preventDefault();
                        window.moduleLoader.loadModule(moduleName, link);
                    }
                }
            });
        });
    }

    // Handle dashboard-specific mobile optimizations
    if (isMobile()) {
        document.addEventListener('moduleLoaded', (e) => {
            if (e.detail.module !== 'dashboard') {
                // Mobile-specific optimizations for non-dashboard modules
                optimizeMobileModule(e.detail.module);
            }
        });
    }

    /**
     * Optimize module content for mobile display
     */
    function optimizeMobileModule(moduleName) {
        // Automatically close modals on mobile after action
        const modals = document.querySelectorAll('.gmModal.active');
        modals.forEach(modal => {
            setTimeout(() => {
                modal.classList.remove('active');
            }, 500);
        });

        // Adjust input sizes for mobile
        const inputs = document.querySelectorAll('input[type="text"], input[type="email"], textarea, select');
        inputs.forEach(input => {
            input.style.fontSize = '16px'; // Prevent zoom on iOS
        });
    }

    // Expose config for debugging
    window.dashboardMobileConfig = {
        isMobile: isMobile(),
        moduleLoaderActive: !!originalLoadModule
    };
})();
