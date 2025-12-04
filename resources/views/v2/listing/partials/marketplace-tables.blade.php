{{-- Marketplace Tables Partial - Stocks and Listings grouped by marketplace --}}
{{-- Loads tables lazily on first toggle click --}}
<script>
    // Function to load marketplace tables on first toggle
    window.loadMarketplaceTables = function(variationId, marketplaceId, eurToGbp, m_min_price, m_price) {
        const toggleId = '#marketplace_toggle_' + variationId + '_' + marketplaceId;
        const toggleElement = $(toggleId);
        const contentContainer = toggleElement.find('.marketplace-tables-container');
        
        // Check if already loaded
        if (contentContainer.data('loaded') === true) {
            return;
        }
        
        // Show loader
        contentContainer.html('<div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2 text-muted">Loading tables...</p></div>');
        
        // Filter listings for this marketplace (null or 1 = marketplace 1)
        const effectiveMarketplaceId = (marketplaceId === null || marketplaceId === 1) ? 1 : marketplaceId;
        
        // Get listings from variation data or load via AJAX
        const variation = window.variationData && window.variationData[variationId];
        let listings = [];
        
        if (variation && variation.listings) {
            listings = variation.listings;
        } else if (variation && variation.variation_data && variation.variation_data.listings) {
            listings = variation.variation_data.listings;
        }
        
        // Filter listings by marketplace
        const marketplaceListings = listings.filter(listing => {
            const listingMarketplaceId = listing.marketplace_id || listing.marketplace?.id || null;
            return (listingMarketplaceId === null || listingMarketplaceId === 1) ? (effectiveMarketplaceId === 1) : (listingMarketplaceId === effectiveMarketplaceId);
        });
        
        // Process listings exactly as original - matching listings.blade.php logic
        let listingsTable = '';
        let countries = window.countries || {};
        let marketplaces = window.marketplaces || {};
        let exchange_rates = window.exchange_rates || {};
        let currencies = window.currencies || {};
        let currency_sign = window.currency_sign || {};
        
        marketplaceListings.forEach(function(listing) {
            let best_price = $('#best_price_'+variationId).text().replace('€', '') || 0;
            let exchange_rates_2 = exchange_rates;
            let currencies_2 = currencies;
            let currency_sign_2 = currency_sign;
            let p_append = '';
            let pm_append = '';
            let pm_append_title = '';
            let possible = 0;
            let classs = '';
            let cost = 0;
            
            if (listing.currency_id != 4) {
                let rates = exchange_rates_2[currencies_2[listing.currency_id]];
                p_append = 'Fr: '+currency_sign_2[listing.currency_id]+(parseFloat(m_price)*parseFloat(rates)).toFixed(2);
                pm_append = 'Fr: '+currency_sign_2[listing.currency_id]+(parseFloat(m_min_price)*parseFloat(rates)).toFixed(2);
                pm_append_title = 'Break Even: '+currency_sign_2[listing.currency_id]+(parseFloat(best_price)*parseFloat(rates)).toFixed(2);
            } else {
                window.eur_listings[variationId] = window.eur_listings[variationId] || [];
                window.eur_listings[variationId].push(listing);
            }
            
            if(listing.target_price > 0 && listing.target_percentage > 0){
                cost = $('#average_cost_'+variationId).text().replace('€', '');
                target = ((parseFloat(cost)+20)/ ((100-parseFloat(listing.target_percentage))/100));
                if(target <= listing.target_price){
                    possible = 1;
                }
                if(listing.target_price >= listing.min_price && listing.target_price <= listing.price && listing.target_price >= listing.buybox_price){
                    classs = 'bg-lightgreen';
                }
            }
            
            // Get marketplace name
            let marketplaceNameDisplay = 'N/A';
            if (listing.marketplace_id && marketplaces && marketplaces[listing.marketplace_id]) {
                marketplaceNameDisplay = marketplaces[listing.marketplace_id].name || 'Marketplace ' + listing.marketplace_id;
            } else if (listing.marketplace && listing.marketplace.name) {
                marketplaceNameDisplay = listing.marketplace.name;
            }
            
            // Get country data - matching original exactly
            // V2 uses listing.country_id as object, original uses listing.country (number) and looks up in countries
            let countryObj = null;
            if (listing.country_id && typeof listing.country_id === 'object' && listing.country_id.code) {
                // V2: country_id is already an object
                countryObj = listing.country_id;
            } else if (listing.country && countries[listing.country]) {
                // Original: country is a number, look it up
                countryObj = countries[listing.country];
            } else if (listing.country_id && (typeof listing.country_id === 'number' || typeof listing.country_id === 'string') && countries[listing.country_id]) {
                // Fallback: country_id is a number
                countryObj = countries[listing.country_id];
            }
            
            if (!countryObj) {
                return; // Skip if no country data
            }
            
            listingsTable += `
                <tr class="${classs}" ${listing.buybox !== 1 ? 'style="background: pink;"' : ''}>
                    <td title="${listing.id} ${countryObj.title || ''}">
                        <a href="https://www.backmarket.${countryObj.market_url}/${countryObj.market_code}/p/gb/${listing.reference_uuid_2 || listing._2 || ''}" target="_blank">
                        <img src="{{ asset('assets/img/flags/') }}/${countryObj.code.toLowerCase()}.svg" height="15">
                        ${countryObj.code}
                        </a>
                        ${marketplaceNameDisplay}
                    </td>
                    <td>
                        <form class="form-inline" method="POST" id="change_limit_${listing.id}">
                            @csrf
                            <input type="submit" hidden>
                        </form>
                        <input type="number" class="form-control ${listing.handler_status == 2 ? 'text-danger':''}" id="min_price_limit_${listing.id}" name="min_price_limit" step="0.01" value="${listing.min_price_limit || ''}" form="change_limit_${listing.id}">
                    </td>
                    <td>
                        <input type="number" class="form-control ${listing.handler_status == 2 ? 'text-danger':''}" id="price_limit_${listing.id}" name="price_limit" step="0.01" value="${listing.price_limit || ''}" form="change_limit_${listing.id}">
                    </td>
                    <td>${listing.buybox_price || ''}
                        <span class="text-danger" title="Buybox Winner Price">
                            ${listing.buybox !== 1 ? '('+(listing.buybox_winner_price || '')+')' : ''}
                        </span>
                    </td>
                    <td>
                        <form class="form-inline" method="POST" id="change_min_price_${listing.id}">
                            @csrf
                            <input type="submit" hidden>
                        </form>
                        <form class="form-inline" method="POST" id="change_price_${listing.id}">
                            @csrf
                            <input type="submit" hidden>
                        </form>
                        <form class="form-inline" method="POST" id="change_target_${listing.id}">
                            @csrf
                            <input type="submit" hidden>
                        </form>
                        <div class="form-floating">
                            <input type="number" class="form-control" id="min_price_${listing.id}" name="min_price" step="0.01" value="${listing.min_price || ''}" form="change_min_price_${listing.id}">
                            <label for="">Min Price</label>
                        </div>
                        <span id="pm_append_${listing.id}" title="${pm_append_title}">
                            ${pm_append}
                        </span>
                    </td>
                    <td>
                        <div class="form-floating">
                            <input type="number" class="form-control" id="price_${listing.id}" name="price" step="0.01" value="${listing.price || ''}" form="change_price_${listing.id}">
                            <label for="">Price</label>
                        </div>
                        ${p_append}
                    </td>
                    <td>${listing.updated_at ? new Date(listing.updated_at).toLocaleString('en-GB', { timeZone: 'Europe/London', hour12: true }) : ''}</td>
                </tr>
            `;
            
            // Bind form submit handlers - matching original
            $(document).ready(function() {
                $("#change_min_price_" + listing.id).on('submit', function(e) {
                    submitForm2(e, listing.id);
                });
                $("#change_price_" + listing.id).on('submit', function(e) {
                    submitForm3(e, listing.id);
                });
                $("#change_limit_" + listing.id).on('submit', function(e) {
                    submitForm5(e, listing.id);
                });
                $("#change_target_" + listing.id).on('submit', function(e) {
                    submitForm6(e, listing.id);
                });
            });
            
            bindListingPriceEnterShortcut(listing.id);
        });
        
        // Load stocks for marketplace 1
        let stocksTable = '';
        if (effectiveMarketplaceId === 1) {
            // Load stocks via AJAX
            $.ajax({
                url: "{{ url('listing/get_variation_available_stocks') }}/" + variationId,
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    let count = 0;
                    data.stocks.forEach(function(item, index) {
                        count++;
                        let price = data.stock_costs[item.id];
                        let vendor = data.vendors[data.po[item.order_id]];
                        let reference_id = data.reference[item.order_id];
                        let topup_ref = data.topup_reference[data.latest_topup_items[item.id]];
                        
                        stocksTable += `
                            <tr>
                                <td>${index + 1}</td>
                                <td data-stock="${item.id}" title="${topup_ref}">
                                    <a href="{{ url('imei?imei=') }}${item.imei || item.serial_number}" target="_blank">
                                        ${item.imei || item.serial_number || ''}
                                    </a>
                                </td>
                                <td>€${price || '0.00'}</td>
                            </tr>
                        `;
                    });
                    
                    // Render complete tables
                    renderMarketplaceTablesContent(variationId, marketplaceId, listingsTable, stocksTable);
                },
                error: function() {
                    renderMarketplaceTablesContent(variationId, marketplaceId, listingsTable, '<tr><td colspan="3" class="text-center text-muted">Error loading stocks</td></tr>');
                }
            });
        } else {
            // Render without stocks for non-marketplace-1
            renderMarketplaceTablesContent(variationId, marketplaceId, listingsTable, '<tr><td colspan="3" class="text-center text-muted">Stocks are only shown for marketplace 1</td></tr>');
        }
    };
    
    // Function to render the tables HTML
    function renderMarketplaceTablesContent(variationId, marketplaceId, listingsTable, stocksTable) {
        const toggleId = '#marketplace_toggle_' + variationId + '_' + marketplaceId;
        const contentContainer = $(toggleId).find('.marketplace-tables-container');
        
        if (listingsTable === '') {
            listingsTable = '<tr><td colspan="7" class="text-center text-muted">No listings for this marketplace</td></tr>';
        }
        
        const tablesHtml = `
            <div class="row g-2">
                
                <div class="col-md">
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
                                </tr>
                            </thead>
                            <tbody id="listings_${variationId}_${marketplaceId}">
                                ${listingsTable}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="col-md-auto">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                            <thead>
                                <tr>
                                    <th><small><b>No</b></small></th>
                                    <th><small><b>IMEI/Serial</b></small></th>
                                    <th><small><b>Cost</b> (<b id="average_cost_${variationId}_${marketplaceId}"></b>)</small></th>
                                </tr>
                            </thead>
                            <tbody id="stocks_${variationId}_${marketplaceId}">
                                ${stocksTable}
                            </tbody>
                        </table>
                    </div>
                </div>
                
            </div>
        `;
        
        contentContainer.html(tablesHtml);
        contentContainer.data('loaded', true);
    }
</script>

