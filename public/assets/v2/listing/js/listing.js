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
                            <td>${listing.updated_at ? new Date(listing.updated_at).toLocaleString('en-GB', { timeZone: 'Europe/London', hour12: true }) : ''}</td>
                            <td class="text-center">
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
function toggleGlobalMarketplace(marketplaceId, badgeElement) {
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
function clearMarketplaceState() {
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
});

