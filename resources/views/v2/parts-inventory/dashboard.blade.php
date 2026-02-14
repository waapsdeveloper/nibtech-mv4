@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Parts Inventory – Dashboard' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/parts-inventory/dashboard') }}">Parts Inventory</a></li>
                <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
            </ol>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-1">Part Types</h6>
                    <h3 class="mb-0">{{ number_format($partsCount ?? 0) }}</h3>
                    <a href="{{ route('v2.parts-inventory.catalog') }}" class="btn btn-sm btn-outline-primary mt-2">Part Catalog</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-1">Batches in stock</h6>
                    <h3 class="mb-0 text-primary">{{ number_format($batchesCount ?? 0) }}</h3>
                    <a href="{{ route('v2.parts-inventory.batch-receive') }}" class="btn btn-sm btn-outline-primary mt-2">Receive batch</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-1">Total units on hand</h6>
                    <h3 class="mb-0 text-info">{{ number_format($totalOnHand ?? 0) }}</h3>
                    <a href="{{ route('v2.parts-inventory.inventory') }}" class="btn btn-sm btn-outline-primary mt-2">Inventory</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-1">Low stock (≤ reorder)</h6>
                    <h3 class="mb-0 {{ ($lowStockCount ?? 0) > 0 ? 'text-warning' : '' }}">{{ number_format($lowStockCount ?? 0) }}</h3>
                    <a href="{{ route('v2.parts-inventory.inventory') }}?low_stock=1" class="btn btn-sm btn-outline-primary mt-2">View</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent part usage</h5>
                    <a href="{{ route('v2.parts-inventory.usage') }}" class="btn btn-sm btn-secondary">All usage</a>
                </div>
                <div class="card-body">
                    @if (isset($recentUsages) && $recentUsages->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Part</th>
                                        <th>Batch</th>
                                        <th>Qty</th>
                                        <th>Cost</th>
                                        <th>Stock / IMEI</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentUsages as $u)
                                        <tr>
                                            <td>{{ $u->part->name ?? '–' }}</td>
                                            <td>{{ $u->batch->batch_number ?? '–' }}</td>
                                            <td>{{ $u->qty }}</td>
                                            <td>{{ number_format($u->total_cost, 2) }}</td>
                                            <td>
                                                @if ($u->stock_id && $u->stock)
                                                    <a href="{{ url('imei') }}?imei={{ $u->stock->imei ?? '' }}{{ $u->stock->serial_number ?? '' }}" target="_blank">{{ $u->stock->imei ?? '' }}{{ $u->stock->serial_number ?? '' }}</a>
                                                @else
                                                    –
                                                @endif
                                            </td>
                                            <td>{{ $u->created_at->format('Y-m-d H:i') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">No part usage recorded yet.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
