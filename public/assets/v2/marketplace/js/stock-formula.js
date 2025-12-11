/**
 * V2 Stock Formula Management JavaScript
 * Handles stock formula configuration and variation search using plain AJAX
 */

(function() {
    'use strict';

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        initializeStockFormula();
    });

    function initializeStockFormula() {
        setupSearchInput();
        setupInlineForms();
        setupResetStockForms();
        setupTotalStockForm();
        setupAutoHideAlerts();
    }

    /**
     * Setup search input with Enter key and button click
     */
    function setupSearchInput() {
        const searchInput = document.getElementById('variation_search_input');
        const searchBtn = document.getElementById('search_btn');
        const resultsContainer = document.getElementById('search_results_container');
        const resultsDiv = document.getElementById('search_results');

        if (!searchInput || !searchBtn) return;

        function performSearch() {
            const searchTerm = searchInput.value.trim();
            
            if (searchTerm.length < 2) {
                showAlert('Please enter at least 2 characters to search', 'warning');
                return;
            }

            // Show loading
            resultsDiv.innerHTML = '<div class="list-group-item text-center">Searching...</div>';
            resultsContainer.style.display = 'block';

            // Perform AJAX search
            $.ajax({
                url: window.StockFormulaConfig.urls.search,
                type: 'GET',
                data: {
                    search: searchTerm,
                    _token: window.StockFormulaConfig.csrfToken
                },
                success: function(response) {
                    displaySearchResults(response.variations || []);
                },
                error: function(xhr) {
                    console.error('Search error:', xhr);
                    resultsDiv.innerHTML = '<div class="list-group-item text-danger">Error searching variations. Please try again.</div>';
                }
            });
        }

        // Enter key handler
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                performSearch();
            }
        });

        // Search button click
        searchBtn.addEventListener('click', function(e) {
            e.preventDefault();
            performSearch();
        });
    }

    /**
     * Display search results
     */
    function displaySearchResults(variations) {
        const resultsDiv = document.getElementById('search_results');
        
        if (variations.length === 0) {
            resultsDiv.innerHTML = '<div class="list-group-item">No variations found</div>';
            return;
        }

        let html = '';
        variations.forEach(function(variation) {
            html += `
                <a href="javascript:void(0)" 
                   class="list-group-item list-group-item-action" 
                   onclick="selectVariation(${variation.id})">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">${variation.sku}</h6>
                    </div>
                    <p class="mb-1">
                        <strong>Model:</strong> ${variation.model}<br>
                        <strong>Storage:</strong> ${variation.storage} | 
                        <strong>Color:</strong> ${variation.color} | 
                        <strong>Grade:</strong> ${variation.grade}
                    </p>
                </a>
            `;
        });

        resultsDiv.innerHTML = html;
    }

    /**
     * Select variation and load marketplace stocks
     */
    window.selectVariation = function(variationId) {
        // Redirect to page with variation_id
        window.location.href = window.StockFormulaConfig.urls.getStocks + '?variation_id=' + variationId;
    };


    /**
     * Setup inline forms
     */
    function setupInlineForms() {
        // Handle form submission
        $(document).on('submit', '.formula-inline-form', function(e) {
            e.preventDefault();
            saveFormulaInline(this);
        });
    }

    /**
     * Save formula inline
     */
    function saveFormulaInline(formElement) {
        const form = $(formElement);
        const variationId = form.data('variation-id');
        const marketplaceId = form.data('marketplace-id');
        
        // Prevent saving formula for first marketplace (ID = 1)
        if (marketplaceId == 1) {
            showAlert('Formula cannot be changed for the first marketplace. Remaining stock is automatically allocated here.', 'warning');
            return;
        }
        
        const value = parseFloat(form.find(`#formula_value_${marketplaceId}`).val());
        const type = form.find(`#formula_type_${marketplaceId}`).val();
        const applyTo = form.find(`#formula_apply_to_${marketplaceId}`).val();
        
        if (isNaN(value) || value < 0) {
            showAlert('Please enter a valid value', 'warning');
            return;
        }

        const formData = {
            value: value,
            type: type,
            apply_to: applyTo,
            _token: window.StockFormulaConfig.csrfToken
        };

        // Show loading
        const submitBtn = form.find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fe fe-loader"></i> Saving...');

        // Save via AJAX
        $.ajax({
            url: window.StockFormulaConfig.urls.saveFormula + variationId + '/formula/' + marketplaceId,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    // Reload page to show updated formula
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showAlert(response.message || 'Error saving formula', 'danger');
                    submitBtn.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr) {
                let errorMsg = 'Error saving formula';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    errorMsg = xhr.responseJSON.errors.join(', ');
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showAlert(errorMsg, 'danger');
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    }

    /**
     * Delete formula
     */
    window.deleteFormula = function(variationId, marketplaceId) {
        // Prevent deleting formula for first marketplace (ID = 1)
        if (marketplaceId == 1) {
            showAlert('Formula cannot be deleted for the first marketplace. Remaining stock is automatically allocated here.', 'warning');
            return;
        }
        
        if (!confirm('Are you sure you want to delete this formula?')) {
            return;
        }

        $.ajax({
            url: window.StockFormulaConfig.urls.deleteFormula + variationId + '/formula/' + marketplaceId,
            type: 'DELETE',
            data: {
                _token: window.StockFormulaConfig.csrfToken
            },
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    // Reload page to show updated state
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showAlert(response.message || 'Error deleting formula', 'danger');
                }
            },
            error: function(xhr) {
                showAlert('Error deleting formula', 'danger');
            }
        });
    };

    /**
     * Setup reset stock forms
     */
    function setupResetStockForms() {
        // Handle form submission
        $(document).on('submit', '.reset-stock-form', function(e) {
            e.preventDefault();
            e.stopPropagation();
            resetStock(this);
            return false;
        });
        
        // Handle button click (since we changed button to type="button")
        $(document).on('click', '.reset-stock-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Reset button clicked');
            const formId = $(this).data('form-id');
            console.log('Form ID:', formId);
            const form = $('#' + formId);
            console.log('Form found:', form.length > 0);
            if (form.length) {
                console.log('Calling resetStock');
                resetStock(form[0]);
            } else {
                console.error('Form not found with ID:', formId);
            }
            return false;
        });
        
        // Also handle Enter key in input
        $(document).on('keydown', '.reset-stock-form input[type="number"]', function(e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                e.stopPropagation();
                const form = $(this).closest('.reset-stock-form');
                if (form.length) {
                    resetStock(form[0]);
                }
                return false;
            }
        });
    }

    /**
     * Reset stock to exact value
     */
    function resetStock(formElement) {
        const form = $(formElement);
        const variationId = form.data('variation-id');
        const marketplaceId = form.data('marketplace-id');
        const stockValue = parseInt(form.find(`#reset_stock_value_${marketplaceId}`).val());
        
        console.log('resetStock called', { variationId, marketplaceId, stockValue });
        
        if (isNaN(stockValue) || stockValue < 0) {
            showAlert('Please enter a valid stock value (0 or greater)', 'warning');
            return;
        }

        // Show loading - find button by class since it's type="button"
        let submitBtn = form.find('.reset-stock-btn');
        if (submitBtn.length === 0) {
            // Fallback to any button in the form
            submitBtn = form.find('button');
        }
        
        if (submitBtn.length === 0) {
            console.error('Button not found in form');
            showAlert('Error: Button not found', 'danger');
            return;
        }
        
        const originalHtml = submitBtn.html();
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');

        // Build URL
        const url = window.StockFormulaConfig.urls.resetStock + variationId + '/stock/' + marketplaceId + '/reset';
        console.log('Making AJAX call to:', url, { stock: stockValue, csrfToken: window.StockFormulaConfig.csrfToken ? 'present' : 'missing' });

        // Reset stock via AJAX
        $.ajax({
            url: url,
            type: 'POST',
            data: {
                stock: stockValue,
                _token: window.StockFormulaConfig.csrfToken
            },
            success: function(response) {
                console.log('Reset stock success:', response);
                if (response.success) {
                    // Update stock display
                    $(`#stock_${marketplaceId}`).text(response.stock);
                    // Update the input value to match
                    form.find(`#reset_stock_value_${marketplaceId}`).val(response.stock);
                    
                    // Update total stock input field dynamically
                    if (response.total_stock !== undefined) {
                        const totalStockInput = $(`#total_stock_stock_formula_${variationId}`);
                        if (totalStockInput.length) {
                            totalStockInput.val(response.total_stock);
                            // Update the original value data attribute as well
                            totalStockInput.data('original-value', response.total_stock);
                        }
                    }
                    
                    showAlert(response.message + ' (Stock: ' + response.stock + ', Total: ' + response.total_stock + ')', 'success');
                    // Re-enable button
                    submitBtn.prop('disabled', false).html(originalHtml);
                } else {
                    showAlert(response.message || 'Error resetting stock', 'danger');
                    submitBtn.prop('disabled', false).html(originalHtml);
                }
            },
            error: function(xhr, status, error) {
                console.error('Reset stock error:', { xhr, status, error, responseText: xhr.responseText });
                let errorMsg = 'Error resetting stock';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    errorMsg = xhr.responseJSON.errors.join(', ');
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    errorMsg = xhr.responseText;
                }
                showAlert(errorMsg, 'danger');
                submitBtn.prop('disabled', false).html(originalHtml);
            }
        });
    }

    /**
     * Setup total stock form for stock formula page (push mechanism like listing page)
     */
    function setupTotalStockForm() {
        // Show/hide Push button based on input value
        $(document).on('input', '[id^="add_total_formula_"]', function() {
            const inputId = $(this).attr('id');
            const matches = inputId.match(/add_total_formula_(\d+)/);
            
            if (!matches) {
                return;
            }
            
            const variationId = matches[1];
            const value = $(this).val();
            
            if (value && parseFloat(value) !== 0) {
                $('#send_total_formula_' + variationId).removeClass('d-none');
            } else {
                $('#send_total_formula_' + variationId).addClass('d-none');
            }
        });

        // Handle form submission
        $(document).on('submit', '[id^="add_qty_total_formula_"]', function(e) {
            e.preventDefault();
            
            const formId = $(this).attr('id');
            const matches = formId.match(/add_qty_total_formula_(\d+)/);
            
            if (!matches) {
                return;
            }
            
            const variationId = matches[1];
            const form = $(this);
            const quantity = parseFloat($('#add_total_formula_' + variationId).val());
            const currentTotal = parseFloat($('#total_stock_stock_formula_' + variationId).val()) || 0;
            
            // Validate quantity
            if (!quantity || quantity === 0 || isNaN(quantity)) {
                return;
            }
            
            // Show loading state on button
            const pushButton = $('#send_total_formula_' + variationId);
            const originalButtonText = pushButton.html();
            pushButton.prop('disabled', true);
            pushButton.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
            pushButton.removeClass('d-none');
            
            // Clear success message
            $('#success_total_stock_formula_' + variationId).text('');
            
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
                        fetchMarketplaceStocksForFormula(variationId);
                        // Reset form and return early
                        resetTotalStockForm(variationId, originalButtonText);
                        $('#total_stock_stock_formula_' + variationId).val(totalStock);
                        $('#success_total_stock_formula_' + variationId).text("Quantity changed by " + quantity + " to " + totalStock);
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
                    $('#total_stock_stock_formula_' + variationId).val(totalStock);
                    $('#success_total_stock_formula_' + variationId).text("Quantity changed by " + quantity + " to " + totalStock);
                    
                    // Update marketplace stock displays
                    if (Object.keys(marketplaceStocks).length > 0) {
                        updateMarketplaceStockDisplaysForFormula(variationId, marketplaceStocks);
                    } else {
                        fetchMarketplaceStocksForFormula(variationId);
                    }
                    
                    // Reset form
                    resetTotalStockForm(variationId, originalButtonText);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    // Try to parse as text if JSON fails (backward compatibility)
                    if (jqXHR.responseText && !isNaN(jqXHR.responseText.trim())) {
                        let totalStock = parseFloat(jqXHR.responseText.trim());
                        // If response is less than current total, it's likely just the added quantity
                        if (totalStock < currentTotal || isNaN(totalStock)) {
                            totalStock = currentTotal + quantity;
                        }
                        $('#total_stock_stock_formula_' + variationId).val(totalStock);
                        $('#success_total_stock_formula_' + variationId).text("Quantity changed by " + quantity + " to " + totalStock);
                        resetTotalStockForm(variationId, originalButtonText);
                        // Fetch marketplace stocks
                        fetchMarketplaceStocksForFormula(variationId);
                        return;
                    }
                    
                    let errorMsg = "Error: " + textStatus;
                    if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
                        errorMsg = jqXHR.responseJSON.error;
                    } else if (jqXHR.responseText) {
                        errorMsg = jqXHR.responseText;
                    }
                    showAlert(errorMsg, 'danger');
                    resetTotalStockForm(variationId, originalButtonText);
                }
            });
        });
    }

    /**
     * Reset total stock form after successful submission
     */
    function resetTotalStockForm(variationId, originalButtonText) {
        // Clear input
        $('#add_total_formula_' + variationId).val('');
        // Restore button and hide it
        const pushButton = $('#send_total_formula_' + variationId);
        pushButton.prop('disabled', false);
        if (originalButtonText) {
            pushButton.html(originalButtonText);
        }
        pushButton.addClass('d-none');
        // Clear success message after 3 seconds
        setTimeout(function() {
            $('#success_total_stock_formula_' + variationId).text('');
        }, 3000);
    }

    /**
     * Fetch marketplace stocks after total stock update
     */
    function fetchMarketplaceStocksForFormula(variationId) {
        if (window.StockFormulaConfig && window.StockFormulaConfig.urls && window.StockFormulaConfig.urls.getStocks) {
            $.ajax({
                url: window.StockFormulaConfig.urls.getStocks + variationId + '/stocks',
                type: 'GET',
                data: { _token: window.StockFormulaConfig.csrfToken },
                success: function(data) {
                    if (data && data.marketplaceStocks) {
                        updateMarketplaceStockDisplaysForFormula(variationId, data.marketplaceStocks);
                    }
                },
                error: function() {
                    console.warn('Could not fetch marketplace stocks');
                }
            });
        }
    }

    /**
     * Update marketplace stock displays after total stock update
     */
    function updateMarketplaceStockDisplaysForFormula(variationId, marketplaceStocks) {
        // Update marketplace stock displays
        Object.keys(marketplaceStocks).forEach(function(marketplaceId) {
            const stockValue = marketplaceStocks[marketplaceId].listed_stock || marketplaceStocks[marketplaceId];
            
            // Update in stock formula page
            const stockElement = $('#stock_' + marketplaceId);
            if (stockElement.length) {
                stockElement.text(stockValue);
            }
            
            // Update reset stock input value
            const resetInput = $('#reset_stock_value_' + marketplaceId);
            if (resetInput.length) {
                resetInput.val(stockValue);
            }
        });
    }

    /**
     * Show alert message
     */
    function showAlert(message, type) {
        type = type || 'info';
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                <span class="alert-inner--text"><strong>${message}</strong></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        $('#alert-container').html(alertHtml);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut('slow', function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Auto-hide alerts
     */
    function setupAutoHideAlerts() {
        setTimeout(function() {
            $('.alert').fadeOut('slow', function() {
                $(this).remove();
            });
        }, 5000);
    }

})();
