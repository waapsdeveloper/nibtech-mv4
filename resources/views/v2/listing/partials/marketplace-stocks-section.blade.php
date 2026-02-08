@php
    // This partial shows all marketplace stocks in one place
    // Expected variables: $variationId, $marketplaces, $process_id (optional), $totalStock
@endphp

@php
    // Get all marketplace stocks for dropdown
    $marketplaceStocksData = [];
    if(isset($marketplaces) && count($marketplaces) > 0) {
        foreach($marketplaces as $marketplaceId => $marketplace) {
            $marketplaceIdInt = (int)$marketplaceId;
            $marketplaceStock = \App\Models\MarketplaceStockModel::where('variation_id', $variationId)
                ->where('marketplace_id', $marketplaceIdInt)
                ->first();
            $currentStock = $marketplaceStock ? ($marketplaceStock->listed_stock ?? 0) : 0;
            $marketplaceStocksData[$marketplaceIdInt] = [
                'name' => $marketplace->name ?? 'Marketplace ' . $marketplaceIdInt,
                'stock' => $currentStock
            ];
        }
    }
    $firstMarketplaceId = !empty($marketplaceStocksData) ? array_key_first($marketplaceStocksData) : null;
    $firstMarketplaceStock = $firstMarketplaceId ? $marketplaceStocksData[$firstMarketplaceId]['stock'] : 0;
@endphp

<div class="marketplace-stocks-section" data-variation-id="{{ $variationId }}" data-first-marketplace-id="{{ $firstMarketplaceId ?? '' }}">

    {{-- Stock Table --}}
    <div class="mt-3">
        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                <thead>
                    <tr>
                        <th><small><b>No</b></small></th>
                        <th><small><b>IMEI/Serial</b></small></th>
                        <th><small><b>Cost</b> (<b id="average_cost_stocks_{{ $variationId }}"></b>)</small></th>
                    </tr>
                </thead>
                <tbody id="stocks_table_{{ $variationId }}">
                    <tr>
                        <td colspan="3" class="text-center text-muted small">Select a marketplace to view stocks</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

