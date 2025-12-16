/**
 * V2 Listing Page Controls JavaScript
 * Handles Export CSV, Toggle All, and other page-level controls
 */

(function() {
    'use strict';

    /**
     * Build listing filters from form
     */
    function buildListingFilters() {
        const form = document.getElementById('filterForm');
        if (!form) return {};

        const formData = new FormData(form);
        const params = {};

        // Get all form values
        for (const [key, value] of formData.entries()) {
            if (value) {
                params[key] = value;
            }
        }

        // Add special parameters from URL
        const urlParams = new URLSearchParams(window.location.search);
        const special = urlParams.get('special');
        if (special) {
            params.special = special;
        }

        return params;
    }

    /**
     * Export CSV functionality
     */
    function initializeExportCSV() {
        const exportBtn = document.getElementById('exportListingsBtn');
        if (!exportBtn) return;

        exportBtn.addEventListener('click', function() {
            const params = buildListingFilters();
            const queryString = new URLSearchParams(params).toString();
            const exportUrl = (window.ListingConfig?.urls?.export || '/listing/export') + '?' + queryString;
            window.open(exportUrl, '_blank');
        });
    }

    /**
     * Toggle All marketplace listings within all variation cards
     */
    function initializeToggleAll() {
        const toggleBtn = document.getElementById('open_all_variations');
        if (!toggleBtn) return;

        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get all marketplace toggle content elements (the collapsible sections)
            const toggleElements = document.querySelectorAll('.marketplace-toggle-content');
            
            if (toggleElements.length === 0) {
                console.warn('No marketplace toggle elements found');
                return;
            }

            // Check current state by looking at the first element
            // If it has 'show' class, it's expanded, so we'll collapse all
            // If it doesn't have 'show' class, it's collapsed, so we'll expand all
            const firstElement = toggleElements[0];
            const shouldExpand = !firstElement.classList.contains('show');

            // Toggle all marketplace listings
            toggleElements.forEach(function(element) {
                // Find the toggle button for this element to update its icon
                const elementId = element.id;
                const toggleButton = document.querySelector(`[data-bs-target="#${elementId}"], [aria-controls="${elementId}"]`);
                
                if (shouldExpand) {
                    // Expand this marketplace listing
                    if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                        const bsCollapse = new bootstrap.Collapse(element, {
                            toggle: false
                        });
                        bsCollapse.show();
                    } else {
                        // Fallback if Bootstrap is not available
                        element.classList.add('show');
                        element.style.display = 'block';
                    }
                    
                    // Update chevron icon to down (expanded)
                    if (toggleButton) {
                        const icon = toggleButton.querySelector('i');
                        if (icon) {
                            icon.classList.remove('fa-chevron-down');
                            icon.classList.add('fa-chevron-up');
                        }
                    }
                } else {
                    // Collapse this marketplace listing
                    if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                        const bsCollapse = new bootstrap.Collapse(element, {
                            toggle: false
                        });
                        bsCollapse.hide();
                    } else {
                        // Fallback if Bootstrap is not available
                        element.classList.remove('show');
                        element.style.display = 'none';
                    }
                    
                    // Update chevron icon to down (collapsed)
                    if (toggleButton) {
                        const icon = toggleButton.querySelector('i');
                        if (icon) {
                            icon.classList.remove('fa-chevron-up');
                            icon.classList.add('fa-chevron-down');
                        }
                    }
                }
            });

            // Update button text to reflect current state
            toggleBtn.textContent = shouldExpand ? 'Collapse All' : 'Toggle All';
        });
    }

    /**
     * Update chevron icons when marketplace listings are toggled
     */
    function initializeChevronIcons() {
        // Listen for Bootstrap collapse events to update chevron icons
        document.addEventListener('show.bs.collapse', function(e) {
            const targetId = e.target.id;
            const toggleButton = document.querySelector(`[data-bs-target="#${targetId}"], [aria-controls="${targetId}"]`);
            if (toggleButton) {
                const icon = toggleButton.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                }
            }
        });

        document.addEventListener('hide.bs.collapse', function(e) {
            const targetId = e.target.id;
            const toggleButton = document.querySelector(`[data-bs-target="#${targetId}"], [aria-controls="${targetId}"]`);
            if (toggleButton) {
                const icon = toggleButton.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                }
            }
        });
    }

    /**
     * Initialize all page controls
     */
    function initializePageControls() {
        initializeExportCSV();
        initializeToggleAll();
        initializeChevronIcons();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePageControls);
    } else {
        initializePageControls();
    }

})();

