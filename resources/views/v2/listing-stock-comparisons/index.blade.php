@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Listing Stock Comparisons' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">Dashboard</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listing-stock-comparisons') }}">Extras</a></li>
                <li class="breadcrumb-item active" aria-current="page">Stock Comparisons</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-1">Total Compared</h6>
                            <h3 class="mb-0">{{ number_format($stats->total ?? 0) }}</h3>
                            @if($latest_comparison_at)
                                <small class="text-muted">Last: {{ $latest_comparison_at->format('Y-m-d H:i') }}</small>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-1">Perfect Matches</h6>
                            <h3 class="mb-0 text-success">{{ number_format($stats->perfect_matches ?? 0) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-1">Discrepancies</h6>
                            <h3 class="mb-0 text-warning">{{ number_format($stats->discrepancies ?? 0) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-1">Shortages</h6>
                            <h3 class="mb-0 text-danger">{{ number_format($stats->shortages ?? 0) }}</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('v2.listing-stock-comparisons.index') }}" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Variation SKU</label>
                            <input type="text" name="variation_sku" class="form-control" value="{{ request('variation_sku') }}" placeholder="Search SKU">
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
                            <label class="form-label">Country</label>
                            <select name="country_code" class="form-control form-select">
                                <option value="">All</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->code }}" {{ request('country_code') == $country->code ? 'selected' : '' }}>{{ $country->code }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control form-select">
                                <option value="">All</option>
                                <option value="perfect" {{ request('status') == 'perfect' ? 'selected' : '' }}>Perfect</option>
                                <option value="discrepancy" {{ request('status') == 'discrepancy' ? 'selected' : '' }}>Discrepancy</option>
                                <option value="shortage" {{ request('status') == 'shortage' ? 'selected' : '' }}>Shortage</option>
                                <option value="excess" {{ request('status') == 'excess' ? 'selected' : '' }}>Excess</option>
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
                            <a href="{{ route('v2.listing-stock-comparisons.index') }}" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Comparisons Table -->
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
                                    <th>Compared At</th>
                                    <th>SKU</th>
                                    <th>Country</th>
                                    <th>API Stock</th>
                                    <th>Our Stock</th>
                                    <th>Pending Orders</th>
                                    <th>Difference</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($comparisons as $comparison)
                                <tr>
                                    <td>{{ $comparison->id }}</td>
                                    <td>{{ $comparison->compared_at->format('Y-m-d H:i:s') }}</td>
                                    <td>
                                        <a href="{{ url('variation') }}?sku={{ $comparison->variation_sku }}" target="_blank">
                                            {{ $comparison->variation_sku }}
                                        </a>
                                    </td>
                                    <td>{{ $comparison->country_code ?? '-' }}</td>
                                    <td class="text-center"><strong>{{ $comparison->api_stock }}</strong></td>
                                    <td class="text-center"><strong>{{ $comparison->our_stock }}</strong></td>
                                    <td class="text-center">
                                        <small>{{ $comparison->pending_orders_count }} orders</small><br>
                                        <strong>{{ $comparison->pending_orders_quantity }} qty</strong>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-{{ $comparison->stock_difference == 0 ? 'success' : ($comparison->stock_difference > 0 ? 'warning' : 'danger') }}">
                                            {{ $comparison->stock_difference > 0 ? '+' : '' }}{{ $comparison->stock_difference }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $comparison->status_badge_class }}">
                                            {{ $comparison->status_label }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('v2.listing-stock-comparisons.show', $comparison->id) }}" class="btn btn-sm btn-info" title="View">
                                            <i class="fe fe-eye"></i>
                                        </a>
                                        <form action="{{ route('v2.listing-stock-comparisons.destroy', $comparison->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this comparison?');">
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
                                    <td colspan="10" class="text-center">No stock comparisons found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="mt-3">
                        {{ $comparisons->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
