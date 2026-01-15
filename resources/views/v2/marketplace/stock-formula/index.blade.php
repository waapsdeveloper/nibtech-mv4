@extends('layouts.app')

@section('styles')
@include('v2.marketplace.stock-formula.partials.styles')
@endsection

@section('content')
@include('v2.marketplace.stock-formula.partials.breadcrumb')

<hr style="border-bottom: 1px solid #000">

<div id="alert-container"></div>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-3" id="stockFormulaTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ $activeTab === 'variations' ? 'active' : '' }}" id="variations-tab" data-bs-toggle="tab" data-bs-target="#variations-pane" type="button" role="tab" aria-controls="variations-pane" aria-selected="{{ $activeTab === 'variations' ? 'true' : 'false' }}">
            <i class="fe fe-list me-1"></i>Variation Formulas
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ $activeTab === 'global' ? 'active' : '' }}" id="global-tab" data-bs-toggle="tab" data-bs-target="#global-pane" type="button" role="tab" aria-controls="global-pane" aria-selected="{{ $activeTab === 'global' ? 'true' : 'false' }}">
            <i class="fe fe-settings me-1"></i>Global Defaults
        </button>
    </li>
</ul>

<!-- Tabs Content -->
<div class="tab-content" id="stockFormulaTabsContent">
    <!-- Variations Tab -->
    <div class="tab-pane fade {{ $activeTab === 'variations' ? 'show active' : '' }}" id="variations-pane" role="tabpanel" aria-labelledby="variations-tab">
        @include('v2.marketplace.stock-formula.partials.search-section')

        @if($selectedVariation)
            @include('v2.marketplace.stock-formula.partials.variation-info')
            @include('v2.marketplace.stock-formula.partials.formulas-section')
        @endif
    </div>

    <!-- Global Defaults Tab -->
    <div class="tab-pane fade {{ $activeTab === 'global' ? 'show active' : '' }}" id="global-pane" role="tabpanel" aria-labelledby="global-tab">
        @include('v2.marketplace.stock-formula.partials.global-defaults-section')
    </div>
</div>

@endsection

@section('scripts')
@include('v2.marketplace.stock-formula.partials.scripts')
<script>
    // Handle tab switching with URL parameters
    $(document).ready(function() {
        // Update URL when tab is clicked
        $('#stockFormulaTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            const targetTab = $(e.target).attr('data-bs-target');
            const tabName = targetTab === '#global-pane' ? 'global' : 'variations';
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
        });
        
        // Handle browser back/forward buttons
        window.addEventListener('popstate', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'variations';
            const tabButton = tab === 'global' ? '#global-tab' : '#variations-tab';
            const tabPane = tab === 'global' ? '#global-pane' : '#variations-pane';
            
            // Show the correct tab
            $('[data-bs-target="' + tabPane + '"]').tab('show');
        });
    });
</script>
@endsection

