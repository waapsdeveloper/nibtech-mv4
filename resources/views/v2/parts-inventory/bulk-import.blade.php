@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Bulk Import Batches' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/parts-inventory/dashboard') }}">Parts Inventory</a></li>
                <li class="breadcrumb-item active" aria-current="page">Bulk Import Batches</li>
            </ol>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {!! session('success') !!}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('bulk_import_errors') && count(session('bulk_import_errors')) > 0)
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <strong>Row errors:</strong>
            <ul class="mb-0 mt-1 small">
                @foreach (array_slice(session('bulk_import_errors'), 0, 20) as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            @if (count(session('bulk_import_errors')) > 20)
                <p class="mb-0 mt-1 small">... and {{ count(session('bulk_import_errors')) - 20 }} more.</p>
            @endif
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body">
                    <form method="POST" action="{{ route('v2.parts-inventory.bulk-import.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="file" class="form-label">CSV file</label>
                            <input type="file" name="file" id="file" class="form-control" accept=".csv,.txt" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Import batches</button>
                        <a href="{{ route('v2.parts-inventory.batch-receive') }}" class="btn btn-secondary">Single batch receive</a>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Bulk import guide</h5>

                    <p class="text-muted mb-3">Upload a CSV to create many batches in one go. Each row = one batch. Parts are identified by <strong>SKU</strong>: existing SKU → add batch to that part; new SKU → create the part then add the batch (no duplicate parts for the same SKU).</p>

                    <h6 class="mb-2">How it works</h6>
                    <ol class="small mb-3">
                        <li>First row of your CSV must be the header with the column names below.</li>
                        <li>For each data row, the importer looks up a part by <code>sku</code>. If a part with that SKU exists in Part Catalog, that part is used. If not, a new part is created: <code>name</code> is required; <code>imei</code> is optional (if provided and found in <a href="{{ url('/inventory') }}" target="_blank">Inventory</a>, product is linked; if invalid or blank, the part is still created and a note is added to the batch — use <strong>Attach IMEI</strong> from the catalog list later if needed).</li>
                        <li>Then a batch is created for that part with <code>batch_number</code>, <code>quantity_received</code>, <code>unit_cost</code>, and optional date/supplier/notes.</li>
                        <li>Same SKU in multiple rows = same part, multiple batches (no duplicate part).</li>
                    </ol>

                    <h6 class="mb-2">CSV format (exact header names)</h6>
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered table-sm small">
                            <thead>
                                <tr>
                                    <th>Column</th>
                                    <th>Required</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td><code>sku</code></td><td>Yes</td><td>Part SKU. Existing SKU = use that part; new SKU = create part (then name and imei required).</td></tr>
                                <tr><td><code>name</code></td><td>When new part</td><td>Part name. Required only when SKU is not found (new part).</td></tr>
                                <tr><td><code>imei</code></td><td>No (for new part: optional)</td><td>IMEI from your <a href="{{ url('/inventory') }}" target="_blank">Inventory</a>. When creating a new part, use to link product (device model); leave blank to attach IMEI later from Part Catalog → Actions → Attach IMEI.</td></tr>
                                <tr><td><code>batch_number</code></td><td>Yes</td><td>Batch reference (e.g. BATCH-001).</td></tr>
                                <tr><td><code>quantity_received</code></td><td>Yes</td><td>Quantity received in this batch.</td></tr>
                                <tr><td><code>unit_cost</code></td><td>Yes</td><td>Cost per unit for this batch.</td></tr>
                                <tr><td><code>received_at</code></td><td>No</td><td>Date received (Y-m-d or M/d/Y). Default: today.</td></tr>
                                <tr><td><code>purchase_date</code></td><td>No</td><td>Purchase date (Y-m-d). If blank, upload/received date is used.</td></tr>
                                <tr><td><code>supplier</code></td><td>No</td><td>Supplier name.</td></tr>
                                <tr><td><code>notes</code></td><td>No</td><td>Optional notes.</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="mb-2"><strong>Downloads</strong></p>
                    <ul class="list-unstyled mb-2">
                        <li><a href="{{ route('v2.parts-inventory.bulk-import.sample') }}" class="btn btn-outline-secondary btn-sm me-2 mb-1"><i class="fe fe-download"></i> Sample CSV</a> — Template with correct header and example rows (uses IMEI from inventory).</li>
                        <li><a href="{{ route('v2.parts-inventory.bulk-import.parts-reference') }}" class="btn btn-outline-primary btn-sm me-2 mb-1"><i class="fe fe-download"></i> Parts reference (SKUs + example IMEI)</a> — Lists current parts and an example IMEI per product. Use an existing SKU to add batches, or a new SKU + name + imei to create a part.</li>
                    </ul>

                    <p class="mb-0"><a href="{{ url('/inventory') }}" class="btn btn-outline-secondary btn-sm me-1" target="_blank">Open Inventory</a> — IMEIs are taken from here. <a href="{{ route('v2.parts-inventory.catalog') }}" class="btn btn-primary btn-sm" target="_blank">Part Catalog</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
