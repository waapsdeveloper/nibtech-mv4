<?php use Illuminate\Support\Str; ?>
@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Marketplace Sync Failures' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">Dashboard</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/marketplace-sync-failures') }}">Extras</a></li>
                <li class="breadcrumb-item active" aria-current="page">Marketplace Sync Failures</li>
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
                            <h6 class="card-title text-muted mb-1">Total Failures</h6>
                            <h3 class="mb-0">{{ number_format($total_failures) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-1">Today</h6>
                            <h3 class="mb-0 text-primary">{{ number_format($today_failures) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-1">This Week</h6>
                            <h3 class="mb-0 text-info">{{ number_format($this_week_failures) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-1">Posted on Marketplace</h6>
                            <h3 class="mb-0 text-warning">{{ number_format($posted_failures) }}</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Marketplace Sync Failures</h5>
                            <small class="text-muted">Track SKUs that fail to sync with marketplace APIs</small>
                        </div>
                        <div>
                            <form action="{{ route('v2.marketplace-sync-failures.truncate') }}" method="POST" class="d-inline" onsubmit="return confirm('⚠️ WARNING: This will permanently delete ALL marketplace sync failure records. This action cannot be undone. Are you sure?');">
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
                    <form method="GET" action="{{ route('v2.marketplace-sync-failures.index') }}" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">SKU</label>
                            <input type="text" name="sku" class="form-control" value="{{ request('sku') }}" placeholder="Search SKU">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Marketplace</label>
                            <select name="marketplace_id" class="form-control form-select">
                                <option value="">All Marketplaces</option>
                                @foreach($marketplaces as $mp)
                                    <option value="{{ $mp->id }}" {{ request('marketplace_id') == $mp->id ? 'selected' : '' }}>{{ $mp->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Posted Status</label>
                            <select name="is_posted" class="form-control form-select">
                                <option value="">All</option>
                                <option value="1" {{ request('is_posted') == '1' ? 'selected' : '' }}>Posted</option>
                                <option value="0" {{ request('is_posted') == '0' ? 'selected' : '' }}>Not Posted</option>
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
                            <a href="{{ route('v2.marketplace-sync-failures.index') }}" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Failures Table -->
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
                                    <th>SKU</th>
                                    <th>Variation</th>
                                    <th>Marketplace</th>
                                    <th>Error Reason</th>
                                    <th>Posted</th>
                                    <th>Failure Count</th>
                                    <th>First Failed</th>
                                    <th>Last Attempted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($failures as $failure)
                                <tr>
                                    <td>{{ $failure->id }}</td>
                                    <td>
                                        <code>{{ $failure->sku }}</code>
                                    </td>
                                    <td>
                                        @if($failure->variation)
                                            <a href="{{ url('variation') }}?id={{ $failure->variation_id }}" target="_blank">
                                                {{ $failure->variation->name ?? 'N/A' }}
                                            </a>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($failure->marketplace)
                                            <span class="badge bg-secondary">{{ $failure->marketplace->name }}</span>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-danger" title="{{ $failure->error_message }}">
                                            {{ Str::limit($failure->error_reason, 50) }}
                                        </span>
                                        @if($failure->error_message)
                                            <br><small class="text-muted">{{ Str::limit($failure->error_message, 80) }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if($failure->is_posted_on_marketplace)
                                            <span class="badge bg-warning">Yes</span>
                                        @else
                                            <span class="badge bg-secondary">No</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $failure->failure_count > 10 ? 'danger' : ($failure->failure_count > 5 ? 'warning' : 'info') }}">
                                            {{ $failure->failure_count }}
                                        </span>
                                    </td>
                                    <td>{{ $failure->first_failed_at ? $failure->first_failed_at->format('Y-m-d H:i') : '-' }}</td>
                                    <td>{{ $failure->last_attempted_at ? $failure->last_attempted_at->format('Y-m-d H:i') : '-' }}</td>
                                    <td>
                                        <form action="{{ route('v2.marketplace-sync-failures.destroy', $failure->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this failure record?');">
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
                                    <td colspan="10" class="text-center">No marketplace sync failures found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="mt-3">
                        {{ $failures->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
