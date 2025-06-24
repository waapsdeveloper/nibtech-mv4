<div>
    {{-- Success is as dangerous as failure. --}}
</div>
<div class="card custom-card">
    <div class="card-header border-bottom-0 d-flex justify-content-between">
        <h3 class="card-title mb-2">Available Inventory by Grade</h3>
        @if (session('user')->hasPermission('dashboard_view_listing_total'))
            <h3 class="card-title mb-2">Total Listed Inventory: {{ $listedInventory }}</h3>
        @endif
    </div>
    <div class="card-body row">
        @foreach ($gradedInventory as $inv)
            <div class="col-lg-3 col-md-4">
                <h6>
                    <a href="{{ url('inventory') }}?grade[]={{ $inv->grade_id }}&status={{ $inv->status_id }}"  wire:navigate>
                        {{ $inv->grade }}: {{ $inv->quantity }} {{ $purchaseStatus[$inv->status_id] ?? '' }}
                    </a>
                </h6>
            </div>
        @endforeach
    </div>

    @if (session('user')->hasPermission('dashboard_view_pending_orders'))
        <h6 class="tx-right mb-3">
            Pending Orders:&nbsp;&nbsp;&nbsp;
            @foreach ($pendingOrdersCount as $pending)
                <span title="Value: €{{ $pending->price }}">
                    {{ $pending->order_type->name }}: {{ $pending->count }}&nbsp;&nbsp;&nbsp;
                </span>
            @endforeach
        </h6>
    @endif
</div>
