<div class="card custom-card" wire:init="loadInventoryOverview">
    <div class="card-header border-bottom-0 d-flex justify-content-between align-items-center">
        <div>
            <h3 class="card-title mb-2">Available Inventory by Grade</h3>
            @if ($canViewListingTotal && $readyToLoad)
                <small class="text-muted">Should be listed: {{ $shouldBeListed }}</small>
            @endif
        </div>
        <div class="d-flex align-items-center gap-2">
            @if ($canViewListingTotal && $readyToLoad)
                <h3 class="card-title mb-0" title="Should Be : {{ $shouldBeListed }}">
                    Total Listed Inventory: {{ $listedInventory }}
                </h3>
            @endif
            @if ($readyToLoad)
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="refreshInventoryOverview" wire:loading.attr="disabled" wire:target="refreshInventoryOverview">
                    <i class="fe fe-refresh-cw"></i>
                </button>
            @endif
        </div>
    </div>

    <div class="card-body">
        <div class="py-3 text-center" wire:loading.flex wire:target="loadInventoryOverview,refreshInventoryOverview">
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            <span class="ms-2">Loading...</span>
        </div>

        <div wire:loading.remove wire:target="loadInventoryOverview,refreshInventoryOverview">
            @if (! $readyToLoad)
                <p class="text-muted mb-0">Access denied or widget disabled.</p>
            @else
                <div class="row g-2">
                    @if ($canViewInventory && $gradedInventory->isNotEmpty())
                        @foreach ($gradedInventory as $inv)
                            <div class="col-lg-3 col-md-4">
                                <h6>
                                    <a href="{{ url('inventory') }}?grade[]={{ $inv->grade_id }}&amp;status={{ $inv->status_id }}" wire:navigate>
                                        {{ $inv->grade }}: {{ $inv->quantity }} {{ $purchaseStatus[$inv->status_id] ?? '' }}
                                    </a>
                                </h6>
                            </div>
                        @endforeach
                    @elseif ($canViewInventory)
                        <p class="text-muted mb-0">No graded inventory found for the current filters.</p>
                    @endif
                </div>

                @if ($canViewPendingOrders && $pendingOrdersCount->isNotEmpty())
                    <h6 class="tx-right mb-0 mt-3">
                        Pending Orders:&nbsp;&nbsp;
                        @foreach ($pendingOrdersCount as $pending)
                            <span title="Value: €{{ $pending->price }}">
                                {{ $pending->order_type->name }}: {{ $pending->count }}&nbsp;&nbsp;
                            </span>
                        @endforeach
                    </h6>
                @elseif ($canViewPendingOrders)
                    <p class="text-muted text-end mb-0 mt-3">No pending orders.</p>
                @endif
            @endif
        </div>
    </div>
</div>
