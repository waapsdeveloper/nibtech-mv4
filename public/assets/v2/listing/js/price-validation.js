/**
 * V2 Listing Price Validation JavaScript
 * Handles price validation and visual feedback
 */

(function() {
    'use strict';

    /**
     * Check minimum price difference and highlight accordingly
     * @param {number} listingId - Listing ID
     */
    window.checkMinPriceDiff = function(listingId) {
        if (!listingId) return;

        const minPriceInput = document.getElementById('min_price_' + listingId);
        const priceInput = document.getElementById('price_' + listingId);

        if (!minPriceInput || !priceInput) return;

        const minVal = parseFloat(minPriceInput.value) || 0;
        const priceVal = parseFloat(priceInput.value) || 0;

        // Remove previous classes
        minPriceInput.classList.remove('bg-red', 'bg-green');
        priceInput.classList.remove('bg-red', 'bg-green');

        // Validation: min_price should be <= price and price should be <= min_price * 1.08
        if (minVal > priceVal || (minVal > 0 && priceVal > 0 && minVal * 1.08 < priceVal)) {
            // Invalid: highlight in red
            minPriceInput.classList.add('bg-red');
            priceInput.classList.add('bg-red');
        } else if (minVal > 0 && priceVal > 0) {
            // Valid: highlight in green
            minPriceInput.classList.add('bg-green');
            priceInput.classList.add('bg-green');
        }
    };

    /**
     * Initialize price validation for all listing inputs
     */
    function initializePriceValidation() {
        // Add validation on blur for min_price and price inputs
        document.addEventListener('blur', function(e) {
            if (e.target && e.target.id) {
                const inputId = e.target.id;
                
                // Check if it's a min_price or price input
                if (inputId.startsWith('min_price_') || inputId.startsWith('price_')) {
                    // Extract listing ID
                    const match = inputId.match(/(\d+)$/);
                    if (match) {
                        const listingId = match[1];
                        // Small delay to ensure both values are updated
                        setTimeout(function() {
                            checkMinPriceDiff(listingId);
                        }, 100);
                    }
                }
            }
        }, true); // Use capture phase to catch blur events

        // Also validate on input change for real-time feedback
        document.addEventListener('input', function(e) {
            if (e.target && e.target.id) {
                const inputId = e.target.id;
                
                if (inputId.startsWith('min_price_') || inputId.startsWith('price_')) {
                    const match = inputId.match(/(\d+)$/);
                    if (match) {
                        const listingId = match[1];
                        // Debounce validation
                        clearTimeout(window.priceValidationTimeout);
                        window.priceValidationTimeout = setTimeout(function() {
                            checkMinPriceDiff(listingId);
                        }, 300);
                    }
                }
            }
        });
    }

    // Add CSS for validation classes if not already present
    if (!document.getElementById('price-validation-styles')) {
        const style = document.createElement('style');
        style.id = 'price-validation-styles';
        style.textContent = `
            .bg-red {
                background-color: #ffcccc !important;
            }
            .bg-green {
                background-color: #ccffcc !important;
            }
        `;
        document.head.appendChild(style);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePriceValidation);
    } else {
        initializePriceValidation();
    }

})();

