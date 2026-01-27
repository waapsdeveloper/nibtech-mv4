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

    {{-- Stock Locks Modal - REMOVED (Stock lock system removed) --}}
    {{-- @include('v2.listing.partials.stock-locks-modal') --}}

    {{-- Stock Comparison Modal --}}
    @include('v2.listing.partials.stock-comparison-modal')
    {{-- /Stock Comparison Modal --}}

    {{-- Variation History Modal --}}
    @include('v2.listing.partials.variation-history-modal')
    {{-- /Variation History Modal --}}

    {{-- Stock Formula Modal --}}
    @include('v2.listing.partials.stock-formula-modal')
    {{-- /Stock Formula Modal --}}
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
            getCompetitors: "{{ url('v2/listings/get_competitors') }}",
            getVariationStocks: "{{ url('listing/get_variation_available_stocks') }}",
            getUpdatedQuantity: "{{ url('v2/listings/get_updated_quantity') }}",
            toggleEnable: "{{ url('listing/toggle_enable') }}",
            getSales: "{{ url('listing/get_sales') }}",
            getBuybox: "{{ url('listing/get_buybox') }}",
            export: "{{ url('listing/export') }}",
            getTargetVariations: "{{ url('listing/get_target_variations') }}",
            updateTarget: "{{ url('listing/update_target') }}",
            imei: "{{ url('imei') }}",
            // getStockLocks: "{{ url('v2/stock-locks/api') }}", // Stock lock system removed
            getMarketplaceStockComparison: "{{ url('v2/listings/get_marketplace_stock_comparison') }}",
            fixStockMismatch: "{{ url('v2/listings/fix_stock_mismatch') }}",
            restoreListingHistory: "{{ url('v2/listings/restore_history') }}",
            getVariationHistory: "{{ url('v2/listings/get_variation_history') }}",
            getStockFormulaModal: "{{ url('v2/marketplace/stock-formula') }}"
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

    // Function to show stock locks modal - REMOVED (Stock lock system removed)
    // function showStockLocksModal(variationId, marketplaceId) {
    //     const modal = new bootstrap.Modal(document.getElementById('stockLocksModal'));
    //     const modalBody = document.getElementById('stockLocksModalBody');
    //     
    //     // Show loading state
    //     modalBody.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2 text-muted">Loading stock locks...</p></div>';
    //     modal.show();
    //     
    //     // Load stock locks via API (returns rendered Blade template)
    //     $.ajax({
    //         url: '{{ url("v2/stock-locks/api") }}',
    //         type: 'GET',
    //         data: {
    //             variation_id: variationId,
    //             marketplace_id: marketplaceId,
    //             show_all: false
    //         },
    //         success: function(html) {
    //             modalBody.innerHTML = html;
    //         },
    //         error: function(xhr, status, error) {
    //             console.error('Error loading stock locks:', error);
    //             modalBody.innerHTML = '<div class="alert alert-danger">Error loading stock locks: ' + error + '</div>';
    //         }
    //     });
    // }

    // Function to show stock formula modal
    function showStockFormulaModal(variationId, sku, productModel, storageName, colorName, gradeName, colorCode) {
        const modal = new bootstrap.Modal(document.getElementById('stockFormulaModal'));
        const modalBody = document.getElementById('stockFormulaModalBody');
        const modalFooter = document.getElementById('stockFormulaModalFooter');
        
        // Hide footer initially
        modalFooter.style.display = 'none';
        
        // Build variation heading HTML
        const variationHeading = `
            <div class="mb-3 pb-2 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <a href="{{url('inventory')}}?sku=${sku}" title="View Inventory" target="_blank" class="text-decoration-none">
                        <span style="background-color: ${colorCode}; width: 30px; height: 16px; display: inline-block; vertical-align: middle;"></span>
                        <strong>${sku}</strong>
                    </a>
                    <a href="https://www.backmarket.fr/bo-seller/listings/active?sku=${sku}" title="View BM Ad" target="_blank" class="text-decoration-none text-muted">
                        - ${productModel} ${storageName} ${colorName} ${gradeName}
                    </a>
                </div>
            </div>
        `;
        
        // Show loading state with variation heading
        modalBody.innerHTML = variationHeading + '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2 text-muted">Loading stock formulas...</p></div>';
        modal.show();
        
        // Load stock formula modal content via API
        $.ajax({
            url: window.ListingConfig.urls.getStockFormulaModal + '/' + variationId + '/modal',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.html) {
                    // Prepend variation heading to the loaded content
                    modalBody.innerHTML = variationHeading + response.html;
                    
                    // Set global toggle
                    const isGlobal = response.is_global || false;
                    const globalToggle = $('#stockFormulaGlobalToggle');
                    globalToggle.prop('checked', isGlobal);
                    globalToggle.prop('disabled', false);
                    $('#stockFormulaGlobalLabel small').text(isGlobal ? 'Global' : 'Per-Variation');
                    
                    // Show footer
                    modalFooter.style.display = 'flex';
                    // Setup save all button handler
                    setupSaveAllFormulas(variationId);
                } else {
                    modalBody.innerHTML = variationHeading + '<div class="alert alert-danger">Error: Invalid response format</div>';
                    modalFooter.style.display = 'flex';
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading stock formula modal:', error);
                let errorMsg = 'Error loading stock formulas';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                } else if (xhr.responseText) {
                    errorMsg = xhr.responseText;
                }
                modalBody.innerHTML = variationHeading + '<div class="alert alert-danger">' + errorMsg + '</div>';
                modalFooter.style.display = 'flex';
            }
        });
    }
    
    // Setup save all formulas button
    function setupSaveAllFormulas(variationId) {
        $('#saveAllFormulasBtn').off('click').on('click', function() {
            const btn = $(this);
            const originalText = btn.html();
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');
            
            // Collect all formula forms
            const forms = $('.formula-inline-form');
            let savedCount = 0;
            let errorCount = 0;
            const totalForms = forms.length;
            
            // Count forms with values
            let formsWithValues = 0;
            forms.each(function() {
                const value = $(this).find('input[name="value"]').val().trim();
                if (value && value !== '') {
                    formsWithValues++;
                }
            });
            
            if (formsWithValues === 0) {
                btn.prop('disabled', false).html(originalText);
                // Don't show alert, just return silently
                return;
            }
            
            // Collect all form data first
            const formsToSave = [];
            forms.each(function() {
                const form = $(this);
                const marketplaceId = form.data('marketplace-id');
                const valueInput = form.find(`#formula_value_${marketplaceId}`);
                const value = valueInput.val().trim();
                
                // Skip if value is empty
                if (!value || value === '') {
                    return;
                }
                
                const numValue = parseFloat(value);
                const type = form.find(`#formula_type_${marketplaceId}`).val();
                const applyTo = form.find(`#formula_apply_to_${marketplaceId}`).val();
                
                if (isNaN(numValue) || numValue < 0) {
                    errorCount++;
                    return;
                }
                
                const minThreshold = form.find(`#min_threshold_${marketplaceId}`).val();
                const maxThreshold = form.find(`#max_threshold_${marketplaceId}`).val();
                
                const saveUrl = (window.StockFormulaConfig && window.StockFormulaConfig.urls && window.StockFormulaConfig.urls.saveFormula) 
                    ? window.StockFormulaConfig.urls.saveFormula + variationId + '/formula/' + marketplaceId
                    : window.ListingConfig.urls.getStockFormulaModal + '/' + variationId + '/formula/' + marketplaceId;
                
                formsToSave.push({
                    url: saveUrl,
                    marketplaceId: marketplaceId,
                    data: {
                        value: numValue,
                        type: type,
                        apply_to: applyTo,
                        min_threshold: minThreshold ? parseInt(minThreshold) : null,
                        max_threshold: maxThreshold ? parseInt(maxThreshold) : null,
                        _token: (window.StockFormulaConfig && window.StockFormulaConfig.csrfToken ? window.StockFormulaConfig.csrfToken : window.ListingConfig.csrfToken)
                    }
                });
            });
            
            // Save forms sequentially using promises
            let savePromise = Promise.resolve();
            formsToSave.forEach(function(formData) {
                savePromise = savePromise.then(function() {
                    return new Promise(function(resolve) {
                        // Get fresh CSRF token from meta tag or config
                        const csrfToken = $('meta[name="csrf-token"]').attr('content') 
                            || (window.StockFormulaConfig && window.StockFormulaConfig.csrfToken)
                            || window.ListingConfig.csrfToken;
                        
                        // Update token in form data
                        formData.data._token = csrfToken;
                        
                        $.ajax({
                            url: formData.url,
                            type: 'POST',
                            data: formData.data,
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            success: function(response) {
                                if (response.success) {
                                    savedCount++;
                                } else {
                                    errorCount++;
                                    console.error('Failed to save formula for marketplace ' + formData.marketplaceId, response);
                                }
                                resolve();
                            },
                            error: function(xhr, status, error) {
                                console.error('Error saving formula for marketplace ' + formData.marketplaceId, {
                                    status: status,
                                    error: error,
                                    response: xhr.responseJSON || xhr.responseText
                                });
                                errorCount++;
                                resolve();
                            }
                        });
                    });
                });
            });
            
            // Wait for all saves to complete
            savePromise.then(function() {
                // Show result
                btn.prop('disabled', false).html(originalText);
                
                if (errorCount === 0) {
                    // Close the modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('stockFormulaModal'));
                    if (modal) {
                        modal.hide();
                    }
                    
                    // Refresh the variation card to show updated thresholds
                    // Reload the page or refresh the specific variation card
                    location.reload();
                } else {
                    // Only show error if there were failures
                    alert(`Error: ${errorCount} of ${formsToSave.length} formulas failed to save. Please try again.`);
                }
            });
            
            return; // Exit early, promise will handle the result
            
        });
    }
    
    // Also expose on window for global access
    window.showStockFormulaModal = showStockFormulaModal;
</script>
<script src="{{asset('assets/v2/listing/js/total-stock-form.js')}}"></script>
<script src="{{asset('assets/v2/listing/js/keyboard-navigation.js')}}"></script>
<script src="{{asset('assets/v2/listing/js/price-validation.js')}}"></script>
<script src="{{asset('assets/v2/listing/js/page-controls.js')}}"></script>
<script src="{{asset('assets/v2/listing/js/bulk-updates.js')}}"></script>
<script src="{{asset('assets/v2/listing/js/listing.js')}}"></script>
@endsection
