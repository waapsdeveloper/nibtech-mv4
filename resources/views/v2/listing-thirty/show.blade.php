@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Listing-30 Details' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">Dashboard</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listing-thirty') }}">Extras</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ route('v2.listing-thirty.index') }}">Listing-30</a></li>
                <li class="breadcrumb-item active" aria-current="page">#{{ $item->id }}</li>
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Record #{{ $item->id }}</h5>
                    <div>
                        <a href="{{ route('v2.listing-thirty.edit', $item->id) }}" class="btn btn-warning btn-sm">Edit</a>
                        <form action="{{ route('v2.listing-thirty.destroy', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this record and all its order refs?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Variation:</strong> @if($item->variation)<a href="{{ url('v2/listings') }}?sku={{ urlencode($item->variation->sku) }}">{{ $item->variation->sku }}</a>@else — @endif</p>
                            <p><strong>SKU:</strong> {{ $item->sku ?? '—' }}</p>
                            <p><strong>Country:</strong> {{ $item->country_code ?? '—' }}</p>
                            <p><strong>BM Listing ID:</strong> {{ $item->bm_listing_id }}</p>
                            <p><strong>BM Listing UUID:</strong> {{ $item->bm_listing_uuid ?? '—' }}</p>
                            <p><strong>Source:</strong> <span class="badge bg-secondary">{{ $item->source }}</span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Quantity:</strong> {{ $item->quantity }}</p>
                            <p><strong>Publication state:</strong> {{ $item->publication_state ?? '—' }}</p>
                            <p><strong>State (grade):</strong> {{ $item->state ?? '—' }}</p>
                            <p><strong>Title:</strong> {{ Str::limit($item->title, 60) ?: '—' }}</p>
                            <p><strong>Price:</strong> {{ $item->price_amount !== null ? number_format($item->price_amount, 2) . ' ' . ($item->price_currency ?? '') : '—' }}</p>
                            <p><strong>Min / Max price:</strong> {{ $item->min_price !== null ? number_format($item->min_price, 2) : '—' }} / {{ $item->max_price !== null ? number_format($item->max_price, 2) : '—' }}</p>
                            <p><strong>Synced at:</strong> {{ $item->synced_at ? $item->synced_at->format('Y-m-d H:i:s') : '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Order refs (listing_thirty_order_refs)</h5>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#addRefForm">Add ref</button>
                </div>
                <div class="card-body">
                    <div class="collapse mb-4" id="addRefForm">
                        <form action="{{ route('v2.listing-thirty.store-ref', $item->id) }}" method="POST" class="row g-3">
                            @csrf
                            <div class="col-md-3">
                                <label class="form-label">Order <span class="text-danger">*</span></label>
                                <select name="order_id" class="form-control form-select" required>
                                    <option value="">Select order</option>
                                    @foreach($orders as $order)
                                        <option value="{{ $order->id }}">{{ $order->reference_id }} (ID {{ $order->id }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">BM Order ID</label>
                                <input type="text" name="bm_order_id" class="form-control" value="{{ old('bm_order_id') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Source command</label>
                                <select name="source_command" class="form-control form-select">
                                    <option value="refresh:new">refresh:new</option>
                                    <option value="refresh:orders">refresh:orders</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Variation ID</label>
                                <input type="number" name="variation_id" class="form-control" value="{{ old('variation_id', $item->variation_id) }}">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">Add ref</button>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Order</th>
                                    <th>BM Order ID</th>
                                    <th>Source command</th>
                                    <th>Variation</th>
                                    <th>Synced at</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($item->refs as $ref)
                                    <tr>
                                        <td>{{ $ref->id }}</td>
                                        <td>
                                            @if($ref->order)
                                                <a href="{{ url('order') }}?id={{ $ref->order->id }}" target="_blank">{{ $ref->order->reference_id ?? 'Order #' . $ref->order_id }}</a>
                                            @else
                                                Order #{{ $ref->order_id }}
                                            @endif
                                        </td>
                                        <td>{{ $ref->bm_order_id ?? '—' }}</td>
                                        <td><span class="badge bg-info">{{ $ref->source_command }}</span></td>
                                        <td>{{ $ref->variation_id ? ($ref->variation ? $ref->variation->sku : '#' . $ref->variation_id) : '—' }}</td>
                                        <td><small>{{ $ref->synced_at ? $ref->synced_at->format('Y-m-d H:i') : '—' }}</small></td>
                                        <td>
                                            <form action="{{ route('v2.listing-thirty.destroy-ref', [$item->id, $ref->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Remove this ref?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No order refs. Add one via refresh:new/refresh:orders or manually above.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <a href="{{ route('v2.listing-thirty.index') }}" class="btn btn-secondary">Back to list</a>
            </div>
        </div>
    </div>
</div>
@endsection
