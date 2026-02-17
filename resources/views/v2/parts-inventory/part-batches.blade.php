@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Batches' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/parts-inventory/dashboard') }}">Parts Inventory</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ route('v2.parts-inventory.inventory') }}">Inventory</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $part->name }}</li>
            </ol>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <p class="mb-0"><strong>Part:</strong> {{ $part->name }} @if($part->sku)<span class="text-muted">({{ $part->sku }})</span>@endif · {{ $part->product->model ?? '–' }}</p>
                        <p class="mb-0 mt-1"><a href="{{ route('v2.parts-inventory.inventory') }}" class="btn btn-sm btn-outline-secondary">← Back to Inventory</a></p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <a href="{{ route('v2.parts-inventory.part-broken.history', $part->id) }}" class="btn btn-outline-secondary" title="View broken parts history"><i class="fe fe-eye"></i></a>
                        <a href="{{ route('v2.parts-inventory.part-broken.add', $part->id) }}" class="btn btn-primary">Add broken parts</a>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="mb-3">Filters</h6>
                    <form method="GET" action="{{ route('v2.parts-inventory.part-batches-page', $part->id) }}" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Batch number</label>
                            <input type="text" name="batch_number" class="form-control" value="{{ request('batch_number') }}" placeholder="Search batch">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Received from</label>
                            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Received to</label>
                            <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                        </div>
                        <div class="col-md-2">
                            <div class="form-check mt-4">
                                <input type="checkbox" name="in_stock" value="1" class="form-check-input" id="in_stock" {{ request('in_stock') === '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="in_stock">In stock only</label>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Filter</button>
                            <a href="{{ route('v2.parts-inventory.part-batches-page', $part->id) }}" class="btn btn-secondary">Reset</a>
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
                                    <th>Batch number</th>
                                    <th>Qty received</th>
                                    <th>Qty remaining</th>
                                    <th>Unit cost</th>
                                    <th>Total cost</th>
                                    <th>Received at</th>
                                    <th>Supplier</th>
                                    <th>Notes</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($batches as $b)
                                    <tr class="{{ $b->quantity_remaining <= 0 ? 'table-secondary' : '' }}">
                                        <td><code>{{ $b->batch_number }}</code></td>
                                        <td>{{ $b->quantity_received }}</td>
                                        <td>{{ $b->quantity_remaining }}</td>
                                        <td>{{ number_format($b->unit_cost, 2) }}</td>
                                        <td>{{ $b->total_cost !== null ? number_format($b->total_cost, 2) : '–' }}</td>
                                        <td>{{ $b->received_at ? $b->received_at->format('Y-m-d') : '–' }}</td>
                                        <td>{{ $b->supplier ?? '–' }}</td>
                                        <td>{{ $b->notes ? Str::limit($b->notes, 50) : '–' }}</td>
                                        <td class="text-end">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">⋯</button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="{{ route('v2.parts-inventory.part-broken.add', $part->id) }}?batch_id={{ $b->id }}">Add broken parts</a></li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">No batches found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $batches->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
