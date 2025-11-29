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
                    <span class="text-muted small">Sales data will load when scrolled into view</span>
                </div>
                {{-- Buybox and Total Orders Info --}}
                <div class="buybox-orders-info mt-1 d-flex gap-2 align-items-center flex-wrap">
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
                    <a class="text-decoration-none" href="{{ url('order') }}?sku={{ $variation->sku }}" target="_blank" title="View all orders for this variation">
                        <small class="badge text-white" style="background-color: #003d82;">
                            Total Orders: {{ $totalOrdersCount ?? 0 }}
                        </small>
                    </a>
                </div>
                {{-- Marketplace Cards Row --}}
                @php
                    $allMarketplaces = $this->getAllMarketplaces();
                @endphp
                @if(!empty($allMarketplaces))
                    <div class="marketplace-cards-row mt-2 d-flex gap-2 flex-wrap">
                        @foreach($allMarketplaces as $marketplaceId => $marketplaceData)
                            @php
                                $marketplaceName = is_object($marketplaceData) 
                                    ? ($marketplaceData->name ?? 'Marketplace ' . $marketplaceId)
                                    : (is_array($marketplaceData) 
                                        ? ($marketplaceData['name'] ?? 'Marketplace ' . $marketplaceId)
                                        : 'Marketplace ' . $marketplaceId);
                                $summary = $marketplaceSummaries[$marketplaceId] ?? [];
                            @endphp
                            <div class="card marketplace-card border shadow-sm" style="min-width: 180px; max-width: 200px; flex: 1 1 auto;">
                                <div class="card-body p-2">
                                    <div class="d-flex align-items-center mb-2 border-bottom pb-1">
                                        <div class="marketplace-avatar me-2" style="width: 28px; height: 28px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 11px; color: white;">
                                            {{ strtoupper(substr($marketplaceName, 0, 2)) }}
                                        </div>
                                        <h6 class="mb-0 small fw-bold text-truncate" style="flex: 1; font-size: 12px;">{{ $marketplaceName }}</h6>
                                    </div>
                                    <div class="sales-summary">
                                        @if(($summary['today_count'] ?? 0) > 0)
                                            <span class="badge bg-info mb-1 d-block" style="font-size: 10px;" title="Today's orders">
                                                Today: €{{ number_format($summary['today_total'] ?? 0, 2) }} ({{ $summary['today_count'] ?? 0 }})
                                            </span>
                                        @endif
                                        @if(($summary['last_7_days_count'] ?? 0) > 0)
                                            <span class="badge bg-secondary mb-1 d-block" style="font-size: 10px;" title="Last 7 days orders">
                                                7d: €{{ number_format($summary['last_7_days_total'] ?? 0, 2) }} ({{ $summary['last_7_days_count'] ?? 0 }})
                                            </span>
                                        @endif
                                        @if(($summary['last_30_days_count'] ?? 0) > 0)
                                            <span class="badge bg-warning mb-1 d-block" style="font-size: 10px;" title="Last 30 days orders">
                                                30d: €{{ number_format($summary['last_30_days_total'] ?? 0, 2) }} ({{ $summary['last_30_days_count'] ?? 0 }})
                                            </span>
                                        @endif
                                        @if(($summary['pending_count'] ?? 0) > 0)
                                            <span class="badge bg-danger mb-1 d-block" style="font-size: 10px;" title="Pending orders">
                                                Pending: {{ $summary['pending_count'] ?? 0 }}
                                            </span>
                                        @endif
                                        @if(($summary['today_count'] ?? 0) == 0 && ($summary['last_7_days_count'] ?? 0) == 0 && ($summary['last_30_days_count'] ?? 0) == 0 && ($summary['pending_count'] ?? 0) == 0)
                                            <span class="text-muted small">No orders</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
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
                        <small class="fw-bold">Pending: {{ $stats['pending_orders'] ?? 0 }}</small>
                    </a>
                </div>
                <div class="mb-1">
                    <a class="text-decoration-none" href="{{ url('inventory') }}?product={{ $variation->product_id }}&storage={{ $variation->storage }}&color={{ $variation->color }}&grade[]={{ $variation->grade }}" target="_blank">
                        <small class="fw-bold">Available: {{ $stats['available_stocks'] ?? 0 }}</small>
                    </a>
                </div>
                <div>
                    <small class="fw-bold">
                        Diff: {{ $stats['stock_difference'] ?? 0 }}
                    </small>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="action-buttons ms-2">
                <a href="javascript:void(0)" class="btn btn-sm btn-link" id="variation_history_{{ $variation->id }}" onClick="show_variation_history({{ $variation->id }}, '{{ $variation->sku }} {{ $variation->product->model ?? '' }} {{ $storages[$variation->storage] ?? '' }} {{ $colors[$variation->color] ?? '' }} {{ $grades[$variation->grade] ?? '' }}')" title="View History">
                    <i class="fas fa-history"></i>
                </a>
                <button class="btn btn-sm btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#marketplaceAccordion_{{ $variation->id }}" aria-expanded="false" aria-controls="marketplaceAccordion_{{ $variation->id }}" title="Toggle Marketplaces">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
        </div>


        {{-- Marketplace Accordion Section --}}
        <div class="card-body p-0 collapse multi_collapse" id="marketplaceAccordion_{{ $variation->id }}" 
             data-variation-id="{{ $variation->id }}">
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

