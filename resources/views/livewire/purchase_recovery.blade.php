@extends('layouts.app')

@section('content')
    <div class="breadcrumb-header justify-content-between" style="border-bottom: 1px solid rgb(216, 212, 212);">
        <div class="left-content">
            <h4 class="mb-1">Purchase Recovery</h4>
            <p class="mb-2">Recreate order items from the original Excel sheet while keeping the same IDs.</p>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary btn-sm" href="{{ url('purchase') }}">Back to Purchase List</a>
                <a class="btn btn-outline-primary btn-sm" href="{{ url('purchase/detail').'/'.$order_id }}">View Purchase Detail</a>
            </div>
        </div>
        <div class="tx-center">
            <h5 class="mb-1">Reference: {{ $order->reference_id }} | Vendor: {{ $order->customer->first_name ?? 'N/A' }}</h5>
            <h6 class="mb-0">Items (current): {{ $current_count }}</h6>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
            <span class="alert-inner--text"><strong>{{ session('success') }}</strong></span>
            <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
        </div>
        @php session()->forget('success'); @endphp
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
            <span class="alert-inner--text"><strong>{{ session('error') }}</strong></span>
            <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
        </div>
        @php session()->forget('error'); @endphp
    @endif

    <div class="row row-sm">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">Counts</h6>
                    <ul class="list-unstyled mb-0">
                        <li>Current order_items: <strong>{{ $current_count }}</strong></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">Import from Excel</h6>
                    <p class="mb-3">Uploads the original purchase sheet (same format as add purchase: name / imei / cost) and inserts rows using the sheet's <strong>ID</strong> values; if an ID is missing, it will try to derive it from linked sold items by <code>stock_id</code>.</p>
                    <form method="POST" action="{{ url('purchase/recovery').'/'.$order_id.'/import' }}" enctype="multipart/form-data" onsubmit="return confirm('Proceed with recovery import?');">
                        @csrf
                        <div class="mb-2">
                            <input class="form-control" type="file" name="recovery_file" accept=".xlsx,.xls,.csv" required>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" value="1" id="price_only" name="price_only">
                            <label class="form-check-label" for="price_only">
                                Update price only (no new rows; matches by id and keeps variation).
                            </label>
                        </div>
                        <button type="submit" class="btn btn-success">Run Import</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">Required columns</h6>
                    <ul class="mb-0">
                        <li><strong>name</strong> (model text)</li>
                        <li><strong>imei</strong> (IMEI/serial; multiple IMEI columns supported)</li>
                        <li><strong>cost</strong> (purchase cost)</li>
                        <li>Optional: id (overrides auto-derivation), linked_id (candidate id), stock_id (will be derived via imei if missing), reference_id, variation_id, quantity, currency, discount, status, admin_id, care_id, reference/notes, created_at, updated_at, deleted_at</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    @if($manual_groups && $manual_groups->count())
    <div class="card mt-3">
        <div class="card-header pb-0 d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Manual recovery (grouped by first variation from stock operations)</h6>
            <small class="text-muted">Use this grouping to manually add prices/ids without a sheet.</small>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover text-nowrap mb-0">
                    <thead>
                        <tr>
                            <th>Variation</th>
                            <th>Count</th>
                            <th>Stock IDs</th>
                            <th>Candidate Item IDs</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($manual_groups as $group)
                            <tr>
                                <td>{{ $group['label'] }} ({{ $group['variation_id'] }})</td>
                                <td>{{ $group['count'] }}</td>
                                <td style="max-width:520px; white-space:normal;">
                                    {{ implode(', ', $group['stock_ids']) }}
                                </td>
                                <td style="max-width:320px; white-space:normal;">
                                    @php
                                        $pairs = collect($group['candidates'])->map(function($c){
                                            return $c['stock_id'].' → '.($c['id'] ?? 'none');
                                        })->implode(', ');
                                    @endphp
                                    {{ $pairs }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    @if($import_result)
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <span class="alert-inner--text"><strong>Import result:</strong> Inserted {{ $import_result['inserted'] }}, updated {{ $import_result['updated'] ?? 0 }}, skipped {{ $import_result['skipped'] }}, errors {{ $import_result['errors'] }}, unmapped {{ $import_result['unmapped'] ?? 0 }}.</span>
            <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif
@endsection
