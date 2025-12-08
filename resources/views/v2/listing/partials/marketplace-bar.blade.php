@php
    // Get marketplace data from controller (name and count)
    // Ensure marketplaceId is integer for consistent key matching
    $marketplaceIdInt = (int)$marketplaceId;
    $marketplaceData = $variation->marketplace_data[$marketplaceIdInt] ?? null;
    $marketplaceName = $marketplaceData['name'] ?? ($marketplace->name ?? 'Marketplace ' . $marketplaceIdInt);
    // Count will be updated dynamically after tables load
    $listingCount = 0; // Initial count, will be updated via JavaScript
    
    // Get listings for this marketplace - use the filtered list from controller
    $marketplaceListings = $marketplaceData['listings'] ?? collect();
    
    // Form inputs are empty by default - user must manually enter values
    $minHandlerValue = '';
    $handlerValue = '';
    $minPriceValue = '';
    $priceValue = '';
    
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
    
    // Get order summary from controller (calculated per marketplace)
    $orderSummary = $marketplaceData['order_summary'] ?? '7 days: €0.00 (0) - 14 days: €0.00 (0) - 30 days: €0.00 (0)';
    
    // Get current listed stock from marketplace_stock table for this specific marketplace
    $marketplaceStock = \App\Models\MarketplaceStockModel::where('variation_id', $variationId)
        ->where('marketplace_id', $marketplaceIdInt)
        ->first();
    $currentStock = $marketplaceStock->listed_stock ?? 0;
@endphp

<div class="marketplace-bar-wrapper border-bottom">
    <div class="p-2">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-bold">
                <span id="marketplace_name_{{ $variationId }}_{{ $marketplaceId }}">{{ $marketplaceName }}</span>
                <span id="marketplace_count_{{ $variationId }}_{{ $marketplaceId }}" class="text-muted small"></span>
            </div>
            <div class="d-flex align-items-center gap-2">

                
                <div>{!! $buyboxFlags !!}</div>

                <!-- Marketplace Stock Editor Component -->
                @include('v2.listing.partials.marketplace-stock-editor', [
                    'variationId' => $variationId,
                    'marketplaceIdInt' => $marketplaceIdInt,
                    'currentStock' => $currentStock,
                    'process_id' => $process_id ?? null
                ])

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

