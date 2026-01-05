@extends('layouts.app')

@section('styles')
<link href="{{asset('assets/plugins/select2/css/select2.min.css')}}" rel="stylesheet" />
<link href="{{asset('assets/v2/listing/css/listing.css')}}" rel="stylesheet" />
@endsection

@section('content')

    <!-- Breadcrumb -->
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <h4>{{ $title_page ?? 'Listings V2' }}</h4>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                <li class="breadcrumb-item active" aria-current="page">Listings V2</li>
            </ol>
        </div>
    </div>
    <!-- /Breadcrumb -->

    @include('v2.listing.partials.filters')

    {{-- Page Controls --}}
    @include('v2.listing.partials.page-controls', [
        'variations' => $variations ?? null,
        'special' => request('special')
    ])

    @if(isset($variations) && $variations->count() > 0)
        @foreach($variations as $variation)
            @include('v2.listing.partials.variation-card', [
                'variation' => $variation,
                'colors' => $colors,
                'storages' => $storages,
                'grades' => $grades,
                'marketplaces' => $marketplaces ?? [],
                'process_id' => $process_id ?? null
            ])
        @endforeach

        {{-- Pagination --}}
        <div class="d-flex justify-content-center">
            {{ $variations->links() }}
        </div>
    @else
        <div class="card">
            <div class="card-body">
                <p class="text-muted">No variations found.</p>
            </div>
        </div>
    @endif


    {{-- Listing History Modal --}}
    @include('v2.listing.partials.listing-history-modal')
    {{-- /Listing History Modal --}}

    {{-- Bulk Update Modal --}}
    @include('v2.listing.partials.bulk-update-modal')
    {{-- /Bulk Update Modal --}}

    {{-- Stock Locks Modal --}}
    @include('v2.listing.partials.stock-locks-modal')
    {{-- /Stock Locks Modal --}}

    {{-- Stock Comparison Modal --}}
    @include('v2.listing.partials.stock-comparison-modal')
    {{-- /Stock Comparison Modal --}}
@endsection

@section('scripts')
<script>
    // Initialize global variables and configuration
    window.countries = {!! json_encode($countries ?? []) !!};
    window.marketplaces = {!! json_encode($marketplaces ?? []) !!};
    window.exchange_rates = {!! json_encode($exchange_rates ?? []) !!};
    window.currencies = {!! json_encode($currencies ?? []) !!};
    window.currency_sign = {!! json_encode($currency_sign ?? []) !!};
    window.eur_gbp = {!! json_encode($eur_gbp ?? 1) !!};
    window.processId = {{ $process_id ?? 'null' }};
    window.eur_listings = window.eur_listings || {};

    // Configure ListingConfig object with URLs and tokens
    window.ListingConfig = {
        urls: {
            getListingHistory: "{{ url('v2/listings/get_listing_history') }}",
            recordChange: "{{ url('v2/listings/record_change') }}",
            updatePrice: "{{ url('v2/listings/update_price') }}",
            updateLimit: "{{ url('v2/listings/update_limit') }}",
            updateMarketplaceHandlers: "{{ url('v2/listings/update_marketplace_handlers') }}",
            updateMarketplacePrices: "{{ url('v2/listings/update_marketplace_prices') }}",
            getListings: "{{ url('v2/listings/get_listings') }}",
            getVariationStocks: "{{ url('listing/get_variation_available_stocks') }}",
            getUpdatedQuantity: "{{ url('v2/listings/get_updated_quantity') }}",
            toggleEnable: "{{ url('listing/toggle_enable') }}",
            getSales: "{{ url('listing/get_sales') }}",
            getBuybox: "{{ url('listing/get_buybox') }}",
            export: "{{ url('listing/export') }}",
            getTargetVariations: "{{ url('listing/get_target_variations') }}",
            updateTarget: "{{ url('listing/update_target') }}",
            imei: "{{ url('imei') }}",
            getStockLocks: "{{ url('v2/stock-locks/api') }}",
            getMarketplaceStockComparison: "{{ url('v2/listings/get_marketplace_stock_comparison') }}",
            fixStockMismatch: "{{ url('v2/listings/fix_stock_mismatch') }}",
            restoreListingHistory: "{{ url('v2/listings/restore_history') }}"
        },
        csrfToken: "{{ csrf_token() }}",
        flagsPath: "{{ asset('assets/img/flags') }}"
    };

    // Global marketplace toggle state
    window.globalMarketplaceState = {
        @foreach($global_marketplace_counts ?? [] as $mpId => $mpData)
            {{ (int)$mpId }}: {{ $loop->first ? 'true' : 'false' }}, // Marketplace 1 is visible by default
        @endforeach
    };

    // Function to show stock locks modal
    function showStockLocksModal(variationId, marketplaceId) {
        const modal = new bootstrap.Modal(document.getElementById('stockLocksModal'));
        const modalBody = document.getElementById('stockLocksModalBody');
        
        // Show loading state
        modalBody.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2 text-muted">Loading stock locks...</p></div>';
        modal.show();
        
        // Load stock locks via API (returns rendered Blade template)
        $.ajax({
            url: '{{ url("v2/stock-locks/api") }}',
            type: 'GET',
            data: {
                variation_id: variationId,
                marketplace_id: marketplaceId,
                show_all: false
            },
            success: function(html) {
                modalBody.innerHTML = html;
            },
            error: function(xhr, status, error) {
                console.error('Error loading stock locks:', error);
                modalBody.innerHTML = '<div class="alert alert-danger">Error loading stock locks: ' + error + '</div>';
            }
        });
    }
</script>
<script src="{{asset('assets/v2/listing/js/total-stock-form.js')}}"></script>
<script src="{{asset('assets/v2/listing/js/keyboard-navigation.js')}}"></script>
<script src="{{asset('assets/v2/listing/js/price-validation.js')}}"></script>
<script src="{{asset('assets/v2/listing/js/page-controls.js')}}"></script>
<script src="{{asset('assets/v2/listing/js/bulk-updates.js')}}"></script>
<script src="{{asset('assets/v2/listing/js/listing.js')}}"></script>
@endsection
