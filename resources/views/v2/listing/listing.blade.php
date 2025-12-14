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

    {{-- Variation History Modal --}}
    <div class="modal fade" id="variationHistoryModal" tabindex="-1" aria-labelledby="variationHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="variation_name"></h5>
                    <h5 class="modal-title" id="variationHistoryModalLabel"> &nbsp; History</h5>
                    <button type="button" class="btn-close " data-bs-dismiss="modal" aria-label="Close">
                        <i data-feather="x" class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Topup Ref</th>
                                <th>Pending Orders</th>
                                <th>Qty Before</th>
                                <th>Qty Added</th>
                                <th>Qty After</th>
                                <th>Admin</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody id="variationHistoryTable">
                            <!-- Data will be populated here via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    {{-- /Variation History Modal --}}

    {{-- Listing History Modal --}}
    <div class="modal fade" id="listingHistoryModal" tabindex="-1" aria-labelledby="listingHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-lg-down" style="max-width: 95vw;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="listingHistoryModalLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i data-feather="x" class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th width="15%">Date</th>
                                    <th width="12%">Field</th>
                                    <th width="15%">Old Value</th>
                                    <th width="15%">New Value</th>
                                    <th width="10%">Change Type</th>
                                    <th width="13%">Changed By</th>
                                    <th width="20%">Reason</th>
                                </tr>
                            </thead>
                            <tbody id="listingHistoryTable">
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Loading history...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{-- /Listing History Modal --}}
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
            getVariationHistory: "{{ url('v2/listings/get_variation_history') }}",
            getListingHistory: "{{ url('v2/listings/get_listing_history') }}",
            recordChange: "{{ url('v2/listings/record_change') }}",
            updatePrice: "{{ url('v2/listings/update_price') }}",
            updateLimit: "{{ url('v2/listings/update_limit') }}",
            updateMarketplaceHandlers: "{{ url('v2/listings/update_marketplace_handlers') }}",
            updateMarketplacePrices: "{{ url('v2/listings/update_marketplace_prices') }}",
            getListings: "{{ url('v2/listings/get_listings') }}",
            getVariationStocks: "{{ url('listing/get_variation_available_stocks') }}",
            toggleEnable: "{{ url('listing/toggle_enable') }}",
            imei: "{{ url('imei') }}"
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
</script>
<script src="{{asset('assets/v2/listing/js/total-stock-form.js')}}"></script>
<script src="{{asset('assets/v2/listing/js/listing.js')}}"></script>
@endsection
