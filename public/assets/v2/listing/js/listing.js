/**
 * V2 Listing Page JavaScript
 * Handles marketplace listings, stock management, and related functionality
 */

// Configuration object - will be populated by Blade template
window.ListingConfig = window.ListingConfig || {
    urls: {},
    csrfToken: '',
    flagsPath: ''
};

// Make global variables available (will be set by Blade)
let countries = window.countries || {};
let marketplaces = window.marketplaces || {};
let exchange_rates = window.exchange_rates || {};
let currencies = window.currencies || {};
let currency_sign = window.currency_sign || {};
let eur_gbp = window.eur_gbp || 1;
let processId = window.processId || null;
window.eur_listings = window.eur_listings || {};

/**
 * Get Buybox functionality
 * @param {number} listingId - Listing ID
 * @param {number} variationId - Variation ID
 * @param {number} buyboxPrice - Buybox price
 */
window.getBuybox = function(listingId, variationId, buyboxPrice) {
    if (!confirm(`Set price to ${buyboxPrice} to get buybox?`)) {
        return;
    }

    const button = document.getElementById('get_buybox_' + listingId);
    if (button) {
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    }

    // Find marketplace ID from the listing row
    const row = button?.closest('tr');
    let marketplaceId = null;
    if (row) {
        // Try to find marketplace ID from row data or parent container
        const marketplaceToggle = row.closest('.marketplace-toggle-content');
        if (marketplaceToggle) {
            const toggleId = marketplaceToggle.id;
            const matches = toggleId.match(/marketplace_toggle_(\d+)_(\d+)/);
            if (matches) {
                marketplaceId = parseInt(matches[2]);
            }
        }
    }

    const updateUrl = window.ListingConfig?.urls?.updatePrice || '/v2/listings/update_price';
    const data = {
        _token: window.ListingConfig?.csrfToken || '',
        price: buyboxPrice
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
        if (result.success) {
            // Reload the listings table
            if (marketplaceId) {
                loadMarketplaceTables(variationId, marketplaceId);
            }
            alert('Price updated successfully!');
        } else {
            alert('Error: ' + (result.error || 'Failed to update price'));
            if (button) {
                button.disabled = false;
                button.innerHTML = 'Get Buybox';
            }
        }
    })
    .catch(error => {
        console.error('Error getting buybox:', error);
        alert('Error updating price');
        if (button) {
            button.disabled = false;
            button.innerHTML = 'Get Buybox';
        }
    });
};

/**
 * Load sales data for variation
 * @param {number} variationId - Variation ID
 */
function loadSalesData(variationId) {
    const salesElement = document.getElementById('sales_' + variationId);
    if (!salesElement) return;

    const salesUrl = (window.ListingConfig?.urls?.getSales || '/listing/get_sales') + '/' + variationId;
    
    fetch(salesUrl + '?csrf=' + (window.ListingConfig?.csrfToken || ''), {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'text/html'
        }
    })
    .then(response => response.text())
    .then(html => {
        salesElement.innerHTML = html;
    })
    .catch(error => {
        console.error('Error loading sales data:', error);
        salesElement.innerHTML = '<span class="text-muted small">Error loading sales data</span>';
    });
}

/**
 * Show variation history modal
 */
function show_variation_history(variationId, variationName) {
    $('#variationHistoryModal').modal('show');
    $('#variation_name').text(variationName);
    $('#variationHistoryTable').html('Loading...');
    
    $.ajax({
        url: window.ListingConfig.urls.getVariationHistory + '/' + variationId,
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            let historyTable = '';
            if (data.listed_stock_verifications && data.listed_stock_verifications.length > 0) {
                data.listed_stock_verifications.forEach(function(item) {
                    historyTable += `
                        <tr>
                            <td>${item.process_ref ?? ''}</td>
                            <td>${item.pending_orders ?? ''}</td>
                            <td>${item.qty_from ?? ''}</td>
                            <td>${item.qty_change ?? ''}</td>
                            <td>${item.qty_to ?? ''}</td>
                            <td>${item.admin ?? ''}</td>
                            <td>${item.created_at ? new Date(item.created_at).toLocaleString('en-GB', { timeZone: 'Europe/London', hour12: true }) : ''}</td>
                        </tr>`;
                });
            } else {
                historyTable = '<tr><td colspan="7" class="text-center text-muted">No history found</td></tr>';
            }
            $('#variationHistoryTable').html(historyTable);
        },
        error: function(xhr) {
            console.error(xhr.responseText);
            $('#variationHistoryTable').html('<tr><td colspan="7" class="text-center text-danger">Error loading history</td></tr>');
        }
    });
}

/**
 * Show listing history modal
 */
/**
 * Show snapshot tooltip on hover
 * @param {Event} event - Mouse event
 * @param {string} snapshotId - Unique ID for the snapshot tooltip
 */
window.showSnapshotTooltip = function(event, snapshotId) {
    try {
        // Get snapshot from global object
        if (!window.listingSnapshots || !window.listingSnapshots[snapshotId]) {
            return;
        }
        
        const snapshot = window.listingSnapshots[snapshotId];
        const tooltip = document.getElementById(`tooltip_${snapshotId}`);
        if (!tooltip) return;
        
        // Get the icon element from the event
        const icon = event.target;
        if (!icon) return;
        
        // Format and set tooltip content
        tooltip.innerHTML = formatSnapshotForTooltip(snapshot);
        
        // Position tooltip
        const iconRect = icon.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        const modalBody = document.querySelector('#listingHistoryModal .modal-body');
        const modalBodyRect = modalBody ? modalBody.getBoundingClientRect() : { left: 0, top: 0, width: window.innerWidth, height: window.innerHeight };
        
        // Position to the left of the icon
        let left = iconRect.left - tooltipRect.width - 10;
        let top = iconRect.top + (iconRect.height / 2) - (tooltipRect.height / 2);
        
        // Adjust if tooltip goes off screen
        if (left < modalBodyRect.left) {
            left = iconRect.right + 10; // Show to the right instead
        }
        if (top + tooltipRect.height > modalBodyRect.top + modalBodyRect.height) {
            top = modalBodyRect.top + modalBodyRect.height - tooltipRect.height - 10;
        }
        if (top < modalBodyRect.top) {
            top = modalBodyRect.top + 10;
        }
        
        tooltip.style.left = (left - modalBodyRect.left + modalBody.scrollLeft) + 'px';
        tooltip.style.top = (top - modalBodyRect.top + modalBody.scrollTop) + 'px';
        tooltip.style.display = 'block';
    } catch (e) {
        console.error('Error showing snapshot tooltip:', e);
    }
};

/**
 * Hide snapshot tooltip
 * @param {string} snapshotId - Unique ID for the snapshot tooltip
 */
window.hideSnapshotTooltip = function(snapshotId) {
    const tooltip = document.getElementById(`tooltip_${snapshotId}`);
    if (tooltip) {
        tooltip.style.display = 'none';
    }
};

/**
 * Format snapshot data for tooltip display
 * @param {object} snapshot - The row snapshot object
 * @returns {string} - HTML formatted snapshot
 */
