@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $data['title_page'] ?? 'Listing Available vs Stocks Discrepancies' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/extras/listing-available-stock-discrepancies') }}">Extras</a></li>
                <li class="breadcrumb-item active" aria-current="page">Available vs Stocks Discrepancies</li>
            </ol>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0">Discrepancy records</h5>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <form action="{{ route('v2.extras.listing-available-stock-discrepancies.fix') }}" method="POST" id="fix-selected-form" class="d-flex align-items-center gap-2">
                    @csrf
                    <input type="hidden" name="ids" id="fix-selected-ids" value="">
                    <button type="submit" class="btn btn-success btn-sm" id="fix-selected-btn" disabled>Fix selected</button>
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
            <p class="text-muted small mb-2"><strong>Two columns:</strong> Listed (BM) = Stock field on listing card. Total stocks (table) = same count as the stocks table in listing card details (get_variation_available_stocks). <strong>Fix</strong> sets Listed (BM) to Total stocks (table) and pushes to Back Market.</p>
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0">
                    <thead>
                        <tr>
                            <th width="40"><input type="checkbox" id="select-all" title="Select all on page"></th>
                            <th>Variation / SKU</th>
                            <th class="text-center" title="Stock field on listing card (Back Market)">Listed (BM)</th>
                            <th class="text-center" title="Same as stocks table in listing card details">Total stocks (table)</th>
                            <th width="160">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($discrepancies as $d)
                        <tr>
                            <td><input type="checkbox" class="discrepancy-cb" value="{{ $d->id }}" data-id="{{ $d->id }}"></td>
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
                            <td class="text-center"><strong>{{ $d->variation ? ($d->variation->listed_stock ?? '—') : '—' }}</strong></td>
                            <td class="text-center"><strong>{{ $stocksTableCounts[$d->variation_id] ?? $d->stocks_table_count }}</strong></td>
                            <td>
                                <form action="{{ route('v2.extras.listing-available-stock-discrepancies.fix') }}" method="POST" class="d-inline" onsubmit="return confirm('Set Listed (BM) to Total stocks ({{ $stocksTableCounts[$d->variation_id] ?? $d->stocks_table_count }}) and push to Back Market?');">
                                    @csrf
                                    <input type="hidden" name="ids[]" value="{{ $d->id }}">
                                    <button type="submit" class="btn btn-sm btn-success">Fix</button>
                                </form>
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
                            <td colspan="5" class="text-center text-muted">No discrepancy records. Run the check to populate.</td>
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
        var checkboxes = document.querySelectorAll('.discrepancy-cb');
        var fixSelectedForm = document.getElementById('fix-selected-form');
        var fixSelectedIds = document.getElementById('fix-selected-ids');
        var fixSelectedBtn = document.getElementById('fix-selected-btn');

        function updateFixSelected() {
            var checked = document.querySelectorAll('.discrepancy-cb:checked');
            fixSelectedBtn.disabled = checked.length === 0;
            fixSelectedIds.value = Array.from(checked).map(function(cb) { return cb.value; }).join(',');
        }

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
                updateFixSelected();
            });
        }
        checkboxes.forEach(function(cb) {
            cb.addEventListener('change', updateFixSelected);
        });
        updateFixSelected();
    });
    </script>
</div>
@endsection
