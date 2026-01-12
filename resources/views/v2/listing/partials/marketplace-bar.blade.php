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
    
    // Build buybox flags - show flags for listings that do NOT have buybox
    $buyboxFlags = '';
    $nonBuyboxListingsForMarketplace = $marketplaceListings->where('buybox', '!=', 1);
    
    if ($nonBuyboxListingsForMarketplace->count() > 0) {
        foreach($nonBuyboxListingsForMarketplace as $listing) {
            $country = $listing->country_id ?? null;
            if ($country && is_object($country)) {
                $countryCode = $country->code ?? '';
                $marketUrl = $country->market_url ?? '';
                $marketCode = $country->market_code ?? '';
                $referenceUuid2 = $listing->reference_uuid_2 ?? '';
                
                if ($countryCode) {
                    $buyboxFlags .= '<a href="https://www.backmarket.' . $marketUrl . '/' . $marketCode . '/p/gb/' . $referenceUuid2 . '" target="_blank" class="btn btn-sm btn-link border p-1 m-1 buybox-flag-link" title="View listing" style="border-color: #ffc0cb !important;">
                        <img src="' . asset('assets/img/flags/' . strtolower($countryCode) . '.svg') . '" height="10" alt="' . $countryCode . '">
                        ' . $countryCode . '
                    </a>';
                }
            }
        }
    }
    
    if (empty($buyboxFlags)) {
        $buyboxFlags = '<span class="text-muted small">All have buybox</span>';
    }
    
    // Get order summary from controller (calculated per marketplace)
    $orderSummary = $marketplaceData['order_summary'] ?? 'Today: €0.00 (0) - Yesterday: €0.00 (0) - 7 days: €0.00 (0) - 14 days: €0.00 (0) - 30 days: €0.00 (0)';
    
    // Get current listed stock from marketplace_stock table for this specific marketplace
    $marketplaceStock = \App\Models\V2\MarketplaceStockModel::where('variation_id', $variationId)
        ->where('marketplace_id', $marketplaceIdInt)
        ->first();
    
    // Calculate stock details for display
    $listedStock = 0;
    $availableStock = 0;
    $pendingStock = 0; // Locked stock (pending/reserved) - kept for reference but not used in display
    
    if ($marketplaceStock) {
        $listedStock = (int) ($marketplaceStock->listed_stock ?? 0);
        
        // Pending stock is the locked stock (reserved/pending) - kept for reference
        $pendingStock = (int) ($marketplaceStock->locked_stock ?? 0);
    }
    
    // Available stock comes from inventory (variation-level physical stock count)
    // This should be the SAME for all marketplaces - comes from available_stocks relationship
    // Use passed availableCount if provided, otherwise calculate from variation
    if (isset($availableCount)) {
        $availableStock = (int) $availableCount;
    } else {
        $availableStocks = $variation->available_stocks ?? collect();
        $availableStock = $availableStocks->count(); // Physical stock count from inventory
    }
    
    // Get locked stock for this variation and marketplace (for lock badge)
    $activeLocks = \App\Models\V2\MarketplaceStockLock::where('variation_id', $variationId)
        ->where('marketplace_id', $marketplaceIdInt)
        ->where('lock_status', 'locked')
        ->get();
    $totalLocked = $activeLocks->sum('quantity_locked');
    $lockedStockCount = $activeLocks->count();
    
    // Use totalLocked if it's more accurate than marketplaceStock->locked_stock
    if ($totalLocked > $pendingStock) {
        $pendingStock = $totalLocked;
    }
    
    // Calculate pending orders for this specific marketplace
    // For Backmarket (marketplace_id = 1), include orders with marketplace_id = 1 or null
    // For other marketplaces, only include orders with that specific marketplace_id
    $pendingOrdersCount = \App\Models\Order_model::whereHas('order_items', function($q) use ($variationId) {
            $q->where('variation_id', $variationId);
        })
        ->where(function($q) use ($marketplaceIdInt) {
            if ($marketplaceIdInt == 1) {
                // Backmarket: include orders with marketplace_id = 1 or null
                $q->whereNull('marketplace_id')
                  ->orWhere('marketplace_id', 1);
            } else {
                // Other marketplaces: only orders with this marketplace_id
                $q->where('marketplace_id', $marketplaceIdInt);
            }
        })
        ->where('status', 2)  // Pending status
        ->where('order_type_id', 3)  // Sales orders
        ->count();
    
    // Debug: Uncomment the line below to see what's being queried (remove after debugging)
    // {{-- Debug: variation_id={{ $variationId }}, marketplace_id={{ $marketplaceIdInt }}, found={{ $marketplaceStock ? 'yes' : 'no' }}, stock={{ $currentStock }} --}}
    
    // Calculate state for this marketplace (using variation state)
    $state = 'Unknown';
    switch($variation->state ?? null) {
        case 0: $state = 'Missing price or comment'; break;
        case 1: $state = 'Pending validation'; break;
        case 2: $state = 'Online'; break;
        case 3: $state = 'Offline'; break;
        case 4: $state = 'Deactivated'; break;
    }