function formatSnapshotForTooltip(snapshot) {
    if (!snapshot || typeof snapshot !== 'object') {
        return 'No snapshot data available';
    }
    
    let html = '<div style="text-align: left; max-width: 400px; font-size: 0.9em;">';
    html += '<strong style="display: block; margin-bottom: 8px; border-bottom: 1px solid #ddd; padding-bottom: 4px;">Row Snapshot:</strong>';
    html += '<table style="width: 100%; border-collapse: collapse;">';
    
    // Helper function to format value
    const formatValue = (value) => {
        if (value === null || value === undefined) return '<em class="text-muted">null</em>';
        if (typeof value === 'boolean') return value ? 'Yes' : 'No';
        if (typeof value === 'number') {
            // Check if it's a decimal
            if (value % 1 !== 0) {
                return parseFloat(value).toFixed(2);
            }
            return value.toString();
        }
        if (typeof value === 'object') {
            return JSON.stringify(value);
        }
        return String(value);
    };
    
    // Display main fields
    const mainFields = [
        { key: 'id', label: 'ID' },
        { key: 'variation_id', label: 'Variation ID' },
        { key: 'marketplace_id', label: 'Marketplace ID' },
        { key: 'country', label: 'Country' },
        { key: 'reference_uuid', label: 'Reference UUID' },
        { key: 'name', label: 'Name' },
        { key: 'min_price', label: 'Min Price' },
        { key: 'max_price', label: 'Max Price' },
        { key: 'price', label: 'Price' },
        { key: 'buybox', label: 'BuyBox' },
        { key: 'buybox_price', label: 'BuyBox Price' },
        { key: 'buybox_winner_price', label: 'BuyBox Winner Price' },
        { key: 'min_price_limit', label: 'Min Price Limit' },
        { key: 'price_limit', label: 'Price Limit' },
        { key: 'handler_status', label: 'Handler Status' },
        { key: 'target_price', label: 'Target Price' },
        { key: 'target_percentage', label: 'Target Percentage' },
        { key: 'status', label: 'Status' },
        { key: 'is_enabled', label: 'Is Enabled' },
        { key: 'created_at', label: 'Created At' },
        { key: 'updated_at', label: 'Updated At' }
    ];
    
    mainFields.forEach(field => {
        if (snapshot.hasOwnProperty(field.key)) {
            html += `<tr style="border-bottom: 1px solid #eee;">`;
            html += `<td style="padding: 4px 8px; font-weight: 600; color: #666;">${field.label}:</td>`;
            html += `<td style="padding: 4px 8px;">${formatValue(snapshot[field.key])}</td>`;
            html += `</tr>`;
        }
    });
    
    // Display nested objects (country_id, marketplace, currency)
    if (snapshot.country_id && typeof snapshot.country_id === 'object') {
        html += `<tr style="border-top: 2px solid #ddd; border-bottom: 1px solid #eee;"><td colspan="2" style="padding: 4px 8px; font-weight: 600; color: #333;">Country Details:</td></tr>`;
        if (snapshot.country_id.id) html += `<tr><td style="padding-left: 20px; padding: 4px 8px;">ID:</td><td style="padding: 4px 8px;">${snapshot.country_id.id}</td></tr>`;
        if (snapshot.country_id.code) html += `<tr><td style="padding-left: 20px; padding: 4px 8px;">Code:</td><td style="padding: 4px 8px;">${snapshot.country_id.code}</td></tr>`;
        if (snapshot.country_id.title) html += `<tr><td style="padding-left: 20px; padding: 4px 8px;">Title:</td><td style="padding: 4px 8px;">${snapshot.country_id.title}</td></tr>`;
    }
    
    if (snapshot.marketplace && typeof snapshot.marketplace === 'object') {
        html += `<tr style="border-top: 2px solid #ddd; border-bottom: 1px solid #eee;"><td colspan="2" style="padding: 4px 8px; font-weight: 600; color: #333;">Marketplace Details:</td></tr>`;
        if (snapshot.marketplace.id) html += `<tr><td style="padding-left: 20px; padding: 4px 8px;">ID:</td><td style="padding: 4px 8px;">${snapshot.marketplace.id}</td></tr>`;
        if (snapshot.marketplace.name) html += `<tr><td style="padding-left: 20px; padding: 4px 8px;">Name:</td><td style="padding: 4px 8px;">${snapshot.marketplace.name}</td></tr>`;
    }
    
    if (snapshot.currency && typeof snapshot.currency === 'object') {
        html += `<tr style="border-top: 2px solid #ddd; border-bottom: 1px solid #eee;"><td colspan="2" style="padding: 4px 8px; font-weight: 600; color: #333;">Currency Details:</td></tr>`;
        if (snapshot.currency.id) html += `<tr><td style="padding-left: 20px; padding: 4px 8px;">ID:</td><td style="padding: 4px 8px;">${snapshot.currency.id}</td></tr>`;
        if (snapshot.currency.code) html += `<tr><td style="padding-left: 20px; padding: 4px 8px;">Code:</td><td style="padding: 4px 8px;">${snapshot.currency.code}</td></tr>`;
        if (snapshot.currency.sign) html += `<tr><td style="padding-left: 20px; padding: 4px 8px;">Sign:</td><td style="padding: 4px 8px;">${snapshot.currency.sign}</td></tr>`;
    }
    
    html += '</table>';
    html += '</div>';
    
    // Escape HTML for tooltip attribute
    return html.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function show_listing_history(listingId, variationId, marketplaceId, countryId, countryCode) {
    $('#listingHistoryModal').modal('show');
    
    // Build listing info text for modal title
    const listingInfo = `Listing ID: ${listingId} | Variation: ${variationId} | Marketplace: ${marketplaceId}${countryCode ? ' | Country: ' + countryCode : ''}`;
    $('#listingHistoryModalLabel').text(listingInfo);
    
    // Show loading state
    $('#listingHistoryTable').html('<tr><td colspan="7" class="text-center text-muted">Loading history...</td></tr>');
    
    // Load listing history
    $.ajax({
        url: window.ListingConfig.urls.getListingHistory + '/' + listingId,
        type: 'GET',
        dataType: 'json',
        data: {
            variation_id: variationId,
            marketplace_id: marketplaceId,
            country_id: countryId
        },
        success: function(data) {
            // Update modal title with descriptive information in two lines
            if (data.listing) {
                const listing = data.listing;
                const line1 = `Listing ID: ${listing.id} | ${listing.variation_name || 'Variation #' + listing.variation_id}`;
                const line2 = `${listing.marketplace_name || 'Marketplace #' + listing.marketplace_id} | ${listing.country_name || listing.country_code || 'Country #' + listing.country_id}`;
                $('#listingHistoryModalLabel').html(`<div>${line1}</div><div class="small text-muted">${line2}</div>`);
            }
            
            // Store snapshots in a global object for tooltip access
            if (!window.listingSnapshots) {
                window.listingSnapshots = {};
            }
            
            let historyTable = '';
            if (data.history && data.history.length > 0) {
                data.history.forEach(function(item) {
                    // Format date
                    const changedDate = item.changed_at ? new Date(item.changed_at).toLocaleString('en-GB', { 
                        timeZone: 'Europe/London', 
                        hour12: true,
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    }) : '';
                    
                    // Format field name
                    const fieldLabels = {
                        'min_handler': 'Min Handler',
                        'price_handler': 'Price Handler',
                        'buybox': 'BuyBox',
                        'buybox_price': 'BuyBox Price',
                        'min_price': 'Min Price',
                        'price': 'Price'
                    };
                    const fieldLabel = fieldLabels[item.field_name] || item.field_name;
                    
                    // Format values based on field type
                    let oldValue = item.old_value !== null && item.old_value !== '' ? item.old_value : 'N/A';
                    let newValue = item.new_value !== null && item.new_value !== '' ? item.new_value : 'N/A';
                    
                    // Format boolean values
                    if (item.field_name === 'buybox') {
                        oldValue = oldValue === '1' || oldValue === 1 || oldValue === true ? 'Yes' : (oldValue === '0' || oldValue === 0 || oldValue === false ? 'No' : oldValue);
                        newValue = newValue === '1' || newValue === 1 || newValue === true ? 'Yes' : (newValue === '0' || newValue === 0 || newValue === false ? 'No' : newValue);
                    }
                    
                    // Format decimal values
                    if (['min_handler', 'price_handler', 'buybox_price', 'min_price', 'price'].includes(item.field_name)) {
                        if (oldValue !== 'N/A' && !isNaN(oldValue)) {
                            oldValue = parseFloat(oldValue).toFixed(2);
                        }
                        if (newValue !== 'N/A' && !isNaN(newValue)) {
                            newValue = parseFloat(newValue).toFixed(2);
                        }
                    }
                    
                    // Highlight changed values
                    const oldValueClass = oldValue !== newValue ? 'text-danger' : '';
                    const newValueClass = oldValue !== newValue ? 'text-success' : '';
                    
                    // Add snapshot icon if row_snapshot exists
                    let snapshotIcon = '';
                    if (item.row_snapshot && typeof item.row_snapshot === 'object') {
                        const snapshotId = `snapshot_${item.id}`;
                        // Store snapshot in global object
                        window.listingSnapshots[snapshotId] = item.row_snapshot;
                        snapshotIcon = `
                            <span class="snapshot-tooltip-wrapper" style="position: relative; display: inline-block;">
                                <i class="fas fa-info-circle text-primary ms-1 snapshot-icon" 
                                   style="cursor: pointer; font-size: 0.9em;" 
                                   data-snapshot-id="${snapshotId}"
                                   onmouseenter="showSnapshotTooltip(event, '${snapshotId}')"
                                   onmouseleave="hideSnapshotTooltip('${snapshotId}')"></i>
                                <div id="tooltip_${snapshotId}" class="snapshot-tooltip" style="display: none;"></div>
                            </span>`;
                    }
                    
                    historyTable += `
                        <tr>
                            <td>${changedDate}</td>
                            <td><strong>${fieldLabel}</strong></td>
                            <td class="${oldValueClass}">${oldValue}${snapshotIcon}</td>
                            <td class="${newValueClass}">${newValue}</td>
                            <td><span class="badge bg-info">${item.change_type || 'listing'}</span></td>
                            <td>${item.admin_name || item.admin_id || 'System'}</td>
                            <td>${item.change_reason || '-'}</td>
                        </tr>`;
                });
            } else {
                historyTable = '<tr><td colspan="7" class="text-center text-muted">No history found for this listing</td></tr>';
            }
            $('#listingHistoryTable').html(historyTable);
        },
        error: function(xhr) {
            console.error('Error loading listing history:', xhr.responseText);
            $('#listingHistoryTable').html('<tr><td colspan="7" class="text-center text-danger">Error loading history. Please try again later.</td></tr>');
        }
    });
}

/**
 * Load marketplace tables when toggle is opened
 */
$(document).on('show.bs.collapse', '.marketplace-toggle-content', function() {
    const toggleElement = $(this);
    const container = toggleElement.find('.marketplace-tables-container');
    
    // Check if already loaded
    if (container.data('loaded') === true) {
        return;
    }
    
    // Extract variationId and marketplaceId from the toggle ID
    const toggleId = toggleElement.attr('id');
    const matches = toggleId.match(/marketplace_toggle_(\d+)_(\d+)/);
    if (!matches) {
        return;
    }
    
    const variationId = parseInt(matches[1]);
    const marketplaceId = parseInt(matches[2]);
    
    // Show loading state
    container.html('<div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2 text-muted">Loading tables...</p></div>');
    
    // Load listings for this marketplace
    loadMarketplaceTables(variationId, marketplaceId);
});

/**
 * Load marketplace tables
 */
function loadMarketplaceTables(variationId, marketplaceId) {
    const container = $(`#marketplace_toggle_${variationId}_${marketplaceId} .marketplace-tables-container`);
    
    // Get listings for this marketplace
    $.ajax({
        url: window.ListingConfig.urls.getListings + '/' + variationId,
        type: 'GET',
        dataType: 'json',
        data: { marketplace_id: marketplaceId },
        success: function(data) {
            let listingsTable = '';
            let m_min_price = 0;
            let m_price = 0;
            
            // Clear eur_listings for this variation
            window.eur_listings[variationId] = [];
            
            // Calculate min prices from listings with country 73 (EUR)
            const eurListings = data.listings.filter(listing => {
                const country = listing.country_id || (listing.country && countries[listing.country]);
                return country && (country.id === 73 || listing.country === 73);
            });
            
            if (eurListings.length > 0) {
                const minPrices = eurListings.map(l => parseFloat(l.min_price) || 0).filter(p => p > 0);
                const prices = eurListings.map(l => parseFloat(l.price) || 0).filter(p => p > 0);
                m_min_price = minPrices.length > 0 ? Math.min(...minPrices) : 0;
                m_price = prices.length > 0 ? Math.min(...prices) : 0;
            }
            
            // Filter listings by marketplace_id
            const marketplaceListings = data.listings.filter(function(listing) {
                return listing.marketplace_id == marketplaceId;
            });
            
            // Update marketplace count after loading
            const listingCount = marketplaceListings.length;
            const countText = listingCount > 0 ? ` (${listingCount} ${listingCount === 1 ? 'listing' : 'listings'})` : '';
            $(`#marketplace_count_${variationId}_${marketplaceId}`).text(countText);
            
            if (marketplaceListings.length === 0) {
                listingsTable = '<tr><td colspan="8" class="text-center text-muted">No listings found for this marketplace</td></tr>';
            } else {
                marketplaceListings.forEach(function(listing) {
                    let best_price = $(`#best_price_${variationId}_${marketplaceId}`).text().replace('€', '') || 0;
                    let exchange_rates_2 = exchange_rates;
                    let currencies_2 = currencies;
                    let currency_sign_2 = currency_sign;
                    let p_append = '';
                    let pm_append = '';
                    let pm_append_title = '';
                    let classs = '';
                    
                    // Handle currency conversions
                    if (listing.currency_id != 4) {
                        let rates = exchange_rates_2[currencies_2[listing.currency_id]];
                        if (rates) {
                            p_append = 'Fr: ' + currency_sign_2[listing.currency_id] + (parseFloat(m_price) * parseFloat(rates)).toFixed(2);
                            pm_append = 'Fr: ' + currency_sign_2[listing.currency_id] + (parseFloat(m_min_price) * parseFloat(rates)).toFixed(2);
                            pm_append_title = 'Break Even: ' + currency_sign_2[listing.currency_id] + (parseFloat(best_price) * parseFloat(rates)).toFixed(2);
                        }
                    } else {
                        // Add EUR listings to eur_listings array
                        window.eur_listings[variationId] = window.eur_listings[variationId] || [];
                        window.eur_listings[variationId].push(listing);
                    }
                    
                    // Get country data
                    let country = listing.country_id || (listing.country && countries[listing.country]);
                    if (!country) {
                        return; // Skip if no country data
                    }
                    
                    // Get marketplace name
                    let marketplaceName = 'N/A';
                    if (listing.marketplace_id && marketplaces && marketplaces[listing.marketplace_id]) {
                        marketplaceName = marketplaces[listing.marketplace_id].name || 'Marketplace ' + listing.marketplace_id;
                    } else if (listing.marketplace && listing.marketplace.name) {
                        marketplaceName = listing.marketplace.name;
                    }
                    
                    const isEnabled = listing.is_enabled !== undefined && listing.is_enabled == 1;
                    const disabledClass = !isEnabled ? 'listing-disabled' : '';
                    const flagsPath = window.ListingConfig.flagsPath || '';
                    listingsTable += `
                        <tr class="${classs} ${disabledClass}" ${listing.buybox !== 1 ? 'style="background: pink;"' : ''} data-listing-id="${listing.id}" data-enabled="${isEnabled ? '1' : '0'}">
                            <td title="${listing.id} ${country.title || ''}">
                                <a href="https://www.backmarket.${country.market_url}/${country.market_code}/p/gb/${listing.reference_uuid_2 || listing._2 || ''}" target="_blank">
                                    <img src="${flagsPath}/${country.code.toLowerCase()}.svg" height="15">
                                    ${country.code}
                                </a>
                                ${marketplaceName}
                            </td>
                            <td>
                                <form class="form-inline" method="POST" id="change_limit_${listing.id}">
                                    <input type="hidden" name="_token" value="${window.ListingConfig.csrfToken}">
                                    <input type="submit" hidden>
                                </form>
                                <input type="number" class="form-control ${listing.handler_status == 2 ? 'text-danger':''}" id="min_price_limit_${listing.id}" name="min_price_limit" step="0.01" value="${listing.min_price_limit || ''}" form="change_limit_${listing.id}" ${!isEnabled ? 'disabled' : ''}>
                            </td>
                            <td>
                                <input type="number" class="form-control ${listing.handler_status == 2 ? 'text-danger':''}" id="price_limit_${listing.id}" name="price_limit" step="0.01" value="${listing.price_limit || ''}" form="change_limit_${listing.id}" ${!isEnabled ? 'disabled' : ''}>
                            </td>
                            <td>${listing.buybox_price || ''}
                                <span class="text-danger" title="Buybox Winner Price">
                                    ${listing.buybox !== 1 ? '(' + (listing.buybox_winner_price || '') + ')' : ''}
                                </span>
                            </td>
                            <td>
                                <form class="form-inline" method="POST" id="change_min_price_${listing.id}">
                                    <input type="hidden" name="_token" value="${window.ListingConfig.csrfToken}">
                                    <input type="submit" hidden>
                                </form>
                                <form class="form-inline" method="POST" id="change_price_${listing.id}">
                                    <input type="hidden" name="_token" value="${window.ListingConfig.csrfToken}">
                                    <input type="submit" hidden>
                                </form>
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="min_price_${listing.id}" name="min_price" step="0.01" value="${listing.min_price || ''}" form="change_min_price_${listing.id}" ${!isEnabled ? 'disabled' : ''}>
                                    <label for="">Min Price</label>
                                </div>
                                <span id="pm_append_${listing.id}" title="${pm_append_title}">
                                    ${pm_append}
                                </span>
                            </td>
                            <td>
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="price_${listing.id}" name="price" step="0.01" value="${listing.price || ''}" form="change_price_${listing.id}" ${!isEnabled ? 'disabled' : ''}>
                                    <label for="">Price</label>
                                </div>
                                ${p_append}
                            </td>
                            <td>${listing.updated_at ? new Date(listing.updated_at).toLocaleString('en-GB', { timeZone: 'Europe/London', hour12: true }) : ''}
                                ${listing.buybox !== 1 && listing.buybox_price > 0 ? (() => {
                                    const buttonClass = (best_price > 0 && best_price < listing.buybox_price) ? 'btn btn-success btn-sm' : 'btn btn-warning btn-sm';
                                    return `<button class="${buttonClass}" id="get_buybox_${listing.id}" onclick="getBuybox(${listing.id}, ${variationId}, ${listing.buybox_price})" style="margin-left: 5px;">
                                                Get Buybox
                                            </button>`;
                                })() : ''}
                            </td>
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <div class="form-check form-switch d-inline-block">
                                        <input 
                                            class="form-check-input toggle-listing-enable" 
                                            type="checkbox" 
                                            role="switch"
                                            id="toggle_listing_${listing.id}"
                                            data-listing-id="${listing.id}"
                                            ${listing.is_enabled !== undefined && listing.is_enabled == 1 ? 'checked' : ''}
                                            style="cursor: pointer;">
                                        <label class="form-check-label" for="toggle_listing_${listing.id}"></label>
                                    </div>
                                    <a href="javascript:void(0)" 
                                       class="btn btn-link btn-sm p-0" 
                                       id="listing_history_${listing.id}" 
                                       onclick="show_listing_history(${listing.id}, ${variationId}, ${marketplaceId}, ${listing.country || (country && country.id) || 'null'}, '${country ? country.code : ''}')" 
                                       data-bs-toggle="modal" 
                                       data-bs-target="#listingHistoryModal"
                                       title="View listing history">
                                        <i class="fas fa-history"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>`;
                });
            }
            
            // Render listings table first
            renderMarketplaceTables(variationId, marketplaceId, listingsTable);
            
            // Load stocks only to calculate best_price (for marketplace 1 only, stocks are common)
            if (marketplaceId === 1) {
                loadStocksForBestPrice(variationId, marketplaceId);
            } else {
                // For other marketplaces, set best_price to empty or calculate from listings
                $(`#best_price_${variationId}_${marketplaceId}`).text('0.00');
            }
        },
        error: function(xhr) {
            console.error(xhr.responseText);
            container.html('<div class="text-center p-4 text-danger">Error loading listings</div>');
        }
    });
}

/**
 * Load stocks only to calculate best_price (stocks table removed from marketplace bar)
 */
function loadStocksForBestPrice(variationId, marketplaceId) {
    $.ajax({
        url: window.ListingConfig.urls.getVariationStocks + '/' + variationId,
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            let stockPrices = []; // Array to collect prices for average calculation
            
            data.stocks.forEach(function(item) {
                let price = data.stock_costs[item.id] || 0;
                // Collect price for average calculation
                if (price) {
                    stockPrices.push(parseFloat(price));
                }
            });
            
            // Calculate and display best_price only (average_cost removed from marketplace bar)
            updateBestPrice(variationId, marketplaceId, stockPrices);
        },
        error: function() {
            // Set default best_price on error
            $(`#best_price_${variationId}_${marketplaceId}`).text('0.00');
        }
    });
}

