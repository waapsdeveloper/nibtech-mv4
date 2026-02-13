@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Stock Comparison Details' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ route('v2.listing-stock-comparisons.index') }}">Stock Comparisons</a></li>
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
                            <form action="{{ route('v2.listing-stock-comparisons.destroy', $comparison->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this comparison?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="{{ route('v2.listing-stock-comparisons.index') }}" class="btn btn-secondary">Back to List</a>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">ID</th>
                                    <td>{{ $comparison->id }}</td>
                                </tr>
                                <tr>
                                    <th>Compared At</th>
                                    <td>{{ $comparison->compared_at->format('Y-m-d H:i:s') }}</td>
                                </tr>
                                <tr>
                                    <th>Variation SKU</th>
                                    <td>
                                        <a href="{{ url('variation') }}?sku={{ $comparison->variation_sku }}" target="_blank">
                                            {{ $comparison->variation_sku }}
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Variation</th>
                                    <td>
                                        @if($comparison->variation)
                                            {{ $comparison->variation->name ?? 'N/A' }}
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Marketplace</th>
                                    <td>
                                        @if($comparison->marketplace)
                                            {{ $comparison->marketplace->name }}
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Country</th>
                                    <td>{{ $comparison->country_code ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td>
                                        <span class="badge bg-{{ $comparison->status_badge_class }}">
                                            {{ $comparison->status_label }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">API Stock</th>
                                    <td class="text-center"><strong class="text-primary">{{ $comparison->api_stock }}</strong></td>
                                </tr>
                                <tr>
                                    <th>Our Stock</th>
                                    <td class="text-center"><strong class="text-info">{{ $comparison->our_stock }}</strong></td>
                                </tr>
                                <tr>
                                    <th>Stock Difference</th>
                                    <td class="text-center">
                                        <span class="badge bg-{{ $comparison->stock_difference == 0 ? 'success' : ($comparison->stock_difference > 0 ? 'warning' : 'danger') }}">
                                            {{ $comparison->stock_difference > 0 ? '+' : '' }}{{ $comparison->stock_difference }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Pending Orders Count</th>
                                    <td class="text-center"><strong>{{ $comparison->pending_orders_count }}</strong></td>
                                </tr>
                                <tr>
                                    <th>Pending Orders Quantity</th>
                                    <td class="text-center"><strong class="text-warning">{{ $comparison->pending_orders_quantity }}</strong></td>
                                </tr>
                                <tr>
                                    <th>Available After Pending</th>
                                    <td class="text-center">
                                        <strong class="{{ $comparison->available_after_pending < 0 ? 'text-danger' : 'text-success' }}">
                                            {{ $comparison->available_after_pending }}
                                        </strong>
                                    </td>
                                </tr>
                                <tr>
                                    <th>API vs Pending Difference</th>
                                    <td class="text-center">
                                        <strong class="{{ $comparison->api_vs_pending_difference < 0 ? 'text-danger' : 'text-success' }}">
                                            {{ $comparison->api_vs_pending_difference }}
                                        </strong>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Is Perfect</th>
                                    <td>
                                        @if($comparison->is_perfect)
                                            <span class="badge bg-success">Yes</span>
                                        @else
                                            <span class="badge bg-secondary">No</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Has Discrepancy</th>
                                    <td>
                                        @if($comparison->has_discrepancy)
                                            <span class="badge bg-warning">Yes</span>
                                        @else
                                            <span class="badge bg-success">No</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Has Shortage</th>
                                    <td>
                                        @if($comparison->has_shortage)
                                            <span class="badge bg-danger">Yes</span>
                                        @else
                                            <span class="badge bg-success">No</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Has Excess</th>
                                    <td>
                                        @if($comparison->has_excess)
                                            <span class="badge bg-warning">Yes</span>
                                        @else
                                            <span class="badge bg-success">No</span>
                                        @endif
                                    </td>
                                </tr>
                                @if($comparison->notes)
                                <tr>
                                    <th>Notes</th>
                                    <td>{{ $comparison->notes }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <th>Created At</th>
                                    <td>{{ $comparison->created_at->format('Y-m-d H:i:s') }}</td>
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
