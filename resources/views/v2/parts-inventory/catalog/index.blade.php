@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Part Catalog' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/parts-inventory/dashboard') }}">Parts Inventory</a></li>
                <li class="breadcrumb-item active" aria-current="page">Part Catalog</li>
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
                <div class="card-body">
                    <form method="GET" action="{{ route('v2.parts-inventory.catalog') }}" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Name, SKU, product, compatible device">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="active" class="form-control form-select">
                                <option value="">All</option>
                                <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Filter</button>
                            <a href="{{ route('v2.parts-inventory.catalog') }}" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">Parts</h5>
                    <div class="d-flex gap-2">
                        <a href="{{ route('v2.parts-inventory.batch-receive') }}" class="btn btn-outline-primary">Batch Receive</a>
                        <a href="{{ route('v2.parts-inventory.bulk-import') }}" class="btn btn-outline-secondary">Bulk Import Batches</a>
                        <a href="{{ route('v2.parts-inventory.catalog.create') }}" class="btn btn-primary">Add Part</a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>SKU</th>
                                    <th>Product</th>
                                    <th>Compatible device</th>
                                    <th>On hand</th>
                                    <th>Reorder level</th>
                                    <th>Unit cost</th>
                                    <th>Batches</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($parts as $part)
                                    <tr>
                                        <td>{{ $part->name }}</td>
                                        <td><code>{{ $part->sku ?? '–' }}</code></td>
                                        <td>{{ $part->product->model ?? '–' }}</td>
                                        <td>{{ $part->compatible_device ?? '–' }}</td>
                                        <td>{{ $part->on_hand }}</td>
                                        <td>{{ $part->reorder_level }}</td>
                                        <td>{{ number_format($part->unit_cost, 2) }}</td>
                                        <td>{{ $part->batches_count ?? 0 }}</td>
                                        <td>
                                            @if ($part->active)
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-secondary">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Actions</button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="{{ route('v2.parts-inventory.catalog.edit', $part->id) }}">Edit</a></li>
                                                    <li><a class="dropdown-item" href="{{ route('v2.parts-inventory.catalog.attach-imei', $part->id) }}">Attach IMEI</a></li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center">No parts found. <a href="{{ route('v2.parts-inventory.catalog.create') }}">Add one</a>.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $parts->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
