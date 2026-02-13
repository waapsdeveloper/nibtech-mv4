@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Listing-30' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listing-thirty') }}">Extras</a></li>
                <li class="breadcrumb-item active" aria-current="page">Listing-30</li>
            </ol>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Listing-30 (BM sync records)</h5>
                            <small class="text-muted">listing_thirty_orders – exactly what came from BackMarket via functions:thirty</small>
                        </div>
                        <a href="{{ route('v2.listing-thirty.create') }}" class="btn btn-primary">
                            <i class="fe fe-plus"></i> Create
                        </a>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('v2.listing-thirty.index') }}" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">SKU</label>
                            <input type="text" name="sku" class="form-control" value="{{ request('sku') }}" placeholder="SKU">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Country</label>
                            <input type="text" name="country_code" class="form-control" value="{{ request('country_code') }}" placeholder="e.g. FR">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Source</label>
                            <select name="source" class="form-control form-select">
                                <option value="">All</option>
                                @foreach($sources as $k => $v)
                                    <option value="{{ $k }}" {{ request('source') == $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">BM Listing ID</label>
                            <input type="text" name="bm_listing_id" class="form-control" value="{{ request('bm_listing_id') }}" placeholder="BM listing_id">
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
                            <a href="{{ route('v2.listing-thirty.index') }}" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <p class="text-muted small mb-3">Total records: <strong>{{ number_format($total_count) }}</strong></p>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Variation / SKU</th>
                                    <th>Country</th>
                                    <th>BM Listing ID</th>
                                    <th>Source</th>
                                    <th>Qty</th>
                                    <th>State</th>
                                    <th>Price</th>
                                    <th>Synced At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($items as $item)
                                    <tr>
                                        <td>{{ $item->id }}</td>
                                        <td>
                                            @if($item->variation)
                                                <a href="{{ url('v2/listings') }}?sku={{ urlencode($item->variation->sku ?? '') }}">{{ $item->variation->sku ?? '-' }}</a>
                                            @else
                                                {{ $item->sku ?? '-' }}
                                            @endif
                                        </td>
                                        <td>{{ $item->country_code ?? '-' }}</td>
                                        <td><small>{{ Str::limit($item->bm_listing_id, 20) }}</small></td>
                                        <td><span class="badge bg-secondary">{{ $item->source }}</span></td>
                                        <td>{{ $item->quantity }}</td>
                                        <td>{{ $item->publication_state ?? '-' }}</td>
                                        <td>{{ $item->price_amount !== null ? number_format($item->price_amount, 2) . ' ' . ($item->price_currency ?? '') : '-' }}</td>
                                        <td><small>{{ $item->synced_at ? $item->synced_at->format('Y-m-d H:i') : '-' }}</small></td>
                                        <td>
                                            <a href="{{ route('v2.listing-thirty.show', $item->id) }}" class="btn btn-sm btn-info" title="View">View</a>
                                            <a href="{{ route('v2.listing-thirty.edit', $item->id) }}" class="btn btn-sm btn-warning" title="Edit">Edit</a>
                                            <form action="{{ route('v2.listing-thirty.destroy', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this record and its refs?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" title="Delete">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center text-muted">No records yet. Run functions:thirty or create one manually.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-center mt-3">
                        {{ $items->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
