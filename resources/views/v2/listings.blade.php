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
        // Build params object, only including non-empty values
        const params = {};
        
        // Get all form values - prioritize select dropdowns over hidden inputs
        const productName = document.querySelector('[name="product_name"]')?.value || '';
        const referenceId = document.querySelector('[name="reference_id"]')?.value || '';
        const product = document.querySelector('[name="product"]')?.value || '';
        const sku = document.querySelector('[name="sku"]')?.value || '';
        const color = document.querySelector('[name="color"]')?.value || '';
        const storage = document.querySelector('[name="storage"]')?.value || '';
        const gradeSelect = document.querySelector('[name="grade[]"]');
        const grades = gradeSelect ? Array.from(gradeSelect.selectedOptions).map(opt => opt.value) : [];
        const category = document.querySelector('[name="category"]')?.value || '';
        const brand = document.querySelector('[name="brand"]')?.value || '';
        const marketplace = document.querySelector('[name="marketplace"]')?.value || '';
        const listedStock = document.querySelector('[name="listed_stock"]')?.value || '';
        const availableStock = document.querySelector('[name="available_stock"]')?.value || '';
        const handlerStatus = document.querySelector('[name="handler_status"]')?.value || '';
        const state = document.querySelector('[name="state"]')?.value || '';
        
        // Get sort and per_page from the select dropdowns (not hidden inputs)
        // These are outside the form but have form="v2-search-form" attribute
        // Always read directly from the DOM to get current values
        const sortSelect = document.getElementById('v2-sort');
        const perPageSelect = document.getElementById('v2-per-page');
        const sort = (sortSelect && sortSelect.value) ? sortSelect.value : '1';
        const perPage = (perPageSelect && perPageSelect.value) ? perPageSelect.value : '10';
        
        // Only add non-empty values to params
        if (productName) params.product_name = productName;
        if (referenceId) params.reference_id = referenceId;
        if (product) params.product = product;
        if (sku) params.sku = sku;
        if (color) params.color = color;
        if (storage) params.storage = storage;
        if (grades.length > 0) params.grade = grades;
        if (category) params.category = category;
        if (brand) params.brand = brand;
        if (marketplace) params.marketplace = marketplace;
        if (listedStock) params.listed_stock = listedStock;
        if (availableStock) params.available_stock = availableStock;
        if (handlerStatus) params.handler_status = handlerStatus;
        if (state) params.state = state;
        
        // Always include sort and per_page
        params.sort = sort;
        params.per_page = perPage;
        
        // Add special parameters from request
        const special = "{{ Request::get('special') }}";
        const sale40 = "{{ Request::get('sale_40') }}";
        const variationId = "{{ Request::get('variation_id') }}";
        const processId = "{{ Request::get('process_id') }}";
        const show = "{{ Request::get('show') }}";
        
        if (special) params.special = special;
        if (sale40) params.sale_40 = sale40;
        if (variationId) params.variation_id = variationId;
        if (processId) params.process_id = processId;
        if (show) params.show = show;

        return Object.assign(params, overrides);
    }

    /**
     * Fetch variations (returns only IDs for lazy loading)
     */
    function fetchVariationsV2(page = 1) {
        const params = buildListingFiltersV2({ page: page });
        
        // Build query string manually to handle arrays properly
        const queryParts = [];
        for (const [key, value] of Object.entries(params)) {
            // Always include sort and per_page, even if empty
            if (key === 'sort' || key === 'per_page') {
                queryParts.push(`${key}=${encodeURIComponent(value || (key === 'sort' ? '1' : '10'))}`);
            } else if (value !== null && value !== undefined && value !== '') {
                if (Array.isArray(value)) {
                    value.forEach(v => {
                        if (v) queryParts.push(`${key}[]=${encodeURIComponent(v)}`);
                    });
                } else {
                    queryParts.push(`${key}=${encodeURIComponent(value)}`);
                }
            }
        }
        const queryString = queryParts.join('&');
        const url = "{{ url('v2/listings/get_variations') }}" + (queryString ? '?' + queryString : '');
        
        // Debug: Log the URL to see what's being sent
        console.log('Fetching variations with URL:', url);
        console.log('Sort value:', params.sort, 'Per page:', params.per_page);
        
        // Update URL in browser without reloading page
        window.history.pushState({}, '', window.location.pathname + (queryString ? '?' + queryString : ''));

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
                    
                    // Initialize deferred sales data loading using Intersection Observer
                    initializeDeferredSalesDataLoading();
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

    /**
     * Initialize deferred sales data loading using Intersection Observer
     * Sales data will only load when variation cards scroll into viewport
     */
    function initializeDeferredSalesDataLoading() {
        // Track which variations have already loaded sales data
        const loadedSalesData = new Set();
        
        // Create Intersection Observer
        const salesObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const element = entry.target;
                    const variationId = element.dataset.variationId || element.id.replace('sales_', '');
                    
                    // Skip if already loaded or loading
                    if (!variationId || loadedSalesData.has(variationId) || element.innerHTML.includes('Average:')) {
                        salesObserver.unobserve(element);
                        return;
                    }
                    
                    // Mark as loading
                    element.innerHTML = '<span class="text-muted small">Loading sales data...</span>';
                    
                    // Load sales data
                    if (typeof $ !== 'undefined' && $.fn.load) {
                        const salesUrl = "{{ url('listing/get_sales') }}/" + variationId + "?csrf={{ csrf_token() }}";
                        $(element).load(salesUrl, function(response, status) {
                            if (status === 'success') {
                                loadedSalesData.add(variationId);
                                salesObserver.unobserve(element);
                            } else {
                                element.innerHTML = '<span class="text-danger small">Failed to load sales data</span>';
                            }
                        });
                    } else {
                        // Fallback to fetch API if jQuery not available
                        fetch("{{ url('listing/get_sales') }}/" + variationId + "?csrf={{ csrf_token() }}")
                            .then(response => response.text())
                            .then(html => {
                                element.innerHTML = html;
                                loadedSalesData.add(variationId);
                                salesObserver.unobserve(element);
                            })
                            .catch(error => {
                                console.error('Error loading sales data:', error);
                                element.innerHTML = '<span class="text-danger small">Failed to load sales data</span>';
                            });
                    }
                }
            });
        }, {
            // Load when element is 100px away from viewport
            rootMargin: '100px'
        });
        
        // Observe all sales info elements
        setTimeout(function() {
            const salesElements = document.querySelectorAll('[id^="sales_"][data-variation-id]');
            salesElements.forEach(element => {
                salesObserver.observe(element);
            });
        }, 500); // Wait for Livewire to finish rendering
    }
    
    // Initialize on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeDeferredSalesDataLoading);
    } else {
        // Already loaded, initialize immediately
        setTimeout(initializeDeferredSalesDataLoading, 500);
    }

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


    /**
     * Submit handler changes for marketplace-specific listings
     */
    function submitForm8Marketplace(event, variationId, marketplaceId) {
        event.preventDefault();
        var form = $('#change_all_handler_' + variationId + '_' + marketplaceId);
        var min_price = $('#all_min_handler_' + variationId + '_' + marketplaceId).val();
        var price = $('#all_handler_' + variationId + '_' + marketplaceId).val();

        if (!min_price && !price) {
            alert('Please enter at least one handler value');
            return;
        }

        // Get listings for this marketplace from the Livewire component
        // We'll need to get the listing IDs from the DOM or make an AJAX call
        var listingIds = [];
        $('#collapse_' + marketplaceId + '_' + variationId + ' .listing-table tbody tr').each(function() {
            var formId = $(this).find('form[id^="change_limit_"]').attr('id');
            if (formId) {
                var listingId = formId.replace('change_limit_', '');
                listingIds.push(listingId);
            }
        });

        if (listingIds.length === 0) {
            alert('No listings found for this marketplace');
            return;
        }

        var updateCount = 0;
        var totalCount = listingIds.length;

        listingIds.forEach(function(listingId) {
            if (min_price > 0) {
                $('#min_price_limit_' + listingId).val(min_price);
            }
            if (price > 0) {
                $('#price_limit_' + listingId).val(price);
            }
            
            // Submit the form for this listing
            submitForm5Marketplace(event, listingId, marketplaceId, function() {
                updateCount++;
                if (updateCount === totalCount) {
                    // Reload the marketplace data
                    if (typeof Livewire !== 'undefined') {
                        Livewire.emit('refresh-marketplace', variationId, marketplaceId);
                    }
                    alert('Handlers updated successfully');
                }
            });
        });
    }

    /**
     * Submit price changes for marketplace-specific listings
     */
    function submitForm4Marketplace(event, variationId, marketplaceId) {
        event.preventDefault();
        var form = $('#change_all_price_' + variationId + '_' + marketplaceId);
        var min_price = $('#all_min_price_' + variationId + '_' + marketplaceId).val();
        var price = $('#all_price_' + variationId + '_' + marketplaceId).val();

        if (!min_price && !price) {
            alert('Please enter at least one price value');
            return;
        }

        // Get listings for this marketplace from the DOM
        var listingIds = [];
        $('#collapse_' + marketplaceId + '_' + variationId + ' .listing-table tbody tr').each(function() {
            var formId = $(this).find('form[id^="change_min_price_"]').attr('id');
            if (formId) {
                var listingId = formId.replace('change_min_price_', '');
                listingIds.push(listingId);
            }
        });

        if (listingIds.length === 0) {
            alert('No listings found for this marketplace');
            return;
        }

        var updateCount = 0;
        var totalCount = listingIds.length;

        listingIds.forEach(function(listingId) {
            if (min_price > 0) {
                $('#min_price_' + listingId).val(min_price);
                submitForm2Marketplace(event, listingId, 'min_price', function() {
                    updateCount++;
                    if (updateCount === totalCount) {
                        if (typeof Livewire !== 'undefined') {
                            Livewire.emit('refresh-marketplace', variationId, marketplaceId);
                        }
                        alert('Prices updated successfully');
                    }
                });
            }
            if (price > 0) {
                $('#price_' + listingId).val(price);
                submitForm3Marketplace(event, listingId, 'price', function() {
                    updateCount++;
                    if (updateCount === totalCount) {
                        if (typeof Livewire !== 'undefined') {
                            Livewire.emit('refresh-marketplace', variationId, marketplaceId);
                        }
                        alert('Prices updated successfully');
                    }
                });
            }
        });
    }

    /**
     * Submit form for updating min price limit (handler)
     */
    function submitForm5Marketplace(event, listingId, marketplaceId, callback) {
        event.preventDefault();
        var form = $('#change_limit_' + listingId);
        var actionUrl = "{{ url('listing/update_limit') }}/" + listingId;
        var formData = form.serialize();
        if (marketplaceId) {
            formData += '&marketplace_id=' + marketplaceId;
        }

        $.ajax({
            type: "POST",
            url: actionUrl,
            data: formData,
            success: function(data) {
                $('#min_price_limit_' + listingId).addClass('bg-green');
                $('#price_limit_' + listingId).addClass('bg-green');
                if (callback && typeof callback === 'function') {
                    callback();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert("Error: " + textStatus + " - " + errorThrown);
                if (callback && typeof callback === 'function') {
                    callback();
                }
            }
        });
    }

    /**
     * Submit form for updating min price
     */
    function submitForm2Marketplace(event, listingId, field, callback) {
        event.preventDefault();
        var form = $('#change_min_price_' + listingId);
        var actionUrl = "{{ url('listing/update_price') }}/" + listingId;

        $.ajax({
            type: "POST",
            url: actionUrl,
            data: form.serialize(),
            success: function(data) {
                $('#min_price_' + listingId).addClass('bg-green');
                if (callback && typeof callback === 'function') {
                    callback();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert("Error: " + textStatus + " - " + errorThrown);
                if (callback && typeof callback === 'function') {
                    callback();
                }
            }
        });
    }

    /**
     * Submit form for updating price
     */
    function submitForm3Marketplace(event, listingId, field, callback) {
        event.preventDefault();
        var form = $('#change_price_' + listingId);
        var actionUrl = "{{ url('listing/update_price') }}/" + listingId;

        $.ajax({
            type: "POST",
            url: actionUrl,
            data: form.serialize(),
            success: function(data) {
                $('#price_' + listingId).addClass('bg-green');
                if (callback && typeof callback === 'function') {
                    callback();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert("Error: " + textStatus + " - " + errorThrown);
                if (callback && typeof callback === 'function') {
                    callback();
                }
            }
        });
    }
</script>
@endsection
