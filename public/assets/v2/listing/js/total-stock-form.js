/**
 * V2 Total Stock Form JavaScript
 * Handles the total stock add quantity form functionality
 */

(function() {
    'use strict';

    /**
     * Initialize total stock form handlers
     */
    function initializeTotalStockForm() {
        // Show/hide Push button based on input value and validate against available stock
        $(document).on('input', '[id^="add_total_"]', function() {
            const inputId = $(this).attr('id');
            const matches = inputId.match(/add_total_(\d+)/);
            
            if (!matches) {
                return;
            }
            
            const variationId = matches[1];
            const input = $(this);
            const value = parseFloat(input.val());
            const pushButton = $('#send_total_' + variationId);
            
            // Clear previous errors
            const errorElement = $('#error_total_' + variationId);
            errorElement.addClass('d-none').text('');
            input.removeClass('is-invalid');
            
            // Allow negative numbers for subtraction, but hide button if empty or zero
            if (isNaN(value) || value === 0) {
                pushButton.addClass('d-none');
                return;
            }
            
            // Valid input - show push button
            pushButton.removeClass('d-none');
        });

        // Handle form submission
        $(document).on('submit', '[id^="add_qty_total_"]', function(e) {
            e.preventDefault();
            
            const formId = $(this).attr('id');
            const matches = formId.match(/add_qty_total_(\d+)/);
            
            if (!matches) {
                return;
            }
            
            const variationId = matches[1];
            const form = $(this);
            const input = $('#add_total_' + variationId);
            const quantity = parseFloat(input.val());
            const currentTotal = parseFloat($('#total_stock_' + variationId).val()) || 0;
            
            // Validate quantity - allow negative numbers for subtraction
            if (isNaN(quantity) || quantity === 0) {
                return;
            }
            
            // Show loading state on button
            const pushButton = $('#send_total_' + variationId);
            const originalButtonText = pushButton.html();
            pushButton.prop('disabled', true);
            pushButton.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
            pushButton.removeClass('d-none');
            
            // Clear success message
            $('#success_total_' + variationId).text('');
            
            // Submit via AJAX
            $.ajax({
                type: "POST",
                url: form.attr('action'),
                data: form.serialize(),
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    // Handle both JSON and plain text responses
                    let totalStock, marketplaceStocks = {};
                    
                    if (typeof response === 'object' && response !== null) {
                        // JSON response - should contain total_stock or quantity
                        totalStock = parseFloat(response.total_stock) || parseFloat(response.quantity) || 0;
                        marketplaceStocks = response.marketplace_stocks || {};
                        
                        // Safety check: if totalStock seems wrong (less than current total), recalculate
                        if (totalStock < currentTotal || isNaN(totalStock)) {
                            totalStock = currentTotal + quantity;
                        }
                    } else if (typeof response === 'string' || typeof response === 'number') {
                        // Plain text or number response (backward compatibility)
                        totalStock = parseFloat(response) || 0;
                        // If the response is less than current total, it's likely just the added quantity
                        if (totalStock < currentTotal || isNaN(totalStock)) {
                            totalStock = currentTotal + quantity;
                        }
                        // Fetch marketplace stocks separately
                        fetchMarketplaceStocks(variationId, {});
                        // Reset form and return early
                        resetForm(variationId, originalButtonText);
                        $('#total_stock_' + variationId).val(totalStock);
                        $('#success_total_' + variationId).text("Quantity changed by " + quantity + " to " + totalStock);
                        
                        // Update listing total quantity display
                        const listingTotalElement = $('#listing_total_quantity_' + variationId);
                        if (listingTotalElement.length) {
                            listingTotalElement.text(totalStock);
                        }
                        return;
                    } else {
                        // Fallback: calculate from current total + quantity added
                        totalStock = currentTotal + quantity;
                        marketplaceStocks = {};
                    }
                    
                    // Final safety check
                    if (isNaN(totalStock) || totalStock < 0) {
                        totalStock = currentTotal + quantity;
                    }
                    
                    // Update the total stock display
                    $('#total_stock_' + variationId).val(totalStock);
                    $('#success_total_' + variationId).text("Quantity changed by " + quantity + " to " + totalStock);
                    
                    // Update listing total quantity display
                    const listingTotalElement = $('#listing_total_quantity_' + variationId);
                    if (listingTotalElement.length) {
                        listingTotalElement.text(totalStock);
                    }
                    
                    // Update marketplace stock displays - use marketplace_stocks (actual saved values)
                    // distribution_preview is just for reference, marketplace_stocks has the correct values
                    // Only update once to avoid double updates
                    updateMarketplaceStockDisplays(variationId, marketplaceStocks);
                    
                    // Run getUpdatedQuantity AJAX to refresh the Backmarket stock badge
                    if (typeof window.fetchBackmarketStockQuantity === 'function' && typeof window.updateBackmarketStockBadge === 'function') {
                        window.fetchBackmarketStockQuantity(variationId, 1).then(function(result) {
                            if (result !== null) {
                                window.updateBackmarketStockBadge(variationId, 1, result);
                            }
                        });
                    }
                    
                    // Update current total for next push
                    const input = $('#add_total_' + variationId);
                    input.data('current-total', totalStock);
                    
                    // Reset form
                    resetForm(variationId, originalButtonText);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    // Try to parse as text if JSON fails (backward compatibility)
                    if (jqXHR.responseText && !isNaN(jqXHR.responseText.trim())) {
                        let totalStock = parseFloat(jqXHR.responseText.trim());
                        // If response is less than current total, it's likely just the added quantity
                        if (totalStock < currentTotal || isNaN(totalStock)) {
                            totalStock = currentTotal + quantity;
                        }
                        $('#total_stock_' + variationId).val(totalStock);
                        $('#success_total_' + variationId).text("Quantity changed by " + quantity + " to " + totalStock);
                        
                        // Update listing total quantity display
                        const listingTotalElement = $('#listing_total_quantity_' + variationId);
                        if (listingTotalElement.length) {
                            listingTotalElement.text(totalStock);
                        }
                        
                        resetForm(variationId, originalButtonText);
                        // Fetch marketplace stocks
                        fetchMarketplaceStocks(variationId, {});
                        return;
                    }
                    
                    let errorMsg = "Error: " + textStatus;
                    if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
                        errorMsg = jqXHR.responseJSON.error;
                    } else if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                        errorMsg = jqXHR.responseJSON.message;
                    } else if (jqXHR.responseText) {
                        errorMsg = jqXHR.responseText;
                    }
                    
                    // Show error in error element if it exists
                    const errorElement = $('#error_total_' + variationId);
                    if (errorElement.length) {
                        errorElement.removeClass('d-none').text(errorMsg);
                        $('#add_total_' + variationId).addClass('is-invalid');
                    } else {
                        alert(errorMsg);
                    }
                    resetForm(variationId, originalButtonText);
                }
            });
        });
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        initializeTotalStockForm();
    });

    // Also initialize if document is already loaded (for dynamically loaded content)
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(initializeTotalStockForm, 1);
    }

    /**
     * Update marketplace stock displays
     */
    function updateMarketplaceStockDisplays(variationId, marketplaceStocks) {
        // Update marketplace bar stock displays
        Object.keys(marketplaceStocks).forEach(function(marketplaceId) {
            const stockValue = marketplaceStocks[marketplaceId];
            
            // Update in marketplace bar - Listed Stock (LS:) display
            // Format: listed_stock_{variationId}_{marketplaceId}
            const listedStockElement = $('#listed_stock_' + variationId + '_' + marketplaceId);
            if (listedStockElement.length) {
                listedStockElement.text(stockValue);
            }
            
            // Also update stock_{variationId}_{marketplaceId} if it exists (for backward compatibility)
            const barStockElement = $('#stock_' + variationId + '_' + marketplaceId);
            if (barStockElement.length) {
                barStockElement.text(stockValue);
            }
            
            // Update in marketplace stocks section if visible
            const stockInput = $('#stock_input_' + variationId + '_' + marketplaceId);
            if (stockInput.length) {
                stockInput.val(stockValue);
                // Also update the display value
                const stockDisplay = $('#stock_display_' + variationId + '_' + marketplaceId);
                if (stockDisplay.length) {
                    stockDisplay.text(stockValue);
                }
            }
        });
    }

    /**
     * Fetch marketplace stocks via API
     */
    function fetchMarketplaceStocks(variationId, callback) {
        // If we have a URL to fetch marketplace stocks, use it
        // Otherwise, we'll update from the response
        if (window.ListingConfig && window.ListingConfig.urls && window.ListingConfig.urls.getVariationStocks) {
            $.ajax({
                url: window.ListingConfig.urls.getVariationStocks,
                type: 'GET',
                data: { variation_id: variationId },
                success: function(data) {
                    if (data && data.marketplace_stocks) {
                        updateMarketplaceStockDisplays(variationId, data.marketplace_stocks);
                    }
                },
                error: function() {
                    console.warn('Could not fetch marketplace stocks');
                }
            });
        }
    }

    /**
     * Reset form after successful submission
     */
    function resetForm(variationId, originalButtonText) {
        // Clear input and remove validation classes
        const input = $('#add_total_' + variationId);
        input.val('').removeClass('is-invalid');
        
        // Clear error message
        $('#error_total_' + variationId).addClass('d-none').text('');
        
        // Restore button and hide it
        const pushButton = $('#send_total_' + variationId);
        pushButton.prop('disabled', false);
        if (originalButtonText) {
            pushButton.html(originalButtonText);
        }
        pushButton.addClass('d-none');
        // Clear success message after 3 seconds
        setTimeout(function() {
            $('#success_total_' + variationId).text('');
        }, 3000);
    }

})();