/**
 * Calculate and display best price only (average_cost removed from marketplace bar)
 */
function updateBestPrice(variationId, marketplaceId, prices) {
    const bestPriceElement = $(`#best_price_${variationId}_${marketplaceId}`);
    
    if (bestPriceElement.length === 0) {
        return; // Element doesn't exist yet
    }
    
    if (prices.length > 0) {
        let average = prices.reduce((a, b) => parseFloat(a) + parseFloat(b), 0) / prices.length;
        // Calculate best_price: (average_cost + 20) / 0.88 (same formula as original)
        let bestPrice = ((parseFloat(average) + 20) / 0.88).toFixed(2);
        bestPriceElement.text(bestPrice);
    } else {
        bestPriceElement.text('0.00');
    }
}

/**
 * Render marketplace tables (only listings, stocks removed)
 */
function renderMarketplaceTables(variationId, marketplaceId, listingsTable) {
    const container = $(`#marketplace_toggle_${variationId}_${marketplaceId} .marketplace-tables-container`);
    
    if (listingsTable === '') {
        listingsTable = '<tr><td colspan="8" class="text-center text-muted">No listings for this marketplace</td></tr>';
    }
    
    const tablesHtml = `
        <div class="table-responsive">
            <table class="table table-hover mb-0 text-md-nowrap">
                <thead>
                    <tr>
                        <th width="80"><small><b>Country</b></small></th>
                        <th width="100" title="Minimum Price Handler"><small><b>Min Hndlr</b></small></th>
                        <th width="100" title="Price Handler"><small><b>Price Hndlr</b></small></th>
                        <th width="80"><small><b>BuyBox</b></small></th>
                        <th title="Min Price" width="120"><small><b>Min </b>(€<b id="best_price_${variationId}_${marketplaceId}"></b>)</small></th>
                        <th width="120"><small><b>Price</b></small></th>
                        <th><small><b>Date</b></small></th>
                        <th width="80" class="text-center"><small><b>Action</b></small></th>
                    </tr>
                </thead>
                <tbody id="listings_${variationId}_${marketplaceId}">
                    ${listingsTable}
                </tbody>
            </table>
        </div>
    `;
    
    container.html(tablesHtml);
    container.data('loaded', true);
    
    // Store original values for all inputs in the newly rendered table
    // This happens immediately after the table is inserted into the DOM
    // All four main inputs: min_price_limit, price_limit, min_price, price
    // Note: buybox_price is not user-editable, it's updated programmatically
    setTimeout(function() {
        const selector = `#listings_${variationId}_${marketplaceId} [id^="min_price_limit_"], #listings_${variationId}_${marketplaceId} [id^="price_limit_"], #listings_${variationId}_${marketplaceId} [id^="min_price_"], #listings_${variationId}_${marketplaceId} [id^="price_"], #listings_${variationId}_${marketplaceId} .toggle-listing-enable`;
        const elements = $(selector);
        console.log(`Found ${elements.length} elements to store for ${variationId}_${marketplaceId}`);
        
        elements.each(function() {
            if ($(this).is(':checkbox')) {
                // For checkboxes, store the checked state
                const id = $(this).attr('id');
                if (id) {
                    window.ChangeDetection.originalValues[id] = {
                        value: $(this).is(':checked') ? '1' : '0',
                        fieldName: 'BuyBox Status',
                        listingId: $(this).data('listing-id')
                    };
                    console.log('Stored checkbox value for:', id);
                }
            } else {
                // For input fields (all four inputs + buybox_price)
                window.ChangeDetection.storeOriginalValue(this);
            }
        });
        console.log(`Stored original values for ${variationId}_${marketplaceId} table. Total stored:`, Object.keys(window.ChangeDetection.originalValues).length);
    }, 200);
}

