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

    <div class="card mt-3">
        <div class="card-header pb-0">
            <h6 class="mb-0">Manual add (no sheet)</h6>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ url('purchase/recovery').'/'.$order_id.'/manual-add' }}" class="row g-2">
                @csrf
                <div class="col-md-3">
                    <label class="form-label">Stock ID</label>
                    <input type="number" class="form-control" name="stock_id" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Price</label>
                    <input type="number" step="0.01" class="form-control" name="price" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Order Item ID (optional)</label>
                    <input type="number" class="form-control" name="id">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Add Item</button>
                </div>
                <div class="col-12">
                    <small class="text-muted">If ID is empty, the system will look at the stock's linked chain and use the single missing linked_id (if found). No new IDs are generated.</small>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header pb-0">
            <h6 class="mb-0">Paste Stock ID + Cost (space separated)</h6>
        </div>
        <div class="card-body">
            @if(session('paste_result') && $import_result)
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <span class="alert-inner--text"><strong>Paste result:</strong> Inserted {{ $import_result['inserted'] }}, updated {{ $import_result['updated'] ?? 0 }}, skipped {{ $import_result['skipped'] }}, errors {{ $import_result['errors'] }}, unmapped {{ $import_result['unmapped'] ?? 0 }}.</span>
                    <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
                </div>
                @php session()->forget('paste_result'); @endphp
            @endif
            @if(session('paste_errors'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <strong>Paste errors (showing up to 100):</strong>
                    <ul class="mb-0">
                        @foreach(session('paste_errors') as $err)
                            @php
                                $raw = $err['raw'] ?? '';
                                if (is_array($raw)) {
                                    $raw = json_encode($raw);
                                }
                            @endphp
                            <li>Line {{ $err['line'] ?? '-' }}: {{ $err['reason'] ?? 'Invalid row' }} — {{ $raw }}</li>
                        @endforeach
                    </ul>
                    <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
                </div>
                @php session()->forget('paste_errors'); @endphp
            @endif
            @if (session('parsed_rows'))
                <div class="alert alert-secondary alert-dismissible fade show" role="alert">
                    <strong>Parsed Rows (showing up to 100):</strong>
                    <ul class="mb-0">
                        @foreach(session('parsed_rows') as $index => $row)
                            @php
                                $raw = $row['raw'] ?? '';
                                if (is_array($raw)) {
                                    $raw = json_encode($raw);
                                }
                            @endphp
                            <li>Line {{ $index + 1 }}: Stock ID {{ $row['stock_id'] ?? 'N/A' }}, Cost {{ $row['cost'] ?? 'N/A' }}, ID {{ $row['id'] ?? 'N/A' }} @if(isset($row['error'])) — <strong>Error: {{ $row['error'] }}</strong> @endif — Raw: {{ $raw }}</li>
                        @endforeach
                    </ul>
                    <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
                </div>
                @php session()->forget('parsed_rows'); @endphp

            @endif
            <form method="POST" action="{{ url('purchase/recovery').'/'.$order_id.'/paste' }}">
                @csrf
                <div class="mb-2">
                    <textarea class="form-control" name="paste_rows" rows="6" placeholder="STOCK_ID COST [ID]\nSTOCK_ID COST [ID]\n..."></textarea>
                </div>
                <small class="text-muted d-block mb-2">Format: STOCK_ID COST [ID] per line. ID is optional. If missing, we use the linked-chain rule.</small>
                <button type="submit" class="btn btn-primary">Process Paste</button>
            </form>
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
                            <th>Manual Add</th>
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
                                            $imei = $c['imei'] ?: ($c['serial'] ?? '');
                                            $status = $c['has_purchase'] ? 'has purchase' : 'missing';
                                            return $c['stock_id'].' ('.$imei.') → '.($c['id'] ?? 'none').' · '.$status;
                                        })->implode(', ');
                                    @endphp
                                    {{ $pairs }}
                                </td>
                                <td style="min-width:220px;">
                                    @foreach($group['candidates'] as $candidate)
                                        @if(!($candidate['has_purchase'] ?? false))
                                            <form method="POST" action="{{ url('purchase/recovery').'/'.$order_id.'/manual-add' }}" class="d-flex gap-1 mb-1">
                                                @csrf
                                                <input type="hidden" name="stock_id" value="{{ $candidate['stock_id'] }}">
                                                <input type="number" step="0.01" class="form-control form-control-sm" name="price" placeholder="Price" required>
                                                <input type="number" class="form-control form-control-sm" name="id" value="{{ $candidate['id'] }}" placeholder="ID">
                                                <button type="submit" class="btn btn-sm btn-primary">Add</button>
                                            </form>
                                        @else
                                            <span class="badge bg-success">Already added</span>
                                        @endif
                                    @endforeach
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
