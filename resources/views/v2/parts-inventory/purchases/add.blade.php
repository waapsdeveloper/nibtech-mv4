@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Add Purchase' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/parts-inventory/dashboard') }}">Parts Inventory</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ route('v2.parts-inventory.dashboard') }}">Parts Inventory</a></li>
                <li class="breadcrumb-item active" aria-current="page">Add Purchase</li>
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
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted small">Record a parts purchase in a batch. Each batch has a system-generated barcode (same for all items in the batch). You can optionally enter a manufacturer barcode. Price can be set now or left as lease (decide later).</p>
                    <form action="{{ route('v2.parts-inventory.purchases.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="redirect_to" value="{{ route('v2.parts-inventory.dashboard') }}">

                        {{-- Batch: existing or new --}}
                        <div class="row g-3 mb-3">
                            <div class="col-12">
                                <label class="form-label fw-medium">Batch</label>
                            </div>
                            @if ($batch)
                                <div class="col-md-12">
                                    <p class="mb-0">
                                        <span class="badge bg-primary">Existing batch</span>
                                        <strong>{{ $batch->system_barcode }}</strong>
                                        @if ($batch->manufacturer_barcode)
                                            <span class="text-muted"> / {{ $batch->manufacturer_barcode }}</span>
                                        @endif
                                    </p>
                                    <input type="hidden" name="batch_id" value="{{ $batch->id }}">
                                    <a href="{{ route('v2.parts-inventory.purchases.add') }}" class="btn btn-sm btn-outline-secondary mt-1">Use a new batch instead</a>
                                </div>
                            @else
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input type="hidden" name="new_batch" value="0">
                                        <input type="checkbox" name="new_batch" value="1" id="new_batch" class="form-check-input" {{ old('new_batch', true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="new_batch">Create new batch</label>
                                    </div>
                                    <p class="small text-muted mb-0 mt-1">System barcode will be auto-generated (e.g. <code>{{ $newBatchBarcode }}</code>).</p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Or add to existing batch (system barcode)</label>
                                    <input type="text" name="existing_system_barcode" class="form-control" value="{{ old('existing_system_barcode') }}" placeholder="e.g. PPB-20260218-0001" maxlength="64">
                                    <small class="text-muted">Leave blank if creating new batch.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Manufacturer barcode (optional, for new batch)</label>
                                    <input type="text" name="manufacturer_barcode" class="form-control" value="{{ old('manufacturer_barcode') }}" placeholder="Supplier/manufacturer barcode" maxlength="255">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Batch notes (optional, for new batch)</label>
                                    <input type="text" name="batch_notes" class="form-control" value="{{ old('batch_notes') }}" placeholder="Optional notes for this batch" maxlength="500">
                                </div>
                            @endif
                        </div>

                        <hr>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Part <span class="text-danger">*</span></label>
                                <select name="repair_part_id" class="form-control form-select" required>
                                    <option value="">Select part</option>
                                    @foreach ($parts as $p)
                                        <option value="{{ $p->id }}" {{ old('repair_part_id') == $p->id ? 'selected' : '' }}>{{ $p->name }} @if($p->sku)({{ $p->sku }})@endif</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Quantity <span class="text-danger">*</span></label>
                                <input type="number" name="quantity" class="form-control" value="{{ old('quantity', 1) }}" min="1" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Unit price (optional)</label>
                                <input type="number" name="unit_price" class="form-control" step="0.01" min="0" value="{{ old('unit_price') }}" placeholder="Leave blank for lease">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="form-check">
                                    <input type="hidden" name="is_lease" value="0">
                                    <input type="checkbox" name="is_lease" value="1" id="is_lease" class="form-check-input" {{ old('is_lease') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_lease">On lease (price later)</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="Optional" maxlength="500">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Save purchase</button>
                                <a href="{{ route('v2.parts-inventory.dashboard') }}" class="btn btn-secondary">Back to Parts Inventory</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
