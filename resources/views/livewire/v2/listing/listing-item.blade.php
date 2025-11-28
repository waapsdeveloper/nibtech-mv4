<div class="card listing-item-card" wire:init="loadRow">
    @if (!$ready || $variation === null)
        {{-- Loading State --}}
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <span class="text-muted">Loading variation {{ $variationId }}...</span>
            </div>
        </div>
    @else
        {{-- Variation Header --}}
        <div class="card-header py-2 d-flex justify-content-between align-items-start flex-wrap">
            <div class="variation-info flex-grow-1">
                <h5 class="mb-1">
                    <a href="{{ url('inventory') }}?sku={{ $variation->sku }}" title="View Inventory" target="_blank">
                        <strong>{{ $variation->sku }}</strong>
                        <span style="background-color: {{ $colors[$variation->color] ?? '' }}; width: 30px; height: 16px; display: inline-block; border-radius: 3px; vertical-align: middle; margin-left: 8px; {{ (str_contains(strtolower($colors[$variation->color] ?? ''), 'white') || strtolower($colors[$variation->color] ?? '') === '#ffffff' || strtolower($colors[$variation->color] ?? '') === '#fff' || str_contains(strtolower($colors[$variation->color] ?? ''), 'starlight')) ? 'border: 1px solid #ccc;' : '' }}"></span>
                    </a>
                    <a href="https://www.backmarket.fr/bo-seller/listings/active?sku={{ $variation->sku }}" title="View BM Ad" target="_blank" class="ms-2">
                        {{ $variation->product->model ?? '' }} {{ $storages[$variation->storage] ?? '' }} {{ $colors[$variation->color] ?? '' }} {{ $grades[$variation->grade] ?? '' }}
                    </a>
                </h5>
                <div class="sales-info" id="sales_{{ $variation->id }}" data-variation-id="{{ $variation->id }}">
                    <span class="text-muted small">Sales data will load when scrolled into view</span>
                </div>
                {{-- Buybox and Total Orders Info --}}
                <div class="buybox-orders-info mt-1 d-flex gap-2 align-items-center flex-wrap">
                    <small class="badge bg-success">
                        Buybox: {{ $pricingInfo['buybox_count'] ?? 0 }}
                    </small>
                    <a class="text-decoration-none" href="{{ url('order') }}?sku={{ $variation->sku }}" target="_blank" title="View all orders for this variation">
                        <small class="badge bg-info">
                            Total Orders: {{ $totalOrdersCount ?? 0 }}
                        </small>
                    </a>
                </div>
            </div>

            {{-- Stock Controls --}}
            <div class="stock-controls ms-2">
                <form class="form-inline" method="POST" id="add_qty_{{ $variation->id }}" action="{{ url('listing/add_quantity') }}/{{ $variation->id }}">
                    @csrf
                    <input type="hidden" name="process_id" value="{{ $processId }}">
                    <div class="form-floating me-2">
                        <input type="text" class="form-control" name="stock" id="quantity_{{ $variation->id }}" value="{{ $variation->listed_stock ?? 0 }}" style="width:60px;" disabled>
                        <label for="">Stock</label>
                    </div>
                    <div class="form-floating me-2">
                        <input type="number" class="form-control" name="stock" id="add_{{ $variation->id }}" value="" style="width:70px;" oninput="toggleButtonOnChange({{ $variation->id }}, this)">
                        <label for="">Add</label>
                    </div>
                    <button id="send_{{ $variation->id }}" class="btn btn-sm btn-light d-none me-2" onclick="submitForm1(event, {{ $variation->id }})">Push</button>
                    <span class="text-success small" id="success_{{ $variation->id }}"></span>
                </form>
            </div>

            {{-- Stats Section --}}
            <div class="stats-section ms-2">
                <div class="mb-1">
                    <a class="text-decoration-none" href="{{ url('order') }}?sku={{ $variation->sku }}&status=2" target="_blank">
                        <small class="badge bg-warning">Pending: {{ $stats['pending_orders'] ?? 0 }}</small>
                    </a>
                </div>
                <div class="mb-1">
                    <a class="text-decoration-none" href="{{ url('inventory') }}?product={{ $variation->product_id }}&storage={{ $variation->storage }}&color={{ $variation->color }}&grade[]={{ $variation->grade }}" target="_blank">
                        <small class="badge bg-success">Available: {{ $stats['available_stocks'] ?? 0 }}</small>
                    </a>
                </div>
                <div>
                    <small class="badge {{ ($stats['has_stock_issue'] ?? false) ? 'bg-danger' : 'bg-info' }}">
                        Diff: {{ $stats['stock_difference'] ?? 0 }}
                    </small>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="action-buttons ms-2">
                <a href="javascript:void(0)" class="btn btn-sm btn-link" id="variation_history_{{ $variation->id }}" onClick="show_variation_history({{ $variation->id }}, '{{ $variation->sku }} {{ $variation->product->model ?? '' }} {{ $storages[$variation->storage] ?? '' }} {{ $colors[$variation->color] ?? '' }} {{ $grades[$variation->grade] ?? '' }}')" data-bs-toggle="modal" data-bs-target="#modal_history" title="View History">
                    <i class="fas fa-history"></i>
                </a>
                <button class="btn btn-sm btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#marketplaceAccordion_{{ $variation->id }}" aria-expanded="false" aria-controls="marketplaceAccordion_{{ $variation->id }}" title="Toggle Marketplaces">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
        </div>


        {{-- Marketplace Accordion Section --}}
        <div class="card-body p-0 collapse multi_collapse" id="marketplaceAccordion_{{ $variation->id }}">
            <div class="accordion" id="marketplaceAccordionInner_{{ $variation->id }}">
                @php
                    // Get all marketplaces from system
                    $allMarketplaces = $this->getAllMarketplaces();
                    // Get marketplace IDs that have listings for this variation
                    $marketplaceIds = $this->getMarketplaceIds();
                @endphp
                
                @foreach($allMarketplaces as $mpId => $marketplaceData)
                    @php
                        $hasListings = in_array($mpId, $marketplaceIds);
                        $marketplaceName = $marketplaceData['name'] ?? 'Marketplace ' . $mpId;
                    @endphp
                    
                    @livewire('v2.listing.marketplace-accordion', [
                        'variationId' => $variation->id,
                        'marketplaceId' => $mpId,
                        'marketplaceName' => $marketplaceName,
                        'exchangeRates' => $exchangeRates,
                        'eurGbp' => $eurGbp,
                        'currencies' => $currencies,
                        'currencySign' => $currencySign,
                        'countries' => $countries,
                        'marketplaces' => $marketplaces,
                    ], key('marketplace-accordion-' . $variation->id . '-' . $mpId))
                @endforeach
            </div>
        </div>
    @endif
</div>

