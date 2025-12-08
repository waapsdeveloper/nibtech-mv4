@extends('layouts.app')

@section('styles')
<link href="{{asset('assets/plugins/select2/css/select2.min.css')}}" rel="stylesheet" />
    <style>
        /* Chrome, Safari, Edge, Opera */
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
        }

        /* Firefox */
        input[type=number] {
        -moz-appearance: textfield;
        }
        .card {
            border: 1px solid #016a5949;
            border-radius: 8px;
            margin-bottom: 20px;
            padding: 15px;
            transition: box-shadow 0.3s;
        }
        .card:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        .card-title {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        .card-body {
            font-size: 1rem;
            color: #333;
            display: flex;
            direction: rtl;
        }
        .card-body * {
            direction: ltr;
        }
        .table-responsive {
            max-height: 805px;
            overflow: scroll;
        }
        .breadcrumb-header {
            padding: 15px;
            background-color: #f8f9fa;
        }
.form-floating>.form-control,
.form-floating>.form-control-plaintext,
.form-floating>.form-select {
  height: calc(2.3rem + 2px) !important;
}

/* Disabled listing row styles */
tr.listing-disabled {
    position: relative;
    opacity: 0.5;
}

tr.listing-disabled td:not(:last-child) {
    position: relative;
}

tr.listing-disabled td:not(:last-child)::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(255, 255, 255, 0.7);
    z-index: 100;
    pointer-events: all;
    cursor: not-allowed;
}

/* Keep action column interactive */
tr.listing-disabled td:last-child {
    pointer-events: auto;
    opacity: 1;
    position: relative;
    z-index: 101;
}

tr.listing-disabled td:not(:last-child) a,
tr.listing-disabled td:not(:last-child) input,
tr.listing-disabled td:not(:last-child) form,
tr.listing-disabled td:not(:last-child) button {
    pointer-events: none;
    cursor: not-allowed;
}