/**
 * Handle parent total stock form input changes
 */
// Total stock form functionality moved to total-stock-form.js

/**
 * Handle marketplace stock form submission
 */
$(document).on('submit', '[id^="add_qty_marketplace_"]', function(e) {
    e.preventDefault();
    
    const formId = $(this).attr('id');
    const matches = formId.match(/add_qty_marketplace_(\d+)_(\d+)/);
    if (!matches) return;
    
    const variationId = matches[1];
    const marketplaceId = matches[2];
    const form = $(this);
    const actionUrl = form.attr('action');
    const quantity = $('#add_marketplace_' + variationId + '_' + marketplaceId).val();
    
    // Disable submission
    $('#send_marketplace_' + variationId + '_' + marketplaceId).addClass('d-none');
    $('#send_marketplace_' + variationId + '_' + marketplaceId).prop('disabled', true);
    $('#success_marketplace_' + variationId + '_' + marketplaceId).text('');
    
    $.ajax({
        type: "POST",
        url: actionUrl,
        data: form.serialize(),
        dataType: 'json',
        success: function(response) {
            // Handle both old format (number) and new format (object with marketplace_stock and total_stock)
            let marketplaceStock, totalStock;
            if (typeof response === 'object' && response.marketplace_stock !== undefined) {
                marketplaceStock = response.marketplace_stock;
                totalStock = response.total_stock;
            } else {
                // Fallback for old format
                marketplaceStock = response;
                totalStock = response;
            }
            
            // Update the marketplace stock display
            $('#quantity_marketplace_' + variationId + '_' + marketplaceId).val(marketplaceStock);
            $('#success_marketplace_' + variationId + '_' + marketplaceId).text("Quantity changed by " + quantity + " to " + marketplaceStock);
            $('#add_marketplace_' + variationId + '_' + marketplaceId).val('');
            
            // Update the parent total stock display
            $('#total_stock_' + variationId).val(totalStock);
        },
        error: function(jqXHR, textStatus, errorThrown) {
            let errorMsg = "Error: " + textStatus;
            if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
                errorMsg = jqXHR.responseJSON.error;
            } else if (jqXHR.responseText) {
                errorMsg = jqXHR.responseText;
            }
            alert(errorMsg);
            $('#send_marketplace_' + variationId + '_' + marketplaceId).prop('disabled', false);
        }
    });
});

/**
 * Handle Enter key on marketplace stock input
 */
$(document).on('keypress', '[id^="add_marketplace_"]', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        const inputId = $(this).attr('id');
        const matches = inputId.match(/add_marketplace_(\d+)_(\d+)/);
        if (!matches) return;
        
        const variationId = matches[1];
        const marketplaceId = matches[2];
        $('#add_qty_marketplace_' + variationId + '_' + marketplaceId).submit();
    }
});

/**
 * Handle listing enable/disable toggle
 */
$(document).on('change', '.toggle-listing-enable', function() {
    const toggle = $(this);
    const listingId = toggle.data('listing-id');
    const isEnabled = toggle.is(':checked') ? 1 : 0;
    
    // Disable toggle during API call
    toggle.prop('disabled', true);
    
    $.ajax({
        type: "POST",
        url: window.ListingConfig.urls.toggleEnable + '/' + listingId,
        data: {
            _token: window.ListingConfig.csrfToken,
            is_enabled: isEnabled
        },
        dataType: 'json',
        success: function(response) {
            // Update the toggle state based on response
            toggle.prop('checked', response.is_enabled == 1);
            toggle.prop('disabled', false);
            
            // Find the row and update its disabled state
            const row = toggle.closest('tr');
            const isEnabled = response.is_enabled == 1;
            
            // Update row classes and data attribute
            if (isEnabled) {
                row.removeClass('listing-disabled');
                row.attr('data-enabled', '1');
            } else {
                row.addClass('listing-disabled');
                row.attr('data-enabled', '0');
            }
            
            // Enable/disable all inputs in the row (except the toggle)
            row.find('input[type="number"]').prop('disabled', !isEnabled);
            row.find('input[type="text"]').prop('disabled', !isEnabled);
        },
        error: function(jqXHR, textStatus, errorThrown) {
            // Revert toggle state on error
            toggle.prop('checked', !isEnabled);
            toggle.prop('disabled', false);
            
            // Revert row state on error
            const row = toggle.closest('tr');
            const wasEnabled = !isEnabled; // Revert to previous state
            
            if (wasEnabled) {
                row.removeClass('listing-disabled');
                row.attr('data-enabled', '1');
                row.find('input[type="number"]').prop('disabled', false);
                row.find('input[type="text"]').prop('disabled', false);
            } else {
                row.addClass('listing-disabled');
                row.attr('data-enabled', '0');
                row.find('input[type="number"]').prop('disabled', true);
                row.find('input[type="text"]').prop('disabled', true);
            }
            
            let errorMsg = "Error updating listing status: " + textStatus;
            if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
                errorMsg = jqXHR.responseJSON.error;
            } else if (jqXHR.responseText) {
                errorMsg = jqXHR.responseText;
            }
            alert(errorMsg);
        }
    });
});

