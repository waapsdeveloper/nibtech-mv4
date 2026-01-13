@php
    $variationId = $variation->id;
    $sku = $variation->sku ?? 'N/A';
    $colorId = $variation->color ?? null;
    // Get color name from session colors array (same as original)
    $colorName = isset($colors[$colorId]) ? $colors[$colorId] : '';
    // Convert color name to CSS color (use name if it's a valid CSS color, otherwise use a default)
    $colorCode = $colorName;
    // If color name is not a valid CSS color, try to map common names
    $colorNameLower = strtolower($colorName);
    $colorMap = [
        'purple' => 'purple',
        'deep purple' => '#663399',
        'dark purple' => '#4B0082',
        'red' => 'red',
        'blue' => 'blue',
        'black' => 'black',
        'white' => 'white',
        'gold' => '#FFD700',
        'silver' => '#C0C0C0',
        'pink' => 'pink',
        'gray' => 'gray',
        'grey' => 'gray',
        'space gray' => '#717378',
        'midnight' => '#1C1C1E',
        'starlight' => '#F5F5DC',
        'beige' => '#F5F5DC',
    ];
    // Check if we have a mapping
    foreach($colorMap as $key => $value) {
        if(str_contains($colorNameLower, $key)) {
            $colorCode = $value;
            break;
        }
    }
    // If still no valid color, use a default
    if(empty($colorCode) || !preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$|^[a-z]+$/i', $colorCode)) {
        $colorCode = '#ccc'; // Default gray
    }
    
    $storageId = $variation->storage ?? null;
    $storageName = isset($storages[$storageId]) ? $storages[$storageId] : '';
    $gradeId = $variation->grade ?? null;
    $gradeName = isset($grades[$gradeId]) ? $grades[$gradeId] : '';
    $productModel = $variation->product->model ?? 'N/A';
    $productId = $variation->product_id ?? 0;
    // Calculate total stock and available stock from all marketplaces
    // Total Stock = Sum of all marketplace listed_stock
    // Available Stock = Sum of all marketplace listed_stock (no locked stock calculation for simplicity)
    $totalStock = 0;
    $totalAvailableStock = 0;
    if(isset($marketplaces) && count($marketplaces) > 0) {
        foreach($marketplaces as $mpId => $mp) {
            $marketplaceIdInt = (int)$mpId;
            $marketplaceStock = \App\Models\MarketplaceStockModel::where('variation_id', $variationId)
                ->where('marketplace_id', $marketplaceIdInt)
                ->first();
            if($marketplaceStock) {
                $listedStock = (int)($marketplaceStock->listed_stock ?? 0);
                $totalStock += $listedStock;
                
                // Calculate available stock for this marketplace (simplified: just use listed_stock, no locked calculation)
                $availableStock = $listedStock;
                $totalAvailableStock += $availableStock;
            }
        }
    }
    // Fallback to variation.listed_stock if no marketplace stock exists yet
    if($totalStock == 0) {
        $totalStock = $variation->listed_stock ?? 0;
    }
    
    // Ensure available stock never exceeds total stock (safety check)
    // Available stock should logically never exceed total stock, but we cap it just in case
    $totalAvailableStock = min($totalAvailableStock, $totalStock);
    
    // Use physical stock count (matching V1 behavior)
    $availableStocks = $variation->available_stocks ?? collect();
    $pendingOrders = $variation->pending_orders ?? collect();
    $pendingBmOrders = $variation->pending_bm_orders ?? collect();
    $physicalAvailableCount = $availableStocks->count(); // Physical stock items count
    $pendingCount = $pendingOrders->sum('quantity'); // Sum of quantities (matching V1 behavior)
    $pendingBmCount = $pendingBmOrders->count();
    
    // Use physical inventory count for display (matching V1)
    $availableCount = $physicalAvailableCount;
    $difference = $availableCount - $pendingCount;
    
    // Calculate average cost from available stocks
    $averageCost = 0;
    if($availableStocks->count() > 0) {
        $stockIds = $availableStocks->pluck('id');
        $stockCosts = \App\Models\Order_item_model::whereHas('order', function($q){
            $q->where('order_type_id', 1);
        })->whereIn('stock_id', $stockIds)->pluck('price');
        
        if($stockCosts->count() > 0) {
            $averageCost = $stockCosts->avg();
        }
    }
    
    // Get withoutBuybox HTML from variation data
    $withoutBuybox = $variation->withoutBuybox ?? '';
@endphp