tr.listing-disabled input[type="number"],
tr.listing-disabled input[type="text"] {
    cursor: not-allowed;
    background-color: #e9ecef;
}
    </style>
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

    <!-- Filters Section -->
    <form method="GET" action="{{ route('v2.view_listing') }}" id="filterForm">
        <div class="row">
            <div class="col-md col-sm-6">
                <div class="form-floating">
                    <input type="text" class="form-control" id="reference_id" name="reference_id" placeholder="Enter IMEI" value="@isset($_GET['reference_id']){{$_GET['reference_id']}}@endisset">
                    <label for="reference_id">Reference ID</label>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="form-floating">
                    <input type="text" class="form-control" id="product_name" name="product_name" placeholder="Enter Product Name" value="@isset($_GET['product_name']){{$_GET['product_name']}}@endisset">
                    <label for="product_name">Search</label>
                </div>
            </div>
            <div class="col-md col-sm-6">
                <div class="form-floating">
                    <input type="text" id="product" name="product" list="products" class="form-control" data-bs-placeholder="Select Status" value="@isset($_GET['product']){{$_GET['product']}}@endisset">
                    <label for="product">Product</label>
                </div>
                <datalist id="products">
                    @foreach ($products as $product)
                        <option value="{{$product->id}}" @if(isset($_GET['product']) && $product->id == $_GET['product']) {{'selected'}}@endif>{{$product->model}}</option>
                    @endforeach
                </datalist>
            </div>
            <div class="col-md col-sm-6">
                <div class="form-floating">
                    <input type="text" class="form-control" name="sku" placeholder="Enter IMEI" value="@isset($_GET['sku']){{$_GET['sku']}}@endisset">
                    <label for="">SKU</label>
                </div>
            </div>
            <div class="col-md col-sm-6">
                <select name="color" class="form-control form-select" data-bs-placeholder="Select Status">
                    <option value="">Color</option>
                    @foreach ($colors as $id => $color)
                        <option value="{{$id}}" @if(isset($_GET['color']) && $id == $_GET['color']) {{'selected'}}@endif>{{$color}}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md col-sm-6">
                <select name="storage" class="form-control form-select" data-bs-placeholder="Select Status">
                    <option value="">Storage</option>
                    @foreach ($storages as $id => $storage)
                        <option value="{{$id}}" @if(isset($_GET['storage']) && $id == $_GET['storage']) {{'selected'}}@endif>{{$storage}}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 col-sm-6">
                <select name="grade[]" class="form-control form-select select2" data-bs-placeholder="Select Status" multiple>
                    <option value="">Grade</option>
                    @foreach ($grades as $id => $grade)
                        <option value="{{$id}}" @if(isset($_GET['grade']) && in_array($id,$_GET['grade'])) {{'selected'}}@endif>{{$grade}}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <br>
        <div class="row">
            <div class="col-md col-sm-6">
                {{-- <div class="card-header">
                    <h4 class="card-title mb-1">Category</h4>
                </div> --}}
                <select name="category" class="form-control form-select" data-bs-placeholder="Select Category">
                    <option value="">Category</option>
                    @foreach ($categories as $category)
                        <option value="{{$category->id}}" @if(isset($_GET['category']) && $category->id == $_GET['category']) {{'selected'}}@endif>{{$category->name}}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md col-sm-6">
                {{-- <div class="card-header">
                    <h4 class="card-title mb-1">Brand</h4>
                </div> --}}
                <select name="brand" class="form-control form-select" data-bs-placeholder="Select Brand">
                    <option value="">Brand</option>
                    @foreach ($brands as $brand)
                        <option value="{{$brand->id}}" @if(isset($_GET['brand']) && $brand->id == $_GET['brand']) {{'selected'}}@endif>{{$brand->name}}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md col-sm-6">
                <select name="listed_stock" class="form-control form-select" data-bs-placeholder="Select listed Stock">
                    <option value="">Listed Stock</option>
                    <option value="1" @if(isset($_GET['listed_stock']) && $_GET['listed_stock'] == 1) {{'selected'}}@endif>With Listing</option>
                    <option value="2" @if(isset($_GET['listed_stock']) && $_GET['listed_stock'] == 2) {{'selected'}}@endif>Without Listing</option>
                </select>
            </div>
            <div class="col-md col-sm-6">
                <select name="available_stock" class="form-control form-select" data-bs-placeholder="Select Available Stock">
                    <option value="">Available Stock</option>
                    <option value="1" @if(isset($_GET['available_stock']) && $_GET['available_stock'] == 1) {{'selected'}}@endif>With Stock</option>
                    <option value="2" @if(isset($_GET['available_stock']) && $_GET['available_stock'] == 2) {{'selected'}}@endif>Without Stock</option>
                </select>
            </div>
            <div class="col-md col-sm-6">
                <select name="state" class="form-control form-select" data-bs-placeholder="Select Publication State">
                    <option value="">Published</option>
                    <option value="10" @if(isset($_GET['state']) && $_GET['state'] == 10) {{'selected'}}@endif>All</option>
                    <option value="0" @if(isset($_GET['state']) && $_GET['state'] == 0) {{'selected'}}@endif>Missing price or comment</option>
                    <option value="1" @if(isset($_GET['state']) && $_GET['state'] == 1) {{'selected'}}@endif>Pending validation</option>
                    <option value="2" @if(isset($_GET['state']) && $_GET['state'] == 2) {{'selected'}}@endif>Online</option>
                    <option value="3" @if(isset($_GET['state']) && $_GET['state'] == 3) {{'selected'}}@endif>Offline</option>
                    <option value="4" @if(isset($_GET['state']) && $_GET['state'] == 4) {{'selected'}}@endif>Deactivated</option>
                </select>
            </div>
            <div class="col-md col-sm-6">
                <select name="handler_status" class="form-select" data-bs-placeholder="Select Handler Status">
                    <option value="">Price Handler</option>
                    <option value="1" @if(isset($_GET['handler_status']) && $_GET['handler_status'] == 1) {{'selected'}}@endif>Active</option>
                    <option value="2" @if(isset($_GET['handler_status']) && $_GET['handler_status'] == 2) {{'selected'}}@endif>Inactive</option>
                    <option value="3" @if(isset($_GET['handler_status']) && $_GET['handler_status'] == 3) {{'selected'}}@endif>ReActived</option>
                </select>
            </div>
            <div class="col-md col-sm-6">
                {{-- <div class="card-header">
                    <h4 class="card-title mb-1">Brand</h4>
                </div> --}}
                <select name="marketplace" class="form-control form-select" data-bs-placeholder="Select Marketplace">
                    <option value="">Marketplace</option>
                    @foreach ($marketplaces_dropdown as $id => $name)
                        <option value="{{$id}}" @if(isset($_GET['marketplace']) && $id == $_GET['marketplace']) {{'selected'}}@endif>{{$name}}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <br>
        <div class="row mb-3">
            <!-- marketplaces toggle section -->
            <div class="d-flex flex-row justify-content-between align-items-center w-100 gap-2 flex-wrap">
                @if(isset($global_marketplace_counts) && count($global_marketplace_counts) > 0)
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        @foreach($global_marketplace_counts as $mpId => $mpData)
                            @php
                                $marketplaceIdInt = (int)$mpId;
                                $mpName = $mpData['name'] ?? 'Marketplace ' . $marketplaceIdInt;
                                $totalCount = $mpData['total_count'] ?? 0;
                                $listingCountText = '(' . $totalCount . ')';
                                $isFirst = $loop->first; // Marketplace 1 is active by default
                            @endphp
                            <span 
                                class="badge global-marketplace-toggle-badge {{ $isFirst ? 'badge-active' : 'badge-inactive' }}" 
                                style="cursor: pointer; user-select: none; background-color: transparent; border: 1px solid {{ $isFirst ? '#28a745' : '#000' }}; color: {{ $isFirst ? '#28a745' : '#000' }}; font-size: 0.9rem; font-weight: 500; padding: 0.35em 0.65em;"
                                data-marketplace-id="{{ $marketplaceIdInt }}"
                                onclick="toggleGlobalMarketplace({{ $marketplaceIdInt }}, this)"
                                title="Click to show/hide {{ $mpName }} for all listings">
                                {{ $mpName }} <span style="opacity: 0.8;">{{ $listingCountText }}</span>
                            </span>
                        @endforeach
                    </div>
                @endif
                
                <div class="d-flex flex-row align-items-center gap-2 flex-wrap">
                    <div class="form-check form-switch d-flex align-items-center gap-2">
                        <input class="form-check-input" type="checkbox" id="sale_40" name="sale_40" value="1" @if (request('sale_40')) {{'checked'}} @endif>
                        <label class="form-check-label" for="sale_40">Sales Below 5%</label>
                    </div>
                    <button class="btn btn-primary pd-x-20" type="submit">{{ __('locale.Search') }}</button>
                    <a href="{{ route('v2.view_listing') }}?per_page=10" class="btn btn-default pd-x-20">Reset</a>
                </div>
            </div>
        </div>

        <input type="hidden" name="page" value="{{ Request::get('page') }}">
        <input type="hidden" name="per_page" value="{{ Request::get('per_page') }}">
        <input type="hidden" name="sort" value="{{ Request::get('sort') ?? 4 }}">
        @if(Request::get('process_id'))
            <input type="hidden" name="process_id" value="{{ Request::get('process_id') }}">
        @endif
    </form>
    <!-- /Filters Section -->

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
@endsection

