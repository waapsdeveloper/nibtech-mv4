@extends('layouts.app')

@section('styles')
    <style>
        .rows { border: 1px solid #016a5949; }
        .columns { background-color: #016a5949; padding-top: 5px; }
        .childs { padding-top: 5px; }
    </style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Parts Inventory – Purchases' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/parts-inventory/dashboard') }}">Parts Inventory</a></li>
                <li class="breadcrumb-item active" aria-current="page">Purchases</li>
            </ol>
        </div>
    </div>

    {{-- Search (same pattern as legacy purchase) --}}
    <div class="row">
        <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
            <center><h4>Search</h4></center>
        </div>
    </div>
    <br>
    <form action="{{ route('v2.parts-inventory.purchases') }}" method="GET" id="search">
        <div class="row">
            <div class="col-lg-3 col-xl-3 col-md-3 col-sm-6">
                <div class="card-header">
                    <h4 class="card-title mb-1">Order ID</h4>
                </div>
                <input type="text" class="form-control" name="order_id" placeholder="Enter ID" value="{{ request('order_id') }}">
            </div>
            <div class="col-lg-3 col-xl-3 col-md-3 col-sm-6">
                <div class="card-header">
                    <h4 class="card-title mb-1">Vendor</h4>
                </div>
                <select name="customer_id" class="form-control form-select">
                    <option value="">Vendor</option>
                    @foreach ($vendors ?? [] as $id => $customer)
                        <option value="{{ $id }}" @if ((string)$id === (string)request('customer_id')) selected @endif>{{ $customer }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3 col-xl-3 col-md-3 col-sm-6">
                <div class="card-header">
                    <h4 class="card-title mb-1">{{ __('locale.Start Date') }}</h4>
                </div>
                <input class="form-control" name="start_date" type="date" value="{{ request('start_date') }}">
            </div>
            <div class="col-lg-3 col-xl-3 col-md-3 col-sm-6">
                <div class="card-header">
                    <h4 class="card-title mb-1">{{ __('locale.End Date') }}</h4>
                </div>
                <input class="form-control" name="end_date" type="date" value="{{ request('end_date') }}">
            </div>
        </div>
        <div class="p-2">
            <button class="btn btn-primary pd-x-20" type="submit">{{ __('locale.Search') }}</button>
            <a href="{{ route('v2.parts-inventory.purchases') }}" class="btn btn-default pd-x-20">Reset</a>
        </div>
        <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
        <input type="hidden" name="status" value="{{ request('status') }}">
    </form>
    <br>

    <div class="row">
        <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
            <center><h4>Purchases</h4></center>
        </div>
    </div>
    <br>

    {{-- Tabs (same as legacy: Pending, Active, Closed, All) --}}
    @php
        $queryParams = request()->only(['order_id', 'customer_id', 'start_date', 'end_date', 'per_page']);
    @endphp
    <div class="d-flex justify-content-between">
        <div>
            <a href="{{ route('v2.parts-inventory.purchases', array_merge($queryParams, ['status' => 'pending'])) }}" class="btn btn-link @if (request('status') === 'pending') bg-white @endif">Pending</a>
            <a href="{{ route('v2.parts-inventory.purchases', array_merge($queryParams, ['status' => 'active'])) }}" class="btn btn-link @if (request('status') === 'active') bg-white @endif">Active</a>
            <a href="{{ route('v2.parts-inventory.purchases', array_merge($queryParams, ['status' => 'closed'])) }}" class="btn btn-link @if (request('status') === 'closed') bg-white @endif">Closed</a>
            <a href="{{ route('v2.parts-inventory.purchases', $queryParams) }}" class="btn btn-link @if (!request('status')) bg-white @endif">All</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <span class="alert-inner--icon"><i class="fe fe-thumbs-up"></i></span>
            <span class="alert-inner--text"><strong>{{ session('success') }}</strong></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @php session()->forget('success'); @endphp
    @endif
    @if (session('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            {{ session('info') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @php session()->forget('info'); @endphp
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <span class="alert-inner--icon"><i class="fe fe-thumbs-down"></i></span>
            <span class="alert-inner--text"><strong>{{ session('error') }}</strong></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @php session()->forget('error'); @endphp
    @endif

    <div class="row">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-header pb-0">
                    <div class="d-flex justify-content-between">
                        <h4 class="card-title mg-b-0"></h4>
                        <h5 class="card-title mg-b-0">{{ __('locale.From') }} {{ $orders->firstItem() ?? 0 }} {{ __('locale.To') }} {{ $orders->lastItem() ?? 0 }} {{ __('locale.Out Of') }} {{ $orders->total() }}</h5>
                        <div class="mg-b-0">
                            <form method="get" action="{{ route('v2.parts-inventory.purchases') }}" class="row form-inline">
                                <label for="perPage" class="card-title inline">per page:</label>
                                <select name="per_page" class="form-select form-select-sm" id="perPage" onchange="this.form.submit()">
                                    <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10</option>
                                    <option value="20" {{ request('per_page') == 20 ? 'selected' : '' }}>20</option>
                                    <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                                    <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                                </select>
                                <input type="hidden" name="order_id" value="{{ request('order_id') }}">
                                <input type="hidden" name="customer_id" value="{{ request('customer_id') }}">
                                <input type="hidden" name="start_date" value="{{ request('start_date') }}">
                                <input type="hidden" name="end_date" value="{{ request('end_date') }}">
                                <input type="hidden" name="status" value="{{ request('status') }}">
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                            <thead>
                                <tr>
                                    <th><small><b>No</b></small></th>
                                    <th><small><b>Order ID</b></small></th>
                                    <th><small><b>Vendor</b></small></th>
                                    <th><small><b>Reference</b></small></th>
                                    <th><small><b>Tracking</b></small></th>
                                    @if (session('user')->hasPermission('view_cost') ?? false)
                                        <th><small><b>Cost</b></small></th>
                                    @endif
                                    <th><small><b>Qty</b></small></th>
                                    <th><small><b>Issues</b></small></th>
                                    <th><small><b>Creation Date</b></small></th>
                                    @if (request('status') !== 'pending')
                                        <th><small><b>Approval Date</b></small></th>
                                    @endif
                                    <th><small><b>Actions</b></small></th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $i = $orders->firstItem() ? $orders->firstItem() - 1 : 0; @endphp
                                @forelse ($orders as $order)
                                    @php
                                        $firstBatch = $order->partBatches->first();
                                        $po = $firstBatch ? $firstBatch->partsPurchaseOrder : null;
                                        $remaining = (int) ($order->part_batches_sum_quantity_remaining ?? 0);
                                        $received = (int) ($order->part_batches_sum_quantity_received ?? 0);
                                        $isPending = $po && (int)$po->status === \App\Models\PartsPurchaseOrder::STATUS_PENDING;
                                        $canApprove = session('user') && (session('user')->role_id == 1 || session('user_id') == 1 || (method_exists(session('user'), 'hasPermission') && session('user')->hasPermission('purchase_approve')));
                                    @endphp
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>
                                            @if ($po)
                                                <a href="{{ route('v2.parts-inventory.purchases.detail', $po->id) }}">{{ $order->reference_id ?? '–' }}</a>
                                            @else
                                                {{ $order->reference_id ?? '–' }}
                                            @endif
                                        </td>
                                        <td>{{ $vendors[$order->customer_id] ?? ($order->customer ? ($order->customer->company ?? $order->customer->first_name) : '–') }}</td>
                                        <td>{{ $order->reference ?? ($po->reference ?? '–') }}</td>
                                        <td>{{ $order->tracking_number ?? '–' }}</td>
                                        @if (session('user')->hasPermission('view_cost') ?? false)
                                            <td>Є{{ number_format((float)($order->part_batches_sum_total_cost ?? 0), 2) }}</td>
                                        @endif
                                        <td>{{ $remaining }}/{{ $received ?: '–' }} @if ($isPending) (Pending) @endif</td>
                                        <td>–</td>
                                        <td style="width:180px">{{ $order->created_at ? $order->created_at->format('Y-m-d H:i') : '–' }}</td>
                                        @if (request('status') !== 'pending')
                                            <td style="width:180px">{{ $order->processed_at ? \Carbon\Carbon::parse($order->processed_at)->format('Y-m-d H:i') : '–' }}</td>
                                        @endif
                                        <td>
                                            <div class="dropdown">
                                                <button type="button" class="btn btn-link btn-sm p-0 border-0 text-dark" data-bs-toggle="dropdown" aria-expanded="false" title="Actions"><i class="fe fe-more-vertical"></i></button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    @if ($po)
                                                        <li><a class="dropdown-item" href="{{ route('v2.parts-inventory.purchases.detail', $po->id) }}"><i class="fe fe-file-text me-2"></i>View</a></li>
                                                        @if ($isPending && $canApprove)
                                                            <li>
                                                                <form method="POST" action="{{ route('v2.parts-inventory.purchases.approve', $po->id) }}" class="d-inline">
                                                                    @csrf
                                                                    <button type="submit" class="dropdown-item text-success"><i class="fe fe-check me-2"></i>Approve</button>
                                                                </form>
                                                            </li>
                                                        @endif
                                                    @endif
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                    @php $i++; @endphp
                                @empty
                                    @php
                                        $colCount = 10 + ((session('user')->hasPermission('view_cost') ?? false) ? 1 : 0) + (request('status') !== 'pending' ? 1 : 0);
                                    @endphp
                                    <tr>
                                        <td colspan="{{ $colCount }}" class="text-center text-muted">No parts purchase orders found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <br>
                    {{ $orders->onEachSide(1)->links() }} {{ __('locale.From') }} {{ $orders->firstItem() ?? 0 }} {{ __('locale.To') }} {{ $orders->lastItem() ?? 0 }} {{ __('locale.Out Of') }} {{ $orders->total() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
