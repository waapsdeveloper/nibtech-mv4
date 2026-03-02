@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Purchase Order' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/parts-inventory/dashboard') }}">Parts Inventory</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ route('v2.parts-inventory.purchases') }}">Purchases</a></li>
                <li class="breadcrumb-item active" aria-current="page">PO {{ $po->reference_id }}</li>
            </ol>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            {{ session('info') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Parts Purchase Order</h5>
                    <div class="d-flex align-items-center gap-2">
                        @if ((int) ($po->status ?? 0) === \App\Models\PartsPurchaseOrder::STATUS_PENDING)
                            <span class="badge bg-warning">Pending</span>
                            @php
                                $user = session('user');
                                $canApprove = $user && ($user->role_id == 1 || session('user_id') == 1 || (method_exists($user, 'hasPermission') && $user->hasPermission('purchase_approve')));
                            @endphp
                            @if ($canApprove)
                                <form method="POST" action="{{ route('v2.parts-inventory.purchases.approve', $po->id) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                </form>
                            @endif
                        @elseif ((int) ($po->status ?? 0) === \App\Models\PartsPurchaseOrder::STATUS_APPROVED)
                            <span class="badge bg-success">Approved</span>
                        @else
                            <span class="badge bg-secondary">{{ $po->status ?? '–' }}</span>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-2">Reference</dt>
                        <dd class="col-sm-10"><code>{{ $po->reference_id }}</code></dd>
                        <dt class="col-sm-2">Vendor</dt>
                        <dd class="col-sm-10">{{ $po->customer ? ($po->customer->company ?? $po->customer->first_name) : '–' }}</dd>
                        <dt class="col-sm-2">Reference (vendor)</dt>
                        <dd class="col-sm-10">{{ $po->reference ?? '–' }}</dd>
                        <dt class="col-sm-2">Created</dt>
                        <dd class="col-sm-10">{{ $po->created_at ? $po->created_at->format('Y-m-d H:i') : '–' }}</dd>
                        <dt class="col-sm-2">Processed by</dt>
                        <dd class="col-sm-10">{{ $po->processedBy ? $po->processedBy->first_name : '–' }}</dd>
                        @if ($po->notes)
                        <dt class="col-sm-2">Notes</dt>
                        <dd class="col-sm-10">{{ $po->notes }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Part batches</h5>
                </div>
                <div class="card-body">
                    @if ($po->partBatches->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Batch</th>
                                    <th>Part</th>
                                    <th>SKU</th>
                                    <th>Qty received</th>
                                    <th>Qty remaining</th>
                                    <th>Unit cost</th>
                                    <th>Received at</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($po->partBatches as $pb)
                                <tr>
                                    <td>{{ $pb->batch_number }}</td>
                                    <td>{{ $pb->repairPart->name ?? '–' }}</td>
                                    <td>{{ $pb->repairPart->sku ?? '–' }}</td>
                                    <td>{{ $pb->quantity_received }}</td>
                                    <td>{{ $pb->quantity_remaining }}</td>
                                    <td>{{ $pb->unit_cost }}</td>
                                    <td>{{ $pb->received_at ? $pb->received_at->format('Y-m-d') : '–' }}</td>
                                    <td>
                                        <a href="{{ route('v2.parts-inventory.batch.edit', $pb->id) }}" class="btn btn-sm btn-outline-primary">Edit batch</a>
                                        <a href="{{ url('v2/parts-inventory/catalog') }}" class="btn btn-sm btn-outline-secondary">Part catalog</a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-muted mb-0">No part batches linked to this purchase order.</p>
                    @endif
                    <a href="{{ route('v2.parts-inventory.purchases') }}" class="btn btn-outline-primary mt-3">Back to Purchases</a>
                    <a href="{{ url('v2/parts-inventory/dashboard') }}" class="btn btn-outline-secondary mt-3">Parts Inventory dashboard</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
