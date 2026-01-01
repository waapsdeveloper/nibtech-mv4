@extends('layouts.app')

@section('styles')
<style>
    .status-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
    .log-card {
        transition: all 0.3s ease;
    }
    .log-card:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $data['title_page'] ?? 'Stock Sync Logs' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">Dashboard</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/logs/stock-sync') }}">Logs</a></li>
                <li class="breadcrumb-item active" aria-current="page">Stock Sync</li>
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
                            <h6 class="card-title text-muted mb-1">Total Logs</h6>
                            <h3 class="mb-0">{{ $stats['total'] }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-1">Running</h6>
                            <h3 class="mb-0 text-warning">{{ $stats['running'] }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-1">Completed</h6>
                            <h3 class="mb-0 text-success">{{ $stats['completed'] }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-1">Failed</h6>
                            <h3 class="mb-0 text-danger">{{ $stats['failed'] }}</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ url('v2/logs/stock-sync') }}" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Marketplace</label>
                            <select name="marketplace_id" class="form-control form-select">
                                <option value="">All Marketplaces</option>
                                @foreach($marketplaces as $mp)
                                    <option value="{{ $mp->id }}" {{ request('marketplace_id') == $mp->id ? 'selected' : '' }}>
                                        {{ $mp->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control form-select">
                                <option value="">All Statuses</option>
                                <option value="running" {{ request('status') == 'running' ? 'selected' : '' }}>Running</option>
                                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                                <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
                                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Per Page</label>
                            <select name="per_page" class="form-control form-select">
                                <option value="20" {{ request('per_page') == 20 ? 'selected' : '' }}>20</option>
                                <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Filter</button>
                            <a href="{{ url('v2/logs/stock-sync') }}" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Logs Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Stock Sync Logs</h5>
                </div>
                <div class="card-body">
                    @if($logs->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Marketplace</th>
                                        <th>Status</th>
                                        <th>Total Records</th>
                                        <th>Synced</th>
                                        <th>Skipped</th>
                                        <th>Errors</th>
                                        <th>Started At</th>
                                        <th>Duration</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($logs as $log)
                                    <tr>
                                        <td>{{ $log->id }}</td>
                                        <td>{{ $log->marketplace->name ?? 'N/A' }} (ID: {{ $log->marketplace_id }})</td>
                                        <td>
                                            @if($log->status == 'running')
                                                <span class="badge bg-warning status-badge">Running</span>
                                            @elseif($log->status == 'completed')
                                                <span class="badge bg-success status-badge">Completed</span>
                                            @elseif($log->status == 'failed')
                                                <span class="badge bg-danger status-badge">Failed</span>
                                            @else
                                                <span class="badge bg-secondary status-badge">{{ ucfirst($log->status) }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $log->total_records }}</td>
                                        <td class="text-success">{{ $log->synced_count }}</td>
                                        <td class="text-warning">{{ $log->skipped_count }}</td>
                                        <td class="text-danger">{{ $log->error_count }}</td>
                                        <td>{{ $log->started_at->format('Y-m-d H:i:s') }}</td>
                                        <td>
                                            @if($log->duration_seconds)
                                                {{ gmdate('H:i:s', $log->duration_seconds) }}
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ url('v2/logs/stock-sync/' . $log->id) }}" class="btn btn-sm btn-primary">
                                                <i class="fe fe-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="mt-3">
                            {{ $logs->appends(request()->query())->links() }}
                        </div>
                    @else
                        <div class="alert alert-info">
                            <i class="fe fe-info me-2"></i>No stock sync logs found.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