@endphp

<div class="marketplace-bar-wrapper border-bottom">
    <div class="p-2">
        {{-- Line 1: Marketplace name, listing count, stock info, badges, buybox flags, and Listings button - Keep together on one line, wrap buybox flags on small screens --}}
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-2" style="gap: 0.5rem;">
            <div class="fw-bold d-flex align-items-center flex-wrap" style="gap: 0.5rem; flex: 1; min-width: 0;">
                <span id="marketplace_name_{{ $variationId }}_{{ $marketplaceId }}">{{ $marketplaceName }}</span>
                {{-- <span id="marketplace_count_{{ $variationId }}_{{ $marketplaceId }}" class="text-muted small"></span> --}}
                <span class="text-muted small d-flex align-items-center flex-wrap" style="gap: 0.25rem;">
                    <span class="text-primary" title="Listed Stock - Total allocated to this marketplace">
                        LS: <span id="listed_stock_{{ $variationId }}_{{ $marketplaceId }}">{{ $listedStock }}</span>
                    </span>
                    <span class="mx-1">|</span>
                    <span class="text-danger" title="Pending Orders - Orders for this marketplace">
                        PO: <span id="pending_orders_{{ $variationId }}_{{ $marketplaceId }}">{{ $pendingOrdersCount }}</span>
                    </span>
                </span>
                {{-- Real-time Backmarket Stock Badge (only for Backmarket) --}}
                @if($marketplaceIdInt === 1)
                    <span id="backmarket_stock_badge_{{ $variationId }}_{{ $marketplaceId }}" class="badge bg-secondary text-white" style="font-size: 0.7rem; font-weight: 600; letter-spacing: 0.5px;">
                        <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                        <span>Loading...</span>
                    </span>
                @endif
                {{-- Commented out: Locked stock badge - functionality preserved but hidden from UI --}}
                {{-- @if($totalLocked > 0)
                    <span class="badge bg-warning text-dark cursor-pointer" 
                          title="{{ $lockedStockCount }} active lock(s) - {{ $totalLocked }} units locked" 
                          data-bs-toggle="tooltip"
                          onclick="showStockLocksModal({{ $variationId }}, {{ $marketplaceIdInt }})"
                          style="cursor: pointer;">
                        <i class="fe fe-lock me-1"></i>{{ $totalLocked }} Locked
                    </span>
                @endif --}}
                {{-- Buybox flags - will wrap to next line on small screens only --}}
                <div class="d-flex align-items-center flex-wrap buybox-flags-container" style="gap: 0.25rem;">
                    {!! $buyboxFlags !!}
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <span class="badge bg-light text-dark d-flex align-items-center gap-1">
                    <span style="width: 8px; height: 8px; background-color: #28a745; border-radius: 50%; display: inline-block;"></span>
                    {{ $state }}
                </span>
                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#marketplace_toggle_{{ $variationId }}_{{ $marketplaceId }}" aria-expanded="false" aria-controls="marketplace_toggle_{{ $variationId }}_{{ $marketplaceId }}" style="min-width: 40px; padding: 6px 12px; font-weight: 600;">
                    <i class="fas fa-chevron-down me-1"></i>
                    <span>Listings</span>
                </button>
            </div>
        </div>
        
        {{-- Line 3: Forms and order summary - Can wrap on small screens --}}
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap: 0.5rem;">
            <div class="d-flex align-items-center flex-wrap" style="gap: 5.5rem;">
                <form class="d-inline-flex gap-1 align-items-center" method="POST" id="change_all_handler_{{ $variationId }}_{{ $marketplaceId }}" onsubmit="return false;">
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
                <form class="d-inline-flex gap-1 align-items-center" method="POST" id="change_all_price_{{ $variationId }}_{{ $marketplaceId }}" onsubmit="return false;">
                    @csrf
                    <div class="form-floating" style="width: 75px;">
                        <input type="number" class="form-control form-control-sm" id="all_min_price_{{ $variationId }}_{{ $marketplaceId }}" name="all_min_price" step="0.01" value="{{ $minPriceValue }}" placeholder="Min Price" style="height: 31px;">
                        <label for="" class="small">Min</label>
                    </div>
                    <div class="form-floating" style="width: 75px;">
                        <input type="number" class="form-control form-control-sm" id="all_price_{{ $variationId }}_{{ $marketplaceId }}" name="all_price" step="0.01" value="{{ $priceValue }}" placeholder="Price" style="height: 31px;">
                        <label for="" class="small">Price</label>
                    </div>
                    <button type="button" class="btn btn-sm btn-success" style="height: 31px; line-height: 1;">Push</button>
                </form>
            </div>
            <div class="small fw-bold text-end" style="min-width: fit-content;">
                @php
                    // Split order summary into parts
                    $parts = explode(' - ', $orderSummary);
                    // First line: Today and Yesterday (first 2 parts)
                    $firstLine = implode(' - ', array_slice($parts, 0, 2));
                    // Second line: 7 days, 14 days, 30 days (remaining parts)
                    $secondLine = implode(' - ', array_slice($parts, 2));
                @endphp
                <div>{{ $firstLine }}</div>
                <div>{{ $secondLine }}</div>
            </div>
        </div>
    </div>
    <div class="marketplace-toggle-content collapse" id="marketplace_toggle_{{ $variationId }}_{{ $marketplaceId }}">
        <div class="border-top marketplace-tables-container" data-loaded="false">
            <div class="text-center p-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted small">Click to load tables...</p>
            </div>
        </div>
    </div>
