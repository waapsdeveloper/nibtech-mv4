@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? ($part->exists ? 'Edit Part' : 'Add Part') }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/parts-inventory/dashboard') }}">Parts Inventory</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ route('v2.parts-inventory.catalog') }}">Part Catalog</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $part->exists ? 'Edit' : 'Add' }}</li>
            </ol>
        </div>
    </div>

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
                    <form method="POST" action="{{ $part->exists ? route('v2.parts-inventory.catalog.update', $part->id) : route('v2.parts-inventory.catalog.store') }}">
                        @csrf
                        <div class="row g-3">
                            @if ($part->exists)
                                <div class="col-md-12">
                                    <p class="text-muted mb-1">Current product: <strong>{{ $part->product->model ?? '–' }}</strong></p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Change product (IMEI from inventory)</label>
                                    <input type="text" name="imei" class="form-control" value="{{ old('imei') }}" placeholder="Enter IMEI to link to a different device model" maxlength="255">
                                    <small class="form-text text-muted">Leave blank to keep current product. IMEI must exist in <a href="{{ url('/inventory') }}" target="_blank">Inventory</a>.</small>
                                </div>
                            @else
                                <div class="col-md-6">
                                    <label class="form-label">IMEI (from inventory)</label>
                                    <input type="text" name="imei" class="form-control" value="{{ old('imei') }}" placeholder="e.g. from Inventory" maxlength="255">
                                    <small class="form-text text-muted">Optional. Enter an IMEI from <a href="{{ url('/inventory') }}" target="_blank">Inventory</a> to link this part to a device model, or leave blank and use <strong>Attach IMEI</strong> from the catalog list later.</small>
                                </div>
                            @endif
                            <div class="col-md-6">
                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $part->name) }}" required maxlength="255">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SKU</label>
                                <input type="text" name="sku" class="form-control" value="{{ old('sku', $part->sku) }}" maxlength="255">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Compatible device</label>
                                <input type="text" name="compatible_device" class="form-control" value="{{ old('compatible_device', $part->compatible_device) }}" maxlength="255">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">On hand</label>
                                <input type="number" name="on_hand" class="form-control" value="{{ old('on_hand', $part->on_hand ?? 0) }}" min="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Reorder level</label>
                                <input type="number" name="reorder_level" class="form-control" value="{{ old('reorder_level', $part->reorder_level ?? 0) }}" min="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Unit cost (default)</label>
                                <input type="number" name="unit_cost" class="form-control" value="{{ old('unit_cost', $part->unit_cost ?? 0) }}" min="0" step="0.01">
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input type="hidden" name="active" value="0">
                                    <input type="checkbox" name="active" value="1" class="form-check-input" id="active" {{ old('active', $part->active ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="active">Active</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">{{ $part->exists ? 'Update' : 'Create' }}</button>
                                <a href="{{ route('v2.parts-inventory.catalog') }}" class="btn btn-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