<div class="card" id="variation_card_{{ $variationId }}" style="padding-left: 5px; padding-right: 5px; width: 100%;">
    <div class="card-header py-0 d-flex justify-content-between" style="padding-left: 5px; padding-right: 5px;">
        <div>
            <h5 class="d-flex align-items-center gap-2">
                <span>
                    <a href="{{url('inventory')}}?sku={{ $sku }}" title="View Inventory" target="_blank">
                        <span style="background-color: {{ $colorCode }}; width: 30px; height: 16px; display: inline-block;"></span>
                        {{ $sku }}
                    </a>
                    <a href="https://www.backmarket.fr/bo-seller/listings/active?sku={{ $sku }}" title="View BM Ad" target="_blank">
                        - {{ $productModel }} {{ $storageName }} {{ $colorName }} {{ $gradeName }}
                    </a>
                </span>
                <a href="javascript:void(0)" class="btn btn-sm btn-link p-0" id="variation_history_{{ $variationId }}" onclick="show_variation_history({{ $variationId }}, '{{ $sku }} {{ $productModel }} {{ $storageName }} {{ $colorName }} {{ $gradeName }}')" title="View History">
                    <i class="fas fa-history"></i>
                </a>
                <a href="javascript:void(0)" class="btn btn-sm btn-link p-0" id="stock_formula_{{ $variationId }}" onclick="showStockFormulaModal({{ $variationId }})" title="Configure Stock Formula">
                    <i class="fe fe-percent"></i>
                </a>
            </h5>
            <div id="sales_{{ $variationId }}" class="small mb-1" style="opacity: 1; font-weight: bold;">
                <span>Loading sales data...</span>
            </div>
        </div>

        <div class="d-flex flex-column align-items-end gap-2">
            @include('v2.listing.partials.total-stock-form', [
                'variationId' => $variationId,
                'totalStock' => $totalStock,
                'availableCount' => $availableCount,
                'process_id' => $process_id ?? null
            ])
            
            {{-- Listing Total Quantity and Average Cost --}}
            <div class="d-flex flex-row align-items-center gap-2" style="font-size: 0.85rem;">
                {{-- <div class="text-muted">
                    <span>Listing Total: </span>
                    <strong id="listing_total_quantity_{{ $variationId }}">{{ $totalStock }}</strong>
                </div>
                <div class="text-muted">
                    <span>Average Cost: </span>
                    <strong id="average_cost_display_{{ $variationId }}">€{{ number_format($averageCost, 2, '.', '') }}</strong>
                </div> --}}
                {{-- ststs --}}
                <div class="d-flex align-items-center justify-content-start gap-2 flex-wrap">
                    <h6 class="mb-0">
                        <a class="" href="{{url('order').'?sku='}}{{$sku}}&status=2" target="_blank">
                            PO: {{ $pendingCount }}
                        </a>
                    </h6>
                    <span class="text-muted">|</span>
                    <h6 class="mb-0" id="available_stock_{{ $variationId }}">
                        <a href="{{url('inventory').'?product='}}{{$productId}}&storage={{$storageId}}&color={{$colorId}}&grade[]={{$gradeId}}" target="_blank">
                            AV: {{ $availableCount }}
                        </a>
                    </h6>
                    <span class="text-muted">|</span>
                    <h6 class="mb-0">DF: {{ $difference }}</h6>
                </div>
                {{-- expansion chevron right left --}}
                <a href="javascript:void(0)" class="btn btn-link p-0" id="stock_expand_toggle_{{ $variationId }}" onclick="toggleStockPanel({{ $variationId }})" style="min-width: 24px;">
                    <i class="fas fa-chevron-right" id="stock_expand_icon_{{ $variationId }}"></i>
                </a>
            </div>
        </div>

        

        {{-- Details toggle button removed - tables now shown in marketplace toggle sections --}}
    </div>
    
    {{-- Marketplace & Stocks Section (Always Expanded) --}}
    <div class="show" id="marketplace_stocks_dropdown_{{ $variationId }}">
        <div class="card-body border-top p-0" style="width: 100%;">
            <div class="d-flex g-0" style="width: 100%; margin: 0;">
                {{-- Left Column: Marketplace Bars --}}
                <div class="marketplace-column flex-grow-1" id="marketplace_column_{{ $variationId }}" style="transition: width 0.3s ease; min-width: 0;">
                    <div>
                        @if(isset($marketplaces) && count($marketplaces) > 0)
                            @foreach($marketplaces as $marketplaceId => $marketplace)
                                @php
                                    $marketplaceIdInt = (int)$marketplaceId;
                                    $isFirst = $loop->first;
                                @endphp
                                <div class="marketplace-bar-container" id="marketplace_bar_{{ $variationId }}_{{ $marketplaceIdInt }}" style="display: {{ $isFirst ? 'block' : 'none' }};">
                                    @include('v2.listing.partials.marketplace-bar', [
                                        'variation' => $variation,
                                        'variationId' => $variationId,
                                        'marketplace' => $marketplace,
                                        'marketplaceId' => $marketplaceId,
                                        'availableCount' => $availableCount,
                                        'process_id' => $process_id ?? null
                                    ])
                                </div>
                            @endforeach
                        @else
                            <div class="text-center text-muted p-3">
                                <small>No marketplaces available</small>
                            </div>
                        @endif
                    </div>
                </div>
                
                {{-- Expandable Right Side Stock Panel (Inside Card) --}}
                <div class="stock-panel" id="stock_panel_{{ $variationId }}" style="display: none; width: 0; overflow: hidden; transition: width 0.3s ease; border-left: 1px solid #dee2e6; flex-shrink: 0;">
                    <div class="d-flex flex-column h-100" style="width: 400px;">
                        <div class="p-3 border-bottom flex-shrink-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Stock List</h6>
                                <button type="button" class="btn btn-sm btn-link p-0" onclick="toggleStockPanel({{ $variationId }})" style="min-width: 24px;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="flex-grow-1 overflow-auto" style="overflow-y: auto;">
                            <div class="p-3 pt-2">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                        <thead>
                                            <tr>
                                                <th><small><b>No</b></small></th>
                                                <th><small><b>IMEI/Serial</b></small></th>
                                                <th><small><b>Cost</b> (<b id="average_cost_stocks_panel_{{ $variationId }}"></b>)</small></th>
                                            </tr>
                                        </thead>
                                        <tbody id="stocks_table_panel_{{ $variationId }}">
                                            <tr>
                                                <td colspan="3" class="text-center text-muted small">Loading stocks...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@once
