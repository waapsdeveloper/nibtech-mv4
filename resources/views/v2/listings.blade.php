@extends('layouts.app')

@section('styles')
<link href="{{asset('assets/plugins/select2/css/select2.min.css')}}" rel="stylesheet" />
<style>
    /* V2 Listing Page Styles */
    .v2-listing-container {
        padding: 20px;
    }
    .v2-header {
        margin-bottom: 20px;
    }
    .v2-header h4 {
        color: #016a59;
        font-size: 1.5rem;
        margin-bottom: 10px;
    }
    .listing-item-card {
        border: 1px solid #016a5949;
        border-radius: 8px;
        margin-bottom: 15px;
        transition: box-shadow 0.3s;
    }
    .listing-item-card:hover {
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }
    .loading-spinner {
        text-align: center;
        padding: 20px;
    }
    .variations-container {
        min-height: 200px;
    }
    .pagination-container {
        margin-top: 20px;
    }
</style>
@endsection

@section('content')
<!-- Breadcrumb -->
<div class="breadcrumb-header justify-content-between">
    <div class="left-content">
        <h4>{{ $title_page }}</h4>
    </div>
    <div class="justify-content-center mt-2">
        <ol class="breadcrumb">
            <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
            <li class="breadcrumb-item active" aria-current="page">Listings V2</li>
        </ol>
    </div>
</div>
<!-- /Breadcrumb -->

<!-- Search Form -->
<form action="" method="GET" id="v2-search-form" onsubmit="event.preventDefault(); fetchVariationsV2();">
    <livewire:search-listing />
    <input type="hidden" name="special" value="{{ Request::get('special') }}">
</form>

<!-- Controls -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="card-title mg-b-0" id="v2-page-info">Loading...</h5>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ url('listed_stock_verification') }}" class="btn btn-primary btn-sm">Verification</a>
        @if (request('special') != 'verify_listing')
            <button class="btn btn-link" type="button" id="toggle-all-variations">Toggle All</button>
            <button class="btn btn-success btn-sm" type="button" id="export-listings-btn">Export CSV</button>
        @endif
        <label for="v2-sort" class="form-label mb-0">Sort:</label>
        <select name="sort" class="form-select w-auto" id="v2-sort" form="v2-search-form">
            <option value="1" {{ Request::get('sort') == 1 ? 'selected' : '' }}>Stock DESC</option>
            <option value="2" {{ Request::get('sort') == 2 ? 'selected' : '' }}>Stock ASC</option>
            <option value="3" {{ Request::get('sort') == 3 ? 'selected' : '' }}>Name DESC</option>
            <option value="4" {{ Request::get('sort') == 4 ? 'selected' : '' }}>Name ASC</option>
        </select>
        <label for="v2-per-page" class="form-label mb-0">Per Page:</label>
        <select name="per_page" class="form-select w-auto" id="v2-per-page" form="v2-search-form">
            <option value="10" {{ Request::get('per_page') == 10 ? 'selected' : '' }}>10</option>
            <option value="20" {{ Request::get('per_page') == 20 ? 'selected' : '' }}>20</option>
            <option value="50" {{ Request::get('per_page') == 50 ? 'selected' : '' }}>50</option>
            <option value="100" {{ Request::get('per_page') == 100 ? 'selected' : '' }}>100</option>
        </select>
    </div>
</div>

<!-- Variations Container -->
<div id="v2-variations" class="variations-container">
    <div class="loading-spinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2 text-muted">Loading variations...</p>
    </div>
</div>

<!-- Pagination -->
<nav aria-label="Page navigation" class="pagination-container">
    <ul id="v2-pagination" class="pagination justify-content-center"></ul>
</nav>

@endsection

