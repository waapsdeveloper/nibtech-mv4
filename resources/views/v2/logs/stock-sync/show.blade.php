@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $data['title_page'] ?? 'Stock Sync Log Details' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">Dashboard</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/logs/stock-sync') }}">Logs</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/logs/stock-sync') }}">Stock Sync</a></li>
                <li class="breadcrumb-item active" aria-current="page">Details</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Stock Sync Log #{{ $log->id }}</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">Marketplace</th>
                                    <td>{{ $log->marketplace->name ?? 'N/A' }} (ID: {{ $log->marketplace_id }})</td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td>
                                        @if($log->status == 'running')
                                            <span class="badge bg-warning">Running</span>
                                        @elseif($log->status == 'completed')
                                            <span class="badge bg-success">Completed</span>
                                        @elseif($log->status == 'failed')
                                            <span class="badge bg-danger">Failed</span>
                                        @else
                                            <span class="badge bg-secondary">{{ ucfirst($log->status) }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Started At</th>
                                    <td>{{ $log->started_at->format('Y-m-d H:i:s') }}</td>
                                </tr>
                                <tr>
                                    <th>Completed At</th>
                                    <td>{{ $log->completed_at ? $log->completed_at->format('Y-m-d H:i:s') : '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Duration</th>
                                    <td>
                                        @if($log->duration_seconds)
                                            {{ gmdate('H:i:s', $log->duration_seconds) }} ({{ $log->duration_seconds }} seconds)
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Triggered By</th>
                                    <td>{{ $log->admin->name ?? 'System' }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">Total Records</th>
                                    <td>{{ $log->total_records }}</td>
                                </tr>
                                <tr>
                                    <th>Successfully Synced</th>
                                    <td class="text-success">{{ $log->synced_count }}</td>
                                </tr>
                                <tr>
                                    <th>Skipped</th>
                                    <td class="text-warning">{{ $log->skipped_count }}</td>
                                </tr>
                                <tr>
                                    <th>Errors</th>
                                    <td class="text-danger">{{ $log->error_count }}</td>
                                </tr>
                                @if($log->total_records > 0)
                                <tr>
                                    <th>Success Rate</th>
                                    <td>
                                        @php
                                            $successRate = ($log->synced_count / $log->total_records) * 100;
                                        @endphp
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $successRate }}%">
                                                {{ number_format($successRate, 1) }}%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @endif
                            </table>
                        </div>
                    </div>

                    @if($log->summary)
                    <div class="mb-4">
                        <h6>Summary</h6>
                        <div class="alert alert-info">
                            {{ $log->summary }}
                        </div>
                    </div>
                    @endif

                    @if($log->error_count > 0 && $log->error_details)
                    <div class="mb-4">
                        <h6>Error Details</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Variation ID</th>
                                        <th>Error</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(array_slice($log->error_details, 0, 100) as $error)
                                    <tr>
                                        <td>{{ $error['variation_id'] ?? 'N/A' }}</td>
                                        <td class="text-danger">{{ $error['error'] ?? 'Unknown error' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if(count($log->error_details) > 100)
                                <p class="text-muted small">Showing first 100 errors. Total: {{ count($log->error_details) }}</p>
                            @endif
                        </div>
                    </div>
                    @endif

                    <div class="mt-4">
                        <a href="{{ url('v2/logs/stock-sync') }}" class="btn btn-secondary">
                            <i class="fe fe-arrow-left"></i> Back to Logs
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

