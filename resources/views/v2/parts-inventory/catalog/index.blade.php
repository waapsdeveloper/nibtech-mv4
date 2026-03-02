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
                    <form method="GET" action="{{ route('v2.parts-inventory.catalog') }}" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Name or SKU">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary me-2">Search</button>
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
                                    <th>On hand</th>
                                    <th>Batches</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($parts as $part)
                                    <tr>
                                        <td>{{ $part->name }}</td>
                                        <td><code>{{ $part->sku ?? '–' }}</code></td>
                                        <td>{{ $part->on_hand }}</td>
                                        <td>
                                            <a href="{{ route('v2.parts-inventory.inventory') }}?search={{ urlencode($part->sku ?? $part->name) }}">{{ $part->batches_count ?? 0 }}</a>
                                        </td>
                                        <td class="text-end">
                                            <div class="dropdown">
                                                <button type="button" class="btn btn-link btn-sm p-0 border-0 text-dark" data-bs-toggle="dropdown" aria-expanded="false" title="Actions"><i class="fe fe-more-vertical"></i></button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="{{ route('v2.parts-inventory.inventory') }}?search={{ urlencode($part->sku ?? $part->name) }}" target="_blank" rel="noopener noreferrer"><i class="fe fe-list me-2"></i>Batch list</a></li>
                                                    @if (($part->batches_with_po_count ?? 0) === 0)
                                                    <li><a class="dropdown-item text-primary" href="{{ route('v2.parts-inventory.batch-receive', ['sku' => $part->sku ?? '', 'name' => $part->name ?? '', 'create_po' => 1]) }}"><i class="fe fe-file-plus me-2"></i>Add PO</a></li>
                                                    @endif
                                                    <li><a class="dropdown-item" href="{{ route('v2.parts-inventory.part-broken.history', $part->id) }}"><i class="fe fe-alert-triangle me-2"></i>Broken history</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form action="{{ route('v2.parts-inventory.catalog.destroy', $part->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this part and all its batches, usages, broken records, and related data? This cannot be undone.');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="dropdown-item text-danger"><i class="fe fe-trash-2 me-2"></i>Delete</button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">No parts found.</td>
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