@once
<script>
    // Function to update marketplace stock editor when dropdown changes
    window.updateMarketplaceStockEditor = function(variationId, marketplaceId) {
        const select = $('#marketplace_stock_select_' + variationId);
        const selectedOption = select.find('option:selected');
        const currentStock = selectedOption.data('stock') || 0;
        const marketplaceIdInt = parseInt(marketplaceId);

        // Remove existing editor
        $('#marketplace_stock_editor_container_' + variationId).empty();

        // Create new editor HTML
        const editorHtml = `
            <div class="marketplace-stock-container d-flex align-items-center gap-1" id="marketplace_stock_${variationId}_${marketplaceIdInt}">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="adjustMarketplaceStock(${variationId}, ${marketplaceIdInt}, -1)" style="width: 28px; height: 28px; padding: 0; line-height: 1;">-</button>
                <input type="number" class="form-control form-control-sm text-center" id="stock_input_${variationId}_${marketplaceIdInt}" value="${currentStock}" style="width: 60px; height: 28px;" min="0" onchange="checkStockChange(${variationId}, ${marketplaceIdInt})" oninput="checkStockChange(${variationId}, ${marketplaceIdInt})">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="adjustMarketplaceStock(${variationId}, ${marketplaceIdInt}, 1)" style="width: 28px; height: 28px; padding: 0; line-height: 1;">+</button>
                <button type="button" class="btn btn-sm btn-primary" id="save_stock_${variationId}_${marketplaceIdInt}" onclick="saveMarketplaceStock(${variationId}, ${marketplaceIdInt})" style="height: 28px; width: 28px; padding: 0; line-height: 1; display: none;" title="Save">
                    <i class="fas fa-check" style="font-size: 0.75rem;"></i>
                </button>
                <button type="button" class="btn btn-sm btn-secondary" id="cancel_stock_${variationId}_${marketplaceIdInt}" onclick="cancelEditMarketplaceStock(${variationId}, ${marketplaceIdInt})" style="height: 28px; width: 28px; padding: 0; line-height: 1; display: none;" title="Cancel">
                    <i class="fas fa-times" style="font-size: 0.75rem;"></i>
                </button>
                <span class="stock-value d-none" id="stock_display_${variationId}_${marketplaceIdInt}">${currentStock}</span>
            </div>
            <span class="text-success small d-none" id="success_marketplace_${variationId}_${marketplaceIdInt}"></span>
        `;

        $('#marketplace_stock_editor_container_' + variationId).html(editorHtml);

        // Initialize original value
        if (typeof window.originalStockValues === 'undefined') {
            window.originalStockValues = {};
        }
        window.originalStockValues[variationId + '_' + marketplaceIdInt] = currentStock;

        // Load stocks for the selected marketplace
        loadStocksForMarketplace(variationId, marketplaceIdInt);
    };

    // Function to load stocks for selected marketplace
    window.loadStocksForMarketplace = function(variationId, marketplaceId, page = 1) {
        const stocksTableBody = $('#stocks_table_' + variationId);
        const averageCostElement = $('#average_cost_stocks_' + variationId);

        // Show loader
        stocksTableBody.html('<tr><td colspan="3" class="text-center p-3"><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Loading...</span></div> <small class="ms-2 text-muted">Loading stocks...</small></td></tr>');

        $.ajax({
            url: window.ListingConfig.urls.getVariationStocks + '/' + variationId + '?page=' + page + '&per_page=50',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                let stocksTable = '';
                let stockPrices = [];

                if (data.stocks && data.stocks.length > 0) {
                    // Calculate starting row number based on pagination
                    let startRow = data.pagination ? ((data.pagination.current_page - 1) * data.pagination.per_page) : 0;
                    data.stocks.forEach(function(item, index) {
                        let price = data.stock_costs[item.id] || 0;
                        let topup_ref = data.topup_reference[data.latest_topup_items[item.id]] || '';
                        let vendor = data.vendors && data.po && data.po[item.order_id] ? (data.vendors[data.po[item.order_id]] || '') : '';
                        let reference_id = data.reference && data.reference[item.order_id] ? data.reference[item.order_id] : '';

                        // Collect price for average calculation
                        if (price) {
                            stockPrices.push(parseFloat(price));
                        }

                        const imeiUrl = window.ListingConfig.urls.imei || '';
                        stocksTable += `
                            <tr>
                                <td><small>${startRow + index + 1}</small></td>
                                <td data-stock="${item.id}" title="${topup_ref}">
                                    <small>
                                        <a href="${imeiUrl}?imei=${item.imei || item.serial_number}" target="_blank">
                                            ${item.imei || item.serial_number || ''}
                                        </a>
                                    </small>
                                </td>
                                <td><small title="${reference_id}"><strong>€${price ? parseFloat(price).toFixed(2) : '0.00'}</strong>${vendor ? ' (' + vendor + ')' : ''}</small></td>
                            </tr>`;
                    });
                } else {
                    stocksTable = '<tr><td colspan="3" class="text-center text-muted small">No stocks available</td></tr>';
                }

                stocksTableBody.html(stocksTable);

                // Calculate average cost value first (before updating header)
                let averageCostValue = '€0.00';
                if (data.average_cost !== undefined) {
                    averageCostValue = `€${parseFloat(data.average_cost).toFixed(2)}`;
                } else {
                    // Fallback to client-side calculation if server doesn't provide it
                    if (stockPrices.length > 0) {
                        let average = stockPrices.reduce((a, b) => parseFloat(a) + parseFloat(b), 0) / stockPrices.length;
                        averageCostValue = `€${average.toFixed(2)}`;
                    }
                }

                // Create pagination HTML function
                function createPaginationHtml(pagination, variationId, marketplaceId, isHeader = false) {
                    let paginationHtml = '<div class="d-flex justify-content-center align-items-center gap-1">';

                    if (pagination.current_page > 1) {
                        paginationHtml += `<button type="button" class="btn btn-sm btn-outline-secondary p-1" onclick="loadStocksForMarketplace(${variationId}, ${marketplaceId}, ${pagination.current_page - 1})" title="Previous page" style="line-height: 1;">
                            <i class="fas fa-chevron-left" style="font-size: 0.7rem;"></i>
                        </button>`;
                    } else {
                        paginationHtml += `<button type="button" class="btn btn-sm btn-outline-secondary p-1" disabled style="line-height: 1;">
                            <i class="fas fa-chevron-left" style="font-size: 0.7rem;"></i>
                        </button>`;
                    }

                    // Show current/total format for both header and bottom
                    paginationHtml += `<span class="badge bg-secondary" style="font-size: ${isHeader ? '0.7rem' : '0.75rem'};">${pagination.current_page}/${pagination.last_page}</span>`;

                    if (pagination.current_page < pagination.last_page) {
                        paginationHtml += `<button type="button" class="btn btn-sm btn-outline-secondary p-1" onclick="loadStocksForMarketplace(${variationId}, ${marketplaceId}, ${pagination.current_page + 1})" title="Next page" style="line-height: 1;">
                            <i class="fas fa-chevron-right" style="font-size: 0.7rem;"></i>
                        </button>`;
                    } else {
                        paginationHtml += `<button type="button" class="btn btn-sm btn-outline-secondary p-1" disabled style="line-height: 1;">
                            <i class="fas fa-chevron-right" style="font-size: 0.7rem;"></i>
                        </button>`;
                    }

                    paginationHtml += '</div>';
                    return paginationHtml;
                }

                // Add pagination controls if pagination data exists
                if (data.pagination) {
                    // Update header with pagination - include average cost value in the HTML
                    let headerPaginationHtml = createPaginationHtml(data.pagination, variationId, marketplaceId, true);
                    let costHeader = stocksTableBody.closest('table').find('thead th').last();
                    if (costHeader.length) {
                        costHeader.html(`<div class="d-flex justify-content-between align-items-center"><div><small><b>Cost</b> (<b id="average_cost_stocks_${variationId}">${averageCostValue}</b>)</small></div><div>${headerPaginationHtml}</div></div>`);
                    }

                    // Add pagination on bottom (after tbody)
                    $('#stocks_pagination_'+variationId+'_bottom').remove();
                    let bottomPaginationHtml = createPaginationHtml(data.pagination, variationId, marketplaceId, false);
                    stocksTableBody.closest('table').after('<div id="stocks_pagination_'+variationId+'_bottom" class="mt-2 px-3 pb-2">' + bottomPaginationHtml + '</div>');
                } else {
                    // If no pagination, just set the average cost normally
                    averageCostElement.text(averageCostValue);
                }
            },
            error: function() {
                stocksTableBody.html('<tr><td colspan="3" class="text-center text-danger small">Error loading stocks</td></tr>');
                averageCostElement.text('€0.00');
            }
        });
    }

    // Load stocks for first marketplace when the section becomes visible
    $(document).ready(function() {
        // Function to initialize stock table for a variation
        function initializeStockTable(variationId) {
            const stocksSection = $('#marketplace_stocks_dropdown_' + variationId);
            const stocksContent = $('.marketplace-stocks-section[data-variation-id="' + variationId + '"]');
            const firstMarketplaceId = stocksContent.data('first-marketplace-id');

            if (stocksSection.length && firstMarketplaceId) {
                // Use Bootstrap collapse event to detect when section is shown
                stocksSection.on('shown.bs.collapse', function() {
                    if (typeof window.loadStocksForMarketplace === 'function') {
                        window.loadStocksForMarketplace(variationId, parseInt(firstMarketplaceId));
                    }
                });

                // Also try to load if already visible
                if (stocksSection.hasClass('show')) {
                    if (typeof window.loadStocksForMarketplace === 'function') {
                        window.loadStocksForMarketplace(variationId, parseInt(firstMarketplaceId));
                    }
                }
            }
        }

        // Initialize for all variations on page
        $('[id^="marketplace_stocks_dropdown_"]').each(function() {
            const id = $(this).attr('id');
            const matches = id.match(/marketplace_stocks_dropdown_(\d+)/);
            if (matches) {
                initializeStockTable(matches[1]);
            }
        });

        // Also initialize for any marketplace-stocks-section elements
        $('.marketplace-stocks-section').each(function() {
            const variationId = $(this).data('variation-id');
            if (variationId) {
                initializeStockTable(variationId);
            }
        });
    });
</script>
@endonce

