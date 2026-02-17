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
            <div class="card mb-4">
                <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0">Parts purchases (by IMEI)</h6>
                    <a href="{{ route('v2.parts-inventory.purchases.add') }}" class="btn btn-primary">Add purchase</a>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="mb-3">Search / filter</h6>
                    <form method="GET" action="{{ route('v2.parts-inventory.purchase-history') }}" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">IMEI / Serial</label>
                            <input type="text" name="imei" class="form-control" value="{{ request('imei') }}" placeholder="Filter by IMEI">
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
                                    <th>IMEI / Serial</th>
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
                                            @if ($p->stock)
                                                <a href="{{ route('v2.parts-inventory.purchase-history', ['imei' => ($p->stock->imei ?? '') . ($p->stock->serial_number ?? '')]) }}">{{ $p->stock->imei ?? '' }}{{ $p->stock->serial_number ?? '' }}</a>
                                            @else
                                                –
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
                                            @if ($p->stock)
                                                <a href="{{ route('v2.parts-inventory.purchases.add', ['imei' => ($p->stock->imei ?? '') . ($p->stock->serial_number ?? '')]) }}" class="btn btn-sm btn-outline-primary">Add for this IMEI</a>
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
