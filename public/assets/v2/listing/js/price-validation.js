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
     * REMOVED: Blur and input event listeners - client wants colors only on Enter key press
     * Colors are now applied only when form is submitted (Enter key) in listing.js
     */
    function initializePriceValidation() {
        // REMOVED: All automatic validation on blur/input
        // Client requirement: No colors until Enter key is pressed
        // Colors are now handled in listing.js on form submission only
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

