@php
    $variationId = $selectedVariation->id;
    $totalStock = $selectedVariation->listed_stock ?? 0;
@endphp

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
                    @include('v2.marketplace.stock-formula.partials.total-stock-form')
                </div>
                @endif
            </div>
            <div class="card-body">
                @foreach($marketplaceStocks as $marketplaceId => $marketplaceStock)
                    @include('v2.marketplace.stock-formula.partials.marketplace-formula-card', [
                        'marketplaceStock' => $marketplaceStock,
                        'variationId' => $variationId
                    ])
                @endforeach
            </div>
        </div>
    </div>
</div>

