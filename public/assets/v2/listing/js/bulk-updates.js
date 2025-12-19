/**
 * V2 Listing Bulk Updates JavaScript
 * Handles bulk update modal and target price/percentage updates
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

        // Add page parameter
        const page = urlParams.get('page') || 1;
        params.page = page;

        return params;
    }

    /**
     * Load target variations for bulk update modal
     */
    function loadTargetVariations() {
        const params = buildListingFilters();
        const queryString = new URLSearchParams(params).toString();
        const url = (window.ListingConfig?.urls?.getTargetVariations || '/listing/get_target_variations') + '?' + queryString;

        const tableBody = document.getElementById('bulkUpdateTable');
        if (!tableBody) return;

        // Show loading state
        tableBody.innerHTML = `
            <tr>
                <td colspan="3" class="text-center text-muted">
                    <div class="spinner-border spinner-border-sm" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    Loading variations...
                </td>
            </tr>
        `;

        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (!data.data || data.data.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="3" class="text-center text-muted">No variations found</td>
                    </tr>
                `;
                return;
            }

            tableBody.innerHTML = '';

            data.data.forEach(function(variation) {
                const variationKey = variation.product_id + '_' + variation.storage + '_' + variation.grade;
                const formId = 'bulk_target_' + variationKey;
                const targetPriceId = 'target_price_' + variationKey;
                const targetPercentageId = 'target_percentage_' + variationKey;
                const listingIdsId = 'listing_ids_' + variationKey;

                // Create form
                const form = document.createElement('form');
                form.id = formId;
                form.className = 'form-inline';
                form.method = 'POST';
                form.style.display = 'none';
                form.innerHTML = `
                    <input type="hidden" name="_token" value="${window.ListingConfig?.csrfToken || ''}">
                    <input type="hidden" name="variation_ids[]" value="${variation.ids || ''}">
                    <input type="hidden" id="${listingIdsId}" name="listing_ids[]" value="${variation.listing_ids || ''}">
                    <input type="submit" hidden>
                `;
                document.body.appendChild(form);

                // Create table row
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${variation.product_name || ''} ${variation.storage_name || ''} ${variation.grade_name || ''}</td>
                    <td>
                        <input type="number" class="form-control" name="target" id="${targetPriceId}" 
                               step="0.01" value="${variation.target_price || ''}" form="${formId}">
                    </td>
                    <td>
                        <input type="number" class="form-control" name="percent" id="${targetPercentageId}" 
                               step="0.01" value="${variation.target_percentage || ''}" form="${formId}">
                    </td>
                `;

                // Add submit handler
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    submitBulkTarget(e, variationKey);
                });

                tableBody.appendChild(row);
            });
        })
        .catch(error => {
            console.error('Error loading target variations:', error);
            tableBody.innerHTML = `
                <tr>
                    <td colspan="3" class="text-center text-danger">Error loading variations</td>
                </tr>
            `;
        });
    }

    /**
     * Submit bulk target update
     * @param {Event} event - Form submit event
     * @param {string} variationKey - Variation key (product_id_storage_grade)
     */
    function submitBulkTarget(event, variationKey) {
        event.preventDefault();

        const form = document.getElementById('bulk_target_' + variationKey);
        if (!form) return;

        const listingIdsInput = document.getElementById('listing_ids_' + variationKey);
        if (!listingIdsInput || !listingIdsInput.value) {
            alert('No listings found for this variation');
            return;
        }

        const listingIds = listingIdsInput.value.split(',');
        const formData = new FormData(form);
        const targetPrice = formData.get('target');
        const targetPercentage = formData.get('percent');
        const updateUrl = window.ListingConfig?.urls?.updateTarget || '/listing/update_target';

        let updateCount = 0;
        const totalUpdates = listingIds.length;

        // Update each listing
        listingIds.forEach(function(listingId) {
            const data = {
                _token: window.ListingConfig?.csrfToken || '',
                target: targetPrice || '',
                percent: targetPercentage || ''
            };

            fetch(updateUrl + '/' + listingId, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                updateCount++;
                if (updateCount === totalUpdates) {
                    // All updates complete
                    const targetPriceInput = document.getElementById('target_price_' + variationKey);
                    const targetPercentageInput = document.getElementById('target_percentage_' + variationKey);
                    
                    if (targetPriceInput) {
                        targetPriceInput.classList.add('bg-green');
                        setTimeout(() => targetPriceInput.classList.remove('bg-green'), 2000);
                    }
                    if (targetPercentageInput) {
                        targetPercentageInput.classList.add('bg-green');
                        setTimeout(() => targetPercentageInput.classList.remove('bg-green'), 2000);
                    }
                }
            })
            .catch(error => {
                console.error('Error updating target for listing ' + listingId + ':', error);
                alert('Error updating target for listing ' + listingId);
            });
        });
    }

    /**
     * Initialize bulk update modal
     */
    function initializeBulkUpdateModal() {
        const bulkModal = document.getElementById('bulkModal');
        if (!bulkModal) return;

        // Load variations when modal is shown
        bulkModal.addEventListener('show.bs.modal', function() {
            loadTargetVariations();
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeBulkUpdateModal);
    } else {
        initializeBulkUpdateModal();
    }

})();

