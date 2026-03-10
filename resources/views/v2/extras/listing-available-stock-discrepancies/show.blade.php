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
                <li class="breadcrumb-item tx-15"><a href="{{ route('v2.extras.listing-available-stock-discrepancies.index') }}">Available vs Stocks Discrepancies</a></li>
                <li class="breadcrumb-item active" aria-current="page">Detail</li>
            </ol>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Discrepancy #{{ $discrepancy->id }}</h5>
        </div>
        <div class="card-body">
            <h6 class="text-muted mb-2">Exact numbers (as on listing variation card)</h6>
            <table class="table table-bordered mb-3">
                <tr>
                    <th width="220">Listed (BM)</th>
                    <td class="fw-bold">{{ $discrepancy->variation ? ($discrepancy->variation->listed_stock ?? '—') : '—' }}</td>
                    <td class="text-muted small">Stock field / Back Market</td>
                </tr>
                <tr>
                    <th>Available (card)</th>
                    <td class="fw-bold">{{ $discrepancy->available_count }}</td>
                    <td class="text-muted small">Card &quot;Available&quot;</td>
                </tr>
                <tr>
                    <th>Total stocks (table)</th>
                    <td class="fw-bold">{{ $discrepancy->stocks_table_count }}</td>
                    <td class="text-muted small">Stocks table row count</td>
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
                    <th>Difference</th>
                    <td><span class="badge {{ $discrepancy->difference > 0 ? 'bg-warning' : 'bg-info' }}">{{ $discrepancy->difference }}</span> (stocks table − available)</td>
                </tr>
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
                <form action="{{ route('v2.extras.listing-available-stock-discrepancies.fix') }}" method="POST" class="d-inline" onsubmit="return confirm('Set Listed (BM) to Total stocks ({{ $discrepancy->stocks_table_count }}) and push to Back Market?');">
                    @csrf
                    <input type="hidden" name="ids[]" value="{{ $discrepancy->id }}">
                    <button type="submit" class="btn btn-success">Fix (set Listed BM to {{ $discrepancy->stocks_table_count }})</button>
                </form>
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