/**
 * Initialize marketplace visibility on page load
 */
$(document).ready(function() {
    // Apply initial state to all marketplace bars
    if (window.globalMarketplaceState) {
        for (let marketplaceId in window.globalMarketplaceState) {
            const isVisible = window.globalMarketplaceState[marketplaceId];
            $('[id^="marketplace_bar_"]').each(function() {
                const id = $(this).attr('id');
                // Match pattern: marketplace_bar_VARIATIONID_MARKETPLACEID
                const matches = id.match(/marketplace_bar_(\d+)_(\d+)/);
                if (matches && parseInt(matches[2]) === parseInt(marketplaceId)) {
                    if (isVisible) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                }
            });
        }
    }
});

/**
 * Global marketplace toggle function
 */
window.toggleGlobalMarketplace = function(marketplaceId, badgeElement) {
    // Initialize global state if not exists
    if (!window.globalMarketplaceState) {
        window.globalMarketplaceState = {};
    }
    
    // Get current state (default to true if not set, based on initial state)
    const badge = $(badgeElement);
    const initialState = badge.data('initial-state');
    const defaultIsActive = initialState === 'active';
    const currentState = window.globalMarketplaceState.hasOwnProperty(marketplaceId) 
        ? window.globalMarketplaceState[marketplaceId] 
        : defaultIsActive;
    
    // Toggle global state
    window.globalMarketplaceState[marketplaceId] = !currentState;
    const isVisible = window.globalMarketplaceState[marketplaceId];
    
    // Save state to localStorage
    saveMarketplaceState();
    
    // Toggle all marketplace bars with this marketplace ID across all variations
    $('[id^="marketplace_bar_"]').each(function() {
        const id = $(this).attr('id');
        // Match pattern: marketplace_bar_VARIATIONID_MARKETPLACEID
        const matches = id.match(/marketplace_bar_(\d+)_(\d+)/);
        if (matches && parseInt(matches[2]) === marketplaceId) {
            if (isVisible) {
                $(this).show();
            } else {
                $(this).hide();
            }
        }
    });
    
    // Update badge appearance
    updateBadgeAppearance(badgeElement, isVisible);
}

/**
 * Save marketplace state to localStorage
 */
function saveMarketplaceState() {
    if (window.globalMarketplaceState) {
        localStorage.setItem('globalMarketplaceState', JSON.stringify(window.globalMarketplaceState));
    }
}

/**
 * Load marketplace state from localStorage
 */
function loadMarketplaceState() {
    try {
        const savedState = localStorage.getItem('globalMarketplaceState');
        if (savedState) {
            window.globalMarketplaceState = JSON.parse(savedState);
            return true;
        }
    } catch (e) {
        console.warn('Failed to load marketplace state from localStorage:', e);
    }
    return false;
}

/**
 * Clear marketplace state and reset to defaults
 */
window.clearMarketplaceState = function() {
    // Clear localStorage
    localStorage.removeItem('globalMarketplaceState');
    
    // Reset global state
    window.globalMarketplaceState = {};
    
    // Reset all badges to their initial state
    $('.global-marketplace-toggle-badge').each(function() {
        const badge = $(this);
        const marketplaceId = parseInt(badge.data('marketplace-id'));
        const initialState = badge.data('initial-state');
        const isActive = initialState === 'active';
        
        // Update state
        window.globalMarketplaceState[marketplaceId] = isActive;
        
        // Update badge appearance
        updateBadgeAppearance(badge[0], isActive);
        
        // Show/hide marketplace bars
        $('[id^="marketplace_bar_"]').each(function() {
            const id = $(this).attr('id');
            const matches = id.match(/marketplace_bar_(\d+)_(\d+)/);
            if (matches && parseInt(matches[2]) === marketplaceId) {
                if (isActive) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            }
        });
    });
    
    // Save the reset state
    saveMarketplaceState();
}

/**
 * Update badge appearance based on state
 */
function updateBadgeAppearance(badgeElement, isVisible) {
    if (isVisible) {
        $(badgeElement).css({
            'border-color': '#28a745',
            'color': '#28a745'
        });
        $(badgeElement).removeClass('badge-inactive').addClass('badge-active');
    } else {
        $(badgeElement).css({
            'border-color': '#000',
            'color': '#000'
        });
        $(badgeElement).removeClass('badge-active').addClass('badge-inactive');
    }
}

/**
 * Restore marketplace state on page load
 */
function restoreMarketplaceState() {
    // Initialize global state if not exists
    if (!window.globalMarketplaceState) {
        window.globalMarketplaceState = {};
    }
    
    // Load saved state
    const hasSavedState = loadMarketplaceState();
    
    // Apply state to badges and marketplace bars
    $('.global-marketplace-toggle-badge').each(function() {
        const badge = $(this);
        const marketplaceId = parseInt(badge.data('marketplace-id'));
        const initialState = badge.data('initial-state');
        const defaultIsActive = initialState === 'active';
        
        // Use saved state if exists, otherwise use initial state
        let isVisible;
        if (hasSavedState && window.globalMarketplaceState.hasOwnProperty(marketplaceId)) {
            // Use saved state value
            isVisible = window.globalMarketplaceState[marketplaceId] === true;
        } else {
            // Use initial state for first time
            isVisible = defaultIsActive;
            window.globalMarketplaceState[marketplaceId] = isVisible;
        }
        
        // Update badge appearance
        updateBadgeAppearance(badge[0], isVisible);
        
        // Show/hide marketplace bars
        $('[id^="marketplace_bar_"]').each(function() {
            const id = $(this).attr('id');
            const matches = id.match(/marketplace_bar_(\d+)_(\d+)/);
            if (matches && parseInt(matches[2]) === marketplaceId) {
                if (isVisible) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            }
        });
    });
    
    // Save state (in case we initialized defaults)
    if (!hasSavedState) {
        saveMarketplaceState();
    }
}

// Restore state when DOM is ready
$(document).ready(function() {
    restoreMarketplaceState();
    initializeChangeDetection();
    
    // Load sales data for all variations on page
    document.querySelectorAll('[id^="sales_"]').forEach(function(element) {
        const variationId = element.id.replace('sales_', '');
        if (variationId) {
            loadSalesData(parseInt(variationId));
        }
    });
});

/**
 * Change Detection System
 * Global object to track original values and detect changes
 */
