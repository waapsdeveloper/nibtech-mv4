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
    $listedStock = $variation->listed_stock ?? 0;
    $availableStocks = $variation->available_stocks ?? collect();
    $pendingOrders = $variation->pending_orders ?? collect();
    $pendingBmOrders = $variation->pending_bm_orders ?? collect();
    $availableCount = $availableStocks->count();
    $pendingCount = $pendingOrders->count();
    $pendingBmCount = $pendingBmOrders->count();
    $difference = $availableCount - $pendingCount;
    
    // State
    $state = 'Unknown';
    switch($variation->state ?? null) {
        case 0: $state = 'Missing price or comment'; break;
        case 1: $state = 'Pending validation'; break;
        case 2: $state = 'Online'; break;
        case 3: $state = 'Offline'; break;
        case 4: $state = 'Deactivated'; break;
    }
    
    // Get withoutBuybox HTML from variation data
    $withoutBuybox = $variation->withoutBuybox ?? '';
@endphp

<div class="card">
    <div class="card-header py-0 d-flex justify-content-between">
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
            <span id="sales_{{ $variationId }}">{!! $variation->sales_data ?? '' !!}</span>
        </div>

        <a href="javascript:void(0)" class="btn btn-link" id="variation_history_{{ $variationId }}" onclick="show_variation_history({{ $variationId }}, {{ json_encode($sku . ' ' . $productModel . ' ' . $storageName . ' ' . $colorName . ' ' . $gradeName) }})" data-bs-toggle="modal" data-bs-target="#variationHistoryModal">
            <i class="fas fa-history"></i>
        </a>

        <form class="form-inline wd-150" method="POST" id="add_qty_{{ $variationId }}" action="{{url('listing/add_quantity')}}/{{ $variationId }}">
            @csrf
            <input type="hidden" name="process_id" value="{{ $process_id ?? '' }}">
            <div class="form-floating">
                <input type="text" class="form-control" name="stock" id="quantity_{{ $variationId }}" value="{{ $listedStock }}" style="width:50px;" disabled>
                <label for="">Stock</label>
            </div>
            <div class="form-floating">
                <input type="number" class="form-control" name="stock" id="add_{{ $variationId }}" value="" style="width:60px;">
                <label for="">Add</label>
            </div>
            <button id="send_{{ $variationId }}" class="btn btn-light d-none">Push</button>
            <span class="text-success" id="success_{{ $variationId }}"></span>
        </form>

        <div class="text-center">
            <h6 class="mb-0">
                <a class="" href="{{url('order').'?sku='}}{{$sku}}&status=2" target="_blank">
                    Pending Order Items: {{ $pendingCount }} (BM Orders: {{ $pendingBmCount }})
                </a>
            </h6>
            <h6 class="mb-0" id="available_stock_{{ $variationId }}">
                <a href="{{url('inventory').'?product='}}{{$productId}}&storage={{$storageId}}&color={{$colorId}}&grade[]={{$gradeId}}" target="_blank">
                    Available: {{ $availableCount }}
                </a>
            </h6>
            <h6 class="mb-0">Difference: {{ $difference }}</h6>
        </div>

        {{-- Details toggle button removed - tables now shown in marketplace toggle sections --}}
    </div>
    <div class="d-flex justify-content-between">
        <div class="pt-3">
            <h6 class="d-inline">Without&nbsp;Buybox</h6>
            {!! $withoutBuybox !!}
        </div>
        <div class="pt-4">
            <h6 class="badge bg-light text-dark">
                {{ $state }}
            </h6>
        </div>
    </div>
    {{-- Details section removed - tables now shown in marketplace toggle sections --}}
    {{-- Marketplace Bars Section - Card Footer --}}
    @if(isset($marketplaces) && count($marketplaces) > 0)
        <div class="card-footer p-0 border-top mt-2">
            @foreach($marketplaces as $marketplaceId => $marketplace)
                @php
                    $marketplaceName = $marketplace->name ?? 'Marketplace ' . $marketplaceId;
                    $marketplaceListings = $variation->listings->where('marketplace_id', $marketplaceId) ?? collect();
                    $listingCount = $marketplaceListings->count();
                    $marketplaceNameWithCount = $marketplaceName . ' (' . $listingCount . ' ' . ($listingCount === 1 ? 'listing' : 'listings') . ')';
                    
                    // Calculate marketplace-specific values for form inputs
                    $minHandlerValue = '';
                    $handlerValue = '';
                    $minPriceValue = '';
                    $priceValue = '';
                    
                    if ($marketplaceListings->count() > 0) {
                        $minPriceLimits = $marketplaceListings->pluck('min_price_limit')->filter()->values();
                        if ($minPriceLimits->count() > 0) {
                            $minHandlerValue = $minPriceLimits->min();
                        }
                        
                        $priceLimits = $marketplaceListings->pluck('price_limit')->filter()->values();
                        if ($priceLimits->count() > 0) {
                            $handlerValue = $priceLimits->min();
                        }
                        
                        $minPrices = $marketplaceListings->pluck('min_price')->filter()->values();
                        if ($minPrices->count() > 0) {
                            $minPriceValue = $minPrices->min();
                        }
                        
                        $prices = $marketplaceListings->pluck('price')->filter()->values();
                        if ($prices->count() > 0) {
                            $priceValue = $prices->min();
                        }
                    }
                    
                    // Build buybox flags
                    $buyboxFlags = '';
                    $buyboxListingsForMarketplace = $marketplaceListings->where('buybox', 1);
                    
                    if ($buyboxListingsForMarketplace->count() > 0) {
                        foreach($buyboxListingsForMarketplace as $listing) {
                            $country = $listing->country_id ?? null;
                            if ($country && is_object($country)) {
                                $countryCode = $country->code ?? '';
                                $marketUrl = $country->market_url ?? '';
                                $marketCode = $country->market_code ?? '';
                                $referenceUuid2 = $listing->reference_uuid_2 ?? '';
                                
                                if ($countryCode) {
                                    $buyboxFlags .= '<a href="https://www.backmarket.' . $marketUrl . '/' . $marketCode . '/p/gb/' . $referenceUuid2 . '" target="_blank" class="btn btn-sm btn-link border p-1 m-1" title="View listing">
                                        <img src="' . asset('assets/img/flags/' . strtolower($countryCode) . '.svg') . '" height="10" alt="' . $countryCode . '">
                                        ' . $countryCode . '
                                    </a>';
                                }
                            }
                        }
                    }
                    
                    if (empty($buyboxFlags)) {
                        $buyboxFlags = '<span class="text-muted small">No buybox</span>';
                    }
                    
                    // Format order summary (static for now)
                    $orderSummary = '7 days: €0.00 (0) - 14 days: €0.00 (0) - 30 days: €0.00 (0)';
                @endphp
                
                <div class="marketplace-bar-wrapper border-bottom">
                    <div class="p-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="fw-bold">{{ $marketplaceNameWithCount }}</div>
                            <div class="d-flex align-items-center gap-2">
                                <div>{!! $buyboxFlags !!}</div>
                                <button class="btn btn-sm btn-link p-0" type="button" data-bs-toggle="collapse" data-bs-target="#marketplace_toggle_{{ $variationId }}_{{ $marketplaceId }}" aria-expanded="false" aria-controls="marketplace_toggle_{{ $variationId }}_{{ $marketplaceId }}" style="min-width: 24px;">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-start gap-2">
                                <form class="d-inline-flex gap-1 align-items-center" method="POST" id="change_all_handler_{{ $variationId }}_{{ $marketplaceId }}">
                                    @csrf
                                    <div class="form-floating" style="width: 75px;">
                                        <input type="number" class="form-control form-control-sm" id="all_min_handler_{{ $variationId }}_{{ $marketplaceId }}" name="all_min_handler" step="0.01" value="{{ $minHandlerValue }}" placeholder="Min" style="height: 31px;">
                                        <label for="" class="small">Min</label>
                                    </div>
                                    <div class="form-floating" style="width: 75px;">
                                        <input type="number" class="form-control form-control-sm" id="all_handler_{{ $variationId }}_{{ $marketplaceId }}" name="all_handler" step="0.01" value="{{ $handlerValue }}" placeholder="Handler" style="height: 31px;">
                                        <label for="" class="small">Handler</label>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-primary" style="height: 31px; line-height: 1;">Change</button>
                                </form>
                                <form class="d-inline-flex gap-1 align-items-center" method="POST" id="change_all_price_{{ $variationId }}_{{ $marketplaceId }}">
                                    @csrf
                                    <div class="form-floating" style="width: 75px;">
                                        <input type="number" class="form-control form-control-sm" id="all_min_price_{{ $variationId }}_{{ $marketplaceId }}" name="all_min_price" step="0.01" value="{{ $minPriceValue }}" placeholder="Min Price" style="height: 31px;">
                                        <label for="" class="small">Min Price</label>
                                    </div>
                                    <div class="form-floating" style="width: 75px;">
                                        <input type="number" class="form-control form-control-sm" id="all_price_{{ $variationId }}_{{ $marketplaceId }}" name="all_price" step="0.01" value="{{ $priceValue }}" placeholder="Price" style="height: 31px;">
                                        <label for="" class="small">Price</label>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-success" style="height: 31px; line-height: 1;">Push</button>
                                </form>
                            </div>
                            <div class="small fw-bold text-end">{{ $orderSummary }}</div>
                        </div>
                    </div>
                    <div class="marketplace-toggle-content collapse" id="marketplace_toggle_{{ $variationId }}_{{ $marketplaceId }}">
                        <div class="p-3 bg-light border-top marketplace-tables-container" data-loaded="false">
                            <div class="text-center p-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 text-muted small">Click to load tables...</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="card-footer mt-3 p-2 text-center text-muted border-top">
            <small>No marketplaces available</small>
        </div>
    @endif
</div>

