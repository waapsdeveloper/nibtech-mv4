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