window.ChangeDetection = window.ChangeDetection || {
    originalValues: {},
    
    // Function to store original value
    storeOriginalValue: function(element) {
        const id = $(element).attr('id');
        if (id) {
            const value = $(element).val() || '';
            const type = $(element).attr('type');
            
            // Store original value (update if already exists to ensure we have the latest)
            this.originalValues[id] = {
                value: value,
                type: type || 'text',
                fieldName: this.getFieldName(id),
                listingId: this.getListingId(id),
                variationId: this.getVariationId(id),
                marketplaceId: this.getMarketplaceId(id)
            };
            
            console.log('Stored original value for', id, ':', value);
        }
    },
    
    // Function to get field name from ID
    getFieldName: function(id) {
        if (id.includes('min_price_limit_')) return 'Min Price Handler';
        if (id.includes('price_limit_')) return 'Price Handler';
        if (id.includes('min_price_') && !id.includes('limit') && !id.includes('all_')) return 'Min Price';
        if (id.includes('price_') && !id.includes('limit') && !id.includes('all_') && !id.includes('best_')) return 'Price';
        if (id.includes('all_min_handler_')) return 'Marketplace Min Handler';
        if (id.includes('all_handler_') && !id.includes('min_')) return 'Marketplace Price Handler';
        if (id.includes('all_min_price_')) return 'Marketplace Min Price';
        if (id.includes('all_price_') && !id.includes('min_')) return 'Marketplace Price';
        if (id.includes('toggle_listing_')) return 'BuyBox Status';
        return 'Unknown Field';
    },
    
    // Function to extract listing ID from element ID
    getListingId: function(id) {
        const match = id.match(/(\d+)$/);
        return match ? match[1] : null;
    },
    
    // Function to extract variation ID from element ID
    getVariationId: function(id) {
        const match = id.match(/_(\d+)_(\d+)/);
        return match ? match[1] : null;
    },
    
    // Function to extract marketplace ID from element ID
    getMarketplaceId: function(id) {
        const match = id.match(/_(\d+)_(\d+)/);
        return match ? match[2] : null;
    },
    
    // Track last alert to prevent duplicates
    lastAlertTime: {},
    
    // Function to detect and show change alert
    detectChange: function(element) {
        const id = $(element).attr('id');
        console.log('detectChange called for:', id);
        
        if (!id) {
            console.log('No ID found');
            return;
        }
        
        if (!this.originalValues[id]) {
            console.log('Original value not stored for:', id, '- storing now');
            // Store it now if not already stored
            this.storeOriginalValue(element);
            return;
        }
        
        const currentValue = $(element).val() || '';
        const originalValue = this.originalValues[id].value || '';
        const fieldName = this.originalValues[id].fieldName;
        
        console.log('Comparing values - Original:', originalValue, 'Current:', currentValue);
        
        // Check if value actually changed (compare as strings)
        if (String(currentValue) !== String(originalValue)) {
            // Prevent duplicate alerts within 2 seconds for the same element
            const now = Date.now();
            const lastAlert = this.lastAlertTime[id];
            if (lastAlert && (now - lastAlert) < 2000) {
                console.log('Skipping duplicate alert for', id, '- last alert was', (now - lastAlert), 'ms ago');
                return;
            }
            
            const changeInfo = {
                field: fieldName,
                listingId: this.originalValues[id].listingId,
                variationId: this.originalValues[id].variationId,
                marketplaceId: this.originalValues[id].marketplaceId,
                oldValue: originalValue || '(empty)',
                newValue: currentValue || '(empty)',
                elementId: id
            };
            
            console.log('Change detected!', changeInfo);
            
            // Record the alert time
            this.lastAlertTime[id] = now;
            
            // Record change to database instead of showing alert
            this.recordChange(changeInfo);
        } else {
            console.log('No change detected - values are the same');
        }
    },
    
    // Function to record change to database via API
    recordChange: function(changeInfo) {
        // Map field names from display names to database field names
        // Note: min_price_limit maps to min_handler, price_limit maps to price_handler
        // Note: buybox_price is not user-editable, it's updated programmatically
        const fieldNameMapping = {
            'Min Price Handler': 'min_handler',  // min_price_limit -> min_handler
            'Price Handler': 'price_handler',     // price_limit -> price_handler
            'Min Price': 'min_price',
            'Price': 'price',
            'BuyBox Status': 'buybox'
        };
        
        // Also check element ID for field name mapping
        let dbFieldName = fieldNameMapping[changeInfo.field];
        if (!dbFieldName && changeInfo.elementId) {
            if (changeInfo.elementId.includes('min_price_limit_')) {
                dbFieldName = 'min_handler';
            } else if (changeInfo.elementId.includes('price_limit_')) {
                dbFieldName = 'price_handler';
            } else if (changeInfo.elementId.includes('min_price_') && !changeInfo.elementId.includes('limit')) {
                dbFieldName = 'min_price';
            } else if (changeInfo.elementId.includes('price_') && !changeInfo.elementId.includes('limit')) {
                dbFieldName = 'price';
            } else if (changeInfo.elementId.includes('toggle_listing_')) {
                dbFieldName = 'buybox';
            }
        }
        
        if (!dbFieldName) {
            dbFieldName = changeInfo.field.toLowerCase().replace(/\s+/g, '_');
        }
        
        // Get listing ID from element ID or changeInfo
        let listingId = changeInfo.listingId;
        if (!listingId && changeInfo.elementId) {
            // Extract listing ID from element ID (e.g., min_price_limit_727 -> 727)
            const match = changeInfo.elementId.match(/(\d+)$/);
            if (match) {
                listingId = match[1];
            }
        }
        
        if (!listingId) {
            console.error('Cannot record change: Listing ID not found', changeInfo);
            return;
        }
        
        // Convert values for buybox field (Enabled/Disabled -> 1/0)
        let oldValue = changeInfo.oldValue === '(empty)' ? null : changeInfo.oldValue;
        let newValue = changeInfo.newValue === '(empty)' ? null : changeInfo.newValue;
        
        if (dbFieldName === 'buybox') {
            oldValue = changeInfo.oldValue === 'Enabled' ? '1' : (changeInfo.oldValue === 'Disabled' ? '0' : changeInfo.oldValue);
            newValue = changeInfo.newValue === 'Enabled' ? '1' : (changeInfo.newValue === 'Disabled' ? '0' : changeInfo.newValue);
        }
        
        // Prepare API request data
        const requestData = {
            listing_id: listingId,
            field_name: dbFieldName,
            old_value: oldValue,
            new_value: newValue,
            change_reason: 'User edit from listing page',
            _token: window.ListingConfig.csrfToken
        };
        
        console.log('Recording change to database:', requestData);
        
        // Call API to record change
        $.ajax({
            url: window.ListingConfig.urls.recordChange || '/v2/listings/record_change',
            type: 'POST',
            data: requestData,
            dataType: 'json',
            success: function(response) {
                console.log('Change recorded successfully:', response);
            },
            error: function(xhr) {
                console.error('Error recording change:', xhr.responseText);
            }
        });
    },
    
    // Store all original values for a specific container
    storeAllOriginalValues: function(containerSelector) {
        const selector = containerSelector || '[id^="min_price_limit_"], [id^="price_limit_"], [id^="min_price_"], [id^="price_"], [id^="all_min_handler_"], [id^="all_handler_"], [id^="all_min_price_"], [id^="all_price_"]';
        $(selector).each(function() {
            window.ChangeDetection.storeOriginalValue(this);
        });
    }
};

/**
 * Initialize change detection for all editable fields
 * Stores original values and tracks changes
 */
function initializeChangeDetection() {
    
    // Store original values on focus (before user changes)
    // All four main inputs: min_price_limit, price_limit, min_price, price
    // Marketplace-level inputs: all_min_handler, all_handler, all_min_price, all_price
    // Note: buybox_price is not user-editable, it's updated programmatically
    $(document).on('focus', '[id^="min_price_limit_"], [id^="price_limit_"], [id^="min_price_"], [id^="price_"], [id^="all_min_handler_"], [id^="all_handler_"], [id^="all_min_price_"], [id^="all_price_"]', function() {
        window.ChangeDetection.storeOriginalValue(this);
    });
    
    // Detect changes on blur event (when user finishes editing and tabs out)
    // All four main inputs: min_price_limit, price_limit, min_price, price
    // Marketplace-level inputs: all_min_handler, all_handler, all_min_price, all_price
    // Note: buybox_price is not user-editable, it's updated programmatically
    // Using only blur to avoid duplicate alerts from both change and blur events
    $(document).on('blur', '[id^="min_price_limit_"], [id^="price_limit_"], [id^="min_price_"], [id^="price_"], [id^="all_min_handler_"], [id^="all_handler_"], [id^="all_min_price_"], [id^="all_price_"]', function(e) {
        const elementId = $(this).attr('id');
        console.log('Blur event fired for:', elementId);
        
        // For marketplace-level inputs, we don't track individual changes
        // They are tracked when the form is submitted (bulk update)
        if (!elementId.includes('all_')) {
            window.ChangeDetection.detectChange(this);
        }
    });
    
    // Handle checkbox/toggle changes
    $(document).on('change', '.toggle-listing-enable', function() {
        const id = $(this).attr('id');
        const listingId = $(this).data('listing-id');
        const isChecked = $(this).is(':checked');
        
        // Store original value if not already stored
        if (!window.ChangeDetection.originalValues[id]) {
            window.ChangeDetection.originalValues[id] = {
                value: isChecked ? '1' : '0',
                fieldName: 'BuyBox Status',
                listingId: listingId
            };
        }
        
        const originalValue = window.ChangeDetection.originalValues[id].value;
        const oldValue = originalValue === '1' ? 'Enabled' : 'Disabled';
        const newValue = isChecked ? 'Enabled' : 'Disabled';
        
        if (oldValue !== newValue) {
            const changeInfo = {
                field: 'BuyBox Status',
                listingId: listingId,
                oldValue: oldValue,
                newValue: newValue,
                elementId: id
            };
            
            window.ChangeDetection.recordChange(changeInfo);
        }
    });
}

/**
 * Handle listing price form submissions (min_price and price)
 * Note: buybox_price is not user-editable, it's updated programmatically
 */
$(document).on('submit', '[id^="change_min_price_"], [id^="change_price_"]', function(e) {
    e.preventDefault();
    
    const formId = $(this).attr('id');
    const matches = formId.match(/change_(min_)?price_(\d+)/);
    if (!matches) return;
    
    const listingId = matches[2];
    const isMinPrice = matches[1] === 'min_';
    const form = $(this);
    const input = isMinPrice ? $(`#min_price_${listingId}`) : $(`#price_${listingId}`);
    const value = parseFloat(input.val());
    
    // Don't submit if value is empty or invalid
    if (!value || isNaN(value)) {
        return;
    }
    
    // Show loading state
    input.prop('disabled', true);
    
    const url = window.ListingConfig.urls.updatePrice || `/v2/listings/update_price/${listingId}`;
    const data = {
        _token: window.ListingConfig.csrfToken,
    };
    
    if (isMinPrice) {
        data.min_price = value;
    } else {
        data.price = value;
    }
    
    $.ajax({
        type: "POST",
        url: url,
        data: data,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Show success feedback
                const row = input.closest('tr');
                row.addClass('table-success');
                setTimeout(function() {
                    row.removeClass('table-success');
                }, 2000);
            }
            input.prop('disabled', false);
        },
        error: function(jqXHR) {
            let errorMsg = "Error updating price";
            if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
                errorMsg = jqXHR.responseJSON.error;
            }
            alert(errorMsg);
            input.prop('disabled', false);
        }
    });
});

