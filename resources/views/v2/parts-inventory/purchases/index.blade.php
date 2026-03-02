@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Purchases' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/parts-inventory/dashboard') }}">Parts Inventory</a></li>
                <li class="breadcrumb-item active" aria-current="page">Purchases</li>
            </ol>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            {{ session('info') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Filters</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('v2.parts-inventory.purchases') }}" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Reference / Batch</label>
                            <input type="text" name="reference_id" class="form-control" value="{{ request('reference_id') }}" placeholder="e.g. BR-20260302-0001">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Start date</label>
                            <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">End date</label>
                            <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All</option>
                                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Search</button>
                            <a href="{{ route('v2.parts-inventory.purchases') }}" class="btn btn-secondary">Reset</a>
                        </div>
                        <input type="hidden" name="per_page" value="{{ request('per_page', 25) }}">
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Vendor</th>
                                    <th>Reference (vendor)</th>
                                    <th>Created</th>
                                    <th>Status</th>
                                    <th>Processed by</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($orders as $order)
                                    @php
                                        $isPending = (int) ($order->status ?? 0) === \App\Models\PartsPurchaseOrder::STATUS_PENDING;
                                        $user = session('user');
                                        $canApprove = $user && ($user->role_id == 1 || session('user_id') == 1 || (method_exists($user, 'hasPermission') && $user->hasPermission('purchase_approve')));
                                    @endphp
                                    <tr>
                                        <td><code>{{ $order->reference_id ?? '–' }}</code></td>
                                        <td>{{ $order->customer ? ($order->customer->company ?? $order->customer->first_name ?? '–') : '–' }}</td>
                                        <td>{{ $order->reference ?? '–' }}</td>
                                        <td>{{ $order->created_at ? $order->created_at->format('Y-m-d H:i') : '–' }}</td>
                                        <td>
                                            @if ($isPending)
                                                <span class="badge bg-warning">Pending</span>
                                            @elseif ((int) ($order->status ?? 0) === \App\Models\PartsPurchaseOrder::STATUS_APPROVED)
                                                <span class="badge bg-success">Approved</span>
                                            @else
                                                <span class="badge bg-secondary">{{ $order->status ?? '–' }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $order->processedBy ? $order->processedBy->first_name : '–' }}</td>
                                        <td>
                                            <div class="dropdown">
                                                <button type="button" class="btn btn-link btn-sm p-0 border-0 text-dark" data-bs-toggle="dropdown" aria-expanded="false" title="Actions"><i class="fe fe-more-vertical"></i></button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="{{ route('v2.parts-inventory.purchases.detail', $order->id) }}"><i class="fe fe-file-text me-2"></i>View</a></li>
                                                    @if ($isPending && $canApprove)
                                                    <li>
                                                        <form method="POST" action="{{ route('v2.parts-inventory.purchases.approve', $order->id) }}" class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item text-success"><i class="fe fe-check me-2"></i>Approve</button>
                                                        </form>
                                                    </li>
                                                    @endif
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No parts purchase orders found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $orders->withQueryString()->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
