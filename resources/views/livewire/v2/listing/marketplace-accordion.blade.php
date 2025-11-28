<div class="accordion-item" data-marketplace-id="{{ $marketplaceId }}" data-variation-id="{{ $variationId }}">
    <h2 class="accordion-header" id="heading_{{ $marketplaceId }}_{{ $variationId }}">
        <button 
            class="accordion-button {{ !$expanded ? 'collapsed' : '' }} p-2" 
            type="button" 
            data-bs-toggle="collapse" 
            data-bs-target="#collapse_{{ $marketplaceId }}_{{ $variationId }}" 
            aria-expanded="{{ $expanded ? 'true' : 'false' }}"
            aria-controls="collapse_{{ $marketplaceId }}_{{ $variationId }}"
            wire:click="toggleAccordion"
        >
            <div class="d-flex justify-content-between align-items-center w-100 me-3">
                <div class="flex-grow-1">
                    <strong>{{ $marketplaceName }}</strong>
                    @if(!$ready)
                        <span class="spinner-border spinner-border-sm ms-2" role="status" style="width: 1rem; height: 1rem;">
                            <span class="visually-hidden">Loading...</span>
                        </span>
                    @elseif($ready && count($listings) > 0)
                        <span class="badge bg-primary ms-2">{{ count($listings) }} listing(s)</span>
                    @endif
                </div>
                @if($ready)
                    <div class="order-summary d-flex gap-1 align-items-center flex-wrap">
                        @if($orderSummary['today_count'] > 0)
                            <span class="badge bg-info" title="Today's orders">Today: €{{ number_format($orderSummary['today_total'], 2) }} ({{ $orderSummary['today_count'] }})</span>
                        @endif
                        @if($orderSummary['last_7_days_count'] > 0)
                            <span class="badge bg-secondary" title="Last 7 days orders">7d: €{{ number_format($orderSummary['last_7_days_total'], 2) }} ({{ $orderSummary['last_7_days_count'] }})</span>
                        @endif
                        @if($orderSummary['last_30_days_count'] > 0)
                            <span class="badge bg-warning" title="Last 30 days orders">30d: €{{ number_format($orderSummary['last_30_days_total'], 2) }} ({{ $orderSummary['last_30_days_count'] }})</span>
                        @endif
                        @if($orderSummary['pending_count'] > 0)
                            <span class="badge bg-danger" title="Pending orders">Pending: {{ $orderSummary['pending_count'] }}</span>
                        @endif
                    </div>
                @endif
            </div>
        </button>
    </h2>
    
    {{-- Bulk Actions Section - Always Visible --}}
    @if($ready && count($listings) > 0)
        <div class="marketplace-bulk-actions p-3 border-top bg-light">
            <div class="row g-3">
                {{-- Change All € Handlers --}}
                <div class="col-md-4">
                    <label class="small fw-bold mb-2 d-block text-muted">Change All € Handlers</label>
                    <form class="d-flex flex-column gap-2" method="POST" id="change_all_handler_{{ $variationId }}_{{ $marketplaceId }}">
                        @csrf
                        <div class="d-flex gap-2 align-items-end">
                            <div class="flex-grow-1">
                                <label class="form-label small mb-1">Min Handler</label>
                                <input type="number" class="form-control form-control-sm" id="all_min_handler_{{ $variationId }}_{{ $marketplaceId }}" name="all_min_handler" step="0.01" value="" placeholder="0.00">
                            </div>
                            <div class="flex-grow-1">
                                <label class="form-label small mb-1">Handler</label>
                                <input type="number" class="form-control form-control-sm" id="all_handler_{{ $variationId }}_{{ $marketplaceId }}" name="all_handler" step="0.01" value="" placeholder="0.00">
                            </div>
                            <button type="button" class="btn btn-sm btn-primary" onclick="submitForm8Marketplace(event, {{ $variationId }}, {{ $marketplaceId }})">
                                Change
                            </button>
                        </div>
                    </form>
                </div>
                
                {{-- Change All € Prices --}}
                <div class="col-md-4">
                    <label class="small fw-bold mb-2 d-block text-muted">Change All € Prices</label>
                    <form class="d-flex flex-column gap-2" method="POST" id="change_all_price_{{ $variationId }}_{{ $marketplaceId }}">
                        @csrf
                        <div class="d-flex gap-2 align-items-end">
                            <div class="flex-grow-1">
                                <label class="form-label small mb-1">Min Price</label>
                                <input type="number" class="form-control form-control-sm" id="all_min_price_{{ $variationId }}_{{ $marketplaceId }}" name="all_min_price" step="0.01" value="" placeholder="0.00">
                            </div>
                            <div class="flex-grow-1">
                                <label class="form-label small mb-1">Price</label>
                                <input type="number" class="form-control form-control-sm" id="all_price_{{ $variationId }}_{{ $marketplaceId }}" name="all_price" step="0.01" value="" placeholder="0.00">
                            </div>
                            <button type="button" class="btn btn-sm btn-success" onclick="submitForm4Marketplace(event, {{ $variationId }}, {{ $marketplaceId }})">
                                Push
                            </button>
                        </div>
                    </form>
                </div>
                
                {{-- Without Buybox --}}
                <div class="col-md-4">
                    <div class="without-buybox-section">
                        <h6 class="fw-bold mb-2">Without Buybox</h6>
                        @php
                            $withoutBuyboxListings = collect($listings)->filter(fn($listing) => ($listing['buybox'] ?? 0) != 1);
                        @endphp
                        <div class="d-flex flex-wrap gap-1">
                            @forelse($withoutBuyboxListings as $listing)
                                @php
                                    $countryId = $listing['country_id'] ?? null;
                                    $country = $countryId ? ($countries[$countryId] ?? null) : null;
                                    $countryCode = is_object($country) ? $country->code : ($country['code'] ?? '');
                                    $marketUrl = is_object($country) ? $country->market_url : ($country['market_url'] ?? '');
                                    $marketCode = is_object($country) ? $country->market_code : ($country['market_code'] ?? '');
                                    $referenceUuid2 = $listing['reference_uuid_2'] ?? '';
                                @endphp
                                <a href="https://www.backmarket.{{ $marketUrl }}/{{ $marketCode }}/p/gb/{{ $referenceUuid2 }}" target="_blank" class="btn btn-sm btn-link text-danger border border-danger p-1">
                                    <img src="{{ asset('assets/img/flags/') }}/{{ strtolower($countryCode) }}.svg" height="10">
                                    {{ $countryCode }}
                                </a>
                            @empty
                                <span class="text-muted small">All listings have buybox or no listings.</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
    <div 
        id="collapse_{{ $marketplaceId }}_{{ $variationId }}" 
        class="accordion-collapse collapse {{ $expanded ? 'show' : '' }}" 
        aria-labelledby="heading_{{ $marketplaceId }}_{{ $variationId }}"
        {{-- Removed data-bs-parent to allow multiple accordions open at once --}}
        wire:ignore.self
        data-variation-id="{{ $variationId }}"
        data-marketplace-id="{{ $marketplaceId }}"
    >
        <script>
            // Ensure loading fires when accordion starts showing
            (function() {
                const collapseElement = document.getElementById('collapse_{{ $marketplaceId }}_{{ $variationId }}');
                if (!collapseElement) return;
                
                const handleShow = function() {
                    @if(!$ready)
                        // Trigger loading immediately when accordion starts to show
                        @this.call('loadData');
                    @endif
                };
                
                // Listen for when accordion starts showing (not just when fully shown)
                collapseElement.addEventListener('show.bs.collapse', handleShow);
                
                // Also trigger if already shown when script runs
                if (collapseElement.classList.contains('show')) {
                    setTimeout(handleShow, 50);
                }
            })();
        </script>
        <div class="accordion-body p-2">
            @if(!$ready)
                <div class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted mt-2 mb-0">Loading marketplace data...</p>
                </div>
            @else
                {{-- Stocks and Listings Tables Side by Side --}}
                <div class="row g-3">
                    {{-- Listings Table Column (Left - 70%) --}}
                    <div class="col-md-8">
                        <div class="listings-table-wrapper">
                            <h6 class="mb-2"><strong>Listings</strong></h6>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-hover mb-0 text-md-nowrap listing-table">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th width="80"><small><b>Country</b></small></th>
                                            <th width="100" title="Minimum Price Handler"><small><b>Min Hndlr</b></small></th>
                                            <th width="100" title="Price Handler"><small><b>Price Hndlr</b></small></th>
                                            <th width="80"><small><b>BuyBox</b></small></th>
                                            <th title="Min Price" width="120"><small><b>Min Price</b></small></th>
                                            <th width="120"><small><b>Price</b></small></th>
                                            <th width="120"><small><b>Target Price</b></small></th>
                                            <th><small><b>Date</b></small></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                @forelse($listings as $listing)
                                    @php
                                        $countryId = $listing['country_id'] ?? null;
                                        $country = $countryId ? ($countries[$countryId] ?? null) : null;
                                        $countryCode = is_object($country) ? $country->code : ($country['code'] ?? '');
                                        $countryTitle = is_object($country) ? $country->title : ($country['title'] ?? '');
                                        $marketUrl = is_object($country) ? $country->market_url : ($country['market_url'] ?? '');
                                        $marketCode = is_object($country) ? $country->market_code : ($country['market_code'] ?? '');
                                        $currencyId = $listing['currency_id'] ?? null;
                                        $currency = $currencyId ? ($currencies[$currencyId] ?? null) : null;
                                        $currencyCode = is_object($currency) ? $currency->code : ($currency['code'] ?? '');
                                        $currencySignValue = $currencyId ? ($currencySign[$currencyId] ?? '') : '';
                                        $handlerStatus = $listing['handler_status'] ?? 1;
                                        $handlerStatusClass = $handlerStatus == 2 ? 'text-danger' : '';
                                    @endphp
                                    <tr class="{{ ($listing['buybox'] ?? 0) != 1 ? 'table-warning' : '' }}">
                                        <td>
                                            <a href="https://www.backmarket.{{ $marketUrl }}/{{ $marketCode }}/p/gb/{{ $listing['reference_uuid_2'] ?? '' }}" target="_blank">
                                                <img src="{{ asset('assets/img/flags/') }}/{{ strtolower($countryCode) }}.svg" height="15" alt="{{ $countryCode }}">
                                                <small>{{ $countryCode }}</small>
                                            </a>
                                        </td>
                                        <td>
                                            <form class="form-inline" method="POST" id="change_limit_{{ $listing['id'] }}">
                                                @csrf
                                                <input type="submit" hidden>
                                            </form>
                                            <input type="number" class="form-control form-control-sm {{ $handlerStatusClass }}" 
                                                id="min_price_limit_{{ $listing['id'] }}" 
                                                name="min_price_limit" 
                                                step="0.01" 
                                                value="{{ $listing['min_price_limit'] ?? 0 }}" 
                                                form="change_limit_{{ $listing['id'] }}"
                                                style="width: 90px;">
                                        </td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm {{ $handlerStatusClass }}" 
                                                id="price_limit_{{ $listing['id'] }}" 
                                                name="price_limit" 
                                                step="0.01" 
                                                value="{{ $listing['price_limit'] ?? 0 }}" 
                                                form="change_limit_{{ $listing['id'] }}"
                                                style="width: 90px;">
                                        </td>
                                        <td>
                                            <small>
                                                {{ ($listing['buybox'] ?? 0) == 1 ? 'Yes' : 'No' }}
                                                @if(($listing['buybox'] ?? 0) != 1 && isset($listing['buybox_winner_price']))
                                                    <br><span class="text-danger">({{ $listing['buybox_winner_price'] }})</span>
                                                @endif
                                            </small>
                                        </td>
                                        <td>
                                            <form class="form-inline" method="POST" id="change_min_price_{{ $listing['id'] }}">
                                                @csrf
                                                <input type="submit" hidden>
                                            </form>
                                            <div class="form-floating">
                                                <input type="number" class="form-control form-control-sm" 
                                                    id="min_price_{{ $listing['id'] }}" 
                                                    name="min_price" 
                                                    step="0.01" 
                                                    value="{{ $listing['min_price'] ?? 0 }}" 
                                                    form="change_min_price_{{ $listing['id'] }}"
                                                    style="width: 100px;">
                                                <label for=""><small>Min</small></label>
                                            </div>
                                        </td>
                                        <td>
                                            <form class="form-inline" method="POST" id="change_price_{{ $listing['id'] }}">
                                                @csrf
                                                <input type="submit" hidden>
                                            </form>
                                            <div class="form-floating">
                                                <input type="number" class="form-control form-control-sm" 
                                                    id="price_{{ $listing['id'] }}" 
                                                    name="price" 
                                                    step="0.01" 
                                                    value="{{ $listing['price'] ?? 0 }}" 
                                                    form="change_price_{{ $listing['id'] }}"
                                                    style="width: 100px;">
                                                <label for=""><small>Price</small></label>
                                            </div>
                                        </td>
                                        <td>
                                            <small>
                                                {{ $listing['target_price'] ?? 0 }}
                                                @if(isset($listing['target_percentage']) && $listing['target_percentage'] > 0)
                                                    <br><span class="text-muted">({{ $listing['target_percentage'] }}%)</span>
                                                @endif
                                            </small>
                                        </td>
                                        <td>
                                            <small>{{ isset($listing['updated_at']) ? \Carbon\Carbon::parse($listing['updated_at'])->format('Y-m-d H:i') : '' }}</small>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">
                                            <small>No listings for this marketplace</small>
                                        </td>
                                    </tr>
                                @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Stocks Table Column (Right - 30%) --}}
                    <div class="col-md-4">
                        <div class="stocks-table-wrapper">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">
                                    <strong>Stocks</strong> 
                                    <small class="text-muted">(Avg: €{{ number_format($averageCost, 2) }})</small>
                                    @if($showAllStocks)
                                        <span class="badge bg-info ms-1">All System</span>
                                    @else
                                        <span class="badge bg-primary ms-1">Marketplace</span>
                                    @endif
                                </h6>
                                <button 
                                    type="button" 
                                    class="btn btn-sm btn-outline-primary" 
                                    wire:click="toggleStocksView"
                                    title="Toggle between marketplace stocks and all stocks"
                                >
                                    <i class="fas fa-exchange-alt"></i> 
                                    {{ $showAllStocks ? 'Show MP' : 'Show All' }}
                                </button>
                            </div>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-bordered table-hover mb-0 text-md-nowrap listing-table">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th><small><b>No</b></small></th>
                                            <th><small><b>IMEI/Serial</b></small></th>
                                            <th><small><b>Cost</b></small></th>
                                            <th><small><b>Vendor</b></small></th>
                                            <th><small><b>PO Ref</b></small></th>
                                            <th><small><b>Topup Ref</b></small></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($stocks as $index => $stock)
                                            @php
                                                $stockId = $stock['id'] ?? 0;
                                                $imei = $stock['imei'] ?? $stock['serial'] ?? '';
                                                $cost = $stockCosts[$stockId] ?? 0;
                                                $orderId = $stock['order_id'] ?? null;
                                                $vendorId = $orderId ? ($po[$orderId] ?? null) : null;
                                                $vendorName = $vendorId ? ($vendors[$vendorId] ?? '') : '';
                                                $poRef = $orderId ? ($reference[$orderId] ?? '') : '';
                                                $topupProcessId = $latestTopupItems[$stockId] ?? null;
                                                $topupRef = $topupProcessId ? ($topupReference[$topupProcessId] ?? '') : '';
                                            @endphp
                                    <tr class="{{ ($stock['status'] ?? 1) != 1 ? 'table-secondary' : '' }}">
                                        <td><small>{{ $index + 1 }}</small></td>
                                        <td><small>{{ $imei }}</small></td>
                                        <td><small>€{{ number_format($cost, 2) }}</small></td>
                                        <td><small>{{ $vendorName }}</small></td>
                                        <td><small>{{ $poRef }}</small></td>
                                        <td><small>{{ $topupRef }}</small></td>
                                    </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">
                                                    <small>No stocks available</small>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

