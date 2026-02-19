@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Purchase History' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/parts-inventory/dashboard') }}">Parts Inventory</a></li>
                <li class="breadcrumb-item active" aria-current="page">Purchase History</li>
            </ol>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
                <strong>Purchase History vs Inventory:</strong> This page records <em>what you bought</em> (for costing and batch tracking). It does not change stock. To <strong>add parts to your on-hand Inventory</strong>, use <a href="{{ route('v2.parts-inventory.batch-receive') }}" class="alert-link">Batch receive</a>. View current stock on <a href="{{ route('v2.parts-inventory.inventory') }}" class="alert-link">Inventory</a>.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

            <div class="card mb-4">
                <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0">Parts purchases (by batch)</h6>
                    <a href="{{ route('v2.parts-inventory.purchases.add') }}" class="btn btn-primary">Add purchase</a>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="mb-3">Search / filter</h6>
                    <form method="GET" action="{{ route('v2.parts-inventory.purchase-history') }}" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">System barcode</label>
                            <input type="text" name="system_barcode" class="form-control" value="{{ request('system_barcode') }}" placeholder="Batch system barcode">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Manufacturer barcode</label>
                            <input type="text" name="manufacturer_barcode" class="form-control" value="{{ request('manufacturer_barcode') }}" placeholder="Manufacturer barcode">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Part</label>
                            <select name="part_id" class="form-control form-select">
                                <option value="">All parts</option>
                                @foreach ($partsForFilter as $id => $name)
                                    <option value="{{ $id }}" {{ request('part_id') == (string)$id ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date from</label>
                            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date to</label>
                            <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Search</button>
                            <a href="{{ route('v2.parts-inventory.purchase-history') }}" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th title="Batch: system barcode and optional manufacturer barcode">Barcode</th>
                                    <th>Part</th>
                                    <th>Qty</th>
                                    <th>Unit price</th>
                                    <th>Total</th>
                                    <th>Lease</th>
                                    <th>Notes</th>
                                    <th>Recorded by</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($purchases as $p)
                                    <tr>
                                        <td>{{ $p->created_at->format('Y-m-d H:i') }}</td>
                                        <td>
                                            @if ($p->batch)
                                                <span class="fw-medium">{{ $p->batch->system_barcode }}</span>
                                                @if ($p->batch->manufacturer_barcode)
                                                    <br><small class="text-muted">{{ $p->batch->manufacturer_barcode }}</small>
                                                @endif
                                                <br>
                                                <a href="{{ route('v2.parts-inventory.purchases.add', ['batch_id' => $p->batch_id]) }}" class="btn btn-sm btn-outline-primary mt-1">Add to this batch</a>
                                            @else
                                                <span class="text-muted">– (legacy)</span>
                                                @if ($p->stock)
                                                    <br><small>IMEI: {{ $p->stock->imei ?? $p->stock->serial_number ?? '–' }}</small>
                                                @endif
                                            @endif
                                        </td>
                                        <td>{{ $p->repairPart->name ?? '–' }} @if($p->repairPart && $p->repairPart->sku)<small class="text-muted">({{ $p->repairPart->sku }})</small>@endif</td>
                                        <td>{{ $p->quantity }}</td>
                                        <td>
                                            @if ($p->unit_price !== null)
                                                {{ number_format($p->unit_price, 2) }}
                                            @else
                                                <span class="text-muted">–</span>
                                                @if ($p->is_lease)
                                                    <form action="{{ route('v2.parts-inventory.purchases.set-price', $p->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <input type="number" name="unit_price" step="0.01" min="0" class="form-control form-control-sm d-inline-block" style="width:80px" placeholder="Set">
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Set</button>
                                                    </form>
                                                @endif
                                            @endif
                                        </td>
                                        <td>{{ $p->unit_price !== null ? number_format($p->total_price, 2) : '–' }}</td>
                                        <td>{{ $p->is_lease ? 'Yes' : 'No' }}</td>
                                        <td>{{ $p->notes ?? '–' }}</td>
                                        <td>{{ $p->admin ? trim(($p->admin->first_name ?? '') . ' ' . ($p->admin->last_name ?? '')) : '–' }}</td>
                                        <td>
                                            @if ($p->batch || ($isAdmin ?? false))
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Actions</button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    @if ($p->batch)
                                                        <li><a class="dropdown-item" href="{{ route('v2.parts-inventory.purchases.add', ['batch_id' => $p->batch_id]) }}">Add to batch</a></li>
                                                    @endif
                                                    @if ($isAdmin ?? false)
                                                        @if ($p->batch)<li><hr class="dropdown-divider"></li>@endif
                                                        <li>
                                                            <form action="{{ route('v2.parts-inventory.purchases.destroy', $p->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this purchase record? This cannot be undone.');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="dropdown-item text-danger">Delete</button>
                                                            </form>
                                                        </li>
                                                    @endif
                                                </ul>
                                            </div>
                                            @else
                                                –
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center text-muted">No purchases found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $purchases->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
