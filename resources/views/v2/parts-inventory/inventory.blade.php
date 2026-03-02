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
                <li class="breadcrumb-item active" aria-current="page">Batches</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('v2.parts-inventory.inventory') }}" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Part name, SKU, batch number">
                        </div>
                        <div class="col-md-2">
                            <div class="form-check mt-4">
                                <input type="checkbox" name="low_stock" value="1" class="form-check-input" id="low_stock" {{ request('low_stock') === '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="low_stock">Low stock only</label>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Filter</button>
                            <a href="{{ route('v2.parts-inventory.inventory') }}" class="btn btn-secondary">Reset</a>
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
                                    <th>SKU</th>
                                    <th>Received at</th>
                                    <th>Qty</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($batches as $b)
                                    @php
                                        $part = $b->repairPart;
                                        $isLow = $part && $b->quantity_remaining <= $part->reorder_level;
                                    @endphp
                                    <tr class="{{ $isLow ? 'table-warning' : '' }}">
                                        <td><code>{{ $b->batch_number ?? '–' }}</code></td>
                                        <td>{{ $part ? ($part->sku ?? '–') : '–' }}</td>
                                        <td>{{ $b->received_at ? $b->received_at->format('Y-m-d H:i') : '–' }}</td>
                                        <td>{{ $b->quantity_remaining ?? 0 }}</td>
                                        <td>
                                            <div class="dropdown">
                                                <button type="button" class="btn btn-link btn-sm p-0 border-0 text-dark" data-bs-toggle="dropdown" aria-expanded="false" title="Actions"><i class="fe fe-more-vertical"></i></button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="{{ route('v2.parts-inventory.batch.edit', $b->id) }}"><i class="fe fe-edit-2 me-2"></i>Edit</a></li>
                                                    @if ($b->parts_purchase_order_id)
                                                    <li><a class="dropdown-item" href="{{ route('v2.parts-inventory.purchases.detail', $b->parts_purchase_order_id) }}"><i class="fe fe-file-text me-2"></i>View purchase order</a></li>
                                                    @elseif ($b->order_id)
                                                    <li><a class="dropdown-item" href="{{ url('purchase/detail/' . $b->order_id) }}"><i class="fe fe-file-text me-2"></i>View purchase order (legacy)</a></li>
                                                    @else
                                                    <li>
                                                        <form method="POST" action="{{ route('v2.parts-inventory.batch.create-purchase-order', $b->id) }}" class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item text-primary"><i class="fe fe-plus me-2"></i>Create purchase order</button>
                                                        </form>
                                                    </li>
                                                    @endif
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">No batches in stock.</td>
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