/**
 * Handle listing limit form submission (min_price_limit and price_limit - handlers)
 */
$(document).on('submit', '[id^="change_limit_"]', function(e) {
    e.preventDefault();
    
    const formId = $(this).attr('id');
    const matches = formId.match(/change_limit_(\d+)/);
    if (!matches) return;
    
    const listingId = matches[1];
    const form = $(this);
    const minLimitInput = $(`#min_price_limit_${listingId}`);
    const priceLimitInput = $(`#price_limit_${listingId}`);
    
    const minLimit = minLimitInput.val() ? parseFloat(minLimitInput.val()) : null;
    const priceLimit = priceLimitInput.val() ? parseFloat(priceLimitInput.val()) : null;
    
    // Show loading state
    minLimitInput.prop('disabled', true);
    priceLimitInput.prop('disabled', true);
    
    const url = window.ListingConfig.urls.updateLimit || `/v2/listings/update_limit/${listingId}`;
    const data = {
        _token: window.ListingConfig.csrfToken,
        min_price_limit: minLimit,
        price_limit: priceLimit
    };
    
    $.ajax({
        type: "POST",
        url: url,
        data: data,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Show success feedback
                const row = minLimitInput.closest('tr');
                row.addClass('table-success');
                setTimeout(function() {
                    row.removeClass('table-success');
                }, 2000);
            }
            minLimitInput.prop('disabled', false);
            priceLimitInput.prop('disabled', false);
        },
        error: function(jqXHR) {
            let errorMsg = "Error updating limits";
            if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
                errorMsg = jqXHR.responseJSON.error;
            }
            alert(errorMsg);
            minLimitInput.prop('disabled', false);
            priceLimitInput.prop('disabled', false);
        }
    });
});

/**
 * Handle Enter key on listing input fields
 * All four main inputs: min_price_limit, price_limit, min_price, price
 * Note: buybox_price is not user-editable, it's updated programmatically
 */
$(document).on('keypress', '[id^="min_price_"], [id^="price_"], [id^="min_price_limit_"], [id^="price_limit_"]', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        const inputId = $(this).attr('id');
        
        // Determine which form to submit
        if (inputId.startsWith('min_price_limit_') || inputId.startsWith('price_limit_')) {
            const listingId = inputId.replace(/^(min_price_limit_|price_limit_)/, '');
            $(`#change_limit_${listingId}`).submit();
        } else if (inputId.startsWith('min_price_') && !inputId.includes('limit') && !inputId.includes('all_')) {
            const listingId = inputId.replace('min_price_', '');
            $(`#change_min_price_${listingId}`).submit();
        } else if (inputId.startsWith('price_') && !inputId.includes('limit') && !inputId.includes('all_') && !inputId.includes('best_')) {
            const listingId = inputId.replace('price_', '');
            $(`#change_price_${listingId}`).submit();
        }
    }
});

/**
 * Handle Enter key on marketplace-level inputs (bulk update)
 */
$(document).on('keypress', '[id^="all_min_handler_"], [id^="all_handler_"], [id^="all_min_price_"], [id^="all_price_"]', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        const inputId = $(this).attr('id');
        const matches = inputId.match(/(\d+)_(\d+)$/);
        if (!matches) return;
        
        const variationId = matches[1];
        const marketplaceId = matches[2];
        
        if (inputId.startsWith('all_min_handler_') || inputId.startsWith('all_handler_')) {
            // Submit handler form
            const button = $(`#change_all_handler_${variationId}_${marketplaceId} button[type="button"]`);
            if (button.length) {
                button.click();
            }
        } else if (inputId.startsWith('all_min_price_') || inputId.startsWith('all_price_')) {
            // Submit price form
            const button = $(`#change_all_price_${variationId}_${marketplaceId} button[type="button"]`);
            if (button.length) {
                button.click();
            }
        }
    }
});

/**
 * Handle marketplace-level handler form submission (bulk update)
 */
$(document).on('click', '[id^="change_all_handler_"] button[type="button"]', function(e) {
    e.preventDefault();
    
    const form = $(this).closest('form');
    const formId = form.attr('id');
    const matches = formId.match(/change_all_handler_(\d+)_(\d+)/);
    if (!matches) return;
    
    const variationId = matches[1];
    const marketplaceId = matches[2];
    const minHandler = parseFloat($(`#all_min_handler_${variationId}_${marketplaceId}`).val());
    const priceHandler = parseFloat($(`#all_handler_${variationId}_${marketplaceId}`).val());
    
    // Validate at least one value provided
    if (isNaN(minHandler) && isNaN(priceHandler)) {
        alert('Please enter at least one handler value');
        return;
    }
    
    const button = $(this);
    const originalText = button.html();
    button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    
    // Build URL with required parameters
    const baseUrl = window.ListingConfig.urls.updateMarketplaceHandlers || '/v2/listings/update_marketplace_handlers';
    const url = `${baseUrl}/${variationId}/${marketplaceId}`;
    
    console.log('Submitting to URL:', url);
    console.log('Variation ID:', variationId, 'Marketplace ID:', marketplaceId);
    
    const data = {
        _token: window.ListingConfig.csrfToken,
    };
    
    if (!isNaN(minHandler)) {
        data.all_min_handler = minHandler;
    }
    if (!isNaN(priceHandler)) {
        data.all_handler = priceHandler;
    }
    
    console.log('Request data:', data);
    
    $.ajax({
        type: "POST",
        url: url,
        data: data,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Reload the listings table to show updated values
                loadMarketplaceTables(variationId, marketplaceId);
                
                // Log success (no alert to avoid disturbance)
                console.log('Handlers updated successfully:', response.message || `Updated ${response.updated_count || 0} listing(s)`);
            }
            button.prop('disabled', false).html(originalText);
        },
        error: function(jqXHR) {
            let errorMsg = "Error updating handlers";
            if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
                errorMsg = jqXHR.responseJSON.error;
            } else if (jqXHR.status === 404) {
                errorMsg = "Route not found. Please check if the route is registered correctly.";
            }
            console.error('Error updating handlers:', jqXHR);
            alert(errorMsg);
            button.prop('disabled', false).html(originalText);
        }
    });
});

/**
 * Handle marketplace-level price form submission (bulk update)
 */
$(document).on('click', '[id^="change_all_price_"] button[type="button"]', function(e) {
    e.preventDefault();
    
    const form = $(this).closest('form');
    const formId = form.attr('id');
    const matches = formId.match(/change_all_price_(\d+)_(\d+)/);
    if (!matches) return;
    
    const variationId = matches[1];
    const marketplaceId = matches[2];
    const minPrice = parseFloat($(`#all_min_price_${variationId}_${marketplaceId}`).val());
    const price = parseFloat($(`#all_price_${variationId}_${marketplaceId}`).val());
    
    // Validate at least one value provided
    if (isNaN(minPrice) && isNaN(price)) {
        alert('Please enter at least one price value');
        return;
    }
    
    const button = $(this);
    const originalText = button.html();
    button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    
    // Construct URL with parameters (similar to handlers form)
    const baseUrl = window.ListingConfig.urls.updateMarketplacePrices || '/v2/listings/update_marketplace_prices';
    const url = `${baseUrl}/${variationId}/${marketplaceId}`;
    const data = {
        _token: window.ListingConfig.csrfToken,
    };
    
    if (!isNaN(minPrice)) {
        data.all_min_price = minPrice;
    }
    if (!isNaN(price)) {
        data.all_price = price;
    }
    
    console.log('Price update URL:', url);
    console.log('Request data:', data);
    
    $.ajax({
        type: "POST",
        url: url,
        data: data,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Reload the listings table to show updated values
                loadMarketplaceTables(variationId, marketplaceId);
                // Log success (no alert to avoid disturbance)
                console.log('Prices updated successfully:', response.message || `Updated ${response.updated_count || 0} listing(s)`);
            }
            button.prop('disabled', false).html(originalText);
        },
        error: function(jqXHR) {
            let errorMsg = "Error updating prices";
            if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
                errorMsg = jqXHR.responseJSON.error;
            }
            alert(errorMsg);
            button.prop('disabled', false).html(originalText);
        }
    });
});

/**
 * Fetch updated stock quantity from Backmarket API for a variation
 * Only fetches if marketplace is Backmarket (ID = 1)
 * 
 * @param {number} variationId
 * @param {number} marketplaceId
 * @returns {Promise<number|null>} Stock quantity or null if not Backmarket
 */
