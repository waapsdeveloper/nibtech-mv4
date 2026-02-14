@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Assign to Repair' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/parts-inventory/dashboard') }}">Parts Inventory</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ route('v2.parts-inventory.items-to-repair') }}">Items to Repair</a></li>
                <li class="breadcrumb-item active" aria-current="page">Assign to repair</li>
            </ol>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Item</h5>
                </div>
                <div class="card-body">
                    @php $imei = $stock->imei ?? $stock->serial_number ?? '–'; @endphp
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td class="text-muted">IMEI / Serial</td><td><a href="{{ url('imei') }}?imei={{ urlencode($imei) }}" target="_blank">{{ $imei }}</a></td></tr>
                        <tr><td class="text-muted">Product</td><td>{{ optional($stock->variation)->product ? $stock->variation->product->model : '–' }}</td></tr>
                        <tr><td class="text-muted">Variation</td><td>{{ $stock->variation ? trim(($stock->variation->storage ?? '') . ' ' . ($stock->variation->color ?? '')) : '–' }}</td></tr>
                        <tr><td class="text-muted">Order ref</td><td>{{ optional($stock->sale_order)->reference_id ?? '–' }}</td></tr>
                    </table>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Assign part for repair</h5>
                </div>
                <div class="card-body">
                    @if ($assignment)
                        <p class="text-success mb-3"><strong>Assigned to repair.</strong> Part: {{ $assignment->repairPart->name ?? '–' }}{{ $assignment->repairPart->sku ? ' (' . $assignment->repairPart->sku . ')' : '' }} — assigned {{ $assignment->assigned_at->format('Y-m-d H:i') }}</p>
                    @endif
                    <form method="POST" action="{{ route('v2.parts-inventory.items-to-repair.assign.store', $stock->id) }}">
                        @csrf
                        <div class="mb-3">
                            <label for="repair_part_id" class="form-label">Part from Parts Inventory <span class="text-danger">*</span></label>
                            <select name="repair_part_id" id="repair_part_id" class="form-select" required>
                                <option value="">— Select part —</option>
                                @foreach ($parts as $p)
                                    <option value="{{ $p->id }}" {{ ($assignment && $assignment->repair_part_id == $p->id) ? 'selected' : '' }}>{{ $p->name }}{{ $p->sku ? ' (' . $p->sku . ')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea name="notes" id="notes" class="form-control" rows="2" maxlength="500" placeholder="Optional notes">{{ $assignment->notes ?? old('notes') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">{{ $assignment ? 'Update assignment' : 'Assign to repair' }}</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Mark as repaired</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Repair may not happen urgently. When the repair is done, check the box below and save to mark this item as repaired and move it back to available inventory.</p>
                    <form method="POST" action="{{ route('v2.parts-inventory.items-to-repair.mark-repaired', $stock->id) }}" id="markRepairedForm" onsubmit="return document.getElementById('mark_repaired').checked && confirm('Mark this item as repaired? It will move to available (status 1).');">
                        @csrf
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="mark_repaired" name="mark_repaired" value="1">
                            <label class="form-check-label" for="mark_repaired">Mark as repaired</label>
                        </div>
                        <button type="submit" class="btn btn-outline-success" id="markRepairedBtn" disabled>Save (mark as repaired)</button>
                    </form>
                    <script>
                        document.getElementById('mark_repaired').addEventListener('change', function() {
                            document.getElementById('markRepairedBtn').disabled = !this.checked;
                        });
                    </script>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
