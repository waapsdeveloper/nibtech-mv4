<div class="card listing-item-card">
    @if (!$ready || $variation === null)
        {{-- Don't show individual loaders - data is preloaded via batch render, cards render instantly --}}
        @if(!isset($preloadedVariationData))
            {{-- Only show loader if no preloaded data (shouldn't happen normally) --}}
            <div @if(!$ready) wire:init="loadRow" @endif class="card-header py-2">
                <div class="d-flex align-items-center">
                    <div class="spinner-border spinner-border-sm text-primary me-2" style="width: 1rem; height: 1rem;" role="status"></div>
                    <span class="text-muted small">Loading...</span>
                </div>
            </div>
        @endif
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
                    @if(isset($salesData) && $salesData)
                        {!! $salesData !!}
                    @else
                        <span class="text-muted small">Sales data will load when scrolled into view</span>
                    @endif
                </div>
                {{-- Buybox Info --}}
                <div class="buybox-info mt-1 d-flex gap-2 align-items-center flex-wrap">
                    <div class="d-flex align-items-center flex-wrap gap-1">
                        <span class="small fw-bold me-1">Buybox:</span>
                        @forelse($buyboxListings as $listing)
                            @php
                                $countryId = $listing['country_id'] ?? null;
                                $country = $countryId ? ($countries[$countryId] ?? null) : null;
                                $countryCode = is_object($country) ? $country->code : ($country['code'] ?? '');
                                $marketUrl = is_object($country) ? $country->market_url : ($country['market_url'] ?? '');
                                $marketCode = is_object($country) ? $country->market_code : ($country['market_code'] ?? '');
                                $referenceUuid2 = $listing['reference_uuid_2'] ?? '';
                            @endphp
                            <a href="https://www.backmarket.{{ $marketUrl }}/{{ $marketCode }}/p/gb/{{ $referenceUuid2 }}" target="_blank" class="btn btn-sm btn-link border p-1" title="View listing">
                                <img src="{{ asset('assets/img/flags/') }}/{{ strtolower($countryCode) }}.svg" height="10" alt="{{ $countryCode }}">
                                {{ $countryCode }}
                            </a>
                        @empty
                            <span class="text-muted small">No buybox listings</span>
                        @endforelse
                    </div>
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
            <div class="stats-section ms-2 text-end">
                <div class="mb-2">
                    <a class="text-decoration-none" href="{{ url('order') }}?sku={{ $variation->sku }}&status=2" target="_blank">
                        <div class="fw-bold" style="font-size: 14px;">Pending: {{ $stats['pending_orders'] ?? 0 }}</div>
                    </a>
                </div>
                <div class="mb-2">
                    <a class="text-decoration-none" href="{{ url('inventory') }}?product={{ $variation->product_id }}&storage={{ $variation->storage }}&color={{ $variation->color }}&grade[]={{ $variation->grade }}" target="_blank">
                        <div class="fw-bold" style="font-size: 14px;">Available: {{ $stats['available_stocks'] ?? 0 }}</div>
                    </a>
                </div>
                <div class="mb-2">
                    <div class="fw-bold" style="font-size: 14px;">
                        Diff: {{ $stats['stock_difference'] ?? 0 }}
                    </div>
                </div>
                <div>
                    <a class="text-decoration-none" href="{{ url('order') }}?sku={{ $variation->sku }}" target="_blank" title="View all orders for this variation">
                        <div class="fw-bold" style="font-size: 14px;">Total Orders: {{ $totalOrdersCount ?? 0 }}</div>
                    </a>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="action-buttons ms-2">
                <a href="javascript:void(0)" class="btn btn-sm btn-link" id="variation_history_{{ $variation->id }}" onClick="show_variation_history({{ $variation->id }}, '{{ $variation->sku }} {{ $variation->product->model ?? '' }} {{ $storages[$variation->storage] ?? '' }} {{ $colors[$variation->color] ?? '' }} {{ $grades[$variation->grade] ?? '' }}')" title="View History">
                    <i class="fas fa-history"></i>
                </a>
                @php
                    $marketplaceIds = $this->getMarketplaceIds();
                    $hasMarketplaceListings = count($marketplaceIds) > 0;
                @endphp
                @if($hasMarketplaceListings)
                    <button class="btn btn-sm btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#marketplaceAccordion_{{ $variation->id }}" aria-expanded="true" aria-controls="marketplaceAccordion_{{ $variation->id }}" title="Toggle Marketplaces">
                        <i class="fas fa-chevron-up"></i>
                    </button>
                @endif
            </div>
        </div>

        @php
            $marketplaceIds = $this->getMarketplaceIds();
            $hasMarketplaceListings = count($marketplaceIds) > 0;
        @endphp
        @if($hasMarketplaceListings)
            {{-- Marketplace Accordion Section --}}
            <div class="card-body p-0 show" id="marketplaceAccordion_{{ $variation->id }}" 
                 data-variation-id="{{ $variation->id }}">
            <div class="accordion marketplace-accordion" id="marketplaceAccordionInner_{{ $variation->id }}">
                @php
                    // Get all marketplaces from system
                    $allMarketplaces = $this->getAllMarketplaces();
                    // Get marketplace IDs that have listings for this variation
                    $marketplaceIds = $this->getMarketplaceIds();
                @endphp
                
                @if(count($marketplaceIds) > 0)
                    @foreach($allMarketplaces as $mpId => $marketplaceData)
                        @php
                            // Only show marketplaces that have listings
                            $hasListings = in_array($mpId, $marketplaceIds);
                            $marketplaceName = $marketplaceData['name'] ?? 'Marketplace ' . $mpId;
                        @endphp
                        
                        @if($hasListings)
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
                        @endif
                    @endforeach
                @else
                    <div class="p-3 text-center text-muted">
                        <small>No marketplace listings available</small>
                    </div>
                @endif
            </div>
        </div>
        @endif
    @endif
</div>