@section('scripts')
<script>
    // Make global variables available
    let countries = {!! json_encode($countries ?? []) !!};
    let marketplaces = {!! json_encode($marketplaces ?? []) !!};
    let exchange_rates = {!! json_encode($exchange_rates ?? []) !!};
    let currencies = {!! json_encode($currencies ?? []) !!};
    let currency_sign = {!! json_encode($currency_sign ?? []) !!};
    let eur_gbp = {!! json_encode($eur_gbp ?? 1) !!};
    window.eur_listings = window.eur_listings || {};

    function show_variation_history(variationId, variationName) {
        $('#variationHistoryModal').modal('show');

        $('#variation_name').text(variationName);
        $('#variationHistoryTable').html('Loading...');
        $.ajax({
            url: "{{ url('v2/listings/get_variation_history') }}/" + variationId,
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                let historyTable = '';
                if (data.listed_stock_verifications && data.listed_stock_verifications.length > 0) {
                    data.listed_stock_verifications.forEach(function(item) {
                        historyTable += `
                            <tr>
                                <td>${item.process_ref ?? ''}</td>
                                <td>${item.pending_orders ?? ''}</td>
                                <td>${item.qty_from ?? ''}</td>
                                <td>${item.qty_change ?? ''}</td>
                                <td>${item.qty_to ?? ''}</td>
                                <td>${item.admin ?? ''}</td>
                                <td>${item.created_at ? new Date(item.created_at).toLocaleString('en-GB', { timeZone: 'Europe/London', hour12: true }) : ''}</td>
                            </tr>`;
                    });
                } else {
                    historyTable = '<tr><td colspan="7" class="text-center text-muted">No history found</td></tr>';
                }
                $('#variationHistoryTable').html(historyTable);
            },
            error: function(xhr) {
                console.error(xhr.responseText);
                $('#variationHistoryTable').html('<tr><td colspan="7" class="text-center text-danger">Error loading history</td></tr>');
            }
        });
    }

    // Load marketplace tables when toggle is opened
    $(document).on('show.bs.collapse', '.marketplace-toggle-content', function() {
        const toggleElement = $(this);
        const container = toggleElement.find('.marketplace-tables-container');
        
        // Check if already loaded
        if (container.data('loaded') === true) {
            return;
        }
        
        // Extract variationId and marketplaceId from the toggle ID
        const toggleId = toggleElement.attr('id');
        const matches = toggleId.match(/marketplace_toggle_(\d+)_(\d+)/);
        if (!matches) {
            return;
        }
        
        const variationId = parseInt(matches[1]);
        const marketplaceId = parseInt(matches[2]);
        
        // Show loading state
        container.html('<div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2 text-muted">Loading tables...</p></div>');
        
        // Load listings for this marketplace
        loadMarketplaceTables(variationId, marketplaceId);
    });

    function loadMarketplaceTables(variationId, marketplaceId) {
        const container = $(`#marketplace_toggle_${variationId}_${marketplaceId} .marketplace-tables-container`);
        
        // Get listings for this marketplace
        $.ajax({
            url: "{{ url('v2/listings/get_listings') }}/" + variationId,
            type: 'GET',
            dataType: 'json',
            data: { marketplace_id: marketplaceId },
            success: function(data) {
                let listingsTable = '';
                let m_min_price = 0;
                let m_price = 0;
                
                // Clear eur_listings for this variation
                window.eur_listings[variationId] = [];
                
                // Calculate min prices from listings with country 73 (EUR)
                const eurListings = data.listings.filter(listing => {
                    const country = listing.country_id || (listing.country && countries[listing.country]);
                    return country && (country.id === 73 || listing.country === 73);
                });
                
                if (eurListings.length > 0) {
                    const minPrices = eurListings.map(l => parseFloat(l.min_price) || 0).filter(p => p > 0);
                    const prices = eurListings.map(l => parseFloat(l.price) || 0).filter(p => p > 0);
                    m_min_price = minPrices.length > 0 ? Math.min(...minPrices) : 0;
                    m_price = prices.length > 0 ? Math.min(...prices) : 0;
                }
                
                // Filter listings by marketplace_id
                const marketplaceListings = data.listings.filter(function(listing) {
                    return listing.marketplace_id == marketplaceId;
                });
                
                // Update marketplace count after loading
                const listingCount = marketplaceListings.length;
                const countText = listingCount > 0 ? ` (${listingCount} ${listingCount === 1 ? 'listing' : 'listings'})` : '';
                $(`#marketplace_count_${variationId}_${marketplaceId}`).text(countText);
                
                if (marketplaceListings.length === 0) {
                    listingsTable = '<tr><td colspan="8" class="text-center text-muted">No listings found for this marketplace</td></tr>';
                } else {
                    marketplaceListings.forEach(function(listing) {
                        let best_price = $(`#best_price_${variationId}_${marketplaceId}`).text().replace('€', '') || 0;
                        let exchange_rates_2 = exchange_rates;
                        let currencies_2 = currencies;
                        let currency_sign_2 = currency_sign;
                        let p_append = '';
                        let pm_append = '';
                        let pm_append_title = '';
                        let classs = '';
                        
                        // Handle currency conversions
                        if (listing.currency_id != 4) {
                            let rates = exchange_rates_2[currencies_2[listing.currency_id]];
                            if (rates) {
                                p_append = 'Fr: ' + currency_sign_2[listing.currency_id] + (parseFloat(m_price) * parseFloat(rates)).toFixed(2);
                                pm_append = 'Fr: ' + currency_sign_2[listing.currency_id] + (parseFloat(m_min_price) * parseFloat(rates)).toFixed(2);
                                pm_append_title = 'Break Even: ' + currency_sign_2[listing.currency_id] + (parseFloat(best_price) * parseFloat(rates)).toFixed(2);
                            }
                        } else {
                            // Add EUR listings to eur_listings array
                            window.eur_listings[variationId] = window.eur_listings[variationId] || [];
                            window.eur_listings[variationId].push(listing);
                        }
                        
                        // Get country data
                        let country = listing.country_id || (listing.country && countries[listing.country]);
                        if (!country) {
                            return; // Skip if no country data
                        }
                        
                        // Get marketplace name
                        let marketplaceName = 'N/A';
                        if (listing.marketplace_id && marketplaces && marketplaces[listing.marketplace_id]) {
                            marketplaceName = marketplaces[listing.marketplace_id].name || 'Marketplace ' + listing.marketplace_id;
                        } else if (listing.marketplace && listing.marketplace.name) {
                            marketplaceName = listing.marketplace.name;
                        }
                        
                        const isEnabled = listing.is_enabled !== undefined && listing.is_enabled == 1;
                        const disabledClass = !isEnabled ? 'listing-disabled' : '';
                        listingsTable += `
                            <tr class="${classs} ${disabledClass}" ${listing.buybox !== 1 ? 'style="background: pink;"' : ''} data-listing-id="${listing.id}" data-enabled="${isEnabled ? '1' : '0'}">
                                <td title="${listing.id} ${country.title || ''}">
                                    <a href="https://www.backmarket.${country.market_url}/${country.market_code}/p/gb/${listing.reference_uuid_2 || listing._2 || ''}" target="_blank">
                                        <img src="{{ asset('assets/img/flags/') }}/${country.code.toLowerCase()}.svg" height="15">
                                        ${country.code}
                                    </a>
                                    ${marketplaceName}
                                </td>
                                <td>
                                    <form class="form-inline" method="POST" id="change_limit_${listing.id}">
                                        @csrf
                                        <input type="submit" hidden>
                                    </form>
                                    <input type="number" class="form-control ${listing.handler_status == 2 ? 'text-danger':''}" id="min_price_limit_${listing.id}" name="min_price_limit" step="0.01" value="${listing.min_price_limit || ''}" form="change_limit_${listing.id}" ${!isEnabled ? 'disabled' : ''}>
                                </td>
                                <td>
                                    <input type="number" class="form-control ${listing.handler_status == 2 ? 'text-danger':''}" id="price_limit_${listing.id}" name="price_limit" step="0.01" value="${listing.price_limit || ''}" form="change_limit_${listing.id}" ${!isEnabled ? 'disabled' : ''}>
                                </td>
                                <td>${listing.buybox_price || ''}
                                    <span class="text-danger" title="Buybox Winner Price">
                                        ${listing.buybox !== 1 ? '(' + (listing.buybox_winner_price || '') + ')' : ''}
                                    </span>
                                </td>
                                <td>
                                    <form class="form-inline" method="POST" id="change_min_price_${listing.id}">
                                        @csrf
                                        <input type="submit" hidden>
                                    </form>
                                    <form class="form-inline" method="POST" id="change_price_${listing.id}">
                                        @csrf
                                        <input type="submit" hidden>
                                    </form>
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="min_price_${listing.id}" name="min_price" step="0.01" value="${listing.min_price || ''}" form="change_min_price_${listing.id}" ${!isEnabled ? 'disabled' : ''}>
                                        <label for="">Min Price</label>
                                    </div>
                                    <span id="pm_append_${listing.id}" title="${pm_append_title}">
                                        ${pm_append}
                                    </span>
                                </td>
                                <td>
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="price_${listing.id}" name="price" step="0.01" value="${listing.price || ''}" form="change_price_${listing.id}" ${!isEnabled ? 'disabled' : ''}>
                                        <label for="">Price</label>
                                    </div>
                                    ${p_append}
                                </td>
                                <td>${listing.updated_at ? new Date(listing.updated_at).toLocaleString('en-GB', { timeZone: 'Europe/London', hour12: true }) : ''}</td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-inline-block">
                                        <input 
                                            class="form-check-input toggle-listing-enable" 
                                            type="checkbox" 
                                            role="switch"
                                            id="toggle_listing_${listing.id}"
                                            data-listing-id="${listing.id}"
                                            ${listing.is_enabled !== undefined && listing.is_enabled == 1 ? 'checked' : ''}
                                            style="cursor: pointer;">
                                        <label class="form-check-label" for="toggle_listing_${listing.id}"></label>
                                    </div>
                                </td>
                            </tr>`;
                    });
                }
                
                // Load stocks for marketplace 1 only
                if (marketplaceId === 1) {
                    loadMarketplaceStocks(variationId, marketplaceId, listingsTable);
                } else {
                    renderMarketplaceTables(variationId, marketplaceId, listingsTable, '<tr><td colspan="3" class="text-center text-muted">Stocks are only shown for marketplace 1</td></tr>');
                }
            },
            error: function(xhr) {
                console.error(xhr.responseText);
                container.html('<div class="text-center p-4 text-danger">Error loading listings</div>');
            }
        });
    }

    function loadMarketplaceStocks(variationId, marketplaceId, listingsTable) {
        $.ajax({
            url: "{{ url('listing/get_variation_available_stocks') }}/" + variationId,
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                let stocksTable = '';
                let count = 0;
                data.stocks.forEach(function(item, index) {
                    count++;
                    let price = data.stock_costs[item.id];
                    let vendor = data.vendors[data.po[item.order_id]];
                    let reference_id = data.reference[item.order_id];
                    let topup_ref = data.topup_reference[data.latest_topup_items[item.id]];
                    
                    stocksTable += `
                        <tr>
                            <td>${index + 1}</td>
                            <td data-stock="${item.id}" title="${topup_ref}">
                                <a href="{{ url('imei?imei=') }}${item.imei || item.serial_number}" target="_blank">
                                    ${item.imei || item.serial_number || ''}
                                </a>
                            </td>
                            <td>€${price || '0.00'}</td>
                        </tr>`;
                });
                
                renderMarketplaceTables(variationId, marketplaceId, listingsTable, stocksTable);
            },
            error: function() {
                renderMarketplaceTables(variationId, marketplaceId, listingsTable, '<tr><td colspan="3" class="text-center text-muted">Error loading stocks</td></tr>');
            }
        });
    }

    function renderMarketplaceTables(variationId, marketplaceId, listingsTable, stocksTable) {
        const container = $(`#marketplace_toggle_${variationId}_${marketplaceId} .marketplace-tables-container`);
        
        if (listingsTable === '') {
            listingsTable = '<tr><td colspan="8" class="text-center text-muted">No listings for this marketplace</td></tr>';
        }
        
        const tablesHtml = `
            <div class="row g-2">
                <div class="col-md">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 text-md-nowrap">
                            <thead>
                                <tr>
                                    <th width="80"><small><b>Country</b></small></th>
                                    <th width="100" title="Minimum Price Handler"><small><b>Min Hndlr</b></small></th>
                                    <th width="100" title="Price Handler"><small><b>Price Hndlr</b></small></th>
                                    <th width="80"><small><b>BuyBox</b></small></th>
                                    <th title="Min Price" width="120"><small><b>Min </b>(€<b id="best_price_${variationId}_${marketplaceId}"></b>)</small></th>
                                    <th width="120"><small><b>Price</b></small></th>
                                    <th><small><b>Date</b></small></th>
                                    <th width="80" class="text-center"><small><b>Action</b></small></th>
                                </tr>
                            </thead>
                            <tbody id="listings_${variationId}_${marketplaceId}">
                                ${listingsTable}
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-md-auto">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                            <thead>
                                <tr>
                                    <th><small><b>No</b></small></th>
                                    <th><small><b>IMEI/Serial</b></small></th>
                                    <th><small><b>Cost</b> (<b id="average_cost_${variationId}_${marketplaceId}"></b>)</small></th>
                                </tr>
                            </thead>
                            <tbody id="stocks_${variationId}_${marketplaceId}">
                                ${stocksTable}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        
        container.html(tablesHtml);
        container.data('loaded', true);
    }
    
    // Handle marketplace stock form input changes
    $(document).on('input', '[id^="add_marketplace_"]', function() {
        const inputId = $(this).attr('id');
        const matches = inputId.match(/add_marketplace_(\d+)_(\d+)/);
        if (!matches) return;
        
        const variationId = matches[1];
        const marketplaceId = matches[2];
        const value = $(this).val();
        
        if (value && parseFloat(value) !== 0) {
            $('#send_marketplace_' + variationId + '_' + marketplaceId).removeClass('d-none');
        } else {
            $('#send_marketplace_' + variationId + '_' + marketplaceId).addClass('d-none');
        }
    });
    
    // Handle marketplace stock form submission
    $(document).on('submit', '[id^="add_qty_marketplace_"]', function(e) {
        e.preventDefault();
        
        const formId = $(this).attr('id');
        const matches = formId.match(/add_qty_marketplace_(\d+)_(\d+)/);
        if (!matches) return;
        
        const variationId = matches[1];
        const marketplaceId = matches[2];
        const form = $(this);
        const actionUrl = form.attr('action');
        const quantity = $('#add_marketplace_' + variationId + '_' + marketplaceId).val();
        
        // Disable submission
        $('#send_marketplace_' + variationId + '_' + marketplaceId).addClass('d-none');
        $('#send_marketplace_' + variationId + '_' + marketplaceId).prop('disabled', true);
        $('#success_marketplace_' + variationId + '_' + marketplaceId).text('');
        
        $.ajax({
            type: "POST",
            url: actionUrl,
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                // Handle both old format (number) and new format (object with marketplace_stock and total_stock)
                let marketplaceStock, totalStock;
                if (typeof response === 'object' && response.marketplace_stock !== undefined) {
                    marketplaceStock = response.marketplace_stock;
                    totalStock = response.total_stock;
                } else {
                    // Fallback for old format
                    marketplaceStock = response;
                    totalStock = response;
                }
                
                // Update the marketplace stock display
                $('#quantity_marketplace_' + variationId + '_' + marketplaceId).val(marketplaceStock);
                $('#success_marketplace_' + variationId + '_' + marketplaceId).text("Quantity changed by " + quantity + " to " + marketplaceStock);
                $('#add_marketplace_' + variationId + '_' + marketplaceId).val('');
                
                // Update the parent total stock display
                $('#total_stock_' + variationId).val(totalStock);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                let errorMsg = "Error: " + textStatus;
                if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
                    errorMsg = jqXHR.responseJSON.error;
                } else if (jqXHR.responseText) {
                    errorMsg = jqXHR.responseText;
                }
                alert(errorMsg);
                $('#send_marketplace_' + variationId + '_' + marketplaceId).prop('disabled', false);
            }
        });
    });
    
    // Handle Enter key on marketplace stock input
    $(document).on('keypress', '[id^="add_marketplace_"]', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            const inputId = $(this).attr('id');
            const matches = inputId.match(/add_marketplace_(\d+)_(\d+)/);
            if (!matches) return;
            
            const variationId = matches[1];
            const marketplaceId = matches[2];
            $('#add_qty_marketplace_' + variationId + '_' + marketplaceId).submit();
        }
    });
    
    // Handle listing enable/disable toggle
    $(document).on('change', '.toggle-listing-enable', function() {
        const toggle = $(this);
        const listingId = toggle.data('listing-id');
        const isEnabled = toggle.is(':checked') ? 1 : 0;
        
        // Disable toggle during API call
        toggle.prop('disabled', true);
        
        $.ajax({
            type: "POST",
            url: "{{ url('listing/toggle_enable') }}/" + listingId,
            data: {
                _token: "{{ csrf_token() }}",
                is_enabled: isEnabled
            },
            dataType: 'json',
            success: function(response) {
                // Update the toggle state based on response
                toggle.prop('checked', response.is_enabled == 1);
                toggle.prop('disabled', false);
                
                // Find the row and update its disabled state
                const row = toggle.closest('tr');
                const isEnabled = response.is_enabled == 1;
                
                // Update row classes and data attribute
                if (isEnabled) {
                    row.removeClass('listing-disabled');
                    row.attr('data-enabled', '1');
                } else {
                    row.addClass('listing-disabled');
                    row.attr('data-enabled', '0');
                }
                
                // Enable/disable all inputs in the row (except the toggle)
                row.find('input[type="number"]').prop('disabled', !isEnabled);
                row.find('input[type="text"]').prop('disabled', !isEnabled);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // Revert toggle state on error
                toggle.prop('checked', !isEnabled);
                toggle.prop('disabled', false);
                
                // Revert row state on error
                const row = toggle.closest('tr');
                const wasEnabled = !isEnabled; // Revert to previous state
                
                if (wasEnabled) {
                    row.removeClass('listing-disabled');
                    row.attr('data-enabled', '1');
                    row.find('input[type="number"]').prop('disabled', false);
                    row.find('input[type="text"]').prop('disabled', false);
                } else {
                    row.addClass('listing-disabled');
                    row.attr('data-enabled', '0');
                    row.find('input[type="number"]').prop('disabled', true);
                    row.find('input[type="text"]').prop('disabled', true);
                }
                
                let errorMsg = "Error updating listing status: " + textStatus;
                if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
                    errorMsg = jqXHR.responseJSON.error;
                } else if (jqXHR.responseText) {
                    errorMsg = jqXHR.responseText;
                }
                alert(errorMsg);
            }
        });
    });

    // Global marketplace toggle state
    let globalMarketplaceState = {
        @foreach($global_marketplace_counts ?? [] as $mpId => $mpData)
            {{ (int)$mpId }}: {{ $loop->first ? 'true' : 'false' }}, // Marketplace 1 is visible by default
        @endforeach
    };

    // Initialize marketplace visibility on page load
    $(document).ready(function() {
        // Apply initial state to all marketplace bars
        for (let marketplaceId in globalMarketplaceState) {
            const isVisible = globalMarketplaceState[marketplaceId];
            $('[id^="marketplace_bar_"]').each(function() {
                const id = $(this).attr('id');
                // Match pattern: marketplace_bar_VARIATIONID_MARKETPLACEID
                const matches = id.match(/marketplace_bar_(\d+)_(\d+)/);
                if (matches && parseInt(matches[2]) === parseInt(marketplaceId)) {
                    if (isVisible) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                }
            });
        }
    });

    // Global marketplace toggle function
    function toggleGlobalMarketplace(marketplaceId, badgeElement) {
        // Toggle global state
        globalMarketplaceState[marketplaceId] = !globalMarketplaceState[marketplaceId];
        const isVisible = globalMarketplaceState[marketplaceId];
        
        // Toggle all marketplace bars with this marketplace ID across all variations
        // Use a more precise selector that matches the exact ID pattern: marketplace_bar_X_Y
        $('[id^="marketplace_bar_"]').each(function() {
            const id = $(this).attr('id');
            // Match pattern: marketplace_bar_VARIATIONID_MARKETPLACEID
            const matches = id.match(/marketplace_bar_(\d+)_(\d+)/);
            if (matches && parseInt(matches[2]) === marketplaceId) {
                if (isVisible) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            }
        });
        
        // Update badge appearance
        if (isVisible) {
            $(badgeElement).css({
                'border-color': '#28a745',
                'color': '#28a745'
            });
            $(badgeElement).removeClass('badge-inactive').addClass('badge-active');
        } else {
            $(badgeElement).css({
                'border-color': '#000',
                'color': '#000'
            });
            $(badgeElement).removeClass('badge-active').addClass('badge-inactive');
        }
    }
</script>
@endsection
