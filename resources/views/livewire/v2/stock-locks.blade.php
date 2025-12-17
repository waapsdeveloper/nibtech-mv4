<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fe fe-lock me-2"></i>Stock Locks
            @if(isset($summary))
                <span class="badge bg-primary ms-2">{{ $summary['active_locks_count'] }} Active</span>
            @endif
        </h5>
    </div>
    <div class="card-body">
        @if(isset($summary) && ($summary['total_locked'] > 0 || $summary['total_consumed'] > 0))
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="card bg-warning bg-opacity-10">
                    <div class="card-body p-2">
                        <small class="text-muted">Locked</small>
                        <h6 class="mb-0">{{ $summary['total_locked'] }}</h6>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success bg-opacity-10">
                    <div class="card-body p-2">
                        <small class="text-muted">Consumed</small>
                        <h6 class="mb-0">{{ $summary['total_consumed'] }}</h6>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger bg-opacity-10">
                    <div class="card-body p-2">
                        <small class="text-muted">Cancelled</small>
                        <h6 class="mb-0">{{ $summary['total_cancelled'] }}</h6>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info bg-opacity-10">
                    <div class="card-body p-2">
                        <small class="text-muted">Active Locks</small>
                        <h6 class="mb-0">{{ $summary['active_locks_count'] }}</h6>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($locks->count() > 0)
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Variation</th>
                        <th>Marketplace</th>
                        <th>Quantity</th>
                        <th>Status</th>
                        <th>Locked At</th>
                        <th>Duration</th>
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
                                <span class="text-muted">N/A</span>
                            @endif
                        </td>
                        <td>
                            @if($lock->marketplaceStock && $lock->marketplaceStock->variation)
                                <small>
                                    <strong>{{ $lock->marketplaceStock->variation->sku ?? 'N/A' }}</strong>
                                    @if($lock->marketplaceStock->variation->product)
                                        <br>{{ $lock->marketplaceStock->variation->product->model ?? '' }}
                                    @endif
                                </small>
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </td>
                        <td>
                            @if($lock->marketplaceStock && $lock->marketplaceStock->marketplace)
                                <span class="badge bg-secondary">
                                    {{ $lock->marketplaceStock->marketplace->name }}
                                </span>
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </td>
                        <td>
                            <strong>{{ $lock->quantity_locked }}</strong>
                        </td>
                        <td>
                            @if($lock->lock_status === 'locked')
                                <span class="badge bg-warning">Locked</span>
                            @elseif($lock->lock_status === 'consumed')
                                <span class="badge bg-success">Consumed</span>
                            @elseif($lock->lock_status === 'cancelled')
                                <span class="badge bg-danger">Cancelled</span>
                            @else
                                <span class="badge bg-secondary">{{ $lock->lock_status }}</span>
                            @endif
                        </td>
                        <td>
                            <small>{{ $lock->locked_at ? $lock->locked_at->format('Y-m-d H:i') : 'N/A' }}</small>
                        </td>
                        <td>
                            @if($lock->locked_at)
                                @php
                                    $endTime = $lock->consumed_at ?? $lock->released_at ?? now();
                                    $duration = $lock->locked_at->diffInMinutes($endTime);
                                @endphp
                                @if($duration < 60)
                                    <small>{{ $duration }}m</small>
                                @elseif($duration < 1440)
                                    <small>{{ round($duration / 60, 1) }}h</small>
                                @else
                                    <small>{{ round($duration / 1440, 1) }}d</small>
                                @endif
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="alert alert-info mb-0">
            <i class="fe fe-info me-2"></i>No stock locks found.
        </div>
        @endif
    </div>
</div>

