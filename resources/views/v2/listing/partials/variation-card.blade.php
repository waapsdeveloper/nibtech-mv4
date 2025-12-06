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
        </div>

        <div class="d-flex flex-column align-items-end gap-2">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                @if(isset($marketplaces) && count($marketplaces) > 0)
                    <div class="d-flex align-items-center gap-1 flex-wrap">
                        @foreach($marketplaces as $mpId => $mp)
                            @php
                                $marketplaceIdInt = (int)$mpId;
                                $marketplaceData = $variation->marketplace_data[$marketplaceIdInt] ?? null;
                                $mpName = $marketplaceData['name'] ?? ($mp->name ?? 'Marketplace ' . $marketplaceIdInt);
                                $marketplaceListings = $marketplaceData['listings'] ?? collect();
                                $listingCount = $marketplaceListings->count();
                                $listingCountText = '(' . $listingCount . ')';
                                $isFirst = $loop->first;
                            @endphp
                            <span 
                                class="badge marketplace-toggle-badge {{ $isFirst ? 'badge-active' : 'badge-inactive' }}" 
                                style="cursor: pointer; user-select: none; background-color: transparent; border: 1px solid {{ $isFirst ? '#28a745' : '#000' }}; color: {{ $isFirst ? '#28a745' : '#000' }}; font-size: 0.9rem; font-weight: 500; padding: 0.35em 0.65em;"
                                data-marketplace-id="{{ $marketplaceIdInt }}"
                                data-variation-id="{{ $variationId }}"
                                onclick="toggleMarketplace({{ $variationId }}, {{ $marketplaceIdInt }}, this)"
                                title="Click to show/hide {{ $mpName }}">
                                {{ $mpName }} <span style="opacity: 0.8;">{{ $listingCountText }}</span>
                            </span>
                        @endforeach
                    </div>
                @endif

                <span class="badge bg-light text-dark d-flex align-items-center gap-1">
                    <span style="width: 8px; height: 8px; background-color: #28a745; border-radius: 50%; display: inline-block;"></span>
                    {{ $state }}
                </span>
                <a href="javascript:void(0)" class="btn btn-link" id="variation_history_{{ $variationId }}" onclick="show_variation_history({{ $variationId }}, {{ json_encode($sku . ' ' . $productModel . ' ' . $storageName . ' ' . $colorName . ' ' . $gradeName) }})" data-bs-toggle="modal" data-bs-target="#variationHistoryModal">
                    <i class="fas fa-history"></i>
                </a>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="form-floating">
                    <input type="text" class="form-control" id="total_stock_{{ $variationId }}" value="{{ $totalStock }}" style="width:140px;" readonly disabled>
                    <label for="">Total Stock</label>
                </div>
            </div>
        </div>

        

        {{-- Details toggle button removed - tables now shown in marketplace toggle sections --}}
    </div>
    {{-- Details section removed - tables now shown in marketplace toggle sections --}}
    {{-- Marketplace Bars Section - Card Footer --}}
    @if(isset($marketplaces) && count($marketplaces) > 0)
        <div class="card-footer p-0 border-top mt-2">
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
        </div>
    @else
        <div class="card-footer mt-3 p-2 text-center text-muted border-top">
            <small>No marketplaces available</small>
        </div>
    @endif
</div>

<script>
function toggleMarketplace(variationId, marketplaceId, badgeElement) {
    const marketplaceBar = document.getElementById('marketplace_bar_' + variationId + '_' + marketplaceId);
    
    if (marketplaceBar) {
        const isVisible = marketplaceBar.style.display !== 'none';
        
        if (isVisible) {
            // Hide the marketplace
            marketplaceBar.style.display = 'none';
            badgeElement.style.borderColor = '#000';
            badgeElement.style.color = '#000';
            badgeElement.classList.remove('badge-active');
            badgeElement.classList.add('badge-inactive');
        } else {
            // Show the marketplace
            marketplaceBar.style.display = 'block';
            badgeElement.style.borderColor = '#28a745';
            badgeElement.style.color = '#28a745';
            badgeElement.classList.remove('badge-inactive');
            badgeElement.classList.add('badge-active');
        }
    }
}
</script>