</div>

@once
<style>
    /* Responsive buybox flags - wrap to next line only on small screens */
    .buybox-flags-container {
        flex-wrap: nowrap;
    }
    
    /* On small screens (below 768px), allow buybox flags to wrap to next line */
    @media (max-width: 767.98px) {
        .buybox-flags-container {
            flex-basis: 100%;
            margin-top: 0.5rem;
        }
    }
    
    /* On very small screens (below 576px), ensure better wrapping */
    @media (max-width: 575.98px) {
        .marketplace-bar-wrapper .d-flex.flex-wrap {
            flex-direction: column;
            align-items: flex-start !important;
        }
        
        .buybox-flags-container {
            width: 100%;
            margin-top: 0.5rem;
        }
    }
</style>
@endonce

@if($marketplaceIdInt === 1)
<script>
    // Fetch Backmarket stock when marketplace bar is rendered
    (function() {
        const variationId = {{ $variationId }};
        const marketplaceId = {{ $marketplaceIdInt }};
        
        // Wait for DOM and functions to be ready
        $(document).ready(function() {
            // Small delay to ensure listing.js is loaded
            setTimeout(function() {
                if (typeof window.fetchBackmarketStockQuantity === 'function') {
                    window.fetchBackmarketStockQuantity(variationId, marketplaceId)
                        .then(function(quantity) {
                            if (quantity !== null && typeof window.updateBackmarketStockBadge === 'function') {
                                window.updateBackmarketStockBadge(variationId, marketplaceId, quantity);
                            }
                        });
                }
            }, 100);
        });
    })();
</script>
@endif

