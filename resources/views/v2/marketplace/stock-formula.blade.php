@extends('layouts.app')

@section('styles')
<style>
    .formula-card {
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        padding: 1rem;
        margin-bottom: 1rem;
        width: 100%;
    }
    .card-body {
        padding: 1rem;
    }
    .formula-inline-form {
        background-color: #f8f9fa;
        padding: 1rem;
        border-radius: 0.375rem;
    }
    .formula-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
        border-radius: 0.25rem;
    }
    .variation-info {
        background-color: #f8f9fa;
        padding: 1rem;
        border-radius: 0.375rem;
        margin-bottom: 1.5rem;
    }
    .search-results {
        max-height: 400px;
        overflow-y: auto;
    }
</style>
@endsection

@section('content')
<!-- breadcrumb -->
<div class="breadcrumb-header justify-content-between">
    <div class="left-content">
        <span class="main-content-title mg-b-0 mg-b-lg-1">Stock Formula Management</span>
    </div>
    <div class="justify-content-center mt-2">
        <ol class="breadcrumb">
            <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboards') }}</a></li>
            <li class="breadcrumb-item tx-15"><a href="{{url('v2/marketplace')}}">Marketplaces</a></li>
            <li class="breadcrumb-item active" aria-current="page">Stock Formula</li>
        </ol>
    </div>
</div>
<!-- /breadcrumb -->
<hr style="border-bottom: 1px solid #000">

<div id="alert-container"></div>

<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header pb-0">
                <h4 class="card-title mg-b-0">Search and Select Variation</h4>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Search Variation (SKU or Model)</label>
                    <div class="input-group">
                        <input type="text" 
                               id="variation_search_input"
                               class="form-control" 
                               value="{{ $searchTerm ?? '' }}"
                               placeholder="Type at least 2 characters to search...">
                        <button class="btn btn-primary" type="button" id="search_btn">
                            <i class="fe fe-search"></i> Search
                        </button>
                    </div>
                    <small class="text-muted">Type at least 2 characters and press Enter or click Search</small>
                </div>

                <div id="search_results_container" class="mt-3" style="display: none;">
                    <h6>Search Results:</h6>
                    <div id="search_results" class="list-group search-results">
                        <!-- Results will be loaded here via AJAX -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($selectedVariation)
@php
    $variationId = $selectedVariation->id;
    $sku = $selectedVariation->sku ?? 'N/A';
    $colorId = $selectedVariation->color ?? null;
    $colorName = isset($colors[$colorId]) ? $colors[$colorId] : '';
    
    // Convert color name to CSS color
    $colorCode = $colorName;
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
    foreach($colorMap as $key => $value) {
        if(str_contains($colorNameLower, $key)) {
            $colorCode = $value;
            break;
        }
    }
    if(empty($colorCode) || !preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$|^[a-z]+$/i', $colorCode)) {
        $colorCode = '#ccc';
    }
    
    $storageId = $selectedVariation->storage ?? null;
    $storageName = isset($storages[$storageId]) ? $storages[$storageId] : '';
    $gradeId = $selectedVariation->grade ?? null;
    $gradeName = isset($grades[$gradeId]) ? $grades[$gradeId] : '';
    $productModel = $selectedVariation->product->model ?? 'N/A';
    $productId = $selectedVariation->product_id ?? 0;
    
    // Get total stock from variation (source of truth, not calculated from children)
    $totalStock = $selectedVariation->listed_stock ?? 0;
    
    $availableStocks = $selectedVariation->available_stocks ?? collect();
    $pendingOrders = $selectedVariation->pending_orders ?? collect();
    $pendingBmOrders = $selectedVariation->pending_bm_orders ?? collect();
    $availableCount = $availableStocks->count();
    $pendingCount = $pendingOrders->count();
    $pendingBmCount = $pendingBmOrders->count();
    $difference = $availableCount - $pendingCount;
@endphp