@section('scripts')
<script>
    // V2 Listing Page JavaScript
    
    // Store reference data for use in components
    window.v2ReferenceData = {
        storages: @json($storages ?? []),
        colors: @json($colors ?? []),
        grades: @json($grades ?? []),
        exchangeRates: @json($exchange_rates ?? []),
        eurGbp: {{ $eur_gbp ?? 0 }},
        currencies: @json($currencies ?? []),
        currencySign: @json($currency_sign ?? []),
        countries: @json($countries ?? []),
        marketplaces: @json($marketplaces ?? []),
        processId: "{{ $process_id ?? '' }}"
    };

    /**
     * Build listing filters from form
     */
    function buildListingFiltersV2(overrides = {}) {
        const form = document.getElementById('v2-search-form');
        const formData = new FormData(form);
        
        const params = {
            product_name: formData.get('product_name') || '',
            reference_id: formData.get('reference_id') || '',
            product: formData.get('product') || '',
            sku: formData.get('sku') || '',
            color: formData.get('color') || '',
            storage: formData.get('storage') || '',
            grade: formData.getAll('grade[]') || [],
            category: formData.get('category') || '',
            brand: formData.get('brand') || '',
            marketplace: formData.get('marketplace') || '',
            listed_stock: formData.get('listed_stock') || '',
            available_stock: formData.get('available_stock') || '',
            handler_status: formData.get('handler_status') || '',
            state: formData.get('state') || '',
            sort: formData.get('sort') || '1',
            per_page: formData.get('per_page') || '10',
            special: "{{ Request::get('special') }}",
            sale_40: "{{ Request::get('sale_40') }}",
            variation_id: "{{ Request::get('variation_id') }}",
            process_id: "{{ Request::get('process_id') }}",
            show: "{{ Request::get('show') }}",
        };

        return Object.assign(params, overrides);
    }

    /**
     * Fetch variations (returns only IDs for lazy loading)
     */
    function fetchVariationsV2(page = 1) {
        const params = buildListingFiltersV2({ page: page });
        const queryString = new URLSearchParams(params).toString();
        const url = "{{ url('v2/listings/get_variations') }}" + '?' + queryString;

        // Show loading state
        document.getElementById('v2-variations').innerHTML = `
            <div class="loading-spinner">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading variations...</p>
            </div>
        `;

        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.data && data.data.length > 0) {
                displayVariationsLazyV2(data);
                updatePaginationV2(data);
            } else {
                document.getElementById('v2-variations').innerHTML = 
                    '<p class="text-center text-muted">No variations found.</p>';
                document.getElementById('v2-page-info').textContent = 'No variations found.';
                updatePaginationV2(data);
            }
        })
        .catch(error => {
            console.error('Error fetching variations:', error);
            document.getElementById('v2-variations').innerHTML = 
                '<p class="text-center text-danger">Error loading variations. Please refresh the page.</p>';
        });
    }

    /**
     * Display variations using lazy-loaded Livewire components
     */
    function displayVariationsLazyV2(variations) {
        const variationIds = variations.data.map(v => v.id);
        const variationsContainer = document.getElementById('v2-variations');
        variationsContainer.innerHTML = '';
        
        document.getElementById('v2-page-info').textContent = 
            `From ${variations.from} To ${variations.to} Out Of ${variations.total}`;

        // Render Livewire components from server
        fetch("{{ url('v2/listings/render_listing_items') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': "{{ csrf_token() }}",
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                variation_ids: variationIds,
                process_id: window.v2ReferenceData.processId,
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.html) {
                variationsContainer.innerHTML = data.html;
                // Rescan for Livewire components
                if (typeof Livewire !== 'undefined') {
                    Livewire.rescan();
                }
            } else {
                variationsContainer.innerHTML = 
                    '<p class="text-center text-danger">Error rendering components.</p>';
            }
        })
        .catch(error => {
            console.error('Error rendering listing items:', error);
            variationsContainer.innerHTML = 
                '<p class="text-center text-danger">Error rendering components. Please refresh the page.</p>';
        });
    }

    /**
     * Update pagination
     */
    function updatePaginationV2(data) {
        const paginationContainer = document.getElementById('v2-pagination');
        paginationContainer.innerHTML = '';

        if (data.last_page <= 1) {
            return;
        }

        // Previous button
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${data.current_page === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="#" onclick="fetchVariationsV2(${data.current_page - 1}); return false;">Previous</a>`;
        paginationContainer.appendChild(prevLi);

        // Page numbers
        for (let i = 1; i <= data.last_page; i++) {
            if (i === 1 || i === data.last_page || (i >= data.current_page - 2 && i <= data.current_page + 2)) {
                const li = document.createElement('li');
                li.className = `page-item ${i === data.current_page ? 'active' : ''}`;
                li.innerHTML = `<a class="page-link" href="#" onclick="fetchVariationsV2(${i}); return false;">${i}</a>`;
                paginationContainer.appendChild(li);
            } else if (i === data.current_page - 3 || i === data.current_page + 3) {
                const li = document.createElement('li');
                li.className = 'page-item disabled';
                li.innerHTML = '<span class="page-link">...</span>';
                paginationContainer.appendChild(li);
            }
        }

        // Next button
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${data.current_page === data.last_page ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="#" onclick="fetchVariationsV2(${data.current_page + 1}); return false;">Next</a>`;
        paginationContainer.appendChild(nextLi);
    }

    // Event listeners
    document.getElementById('v2-sort').addEventListener('change', () => fetchVariationsV2(1));
    document.getElementById('v2-per-page').addEventListener('change', () => fetchVariationsV2(1));

    // Toggle all variations
    document.getElementById('toggle-all-variations')?.addEventListener('click', function() {
        const collapses = document.querySelectorAll('.multi_collapse');
        const isExpanded = collapses[0]?.classList.contains('show');
        collapses.forEach(collapse => {
            if (isExpanded) {
                bootstrap.Collapse.getInstance(collapse)?.hide();
            } else {
                new bootstrap.Collapse(collapse).show();
            }
        });
    });

    // Export CSV
    document.getElementById('export-listings-btn')?.addEventListener('click', function() {
        const params = buildListingFiltersV2();
        const queryString = new URLSearchParams(params).toString();
        window.location.href = "{{ url('listing/export') }}?" + queryString;
    });

    // Initial load
    document.addEventListener('DOMContentLoaded', function() {
        fetchVariationsV2({{ Request::get('page', 1) }});
    });
</script>
@endsection
