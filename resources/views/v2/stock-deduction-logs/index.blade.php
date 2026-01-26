@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Stock Deduction Logs' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">Dashboard</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/stock-deduction-logs') }}">Extras</a></li>
                <li class="breadcrumb-item active" aria-current="page">Stock Deduction Logs</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-1">Total Deductions</h6>
                            <h3 class="mb-0">{{ number_format($total_deductions) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-1">Today</h6>
                            <h3 class="mb-0 text-primary">{{ number_format($today_deductions) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-1">This Week</h6>
                            <h3 class="mb-0 text-info">{{ number_format($this_week_deductions) }}</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Stock Deduction Logs</h5>
                            <small class="text-muted">Manage stock deduction log records</small>
                        </div>
                        <div>
                            <form action="{{ route('v2.stock-deduction-logs.truncate') }}" method="POST" class="d-inline" onsubmit="return confirm('⚠️ WARNING: This will permanently delete ALL stock deduction log records. This action cannot be undone. Are you sure?');">
                                @csrf
                                <button type="submit" class="btn btn-danger">
                                    <i class="fe fe-trash-2"></i> Truncate All Records
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('v2.stock-deduction-logs.index') }}" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Variation SKU</label>
                            <input type="text" name="variation_sku" class="form-control" value="{{ request('variation_sku') }}" placeholder="Search SKU">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Order Reference</label>
                            <input type="text" name="order_reference_id" class="form-control" value="{{ request('order_reference_id') }}" placeholder="Order Reference">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Deduction Reason</label>
                            <select name="deduction_reason" class="form-control form-select">
                                <option value="">All Reasons</option>
                                @foreach($deduction_reasons as $key => $label)
                                    <option value="{{ $key }}" {{ request('deduction_reason') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Marketplace</label>
                            <select name="marketplace_id" class="form-control form-select">
                                <option value="">All</option>
                                @foreach($marketplaces as $mp)
                                    <option value="{{ $mp->id }}" {{ request('marketplace_id') == $mp->id ? 'selected' : '' }}>{{ $mp->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date From</label>
                            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date To</label>
                            <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="{{ route('v2.stock-deduction-logs.index') }}" class="btn btn-secondary">Reset</a>
                            <a href="{{ route('v2.stock-deduction-logs.create') }}" class="btn btn-success float-end">Create New</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Logs Table -->
            <div class="card">
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Deduction At</th>
                                    <th>Variation SKU</th>
                                    <th>Order Reference</th>
                                    <th>Reason</th>
                                    <th>Variation Stock</th>
                                    <th>Marketplace Stock</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                <tr>
                                    <td>{{ $log->id }}</td>
                                    <td>{{ $log->deduction_at->format('Y-m-d H:i:s') }}</td>
                                    <td>
                                        <a href="{{ url('variation') }}?sku={{ $log->variation_sku }}" target="_blank">
                                            {{ $log->variation_sku }}
                                        </a>
                                    </td>
                                    <td>
                                        @if($log->order_reference_id)
                                            <a href="{{ url('order') }}?order_id={{ $log->order_reference_id }}" target="_blank">
                                                {{ $log->order_reference_id }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $log->deduction_reason == 'new_order_status_1' ? 'primary' : 'info' }}">
                                            {{ $log->deduction_reason_label }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-muted">{{ $log->before_variation_stock }}</span>
                                        <i class="fe fe-arrow-right mx-1"></i>
                                        <strong class="{{ $log->after_variation_stock < 0 ? 'text-danger' : '' }}">{{ $log->after_variation_stock }}</strong>
                                    </td>
                                    <td>
                                        <span class="text-muted">{{ $log->before_marketplace_stock }}</span>
                                        <i class="fe fe-arrow-right mx-1"></i>
                                        <strong class="{{ $log->after_marketplace_stock < 0 ? 'text-danger' : '' }}">{{ $log->after_marketplace_stock }}</strong>
                                    </td>
                                    <td>
                                        @if($log->order_status)
                                            <span class="badge bg-secondary">{{ $log->order_status_name }}</span>
                                        @endif
                                        @if($log->is_new_order)
                                            <span class="badge bg-success">New Order</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('v2.stock-deduction-logs.show', $log->id) }}" class="btn btn-sm btn-info" title="View">
                                            <i class="fe fe-eye"></i>
                                        </a>
                                        <a href="{{ route('v2.stock-deduction-logs.edit', $log->id) }}" class="btn btn-sm btn-warning" title="Edit">
                                            <i class="fe fe-edit"></i>
                                        </a>
                                        <form action="{{ route('v2.stock-deduction-logs.destroy', $log->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this log?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                <i class="fe fe-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center">No stock deduction logs found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="mt-3">
                        {{ $logs->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
