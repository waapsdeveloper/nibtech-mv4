@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $data['title_page'] ?? 'Discrepancy detail' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/extras/listing-available-stock-discrepancies') }}">Extras</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ route('v2.extras.listing-available-stock-discrepancies.index') }}">Listed vs Should Be</a></li>
                <li class="breadcrumb-item active" aria-current="page">Detail</li>
            </ol>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h5 class="card-title mb-0">Dashboard totals</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <span class="text-muted small">Total Listed</span>
                    <strong class="d-block">{{ number_format($totals['listed_total'] ?? 0) }}</strong>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small">Should Be</span>
                    <strong class="d-block">{{ number_format($totals['should_be_total'] ?? 0) }}</strong>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small">Difference</span>
                    @php $diff = $totals['difference_total'] ?? 0; @endphp
                    <strong class="d-block {{ $diff > 0 ? 'text-warning' : ($diff < 0 ? 'text-info' : '') }}">{{ $diff >= 0 ? '+' : '' }}{{ number_format($diff) }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Discrepancy #{{ $discrepancy->id }}</h5>
        </div>
        <div class="card-body">
            <h6 class="text-muted mb-2">Per-variation numbers</h6>
            <table class="table table-bordered mb-3">
                <tr>
                    <th width="220">Listed</th>
                    <td class="fw-bold">{{ $discrepancy->listed_stock ?? '—' }}</td>
                    <td class="text-muted small">Current variation.listed_stock</td>
                </tr>
                <tr>
                    <th>Should Be</th>
                    <td class="fw-bold">{{ $discrepancy->should_be ?? '—' }}</td>
                    <td class="text-muted small">Computed (grade&lt;6 stock − process 22 − pending items)</td>
                </tr>
                <tr>
                    <th>Difference</th>
                    <td>
                        @php $rowDiff = (int)($discrepancy->difference ?? 0); @endphp
                        <span class="badge {{ $rowDiff > 0 ? 'bg-warning text-dark' : 'bg-info' }}">{{ $rowDiff >= 0 ? '+' : '' }}{{ $rowDiff }}</span>
                    </td>
                    <td class="text-muted small">Listed − Should Be</td>
                </tr>
            </table>
            <table class="table table-bordered mb-0">
                <tr>
                    <th width="200">Variation ID</th>
                    <td>{{ $discrepancy->variation_id }}</td>
                </tr>
                <tr>
                    <th>SKU</th>
                    <td>
                        @if($discrepancy->variation)
                            <a href="{{ url('listing') }}?variation_id={{ $discrepancy->variation_id }}" target="_blank">{{ $discrepancy->variation->sku ?? $discrepancy->variation_sku }}</a>
                        @else
                            {{ $discrepancy->variation_sku ?? '—' }}
                        @endif
                    </td>
                </tr>
                @if($discrepancy->variation && $discrepancy->variation->product)
                <tr>
                    <th>Product</th>
                    <td>{{ $discrepancy->variation->product->model ?? '—' }}</td>
                </tr>
                @endif
                <tr>
                    <th>Detected at</th>
                    <td>{{ $discrepancy->detected_at ? $discrepancy->detected_at->format('Y-m-d H:i:s') : '—' }}</td>
                </tr>
                <tr>
                    <th>Updated at</th>
                    <td>{{ $discrepancy->updated_at ? $discrepancy->updated_at->format('Y-m-d H:i:s') : '—' }}</td>
                </tr>
            </table>
            <div class="mt-3">
                <a href="{{ route('v2.extras.listing-available-stock-discrepancies.index') }}" class="btn btn-secondary">Back to list</a>
                @if((int)($discrepancy->difference ?? 0) < 0)
                <form action="{{ route('v2.extras.listing-available-stock-discrepancies.fix') }}" method="POST" class="d-inline" onsubmit="return confirm('Set Listed to {{ $discrepancy->should_be }} in DB only (no Back Market push)?');">
                    @csrf
                    <input type="hidden" name="ids[]" value="{{ $discrepancy->id }}">
                    <button type="submit" class="btn btn-success">Fix (set Listed → {{ $discrepancy->should_be }}, DB only)</button>
                </form>
                @else
                <span class="text-muted small">(Positive discrepancy — fix not applied)</span>
                @endif
                <form action="{{ route('v2.extras.listing-available-stock-discrepancies.destroy', $discrepancy->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this record?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
