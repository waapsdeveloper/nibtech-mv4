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
    // Calculate total stock from all marketplaces
    $totalStock = 0;
    if(isset($marketplaces) && count($marketplaces) > 0) {
        foreach($marketplaces as $mpId => $mp) {
            $marketplaceIdInt = (int)$mpId;
            $marketplaceStock = \App\Models\MarketplaceStockModel::where('variation_id', $variationId)
                ->where('marketplace_id', $marketplaceIdInt)
                ->first();
            if($marketplaceStock) {
                $totalStock += $marketplaceStock->listed_stock ?? 0;
            }
        }
    }
    // Fallback to variation.listed_stock if no marketplace stock exists yet
    if($totalStock == 0) {
        $totalStock = $variation->listed_stock ?? 0;
    }
    $availableStocks = $variation->available_stocks ?? collect();
    $pendingOrders = $variation->pending_orders ?? collect();
    $pendingBmOrders = $variation->pending_bm_orders ?? collect();
    $availableCount = $availableStocks->count();
    $pendingCount = $pendingOrders->count();
    $pendingBmCount = $pendingBmOrders->count();
    $difference = $availableCount - $pendingCount;
    
    // Get withoutBuybox HTML from variation data
    $withoutBuybox = $variation->withoutBuybox ?? '';
@endphp

<div class="card" style="padding-left: 5px; padding-right: 5px; width: 100%;">
    <div class="card-header py-0 d-flex justify-content-between" style="padding-left: 5px; padding-right: 5px;">
        <div>
            <h5>
                <a href="{{url('inventory')}}?sku={{ $sku }}" title="View Inventory" target="_blank">
                    <span style="background-color: {{ $colorCode }}; width: 30px; height: 16px; display: inline-block;"></span>
                    {{ $sku }}
                </a>
                <a href="https://www.backmarket.fr/bo-seller/listings/active?sku={{ $sku }}" title="View BM Ad" target="_blank">
                    - {{ $productModel }} {{ $storageName }} {{ $colorName }} {{ $gradeName }}
                </a>
            </h5>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="d-flex align-items-center justify-content-start gap-2 flex-wrap">
                    <h6 class="mb-0">
                        <a class="" href="{{url('order').'?sku='}}{{$sku}}&status=2" target="_blank">
                            Pending Order Items: {{ $pendingCount }} (BM Orders: {{ $pendingBmCount }})
                        </a>
                    </h6>
                    <span class="text-muted">|</span>
                    <h6 class="mb-0" id="available_stock_{{ $variationId }}">
                        <a href="{{url('inventory').'?product='}}{{$productId}}&storage={{$storageId}}&color={{$colorId}}&grade[]={{$gradeId}}" target="_blank">
                            Available: {{ $availableCount }}
                        </a>
                    </h6>
                    <span class="text-muted">|</span>
                    <h6 class="mb-0">Difference: {{ $difference }}</h6>
                </div>
                <a href="javascript:void(0)" class="btn btn-link" id="variation_history_{{ $variationId }}" onclick="show_variation_history({{ $variationId }}, {{ json_encode($sku . ' ' . $productModel . ' ' . $storageName . ' ' . $colorName . ' ' . $gradeName) }})" data-bs-toggle="modal" data-bs-target="#variationHistoryModal">
                    <i class="fas fa-history"></i>
                </a>
            </div>
        </div>

        <div class="d-flex flex-column align-items-end gap-2">
            @include('v2.listing.partials.total-stock-form', [
                'variationId' => $variationId,
                'totalStock' => $totalStock,
                'process_id' => $process_id ?? null
            ])
        </div>

        

        {{-- Details toggle button removed - tables now shown in marketplace toggle sections --}}
    </div>
    
    {{-- Marketplace & Stocks Dropdown Section --}}
    <div class="collapse" id="marketplace_stocks_dropdown_{{ $variationId }}">
        <div class="card-body border-top p-0" style="width: 100%;">
            <div class="row g-0" style="width: 100%; margin: 0;">
                {{-- Left Column: Marketplace Bars --}}
                <div class="col-md-8 border-end" style="max-height: 600px; overflow-y: auto;">
                    <div class="">
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
                
                {{-- Right Column: Stocks Section --}}
                <div class="col-md-4" style="max-height: 600px; overflow-y: auto;">
                    @include('v2.listing.partials.marketplace-stocks-section', [
                        'variationId' => $variationId,
                        'marketplaces' => $marketplaces ?? [],
                        'process_id' => $process_id ?? null
                    ])
                </div>
            </div>
        </div>
    </div>
</div>


