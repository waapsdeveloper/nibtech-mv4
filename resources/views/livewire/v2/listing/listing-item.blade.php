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
                        <span style="background-color: {{ $colors[$variation->color] ?? '' }}; width: 30px; height: 16px; display: inline-block; border-radius: 3px; vertical-align: middle; {{ (str_contains(strtolower($colors[$variation->color] ?? ''), 'white') || strtolower($colors[$variation->color] ?? '') === '#ffffff' || strtolower($colors[$variation->color] ?? '') === '#fff' || str_contains(strtolower($colors[$variation->color] ?? ''), 'starlight')) ? 'border: 1px solid #ccc;' : '' }}"></span>
                        <strong>{{ $variation->sku }}</strong>
                    </a>
                    <a href="https://www.backmarket.fr/bo-seller/listings/active?sku={{ $variation->sku }}" title="View BM Ad" target="_blank" class="ms-2">
                        {{ $variation->product->model ?? '' }} {{ $storages[$variation->storage] ?? '' }} {{ $colors[$variation->color] ?? '' }} {{ $grades[$variation->grade] ?? '' }}
                    </a>
                </h5>
                <div class="sales-info" id="sales_{{ $variation->id }}">Loading sales data...</div>
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

        {{-- Bulk Actions Row --}}
        <div class="bulk-actions-row p-2 border-top">
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="small fw-bold mb-1 d-block">Change All € Handlers</label>
                    <form class="form-inline d-flex align-items-center gap-2" method="POST" id="change_all_handler_{{ $variation->id }}">
                        @csrf
                        <div class="form-floating">
                            <input type="number" class="form-control form-control-sm" id="all_min_handler_{{ $variation->id }}" name="all_min_handler" step="0.01" value="" style="width:90px;">
                            <label for="">Min Handler</label>
                        </div>
                        <div class="form-floating">
                            <input type="number" class="form-control form-control-sm" id="all_handler_{{ $variation->id }}" name="all_handler" step="0.01" value="" style="width:90px;">
                            <label for="">Handler</label>
                        </div>
                        <div class="btn-group d-inline">
                            <button type="button" class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" id="change_handler_dropdown_{{ $variation->id }}" onclick="populateHandlerDropdownOnClick({{ $variation->id }})">
                                Change
                            </button>
                            <ul class="dropdown-menu" id="change_handler_menu_{{ $variation->id }}">
                                <li><span class="dropdown-item-text">Loading...</span></li>
                            </ul>
                        </div>
                    </form>
                </div>
                <div class="col-md-4">
                    <label class="small fw-bold mb-1 d-block">Change All € Prices</label>
                    <form class="form-inline d-flex align-items-center gap-2" method="POST" id="change_all_price_{{ $variation->id }}">
                        @csrf
                        <div class="form-floating">
                            <input type="number" class="form-control form-control-sm" id="all_min_price_{{ $variation->id }}" name="all_min_price" step="0.01" value="" style="width:90px;">
                            <label for="">Min Price</label>
                        </div>
                        <div class="form-floating">
                            <input type="number" class="form-control form-control-sm" id="all_price_{{ $variation->id }}" name="all_price" step="0.01" value="" style="width:90px;">
                            <label for="">Price</label>
                        </div>
                        <div class="btn-group d-inline">
                            <button type="button" class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" id="change_price_dropdown_{{ $variation->id }}" onclick="populatePriceDropdownOnClick({{ $variation->id }})">
                                Push
                            </button>
                            <ul class="dropdown-menu" id="change_price_menu_{{ $variation->id }}">
                                <li><span class="dropdown-item-text">Loading...</span></li>
                            </ul>
                        </div>
                    </form>
                </div>
                <div class="col-md-4">
                    <div class="without-buybox-section">
                        <h6 class="fw-bold">Without Buybox</h6>
                        @php
                            $withoutBuyboxListings = $variation->listings->filter(fn($listing) => $listing->buybox !== 1);
                        @endphp
                        @forelse($withoutBuyboxListings as $listing)
                            <a href="https://www.backmarket.{{ $listing->country_id->market_url ?? '' }}/{{ $listing->country_id->market_code ?? '' }}/p/gb/{{ $listing->reference_uuid_2 ?? '' }}" target="_blank" class="btn btn-link text-danger border border-danger p-1 m-1">
                                <img src="{{ asset('assets/img/flags/') }}/{{ strtolower($listing->country_id->code ?? '') }}.svg" height="10">
                                {{ $listing->country_id->code ?? '' }}
                            </a>
                        @empty
                            <span class="text-muted">All listings have buybox or no listings.</span>
                        @endforelse
                    </div>
                    <div class="status-badge-section mt-2">
                        <h6 class="badge bg-light text-dark">
                            {{ [0 => 'Missing price/comment', 1 => 'Pending validation', 2 => 'Online', 3 => 'Offline', 4 => 'Deactivated'][$variation->state] ?? 'Unknown' }}
                        </h6>
                    </div>
                </div>
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

