/**
 * AJAX Module Loader
 * Dynamically loads modules for better mobile experience
 */

class ModuleLoader {
    constructor() {
        this.currentModule = null;
        this.isLoading = false;
        this.isMobile = () => window.innerWidth <= 768;
        this.moduleCache = {};
        this.init();
    }

    init() {
        this.detectCurrentModule();
        this.attachNavigationHandlers();
        this.handleBackButton();
    }

    /**
     * Detect current module from page
     */
    detectCurrentModule() {
        const path = window.location.pathname;
        const matches = path.match(/\/modules\/([a-z-]+)\.php/i);
        if (matches) {
            this.currentModule = matches[1];
        }
    }

    /**
     * Attach AJAX handlers to sidebar navigation
     */
    attachNavigationHandlers() {
        const navLinks = document.querySelectorAll('.nav-link');
        
        navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                const href = link.getAttribute('href');
                
                // Check if it's a module link
                if (href && href.includes('/modules/') && href.endsWith('.php')) {
                    // For mobile, use AJAX
                    if (this.isMobile()) {
                        e.preventDefault();
                        const moduleName = this.extractModuleName(href);
                        this.loadModule(moduleName, link);
                    }
                    // For desktop, allow normal navigation
                }
            });
        });
    }

    /**
     * Extract module name from URL
     */
    extractModuleName(href) {
        const matches = href.match(/\/modules\/([a-z-]+)\.php/i);
        return matches ? matches[1] : null;
    }

    /**
     * Load module via AJAX
     */
    async loadModule(moduleName, navElement = null) {
        if (this.isLoading) return;
        if (this.currentModule === moduleName && this.moduleCache[moduleName]) {
            this.displayCachedModule(moduleName);
            return;
        }

        this.isLoading = true;
        
        try {
            // Show loading indicator
            this.showLoadingIndicator();

            // Check cache first
            if (this.moduleCache[moduleName]) {
                this.displayModule(moduleName, this.moduleCache[moduleName], navElement);
                this.isLoading = false;
                return;
            }

            // Fetch module content
            const response = await fetch(`${window.APP_BASE_PATH}api/ajax-content.php?module=${moduleName}&action=view`);
            
            if (!response.ok) {
                throw new Error(`Failed to load module: ${response.status}`);
            }

            const content = await response.text();

            // Cache the content
            this.moduleCache[moduleName] = content;

            // Display the module
            this.displayModule(moduleName, content, navElement);

            // Update browser history
            const url = `${window.APP_BASE_PATH}modules/${moduleName}.php`;
            window.history.pushState({ module: moduleName }, moduleName, url);

        } catch (error) {
            console.error('Error loading module:', error);
            showToast('Failed to load module. Please try again.', 'error');
        } finally {
            this.isLoading = false;
            this.hideLoadingIndicator();
        }
    }

    /**
     * Display module content in main area
     */
    displayModule(moduleName, content, navElement = null) {
        const mainContent = document.querySelector('.main-content');
        if (!mainContent) return;

        // Get the page-body or create one
        let pageBody = mainContent.querySelector('.page-body');
        if (!pageBody) {
            pageBody = document.createElement('div');
            pageBody.className = 'page-body';
            mainContent.appendChild(pageBody);
        }

        // Replace content
        pageBody.innerHTML = content;

        // Update current module
        this.currentModule = moduleName;

        // Update active navigation
        this.updateActiveNavigation(navElement, moduleName);

        // Scroll to top on mobile
        if (this.isMobile()) {
            setTimeout(() => {
                mainContent.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }

        // Trigger any necessary initialization scripts
        this.initializeModuleContent(moduleName);

        // Close mobile menu if open
        this.closeMobileMenu();
    }

    /**
     * Display cached module
     */
    displayCachedModule(moduleName) {
        const content = this.moduleCache[moduleName];
        if (content) {
            this.displayModule(moduleName, content);
        }
    }

    /**
     * Update active navigation state
     */
    updateActiveNavigation(navElement, moduleName) {
        // Remove active class from all nav links
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('active');
        });

        // Add active class to current link
        if (navElement) {
            navElement.classList.add('active');
        } else {
            // Find and activate the link for this module
            const moduleLink = document.querySelector(`a[href*="/${moduleName}.php"]`);
            if (moduleLink) {
                moduleLink.classList.add('active');
            }
        }
    }

    /**
     * Initialize module-specific scripts
     */
    initializeModuleContent(moduleName) {
        // Run counters animation if present
        if (typeof initCounters === 'function') {
            initCounters();
        }

        // Initialize cards animation if present
        if (typeof initCardEntrance === 'function') {
            initCardEntrance();
        }

        // Re-attach any event handlers needed for the module
        this.reAttachModuleHandlers(moduleName);

        // Ensure all modal data is visible on mobile
        if (this.isMobile()) {
            setTimeout(() => {
                this.optimizeModalsForMobile();
                this.ensureDataVisibility();
            }, 100);
        }

        // Trigger any module-specific initialization
        const event = new CustomEvent('moduleLoaded', { detail: { module: moduleName } });
        document.dispatchEvent(event);
    }

    /**
     * Ensure all data elements are visible on mobile
     */
    ensureDataVisibility() {
        const mainContent = document.querySelector('.main-content, .page-body, [data-content]');
        if (!mainContent) return;

        // Check all hidden elements
        mainContent.querySelectorAll('*').forEach(el => {
            const style = window.getComputedStyle(el);
            
            // Fix display: none elements that should be visible
            if (style.display === 'none' && !el.classList.contains('modal')) {
                if (el.textContent && el.textContent.trim() && el.offsetParent === null) {
                    el.style.display = 'block';
                }
            }

            // Fix overflow issues
            if (style.overflow === 'hidden' && el.scrollHeight > el.offsetHeight) {
                el.style.overflow = 'auto';
                el.style.overflowX = 'hidden';
            }

            // Fix text truncation issues
            if (style.whiteSpace === 'nowrap' && el.textContent && el.textContent.trim()) {
                el.style.whiteSpace = 'normal';
                el.style.wordBreak = 'break-word';
            }
        });

        // Specifically check table rows for visibility
        mainContent.querySelectorAll('table tbody tr').forEach(row => {
            row.style.display = 'table-row';
            row.querySelectorAll('td').forEach(cell => {
                cell.style.display = 'table-cell';
                cell.style.visibility = 'visible';
                cell.style.opacity = '1';
            });
        });
    }

    /**
     * Re-attach event handlers for dynamically loaded content
     */
    reAttachModuleHandlers(moduleName) {
        // Example: Re-attach form submission handlers
        const forms = document.querySelectorAll('form[data-ajax]');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => this.handleFormSubmit(e, moduleName));
        });

        // Re-attach delete/action buttons
        const actionButtons = document.querySelectorAll('[data-action]');
        actionButtons.forEach(button => {
            button.addEventListener('click', (e) => this.handleActionButton(e, moduleName));
        });

        // Re-attach modal handlers
        this.attachModalHandlers();

        // Ensure modals are visible on mobile
        this.optimizeModalsForMobile();
    }

    /**
     * Attach modal close and open handlers
     */
    attachModalHandlers() {
        // Find all modal close buttons
        const closeButtons = document.querySelectorAll('[class*="modal-close"], [onclick*="closeModal"], [onclick*="closeDetail"]');
        closeButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const modal = btn.closest('.modal') || btn.closest('.gmModal');
                if (modal) {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // Attach modal backdrop click handlers
        const modals = document.querySelectorAll('.modal, .gmModal');
        modals.forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });
    }

    /**
     * Optimize modals for mobile visibility
     */
    optimizeModalsForMobile() {
        if (!this.isMobile()) return;

        const modals = document.querySelectorAll('.modal, .gmModal');
        modals.forEach(modal => {
            // Ensure modal is properly displayed when active
            if (modal.classList.contains('active')) {
                modal.style.display = 'flex';
                modal.style.zIndex = '9999';
                document.body.style.overflow = 'hidden';
            }

            // Fix modal content visibility
            const content = modal.querySelector('.modal-content, .gmModal-box');
            if (content) {
                content.style.display = 'block';
                content.style.visibility = 'visible';
                content.style.opacity = '1';
            }

            // Ensure all form fields are visible
            const formFields = modal.querySelectorAll('input, textarea, select, [contenteditable]');
            formFields.forEach(field => {
                field.style.visibility = 'visible';
                field.style.display = 'block';
                field.style.opacity = '1';
                if (field.offsetHeight === 0) {
                    console.warn('Form field has zero height:', field);
                }
            });

            // Ensure all text content is visible
            const textElements = modal.querySelectorAll('p, span, div, label, td');
            textElements.forEach(el => {
                if (el.textContent && el.textContent.trim()) {
                    el.style.visibility = 'visible';
                    el.style.display = 'block';
                    el.style.opacity = '1';
                }
            });
        });
    }

    /**
     * Handle form submission via AJAX
     */
    async handleFormSubmit(e, moduleName) {
        const form = e.target;
        if (!form.hasAttribute('data-ajax')) {
            return; // Not an AJAX form
        }

        e.preventDefault();

        const formData = new FormData(form);
        formData.append('X-Requested-With', 'XMLHttpRequest');

        try {
            const response = await fetch(form.action || `${window.APP_BASE_PATH}api/${moduleName}-api.php`, {
                method: form.method || 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();

            if (result.success) {
                showToast(result.message || 'Operation successful', 'success');
                // Reload module content
                this.reloadCurrentModule();
            } else {
                showToast(result.message || 'Operation failed', 'error');
            }
        } catch (error) {
            console.error('Form submission error:', error);
            showToast('An error occurred. Please try again.', 'error');
        }
    }

    /**
     * Handle action buttons (edit, delete, etc.)
     */
    handleActionButton(e, moduleName) {
        const button = e.currentTarget;
        const action = button.getAttribute('data-action');
        const id = button.getAttribute('data-id');

        if (action === 'delete') {
            this.confirmDelete(id, moduleName);
        } else if (action === 'edit') {
            // Edit handler would go here
            console.log('Edit:', id);
        }
    }

    /**
     * Confirm and delete item
     */
    confirmDelete(id, moduleName) {
        if (!confirm('Are you sure you want to delete this item?')) {
            return;
        }

        // Call the appropriate delete API
        fetch(`${window.APP_BASE_PATH}api/${moduleName}-api.php`, {
            method: 'POST',
            body: JSON.stringify({
                action: 'delete',
                id: id
            }),
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                showToast('Deleted successfully', 'success');
                this.reloadCurrentModule();
            } else {
                showToast(result.message || 'Delete failed', 'error');
            }
        })
        .catch(err => {
            console.error('Delete error:', err);
            showToast('An error occurred', 'error');
        });
    }

    /**
     * Reload current module content
     */
    async reloadCurrentModule() {
        if (this.currentModule) {
            // Clear cache for current module
            delete this.moduleCache[this.currentModule];
            await this.loadModule(this.currentModule);
        }
    }

    /**
     * Handle browser back button
     */
    handleBackButton() {
        window.addEventListener('popstate', (e) => {
            if (e.state && e.state.module) {
                this.loadModule(e.state.module);
            }
        });
    }

    /**
     * Close mobile menu
     */
    closeMobileMenu() {
        const mobileOverlay = document.getElementById('mobileOverlay');
        const sidebar = document.getElementById('sidebar');
        
        if (mobileOverlay && sidebar) {
            mobileOverlay.classList.remove('active');
            sidebar.classList.remove('active');
        }
    }

    /**
     * Show loading indicator
     */
    showLoadingIndicator() {
        const mainContent = document.querySelector('.main-content');
        if (!mainContent) return;

        const loader = document.createElement('div');
        loader.id = 'moduleLoader';
        loader.className = 'module-loader';
        loader.innerHTML = `
            <div class="spinner"></div>
            <p>Loading...</p>
        `;
        mainContent.appendChild(loader);
    }

    /**
     * Hide loading indicator
     */
    hideLoadingIndicator() {
        const loader = document.getElementById('moduleLoader');
        if (loader) {
            loader.remove();
        }
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.moduleLoader = new ModuleLoader();
    });
} else {
    window.moduleLoader = new ModuleLoader();
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ModuleLoader;
}