<style>
    .marketplace-column {
        transition: width 0.3s ease;
    }
    .stock-panel {
        display: none;
        align-items: stretch;
    }
    .stock-panel.show {
        display: flex !important;
        width: 400px !important;
    }
</style>
<script>
    function toggleStockPanel(variationId) {
        const panel = $('#stock_panel_' + variationId);
        const icon = $('#stock_expand_icon_' + variationId);
        const marketplaceColumn = $('#marketplace_column_' + variationId);
        const isExpanded = panel.hasClass('show');
        
        if (isExpanded) {
            // Collapse
            panel.removeClass('show');
            icon.removeClass('fa-chevron-left').addClass('fa-chevron-right');
            // Reset marketplace column width to auto/full
            marketplaceColumn.css('width', '');
        } else {
            // Expand
            panel.addClass('show');
            icon.removeClass('fa-chevron-right').addClass('fa-chevron-left');
            // Set marketplace column width to make room for panel
            marketplaceColumn.css('width', 'calc(100% - 400px)');
            
            // Load stocks if not already loaded
            const tableBody = $('#stocks_table_panel_' + variationId);
            const firstRow = tableBody.find('tr:first');
            if (firstRow.length && firstRow.find('td').text().includes('Loading')) {
                loadStocksForPanel(variationId);
            }
        }
    }
    
    function loadStocksForPanel(variationId) {
        const stocksTableBody = $('#stocks_table_panel_' + variationId);
        const averageCostElement = $('#average_cost_stocks_panel_' + variationId);
        
        if (!window.ListingConfig || !window.ListingConfig.urls || !window.ListingConfig.urls.getVariationStocks) {
            stocksTableBody.html('<tr><td colspan="3" class="text-center text-danger small">Configuration error</td></tr>');
            return;
        }
        
        $.ajax({
            url: window.ListingConfig.urls.getVariationStocks + '/' + variationId,
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                let stocksTable = '';
                let stockPrices = [];
                
                if (data.stocks && data.stocks.length > 0) {
                    data.stocks.forEach(function(item, index) {
                        let price = data.stock_costs[item.id] || 0;
                        let topup_ref = data.topup_reference[data.latest_topup_items[item.id]] || '';
                        
                        // Collect price for average calculation
                        if (price) {
                            stockPrices.push(parseFloat(price));
                        }
                        
                        const imeiUrl = window.ListingConfig.urls.imei || '';
                        stocksTable += `
                            <tr>
                                <td><small>${index + 1}</small></td>
                                <td data-stock="${item.id}" title="${topup_ref}">
                                    <small>
                                        <a href="${imeiUrl}?imei=${item.imei || item.serial_number}" target="_blank">
                                            ${item.imei || item.serial_number || ''}
                                        </a>
                                    </small>
                                </td>
                                <td><small>€${price ? parseFloat(price).toFixed(2) : '0.00'}</small></td>
                            </tr>`;
                    });
                } else {
                    stocksTable = '<tr><td colspan="3" class="text-center text-muted small">No stocks available</td></tr>';
                }
                
                stocksTableBody.html(stocksTable);
                
                // Calculate and display average cost
                if (stockPrices.length > 0) {
                    let average = stockPrices.reduce((a, b) => parseFloat(a) + parseFloat(b), 0) / stockPrices.length;
                    averageCostElement.text(`€${average.toFixed(2)}`);
                } else {
                    averageCostElement.text('€0.00');
                }
            },
            error: function() {
                stocksTableBody.html('<tr><td colspan="3" class="text-center text-danger small">Error loading stocks</td></tr>');
                averageCostElement.text('€0.00');
            }
        });
    }
</script>
@endonce