window.fetchBackmarketStockQuantity = function(variationId, marketplaceId) {
    // Only fetch for Backmarket (marketplace ID = 1)
    if (marketplaceId !== 1) {
        return Promise.resolve(null);
    }
    
    if (!window.ListingConfig || !window.ListingConfig.urls || !window.ListingConfig.urls.getUpdatedQuantity) {
        console.warn('getUpdatedQuantity URL not configured');
        return Promise.resolve(null);
    }
    
    return $.ajax({
        url: window.ListingConfig.urls.getUpdatedQuantity + '/' + variationId,
        type: 'GET',
        dataType: 'json',
        headers: {
            'X-CSRF-TOKEN': window.ListingConfig.csrfToken
        }
    }).then(function(response) {
        if (response.success && response.quantity !== undefined) {
            return response.quantity;
        }
        return null;
    }).catch(function(xhr, status, error) {
        console.error('Error fetching Backmarket stock quantity:', error);
        return null;
    });
};

/**
 * Update Backmarket stock badge in marketplace bar
 * 
 * @param {number} variationId
 * @param {number} marketplaceId
 * @param {number} quantity
 */
/**
 * Show stock comparison modal
 * 
 * @param {number} variationId
 */
window.showStockComparison = function(variationId) {
    const modal = new bootstrap.Modal(document.getElementById('stockComparisonModal'));
    const loadingDiv = $('#stockComparisonLoading');
    const contentDiv = $('#stockComparisonContent');
    const errorDiv = $('#stockComparisonError');
    const fixBtn = $('#fixStockMismatchBtn');
    
    // Store variation ID for fix button
    fixBtn.data('variation-id', variationId);
    
    // Show loading, hide content and error
    loadingDiv.show();
    contentDiv.hide();
    errorDiv.hide();
    fixBtn.hide();
    
    // Show modal
    modal.show();
    
    // Fetch comparison data
    if (!window.ListingConfig || !window.ListingConfig.urls || !window.ListingConfig.urls.getMarketplaceStockComparison) {
        errorDiv.find('#stockComparisonErrorMessage').text('Comparison URL not configured');
        loadingDiv.hide();
        errorDiv.show();
        return;
    }
    
    $.ajax({
        url: window.ListingConfig.urls.getMarketplaceStockComparison + '/' + variationId,
        type: 'GET',
        dataType: 'json',
        headers: {
            'X-CSRF-TOKEN': window.ListingConfig.csrfToken
        },
        success: function(response) {
            if (response.success) {
                // Populate modal with data
                $('#comparisonVariationSku').text(response.variation_sku || 'N/A');
                
                // Show total stock (this is the total stock we have in the system)
                $('#comparisonTotalStock').text(response.total_stock || 0);
                
                // Show API comparison if API stock is available
                if (response.api_stock !== null && response.api_stock !== undefined) {
                    const apiStock = parseInt(response.api_stock) || 0;
                    const ourStock = response.totals.available_stock || 0;
                    const difference = apiStock - ourStock;
                    
                    $('#comparisonApiStock').text(apiStock);
                    $('#comparisonOurStock').text(ourStock);
                    $('#comparisonDiffValue').text(difference > 0 ? '+' + difference : difference);
                    
                    // Color code the difference badge
                    const diffBadge = $('#comparisonDiffValue');
                    const diffCell = $('#comparisonDifference');
                    if (difference > 0) {
                        diffBadge.removeClass('bg-warning bg-danger').addClass('bg-success');
                    } else if (difference < 0) {
                        diffBadge.removeClass('bg-warning bg-success').addClass('bg-danger');
                    } else {
                        diffBadge.removeClass('bg-danger bg-success').addClass('bg-warning');
                    }
                    
                    $('#apiComparisonCard').show();
                } else {
                    $('#apiComparisonCard').hide();
                }
                
                // Populate marketplace table
                let tableBody = '';
                let totalListings = 0;
                
                if (response.marketplaces && response.marketplaces.length > 0) {
                    response.marketplaces.forEach(function(mp) {
                        const isBackmarket = mp.is_backmarket;
                        const rowClass = isBackmarket ? 'table-warning' : '';
                        tableBody += '<tr class="' + rowClass + '">';
                        tableBody += '<td>' + mp.marketplace_name + (isBackmarket ? ' <span class="badge bg-primary">API</span>' : '') + '</td>';
                        tableBody += '<td class="text-center">' + mp.listed_stock + '</td>';
                        tableBody += '<td class="text-center">' + mp.available_stock + '</td>';
                        tableBody += '<td class="text-center">' + mp.locked_stock + '</td>';
                        tableBody += '<td class="text-center">' + mp.listing_count + '</td>';
                        tableBody += '</tr>';
                        totalListings += mp.listing_count || 0;
                    });
                } else {
                    tableBody = '<tr><td colspan="5" class="text-center text-muted">No marketplace data available</td></tr>';
                }
                
                $('#comparisonMarketplaceTableBody').html(tableBody);
                $('#comparisonTotalListed').text(response.totals.listed_stock || 0);
                $('#comparisonTotalAvailable').text(response.totals.available_stock || 0);
                $('#comparisonTotalLocked').text(response.totals.locked_stock || 0);
                $('#comparisonTotalListings').text(totalListings);
                
                // Show content, hide loading
                loadingDiv.hide();
                contentDiv.show();
                
                // Show fix button if there are mismatches
                const hasMismatch = response.api_stock !== null && response.totals.available_stock !== response.api_stock;
                const parentMismatch = response.total_stock !== response.totals.listed_stock;
                if (hasMismatch || parentMismatch) {
                    $('#fixStockMismatchBtn').show();
                } else {
                    $('#fixStockMismatchBtn').hide();
                }
            } else {
                errorDiv.find('#stockComparisonErrorMessage').text(response.error || 'Failed to load comparison data');
                loadingDiv.hide();
                errorDiv.show();
            }
        },
        error: function(xhr, status, error) {
            console.error('Error fetching stock comparison:', error);
            errorDiv.find('#stockComparisonErrorMessage').text('Error loading comparison data: ' + error);
            loadingDiv.hide();
            errorDiv.show();
        }
    });
};

/**
 * Fix stock mismatch for a variation
 */
window.fixStockMismatch = function() {
    const variationId = $('#fixStockMismatchBtn').data('variation-id');
    if (!variationId) {
        alert('Variation ID not found');
        return;
    }
    
    if (!confirm('Are you sure you want to fix stock mismatches for this variation? This will sync marketplace stocks with API and parent stock.')) {
        return;
    }
    
    const btn = $('#fixStockMismatchBtn');
    const originalHtml = btn.html();
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Fixing...');
    
    if (!window.ListingConfig || !window.ListingConfig.urls || !window.ListingConfig.urls.fixStockMismatch) {
        alert('Fix URL not configured');
        btn.prop('disabled', false).html(originalHtml);
        return;
    }
    
    $.ajax({
        url: window.ListingConfig.urls.fixStockMismatch + '/' + variationId,
        type: 'POST',
        dataType: 'json',
        headers: {
            'X-CSRF-TOKEN': window.ListingConfig.csrfToken
        },
        success: function(response) {
            if (response.success) {
                alert('Stock mismatches fixed successfully!\n\nFixes applied: ' + response.fixes_applied + '\n\nParent stock: ' + response.summary.parent_stock_before + ' → ' + response.summary.parent_stock_after);
                
                // Reload the comparison data
                window.showStockComparison(variationId);
                
                // Reload the page to reflect changes
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                alert('Error: ' + (response.error || 'Failed to fix stock mismatch'));
                btn.prop('disabled', false).html(originalHtml);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error fixing stock mismatch:', error);
            let errorMsg = 'Error fixing stock mismatch: ' + error;
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMsg = xhr.responseJSON.error;
            }
            alert(errorMsg);
            btn.prop('disabled', false).html(originalHtml);
        }
    });
};

window.updateBackmarketStockBadge = function(variationId, marketplaceId, quantity) {
    const badgeElement = $('#backmarket_stock_badge_' + variationId + '_' + marketplaceId);
    
    if (badgeElement.length) {
        if (quantity !== null && quantity !== undefined) {
            // Remove loading spinner, show quantity
            badgeElement
                .removeClass('bg-secondary d-none')
                .addClass('bg-primary')
                .html('<span class="stock-value">' + quantity + '</span> <small class="ms-1">(API)</small>');
            
            // Compare API stock with our available stock and mark as red if different
            const availableStockElement = $('#available_stock_' + variationId + '_' + marketplaceId);
            
            if (availableStockElement.length) {
                const ourAvailableStock = parseInt(availableStockElement.text().trim()) || 0;
                const apiStock = parseInt(quantity) || 0;
                
                // If stock numbers are different, mark our available stock as red
                if (ourAvailableStock !== apiStock) {
                    availableStockElement.css({
                        'color': '#dc3545',
                        'font-weight': 'bold'
                    });
                } else {
                    // Reset to default color if they match
                    availableStockElement.css({
                        'color': '',
                        'font-weight': ''
                    });
                }
            }
        } else {
            // Hide badge if fetch failed
            badgeElement.addClass('d-none');
        }
    }
};