<div class="row mt-3">
    <div class="col-xl-12">
        <div class="card" style="padding-left: 5px; padding-right: 5px;">
            <div class="card-header py-0 d-flex justify-content-between" style="padding-left: 5px; padding-right: 5px;">
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
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="d-flex align-items-center justify-content-start gap-2 flex-wrap">
                            <h6 class="mb-0">
                                <a class="" href="{{url('order').'?sku='}}{{$sku}}&status=2" target="_blank">
                                    Pending Order Items: {{ $pendingCount }} (BM Orders: {{ $pendingBmCount }})
                                </a>
                            </h6>
                            <span class="text-muted">|</span>
                            <h6 class="mb-0">
                                <a href="{{url('inventory').'?product='}}{{$productId}}&storage={{$storageId}}&color={{$colorId}}&grade[]={{$gradeId}}" target="_blank">
                                    Available: {{ $availableCount }}
                                </a>
                            </h6>
                            <span class="text-muted">|</span>
                            <h6 class="mb-0">Difference: {{ $difference }}</h6>
                            <span class="text-muted">|</span>
                            <h6 class="mb-0">Total Stock: {{ $totalStock }}</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header pb-0 d-flex justify-content-between align-items-start">
                <div>
                    <h4 class="card-title mg-b-0">Marketplace Stock Formulas</h4>
                    <p class="text-muted small mb-0">Configure how stock is distributed across marketplaces when stock is updated</p>
                </div>
                @if($selectedVariation)
                <div class="ms-3">
                    <form class="form-inline d-inline-flex gap-1 align-items-center" id="total_stock_form_formula_{{ $selectedVariation->id }}" action="{{url('listing/add_quantity')}}/{{ $selectedVariation->id }}">
                        @csrf
                        <div class="form-floating">
                            <input type="number" class="form-control" id="total_stock_stock_formula_{{ $selectedVariation->id }}" value="{{ $totalStock }}" style="width:120px;" min="0" step="1">
                            <label for="" class="small">Total Stock</label>
                        </div>
                        <button type="button" id="save_total_stock_formula_{{ $selectedVariation->id }}" class="btn btn-sm btn-primary" style="height: 31px; line-height: 1;" title="Update total stock and distribute to marketplaces">
                            <i class="fe fe-save"></i> Update
                        </button>
                        <span class="text-success small" id="success_total_stock_formula_{{ $selectedVariation->id }}"></span>
                    </form>
                </div>
                @endif
            </div>
            <div class="card-body">
                @foreach($marketplaceStocks as $marketplaceId => $marketplaceStock)
                <div class="formula-card" id="marketplace_card_{{ $marketplaceStock['marketplace_id'] }}">
                    <div class="d-flex justify-content-between align-items-center gap-3">
                        <!-- Left Side: Marketplace Info -->
                        <div class="d-flex align-items-center gap-3">
                            <div>
                                <h6 class="mb-0">{{ $marketplaceStock['marketplace_name'] }}</h6>
                                <small class="text-muted">
                                    Stock: <span id="stock_{{ $marketplaceStock['marketplace_id'] }}">{{ $marketplaceStock['listed_stock'] }}</span>
                                </small>
                                <!-- Reset Stock Form -->
                                <div class="mt-1">
                                    <form class="d-inline-flex align-items-center gap-1 reset-stock-form" 
                                          id="reset_stock_form_{{ $marketplaceStock['marketplace_id'] }}" 
                                          data-variation-id="{{ $selectedVariation->id }}" 
                                          data-marketplace-id="{{ $marketplaceStock['marketplace_id'] }}"
                                          onsubmit="return false;">
                                        <input type="number" 
                                               class="form-control form-control-sm" 
                                               name="stock"
                                               id="reset_stock_value_{{ $marketplaceStock['marketplace_id'] }}"
                                               value="{{ $marketplaceStock['listed_stock'] }}"
                                               min="0"
                                               placeholder="Set stock"
                                               style="width: 80px; height: 24px; font-size: 0.75rem;"
                                               required>
                                        <button type="button" class="btn btn-sm btn-secondary reset-stock-btn" style="height: 24px; padding: 0 8px; font-size: 0.75rem;" title="Reset stock to exact value" data-form-id="reset_stock_form_{{ $marketplaceStock['marketplace_id'] }}">
                                            <i class="fe fe-refresh-cw"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            @if($marketplaceStock['has_formula'])
                            @php $formula = $marketplaceStock['formula']; @endphp
                            <div id="formula_display_{{ $marketplaceStock['marketplace_id'] }}">
                                <span class="formula-badge bg-info text-white">
                                    {{ $formula['value'] ?? '' }}{{ ($formula['type'] ?? 'percentage') == 'percentage' ? '%' : '=' }} 
                                    ({{ ($formula['apply_to'] ?? 'pushed') == 'pushed' ? 'Pushed' : 'Total' }})
                                </span>
                            </div>
                            @endif
                        </div>
                        
                        <!-- Right Side: Formula Form -->
                        <div class="flex-shrink-0">
                            <form class="formula-inline-form" id="formula_form_{{ $marketplaceStock['marketplace_id'] }}" data-variation-id="{{ $selectedVariation->id }}" data-marketplace-id="{{ $marketplaceStock['marketplace_id'] }}">
                                <div class="d-flex align-items-center gap-2">
                                    <input type="number" 
                                           class="form-control form-control-sm" 
                                           name="value" 
                                           id="formula_value_{{ $marketplaceStock['marketplace_id'] }}"
                                           value="{{ ($marketplaceStock['has_formula'] && isset($marketplaceStock['formula']['value'])) ? $marketplaceStock['formula']['value'] : '' }}"
                                           step="0.01"
                                           min="0"
                                           placeholder="Value"
                                           style="width: 100px;"
                                           required>
                                    <select class="form-control form-control-sm" name="type" id="formula_type_{{ $marketplaceStock['marketplace_id'] }}" style="width: 80px;">
                                        <option value="percentage" {{ ($marketplaceStock['has_formula'] && isset($marketplaceStock['formula']['type']) && $marketplaceStock['formula']['type'] == 'percentage') ? 'selected' : '' }}>%</option>
                                        <option value="fixed" {{ ($marketplaceStock['has_formula'] && isset($marketplaceStock['formula']['type']) && $marketplaceStock['formula']['type'] == 'fixed') ? 'selected' : '' }}>=</option>
                                    </select>
                                    <select class="form-control form-control-sm" name="apply_to" id="formula_apply_to_{{ $marketplaceStock['marketplace_id'] }}" style="width: 150px;">
                                        <option value="pushed" {{ ($marketplaceStock['has_formula'] && isset($marketplaceStock['formula']['apply_to']) && $marketplaceStock['formula']['apply_to'] == 'pushed') ? 'selected' : 'selected' }}>Pushed Value</option>
                                        <option value="total" {{ ($marketplaceStock['has_formula'] && isset($marketplaceStock['formula']['apply_to']) && $marketplaceStock['formula']['apply_to'] == 'total') ? 'selected' : '' }}>Total Stock</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="fe fe-save"></i> Save
                                    </button>
                                    @if($marketplaceStock['has_formula'])
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteFormula({{ $selectedVariation->id }}, {{ $marketplaceStock['marketplace_id'] }})">
                                        <i class="fe fe-trash"></i>
                                    </button>
                                    @endif
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif

@endsection

@section('scripts')
<script>
    // Configuration
    window.StockFormulaConfig = {
        urls: {
            search: "{{ url('v2/marketplace/stock-formula/search') }}",
            getStocks: "{{ url('v2/marketplace/stock-formula') }}/",
            saveFormula: "{{ url('v2/marketplace/stock-formula') }}/",
            deleteFormula: "{{ url('v2/marketplace/stock-formula') }}/",
            resetStock: "{{ url('v2/marketplace/stock-formula') }}/",
        },
        csrfToken: "{{ csrf_token() }}",
        selectedVariationId: {{ $selectedVariation->id ?? 'null' }},
        marketplaces: @json($marketplaces->values()->all() ?? [])
    };
</script>
<script src="{{asset('assets/v2/marketplace/js/stock-formula.js')}}"></script>
@endsection

