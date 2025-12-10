@extends('layouts.app')

@section('styles')
<style>
    .formula-card {
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        padding: 1rem;
        margin-bottom: 1rem;
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

@if (session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <span class="alert-inner--icon"><i class="fe fe-thumbs-up"></i></span>
    <span class="alert-inner--text"><strong>{{session('success')}}</strong></span>
    <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
</div>
@php session()->forget('success'); @endphp
@endif

@if (session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <span class="alert-inner--icon"><i class="fe fe-thumbs-down"></i></span>
    <span class="alert-inner--text"><strong>{{session('error')}}</strong></span>
    <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
</div>
@php session()->forget('error'); @endphp
@endif

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
                               wire:model.debounce.500ms="searchTerm" 
                               placeholder="Type at least 2 characters to search...">
                        <button class="btn btn-primary" type="button" wire:click="performSearch" id="search_btn">
                            <i class="fe fe-search"></i> Search
                        </button>
                    </div>
                    <small class="text-muted">Type at least 2 characters and press Enter or click Search</small>
                </div>

                @if($searchTerm && strlen($searchTerm) >= 2)
                <div class="mt-3">
                    <h6>Search Results:</h6>
                    <div class="list-group">
                        @forelse($variations as $variation)
                        <a href="javascript:void(0)" 
                           class="list-group-item list-group-item-action" 
                           wire:click="selectVariation({{ $variation->id }})">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">{{ $variation->sku }}</h6>
                            </div>
                            <p class="mb-1">
                                <strong>Model:</strong> {{ $variation->product->model ?? 'N/A' }}<br>
                                <strong>Storage:</strong> {{ $variation->storage_id->name ?? 'N/A' }} | 
                                <strong>Color:</strong> {{ $variation->color_id->name ?? 'N/A' }} | 
                                <strong>Grade:</strong> {{ $variation->grade_id->name ?? 'N/A' }}
                            </p>
                        </a>
                        @empty
                        <div class="list-group-item">No variations found</div>
                        @endforelse
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if($selectedVariation)
<div class="row mt-3">
    <div class="col-xl-12">
        <div class="variation-info">
            <h5>Selected Variation</h5>
            <p class="mb-0">
                <strong>SKU:</strong> {{ $selectedVariation->sku }} | 
                <strong>Model:</strong> {{ $selectedVariation->product->model ?? 'N/A' }} | 
                <strong>Current Stock:</strong> {{ $selectedVariation->listed_stock ?? 0 }}
            </p>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header pb-0">
                <h4 class="card-title mg-b-0">Marketplace Stock Formulas</h4>
                <p class="text-muted small mb-0">Configure how stock is distributed across marketplaces when stock is updated</p>
            </div>
            <div class="card-body">
                @foreach($marketplaceStocks as $marketplaceStock)
                <div class="formula-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6>{{ $marketplaceStock['marketplace_name'] }}</h6>
                            <p class="mb-1">
                                <strong>Current Stock:</strong> {{ $marketplaceStock['listed_stock'] }}
                            </p>
                            @if($marketplaceStock['has_formula'])
                            @php $formula = $marketplaceStock['formula']; @endphp
                            <div class="mt-2">
                                <span class="formula-badge bg-info text-white">
                                    Type: {{ ucfirst($formula['type']) }}
                                </span>
                                @if(isset($formula['marketplaces']) && count($formula['marketplaces']) > 0)
                                <div class="mt-2">
                                    <small><strong>Distribution:</strong></small>
                                    @foreach($formula['marketplaces'] as $mp)
                                    @php 
                                        $mpName = collect($marketplaces)->firstWhere('id', $mp['marketplace_id'])->name ?? 'Marketplace ' . $mp['marketplace_id'];
                                    @endphp
                                    <span class="badge bg-secondary me-1">
                                        {{ $mpName }}: {{ $mp['value'] }}{{ $formula['type'] == 'percentage' ? '%' : ' units' }}
                                    </span>
                                    @endforeach
                                    @if($formula['remaining_to_marketplace_1'] ?? false)
                                    <span class="badge bg-warning text-dark">Remaining → Marketplace 1</span>
                                    @endif
                                </div>
                                @endif
                            </div>
                            @else
                            <span class="text-muted small">No formula configured</span>
                            @endif
                        </div>
                        <div>
                            <button class="btn btn-sm btn-primary" wire:click="editFormula({{ $marketplaceStock['marketplace_id'] }})">
                                <i class="fe fe-edit"></i> {{ $marketplaceStock['has_formula'] ? 'Edit' : 'Add' }} Formula
                            </button>
                            @if($marketplaceStock['has_formula'])
                            <button class="btn btn-sm btn-danger" wire:click="deleteFormula({{ $marketplaceStock['marketplace_id'] }})" onclick="return confirm('Are you sure you want to delete this formula?')">
                                <i class="fe fe-trash"></i> Delete
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif

@if($showFormulaForm)
<!-- Formula Modal/Form -->
<div class="modal fade show" style="display: block;" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Configure Stock Distribution Formula</h5>
                <button type="button" class="btn-close" wire:click="cancelEdit"></button>
            </div>
            <div class="modal-body">
                <form wire:submit.prevent="saveFormula">
                    <div class="form-group mb-3">
                        <label>Formula Type</label>
                        <select class="form-control" wire:model="formulaType">
                            <option value="percentage">Percentage</option>
                            <option value="fixed">Fixed Number</option>
                        </select>
                        <small class="text-muted">
                            @if($formulaType == 'percentage')
                            Enter percentages that will be applied to the stock increment amount
                            @else
                            Enter fixed numbers that will be distributed from the stock increment amount
                            @endif
                        </small>
                    </div>

                    <div class="form-group mb-3">
                        <label>Marketplace Distribution</label>
                        <button type="button" class="btn btn-sm btn-success mb-2" wire:click="addFormulaMarketplace">
                            <i class="fe fe-plus"></i> Add Marketplace
                        </button>

                        @foreach($formulaMarketplaces as $index => $mp)
                        <div class="row mb-2">
                            <div class="col-md-6">
                                <select class="form-control" wire:model="formulaMarketplaces.{{ $index }}.marketplace_id">
                                    <option value="">Select Marketplace</option>
                                    @foreach($marketplaces as $marketplace)
                                    <option value="{{ $marketplace->id }}">{{ $marketplace->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="number" 
                                       class="form-control" 
                                       wire:model="formulaMarketplaces.{{ $index }}.value" 
                                       step="0.01"
                                       placeholder="{{ $formulaType == 'percentage' ? 'Percentage' : 'Fixed Amount' }}">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-sm btn-danger" wire:click="removeFormulaMarketplace({{ $index }})">
                                    <i class="fe fe-trash"></i>
                                </button>
                            </div>
                        </div>
                        @endforeach

                        @if(count($formulaMarketplaces) == 0)
                        <p class="text-muted small">No marketplaces added. Click "Add Marketplace" to configure distribution.</p>
                        @endif
                    </div>

                    <div class="form-group mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" wire:model="remainingToMarketplace1" id="remainingToMarketplace1">
                            <label class="form-check-label" for="remainingToMarketplace1">
                                Distribute remaining stock to Marketplace 1
                            </label>
                        </div>
                        <small class="text-muted">
                            If enabled, any stock left after applying the formula will be added to Marketplace 1
                        </small>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="cancelEdit">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Formula</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
@endif

@endsection

@section('scripts')
<script src="{{asset('assets/v2/marketplace/js/stock-formula.js')}}"></script>
<script>
    // Auto-hide alerts after 5 seconds
    function autoHideAlerts() {
        setTimeout(function() {
            $('.alert').fadeOut('slow', function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Initialize when Livewire is ready
    function initStockFormula() {
        autoHideAlerts();
        
        // Handle Enter key on search input
        const searchInput = document.getElementById('variation_search_input');
        const searchBtn = document.getElementById('search_btn');
        
        if (searchInput) {
            // Remove any existing listeners
            const newInput = searchInput.cloneNode(true);
            searchInput.parentNode.replaceChild(newInput, searchInput);
            
            newInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Trigger search button click or Livewire method
                    if (searchBtn) {
                        searchBtn.click();
                    } else if (window.Livewire) {
                        // Find Livewire component
                        const wireId = document.querySelector('[wire\\:id]')?.getAttribute('wire:id');
                        if (wireId) {
                            const component = Livewire.find(wireId);
                            if (component && component.$wire) {
                                component.$wire.call('performSearch');
                            }
                        }
                    }
                }
            });
        }
    }

    // For Livewire 2.x
    document.addEventListener('livewire:load', function () {
        initStockFormula();
    });

    // For Livewire 3.x
    document.addEventListener('livewire:init', function () {
        initStockFormula();
    });

    // DOM ready fallback
    document.addEventListener('DOMContentLoaded', function() {
        // Wait a bit for Livewire to initialize
        setTimeout(initStockFormula, 500);
    });
</script>
@endsection
