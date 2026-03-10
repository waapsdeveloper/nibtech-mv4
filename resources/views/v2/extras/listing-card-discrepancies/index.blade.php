@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $data['title_page'] ?? 'Listing card mismatches' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ route('v2.extras.listing-available-stock-discrepancies.index') }}">Extras</a></li>
                <li class="breadcrumb-item active" aria-current="page">Stock vs Available vs Table</li>
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
            <h5 class="card-title mb-0">Variations where Stock, Available, and Stocks table don't match</h5>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <form action="{{ route('v2.extras.listing-card-discrepancies.fix') }}" method="POST" id="fix-selected-form" class="d-flex align-items-center gap-2">
                    @csrf
                    <input type="hidden" name="ids" id="fix-selected-ids" value="">
                    <button type="submit" class="btn btn-success btn-sm" id="fix-selected-btn" disabled>Fix selected (set to Table count, DB only)</button>
                </form>
                <form action="{{ route('v2.extras.listing-card-discrepancies.run-check') }}" method="POST" class="d-flex align-items-center gap-2">
                    @csrf
                    <label for="chunk" class="form-label mb-0 small">Chunk:</label>
                    <input type="number" name="chunk" id="chunk" value="500" min="100" max="2000" step="100" class="form-control form-control-sm" style="width: 80px;">
                    <button type="submit" class="btn btn-primary btn-sm">Run check</button>
                </form>
                <a href="{{ route('v2.extras.listing-available-stock-discrepancies.index') }}" class="btn btn-outline-secondary btn-sm">Listed vs Should Be</a>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-2"><strong>Stock</strong> = <code>variation.listed_stock</code> (Stock field on listing card). <strong>Available</strong> = card “Available” (available_stocks count). <strong>Stocks table</strong> = count from details table (get_variation_available_stocks). <strong>Fix</strong> sets Listed and Available override to Stocks table count (DB only).</p>
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0">
                    <thead>
                        <tr>
                            <th width="40"><input type="checkbox" id="select-all" title="Select all"></th>
                            <th>Variation / SKU</th>
                            <th class="text-center">Stock (Listed)</th>
                            <th class="text-center">Available</th>
                            <th class="text-center">Stocks table</th>
                            <th width="160">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($discrepancies as $d)
                        <tr>
                            <td><input type="checkbox" class="row-cb" value="{{ $d->id }}"></td>
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
                            <td class="text-center"><strong>{{ $d->listed_stock }}</strong></td>
                            <td class="text-center"><strong>{{ $d->available_count }}</strong></td>
                            <td class="text-center"><strong>{{ $d->stocks_table_count }}</strong></td>
                            <td>
                                <form action="{{ route('v2.extras.listing-card-discrepancies.fix') }}" method="POST" class="d-inline" onsubmit="return confirm('Set Stock and Available to {{ $d->stocks_table_count }} (DB only)?');">
                                    @csrf
                                    <input type="hidden" name="ids[]" value="{{ $d->id }}">
                                    <button type="submit" class="btn btn-sm btn-success">Fix</button>
                                </form>
                                <form action="{{ route('v2.extras.listing-card-discrepancies.destroy', $d->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this record?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">No records. Run the check to find variations where Stock, Available, and Stocks table count differ.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($discrepancies->hasPages())
            <div class="mt-3">{{ $discrepancies->links() }}</div>
            @endif
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var selectAll = document.getElementById('select-all');
        var rowCbs = document.querySelectorAll('.row-cb');
        var fixIds = document.getElementById('fix-selected-ids');
        var fixBtn = document.getElementById('fix-selected-btn');
        function update() {
            var checked = document.querySelectorAll('.row-cb:checked');
            fixBtn.disabled = checked.length === 0;
            fixIds.value = Array.from(checked).map(function(c) { return c.value; }).join(',');
        }
        if (selectAll) selectAll.addEventListener('change', function() { rowCbs.forEach(function(c) { c.checked = selectAll.checked; }); update(); });
        rowCbs.forEach(function(c) { c.addEventListener('change', update); });
        update();
    });
    </script>
</div>
@endsection
