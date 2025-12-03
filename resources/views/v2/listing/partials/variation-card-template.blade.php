{{-- This Blade template generates a JavaScript template function for variation cards --}}
<script>
    window.renderVariationCard = function(variation, data) {
        const { colors, storages, grades, countries, marketplaces, eurToGbp, m_min_price, m_price, withoutBuybox, state, listedStock, process_id } = data;
        
        return `
            <div class="card">
                <div class="card-header py-0 d-flex justify-content-between">
                    <div>
                        <h5>
                            <a href="{{ url('inventory') }}?sku=${variation.sku}" title="View Inventory" target="_blank">
                                <span style="background-color: ${colors[variation.color] || ''}; width: 30px; height: 16px; display: inline-block;"></span>
                                ${variation.sku}
                            </a>
                            <a href="https://www.backmarket.fr/bo-seller/listings/active?sku=${variation.sku}" title="View BM Ad" target="_blank">
                                - ${variation.product.model} ${storages[variation.storage] || ''} ${colors[variation.color] || ''} ${grades[variation.grade] || ''}
                            </a>
                        </h5>
                        <span id="sales_${variation.id}"></span>
                    </div>

                    <a href="javascript:void(0)" class="btn btn-link" id="variation_history_${variation.id}" onClick="show_variation_history(${variation.id}, '${variation.sku} ${variation.product.model} ${storages[variation.storage] || ''} ${colors[variation.color] || ''} ${grades[variation.grade] || ''}')" data-bs-toggle="modal" data-bs-target="#modal_history">
                        <i class="fas fa-history"></i>
                    </a>

                    <form class="form-inline wd-150" method="POST" id="add_qty_${variation.id}" action="{{ url('listing/add_quantity') }}/${variation.id}">
                        @csrf
                        <input type="hidden" name="process_id" value="${process_id || ''}">
                        <div class="form-floating">
                            <input type="text" class="form-control" name="stock" id="quantity_${variation.id}" value="${listedStock || 0}" style="width:50px;" disabled>
                            <label for="">Stock</label>
                        </div>
                        <div class="form-floating">
                            <input type="number" class="form-control" name="stock" id="add_${variation.id}" value="" style="width:60px;" oninput="toggleButtonOnChange(${variation.id}, this)" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'add_'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'add_', true); }">
                            <label for="">Add</label>
                        </div>
                        <button id="send_${variation.id}" class="btn btn-light d-none" onclick="submitForm1(event, ${variation.id})">Push</button>
                        <span class="text-success" id="success_${variation.id}"></span>
                    </form>

                    <div class="text-center">
                        <h6 class="mb-0">
                            <a class="" href="{{ url('order') }}?sku=${variation.sku}&status=2" target="_blank">
                                Pending Order Items: ${variation.pending_orders.length || 0} (BM Orders: ${variation.pending_bm_orders.length || 0})
                            </a>
                        </h6>
                        <h6 class="mb-0" id="available_stock_${variation.id}">
                            <a href="{{ url('inventory') }}?product=${variation.product_id}&storage=${variation.storage}&color=${variation.color}&grade[]=${variation.grade}" target="_blank">
                                Available: ${variation.available_stocks.length || 0}
                            </a>
                        </h6>
                        <h6 class="mb-0">Difference: ${variation.available_stocks.length - variation.pending_orders.length}</h6>
                    </div>

                    <button class="btn btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#details_${variation.id}" aria-expanded="false" aria-controls="details_${variation.id}" onClick="getVariationDetails(${variation.id}, ${eurToGbp}, ${m_min_price}, ${m_price})">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
                <div class="d-flex justify-content-between">
                    <div class="pt-3">
                        <h6 class="d-inline">Without&nbsp;Buybox</h6>
                        ${withoutBuybox || ''}
                    </div>
                    <div class="pt-4">
                        <h6 class="badge bg-light text-dark">
                            ${state || 'Unknown'}
                        </h6>
                    </div>
                </div>
                <div class="card-body p-2 collapse multi_collapse" id="details_${variation.id}">
                    <div class="col-md-auto">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>No</b></small></th>
                                        <th><small><b>IMEI/Serial</b></small></th>
                                        <th><small><b>Cost</b> (<b id="average_cost_${variation.id}"></b>)</small></th>
                                    </tr>
                                </thead>
                                <tbody id="stocks_${variation.id}">
                                    ${data.stocksTable || ''}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th width="80"><small><b>Country</b></small></th>
                                        <th width="100" title="Minimum Price Handler"><small><b>Min Hndlr</b></small></th>
                                        <th width="100" title="Price Handler"><small><b>Price Hndlr</b></small></th>
                                        <th width="80"><small><b>BuyBox</b></small></th>
                                        <th title="Min Price" width="120"><small><b>Min </b>(€<b id="best_price_${variation.id}"></b>)</small></th>
                                        <th width="120"><small><b>Price</b></small></th>
                                        <th><small><b>Date</b></small></th>
                                    </tr>
                                </thead>
                                <tbody id="listings_${variation.id}">
                                    ${data.listingsTable || ''}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                {{-- Marketplace Bars Section - Card Footer --}}
                ${(() => {
                    if (!marketplaces || Object.keys(marketplaces).length === 0) {
                        return '<div class="card-footer mt-3 p-2 text-center text-muted border-top"><small>No marketplaces available</small></div>';
                    }
                    
                    // Get marketplace summaries if available
                    const marketplaceSummaries = data.marketplace_summaries || variation.marketplace_summaries || {};
                    
                    // Get buybox listings for this variation
                    const buyboxListings = data.buybox_listings || variation.buybox_listings || [];
                    
                    // If buybox_listings not in data, extract from listings
                    let buyboxListingsExtracted = buyboxListings;
                    if (buyboxListings.length === 0 && variation.listings) {
                        buyboxListingsExtracted = variation.listings.filter(listing => listing.buybox === 1);
                    }
                    
                    // Build buybox map by marketplace
                    const buyboxMap = {};
                    buyboxListingsExtracted.forEach(listing => {
                        const mpId = listing.marketplace_id || (listing.marketplace && listing.marketplace.id);
                        if (mpId) {
                            if (!buyboxMap[mpId]) {
                                buyboxMap[mpId] = [];
                            }
                            buyboxMap[mpId].push(listing);
                        }
                    });
                    
                    // Get listings grouped by marketplace
                    const listingsByMarketplace = {};
                    if (variation.listings && Array.isArray(variation.listings)) {
                        variation.listings.forEach(listing => {
                            if (listing.marketplace_id) {
                                if (!listingsByMarketplace[listing.marketplace_id]) {
                                    listingsByMarketplace[listing.marketplace_id] = [];
                                }
                                listingsByMarketplace[listing.marketplace_id].push(listing);
                            }
                        });
                    }
                    
                    let marketplaceBars = '<div class="card-footer p-0 border-top mt-2">';
                    
                    // Loop through ALL marketplaces
                    Object.keys(marketplaces).forEach(marketplaceId => {
                        const marketplace = marketplaces[marketplaceId];
                        const marketplaceName = marketplace ? (marketplace.name || 'Marketplace ' + marketplaceId) : 'Marketplace ' + marketplaceId;
                        const marketplaceListings = listingsByMarketplace[marketplaceId] || [];
                        const marketplaceBuyboxListings = buyboxMap[marketplaceId] || [];
                        const summary = marketplaceSummaries[marketplaceId] || {};
                        
                        // Calculate marketplace-specific values for form inputs
                        let minHandlerValue = '';
                        let handlerValue = '';
                        let minPriceValue = '';
                        let priceValue = '';
                        
                        if (marketplaceListings.length > 0) {
                            // Calculate min_handler (min_price_limit) - use minimum value
                            const minPriceLimits = marketplaceListings.map(l => l.min_price_limit).filter(v => v != null && v !== '');
                            if (minPriceLimits.length > 0) {
                                minHandlerValue = Math.min(...minPriceLimits);
                            }
                            
                            // Calculate handler (price_limit) - use minimum value
                            const priceLimits = marketplaceListings.map(l => l.price_limit).filter(v => v != null && v !== '');
                            if (priceLimits.length > 0) {
                                handlerValue = Math.min(...priceLimits);
                            }
                            
                            // Calculate min_price - use minimum value
                            const minPrices = marketplaceListings.map(l => l.min_price).filter(v => v != null && v !== '');
                            if (minPrices.length > 0) {
                                minPriceValue = Math.min(...minPrices);
                            }
                            
                            // Calculate price - use minimum value
                            const prices = marketplaceListings.map(l => l.price).filter(v => v != null && v !== '');
                            if (prices.length > 0) {
                                priceValue = Math.min(...prices);
                            }
                        }
                        
                        // Build buybox flags - get buybox listings for this marketplace from all listings
                        let buyboxFlags = '';
                        const buyboxListingsForMarketplace = marketplaceListings.filter(listing => listing.buybox === 1);
                        
                        if (buyboxListingsForMarketplace.length > 0) {
                            buyboxListingsForMarketplace.forEach(listing => {
                                // Handle country_id as object or ID
                                let country = null;
                                let countryCode = '';
                                let marketUrl = '';
                                let marketCode = '';
                                
                                // Check if country_id is an object (from eager loading)
                                if (listing.country_id && typeof listing.country_id === 'object' && listing.country_id.code) {
                                    country = listing.country_id;
                                    countryCode = country.code || '';
                                    marketUrl = country.market_url || '';
                                    marketCode = country.market_code || '';
                                } 
                                // Check if country_id is a number/ID
                                else if (listing.country_id && (typeof listing.country_id === 'number' || typeof listing.country_id === 'string')) {
                                    country = countries[listing.country_id];
                                    if (country) {
                                        countryCode = (country.code || '').toString();
                                        marketUrl = (country.market_url || '').toString();
                                        marketCode = (country.market_code || '').toString();
                                    }
                                }
                                // Check if listing has country property
                                else if (listing.country) {
                                    country = listing.country;
                                    countryCode = (country.code || '').toString();
                                    marketUrl = (country.market_url || '').toString();
                                    marketCode = (country.market_code || '').toString();
                                }
                                
                                if (countryCode) {
                                    const referenceUuid2 = listing.reference_uuid_2 || listing._2 || '';
                                    buyboxFlags += `<a href="https://www.backmarket.${marketUrl}/${marketCode}/p/gb/${referenceUuid2}" target="_blank" class="btn btn-sm btn-link border p-1 m-1" title="View listing">
                                        <img src="{{ asset('assets/img/flags/') }}/${countryCode.toLowerCase()}.svg" height="10" alt="${countryCode}">
                                        ${countryCode}
                                    </a>`;
                                }
                            });
                        }
                        
                        if (!buyboxFlags) {
                            buyboxFlags = '<span class="text-muted small">No buybox</span>';
                        }
                        
                        // Format order summary
                        const orderSummary = `7 days: €${(summary.last_7_days_total || 0).toFixed(2)} (${summary.last_7_days_count || 0}) - 14 days: €${(summary.last_14_days_total || 0).toFixed(2)} (${summary.last_14_days_count || 0}) - 30 days: €${(summary.last_30_days_total || 0).toFixed(2)} (${summary.last_30_days_count || 0})`;
                        
                        // Generate toggle content HTML using the helper function if available, otherwise inline
                        const toggleContent = (window.renderMarketplaceToggleContent) 
                            ? window.renderMarketplaceToggleContent(variation.id, marketplaceId, marketplaceName, {
                                marketplaceListings: marketplaceListings,
                                summary: summary
                            })
                            : `
                            <div class="marketplace-toggle-content collapse" id="marketplace_toggle_${variation.id}_${marketplaceId}">
                                <div class="p-3 bg-light border-top">
                                    <div class="row">
                                        <div class="col-12">
                                            <h6 class="fw-bold mb-3">Marketplace Details</h6>
                                            <p class="text-muted small">This is a test view for marketplace <strong>${marketplaceName}</strong> (ID: ${marketplaceId}) of variation ${variation.id}.</p>
                                            <p class="text-muted small">Additional content can be added here based on requirements.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        marketplaceBars += `
                            <div class="marketplace-bar-wrapper border-bottom">
                                <div class="p-2">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="fw-bold">${marketplaceName}</div>
                                        <div class="d-flex align-items-center gap-2">
                                            <div>${buyboxFlags}</div>
                                            <button class="btn btn-sm btn-link p-0" type="button" data-bs-toggle="collapse" data-bs-target="#marketplace_toggle_${variation.id}_${marketplaceId}" aria-expanded="false" aria-controls="marketplace_toggle_${variation.id}_${marketplaceId}" style="min-width: 24px;" onclick="this.querySelector('i').classList.toggle('fa-chevron-down'); this.querySelector('i').classList.toggle('fa-chevron-up');">
                                                <i class="fas fa-chevron-down"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-start gap-2">
                                            <form class="d-inline-flex gap-1 align-items-center" method="POST" id="change_all_handler_${variation.id}_${marketplaceId}">
                                                @csrf
                                                <div class="form-floating" style="width: 75px;">
                                                    <input type="number" class="form-control form-control-sm" id="all_min_handler_${variation.id}_${marketplaceId}" name="all_min_handler" step="0.01" value="${minHandlerValue}" placeholder="Min" style="height: 31px;" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'all_min_handler_'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'all_min_handler_', true); }">
                                                    <label for="" class="small">Min</label>
                                                </div>
                                                <div class="form-floating" style="width: 75px;">
                                                    <input type="number" class="form-control form-control-sm" id="all_handler_${variation.id}_${marketplaceId}" name="all_handler" step="0.01" value="${handlerValue}" placeholder="Handler" style="height: 31px;" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'all_handler_'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'all_handler_', true); }">
                                                    <label for="" class="small">Handler</label>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-primary" style="height: 31px; line-height: 1;" onclick="submitForm8(event, ${variation.id}, window.eur_listings[${variation.id}] || [], ${marketplaceId})">Change</button>
                                            </form>
                                            <form class="d-inline-flex gap-1 align-items-center" method="POST" id="change_all_price_${variation.id}_${marketplaceId}">
                                                @csrf
                                                <div class="form-floating" style="width: 75px;">
                                                    <input type="number" class="form-control form-control-sm" id="all_min_price_${variation.id}_${marketplaceId}" name="all_min_price" step="0.01" value="${minPriceValue}" placeholder="Min Price" style="height: 31px;" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'all_min_price_'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'all_min_price_', true); }">
                                                    <label for="" class="small">Min Price</label>
                                                </div>
                                                <div class="form-floating" style="width: 75px;">
                                                    <input type="number" class="form-control form-control-sm" id="all_price_${variation.id}_${marketplaceId}" name="all_price" step="0.01" value="${priceValue}" placeholder="Price" style="height: 31px;" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'all_price_'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'all_price_', true); }">
                                                    <label for="" class="small">Price</label>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-success" style="height: 31px; line-height: 1;" onclick="submitForm4(event, ${variation.id}, window.eur_listings[${variation.id}] || [], ${marketplaceId})">Push</button>
                                            </form>
                                        </div>
                                        <div class="small fw-bold text-end">${orderSummary}</div>
                                    </div>
                                </div>
                                ${toggleContent}
                            </div>
                        `;
                    });
                    
                    marketplaceBars += '</div>';
                    
                    return marketplaceBars;
                })()}
            </div>
            
            
        </div>
        `;
    };
</script>

with same dis