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
     * Setup total stock form for stock formula page
     */
    function setupTotalStockForm() {
        // Store original value on page load
        $(document).ready(function() {
            $('[id^="total_stock_stock_formula_"]').each(function() {
                const currentValue = parseFloat($(this).val()) || 0;
                $(this).data('original-value', currentValue);
            });
        });

        // Handle Update button click
        $(document).on('click', '[id^="save_total_stock_formula_"]', function() {
            const buttonId = $(this).attr('id');
            const matches = buttonId.match(/save_total_stock_formula_(\d+)/);
            
            if (!matches) {
                return;
            }
            
            const variationId = matches[1];
            const totalStockInput = $('#total_stock_stock_formula_' + variationId);
            const newTotalStock = parseFloat(totalStockInput.val());
            const originalTotalStock = parseFloat(totalStockInput.data('original-value')) || parseFloat(totalStockInput.attr('value')) || 0;
            
            if (isNaN(newTotalStock) || newTotalStock < 0) {
                showAlert('Please enter a valid stock value (0 or greater)', 'warning');
                return;
            }

            if (newTotalStock === originalTotalStock) {
                showAlert('No change in stock value', 'info');
                return;
            }

            // Show loading
            const saveButton = $(this);
            const originalHtml = saveButton.html();
            saveButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
            
            // Clear success message
            $('#success_total_stock_formula_' + variationId).text('');

            // Submit via AJAX - send exact stock value
            const form = $('#total_stock_form_formula_' + variationId);
            $.ajax({
                type: "POST",
                url: form.attr('action'),
                data: {
                    set_exact_stock: true,
                    exact_stock_value: newTotalStock,
                    _token: window.StockFormulaConfig.csrfToken
                },
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    let totalStock, marketplaceStocks = {};
                    
                    if (typeof response === 'object' && response !== null) {
                        totalStock = parseFloat(response.total_stock) || parseFloat(response.quantity) || 0;
                        marketplaceStocks = response.marketplace_stocks || {};
                    } else {
                        totalStock = parseFloat(response) || newTotalStock;
                    }
                    
                    // Update the total stock display
                    $('#total_stock_stock_formula_' + variationId).val(totalStock);
                    $('#total_stock_stock_formula_' + variationId).data('original-value', totalStock);
                    $('#success_total_stock_formula_' + variationId).text("Stock updated to " + totalStock);
                    
                    // Update marketplace stock displays
                    if (Object.keys(marketplaceStocks).length > 0) {
                        updateMarketplaceStockDisplaysForFormula(variationId, marketplaceStocks);
                    } else {
                        fetchMarketplaceStocksForFormula(variationId);
                    }
                    
                    // Re-enable button (keep it visible)
                    saveButton.prop('disabled', false).html(originalHtml);
                    
                    // Clear success message after 3 seconds
                    setTimeout(function() {
                        $('#success_total_stock_formula_' + variationId).text('');
                    }, 3000);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    let errorMsg = "Error: " + textStatus;
                    if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
                        errorMsg = jqXHR.responseJSON.error;
                    } else if (jqXHR.responseText) {
                        errorMsg = jqXHR.responseText;
                    }
                    showAlert(errorMsg, 'danger');
                    saveButton.prop('disabled', false).html(originalHtml);
                }
            });
        });
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
