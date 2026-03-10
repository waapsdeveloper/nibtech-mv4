@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $data['title_page'] ?? 'Listed vs Should Be' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/extras/listing-available-stock-discrepancies') }}">Extras</a></li>
                <li class="breadcrumb-item active" aria-current="page">Listed vs Should Be</li>
            </ol>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <div class="card mb-3">
        <div class="card-header">
            <h5 class="card-title mb-0">Dashboard totals (same formula as widget)</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <span class="text-muted small d-block">Total Listed</span>
                        <strong class="fs-4">{{ number_format($totals['listed_total'] ?? 0) }}</strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <span class="text-muted small d-block">Should Be</span>
                        <strong class="fs-4">{{ number_format($totals['should_be_total'] ?? 0) }}</strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <span class="text-muted small d-block">Difference (Listed − Should Be)</span>
                        @php $diff = $totals['difference_total'] ?? 0; @endphp
                        <strong class="fs-4 {{ $diff > 0 ? 'text-warning' : ($diff < 0 ? 'text-info' : '') }}">{{ $diff >= 0 ? '+' : '' }}{{ number_format($diff) }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0">Discrepancy records (per variation, grade &lt; 6)</h5>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <form action="{{ route('v2.extras.listing-available-stock-discrepancies.fix') }}" method="POST" id="fix-selected-form" class="d-flex align-items-center gap-2">
                    @csrf
                    <input type="hidden" name="ids" id="fix-selected-ids" value="">
                    <button type="submit" class="btn btn-success btn-sm" id="fix-selected-btn" disabled>Fix selected (set Listed → Should Be, DB only)</button>
                </form>
                <form action="{{ route('v2.extras.listing-available-stock-discrepancies.run-check') }}" method="POST" class="d-flex align-items-center gap-2">
                    @csrf
                    <label for="chunk" class="form-label mb-0 small">Chunk:</label>
                    <input type="number" name="chunk" id="chunk" value="500" min="100" max="2000" step="100" class="form-control form-control-sm" style="width: 80px;">
                    <button type="submit" class="btn btn-primary btn-sm">Run check</button>
                </form>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-2"><strong>Listed</strong> = current <code>variation.listed_stock</code>. <strong>Should Be</strong> = same formula as dashboard: (stock count grade&lt;6 − process type 22 − pending order items qty). <strong>Fix</strong> only applies to <strong>negative</strong> discrepancies (listed &lt; should be): sets Listed to Should Be in our DB only (no Back Market push).</p>
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0">
                    <thead>
                        <tr>
                            <th width="40"><input type="checkbox" id="select-all" title="Select all fixable (negative) on page"></th>
                            <th>Variation / SKU</th>
                            <th class="text-center">Listed</th>
                            <th class="text-center">Should Be</th>
                            <th class="text-center">Difference</th>
                            <th width="180">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($discrepancies as $d)
                        @php $rowDiff = (int)($d->difference ?? 0); $isNegative = $rowDiff < 0; @endphp
                        <tr>
                            <td>
                                @if($isNegative)
                                    <input type="checkbox" class="discrepancy-cb fixable-cb" value="{{ $d->id }}" data-id="{{ $d->id }}">
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($d->variation)
                                    <a href="{{ url('listing') }}?variation_id={{ $d->variation_id }}" target="_blank">{{ $d->variation_sku ?? $d->variation->sku }}</a>
                                    @if($d->variation->product)
                                        <br><small class="text-muted">{{ $d->variation->product->model ?? '' }}</small>
                                    @endif
                                @else
                                    {{ $d->variation_sku ?? 'Variation #' . $d->variation_id }}
                                @endif
                            </td>
                            <td class="text-center"><strong>{{ $d->listed_stock ?? '—' }}</strong></td>
                            <td class="text-center"><strong>{{ $d->should_be ?? '—' }}</strong></td>
                            <td class="text-center">
                                <span class="badge {{ $rowDiff > 0 ? 'bg-warning text-dark' : 'bg-info' }}">{{ $rowDiff >= 0 ? '+' : '' }}{{ $rowDiff }}</span>
                            </td>
                            <td>
                                @if($isNegative)
                                <form action="{{ route('v2.extras.listing-available-stock-discrepancies.fix') }}" method="POST" class="d-inline" onsubmit="return confirm('Set Listed to {{ $d->should_be }} in DB only (no Back Market push)?');">
                                    @csrf
                                    <input type="hidden" name="ids[]" value="{{ $d->id }}">
                                    <button type="submit" class="btn btn-sm btn-success">Fix</button>
                                </form>
                                @else
                                <span class="text-muted small">(positive — no fix)</span>
                                @endif
                                <a href="{{ route('v2.extras.listing-available-stock-discrepancies.show', $d->id) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                <form action="{{ route('v2.extras.listing-available-stock-discrepancies.destroy', $d->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this record?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">No discrepancy records. Run the check to populate.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($discrepancies->hasPages())
            <div class="mt-3">
                {{ $discrepancies->links() }}
            </div>
            @endif
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var selectAll = document.getElementById('select-all');
        var fixableCbs = document.querySelectorAll('.fixable-cb');
        var fixSelectedForm = document.getElementById('fix-selected-form');
        var fixSelectedIds = document.getElementById('fix-selected-ids');
        var fixSelectedBtn = document.getElementById('fix-selected-btn');

        function updateFixSelected() {
            var checked = document.querySelectorAll('.fixable-cb:checked');
            fixSelectedBtn.disabled = checked.length === 0;
            fixSelectedIds.value = Array.from(checked).map(function(cb) { return cb.value; }).join(',');
        }

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                document.querySelectorAll('.fixable-cb').forEach(function(cb) { cb.checked = selectAll.checked; });
                updateFixSelected();
            });
        }
        document.querySelectorAll('.fixable-cb').forEach(function(cb) {
            cb.addEventListener('change', updateFixSelected);
        });
        updateFixSelected();
    });
    </script>
</div>
@endsection
