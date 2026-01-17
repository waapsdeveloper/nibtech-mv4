@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Stock Deduction Log Details' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">Dashboard</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ route('v2.stock-deduction-logs.index') }}">Stock Deduction Logs</a></li>
                <li class="breadcrumb-item active" aria-current="page">Details</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <a href="{{ route('v2.stock-deduction-logs.edit', $log->id) }}" class="btn btn-warning">Edit</a>
                            <form action="{{ route('v2.stock-deduction-logs.destroy', $log->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this log?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="{{ route('v2.stock-deduction-logs.index') }}" class="btn btn-secondary">Back to List</a>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">ID</th>
                                    <td>{{ $log->id }}</td>
                                </tr>
                                <tr>
                                    <th>Deduction At</th>
                                    <td>{{ $log->deduction_at->format('Y-m-d H:i:s') }}</td>
                                </tr>
                                <tr>
                                    <th>Variation SKU</th>
                                    <td>
                                        <a href="{{ url('variation') }}?sku={{ $log->variation_sku }}" target="_blank">
                                            {{ $log->variation_sku }}
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Variation</th>
                                    <td>
                                        @if($log->variation)
                                            {{ $log->variation->name ?? 'N/A' }}
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Marketplace</th>
                                    <td>
                                        @if($log->marketplace)
                                            {{ $log->marketplace->name }}
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Order Reference</th>
                                    <td>
                                        @if($log->order_reference_id)
                                            <a href="{{ url('order') }}?order_id={{ $log->order_reference_id }}" target="_blank">
                                                {{ $log->order_reference_id }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Order</th>
                                    <td>
                                        @if($log->order)
                                            <a href="{{ url('order') }}?id={{ $log->order->id }}" target="_blank">
                                                Order #{{ $log->order->id }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">Deduction Reason</th>
                                    <td>
                                        <span class="badge bg-{{ $log->deduction_reason == 'new_order_status_1' ? 'primary' : 'info' }}">
                                            {{ $log->deduction_reason_label }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Order Status</th>
                                    <td>
                                        @if($log->order_status)
                                            <span class="badge bg-secondary">{{ $log->order_status_name }}</span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Old Order Status</th>
                                    <td>{{ $log->old_order_status ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Is New Order</th>
                                    <td>
                                        @if($log->is_new_order)
                                            <span class="badge bg-success">Yes</span>
                                        @else
                                            <span class="badge bg-secondary">No</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Variation Stock</th>
                                    <td>
                                        <span class="text-muted">{{ $log->before_variation_stock }}</span>
                                        <i class="fe fe-arrow-right mx-2"></i>
                                        <strong class="{{ $log->after_variation_stock < 0 ? 'text-danger' : 'text-success' }}">
                                            {{ $log->after_variation_stock }}
                                        </strong>
                                        <span class="text-muted ms-2">({{ $log->after_variation_stock - $log->before_variation_stock }})</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Marketplace Stock</th>
                                    <td>
                                        <span class="text-muted">{{ $log->before_marketplace_stock }}</span>
                                        <i class="fe fe-arrow-right mx-2"></i>
                                        <strong class="{{ $log->after_marketplace_stock < 0 ? 'text-danger' : 'text-success' }}">
                                            {{ $log->after_marketplace_stock }}
                                        </strong>
                                        <span class="text-muted ms-2">({{ $log->after_marketplace_stock - $log->before_marketplace_stock }})</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Notes</th>
                                    <td>{{ $log->notes ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Created At</th>
                                    <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                                </tr>
                                <tr>
                                    <th>Updated At</th>
                                    <td>{{ $log->updated_at->format('Y-m-d H:i:s') }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
