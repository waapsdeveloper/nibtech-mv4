<div class="p-4">
    {{-- Summary Cards --}}
    @if(isset($summary) && ($summary['total_locked'] > 0 || $summary['total_consumed'] > 0 || $summary['total_cancelled'] > 0 || $summary['active_locks_count'] > 0))
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card bg-warning text-white">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start w-100">
                        <div>
                            <h2 class="mb-1 fw-bold">{{ $summary['active_locks_count'] ?? 0 }}</h2>
                            <div class="small" style="opacity: 0.9;">Active Locks</div>
                        </div>
                        <div class="ms-3">
                            <i class="fe fe-lock" style="font-size: 2.5rem; opacity: 0.7;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start w-100">
                        <div>
                            <h2 class="mb-1 fw-bold">{{ $summary['total_locked'] ?? 0 }}</h2>
                            <div class="small" style="opacity: 0.9;">Total Locked</div>
                        </div>
                        <div class="ms-3">
                            <i class="fe fe-package" style="font-size: 2.5rem; opacity: 0.7;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start w-100">
                        <div>
                            <h2 class="mb-1 fw-bold">{{ $summary['total_consumed'] ?? 0 }}</h2>
                            <div class="small" style="opacity: 0.9;">Consumed</div>
                        </div>
                        <div class="ms-3">
                            <i class="fe fe-check-circle" style="font-size: 2.5rem; opacity: 0.7;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-danger text-white">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start w-100">
                        <div>
                            <h2 class="mb-1 fw-bold">{{ $summary['total_cancelled'] ?? 0 }}</h2>
                            <div class="small" style="opacity: 0.9;">Cancelled</div>
                        </div>
                        <div class="ms-3">
                            <i class="fe fe-x-circle" style="font-size: 2.5rem; opacity: 0.7;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Locks Table --}}
    @if($locks->count() > 0)
    <div class="table-responsive px-4 pb-4">
        <table class="table table-bordered table-hover table-sm">
            <thead class="table-light">
                <tr>
                    <th class="text-start ps-0">Order Reference</th>
                    <th class="text-start ps-0">SKU</th>
                    <th class="text-start ps-0">Marketplace</th>
                    <th class="text-start ps-0">Quantity</th>
                    <th class="text-start ps-0">Status</th>
                    <th class="text-start ps-0">Locked At</th>
                    <th class="text-start ps-0">Duration (min)</th>
                </tr>
            </thead>
                <tbody>
                    @foreach($locks as $lock)
                    <tr>
                        <td>
                            @if($lock->order)
                                <a href="{{ url('order') }}?order_id={{ $lock->order->reference_id }}" target="_blank">
                                    {{ $lock->order->reference_id }}
                                </a>
                            @else
                                N/A
                            @endif
                        </td>
                        <td>{{ $lock->orderItem->variation->sku ?? ($lock->marketplaceStock->variation->sku ?? 'N/A') }}</td>
                        <td>{{ $lock->marketplaceStock->marketplace->name ?? 'N/A' }}</td>
                        <td>{{ $lock->quantity_locked }}</td>
                        <td>
                            @php
                                $statusClass = $lock->lock_status === 'locked' ? 'warning' : 
                                               ($lock->lock_status === 'consumed' ? 'success' : 'danger');
                            @endphp
                            <span class="badge bg-{{ $statusClass }}">
                                {{ strtoupper($lock->lock_status) }}
                            </span>
                        </td>
                        <td>{{ $lock->locked_at ? $lock->locked_at->format('Y-m-d H:i:s') : 'N/A' }}</td>
                        <td>
                            @if($lock->locked_at)
                                {{ now()->diffInMinutes($lock->locked_at) }}
                            @else
                                N/A
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
    <div class="alert alert-info mx-4 mb-4">
        No stock locks found.
    </div>
    @endif
</div>

