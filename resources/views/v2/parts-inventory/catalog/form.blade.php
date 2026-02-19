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
                            <div class="col-md-6">
                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $part->name) }}" required maxlength="255">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">On hand</label>
                                <input type="number" name="on_hand" class="form-control" value="{{ old('on_hand', $part->on_hand ?? 0) }}" min="0">
                            </div>
                            <div class="col-12">
                                <label class="form-label">SKU / Barcode</label>
                                <div class="input-group">
                                    <input type="text" name="sku" id="part-sku" class="form-control" value="{{ old('sku', $part->sku) }}" maxlength="255" placeholder="Same for the whole batch — scan or generate below" autocomplete="off">
                                    <span class="input-group-text text-muted px-2">Generate or capture</span>
                                    <button type="button" class="btn btn-outline-secondary" id="btn-scan-barcode" title="Focus field and scan with barcode gun">
                                        <i class="fe fe-maximize-2" aria-hidden="true"></i>
                                        <span class="d-none d-sm-inline ms-1">Scan</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="btn-generate-barcode" title="Generate system barcode (e.g. PRT-20250219-XXXX)">
                                        <i class="fe fe-hash" aria-hidden="true"></i>
                                        <span class="d-none d-sm-inline ms-1">Generate</span>
                                    </button>
                                </div>
                                <small class="form-text text-muted" id="scan-hint" style="display: none;">Listening for barcode gun — scan now.</small>
                            </div>
                            <div class="col-md-6">
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

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var skuInput = document.getElementById('part-sku');
    var scanBtn = document.getElementById('btn-scan-barcode');
    var generateBtn = document.getElementById('btn-generate-barcode');
    var scanHint = document.getElementById('scan-hint');

    if (scanBtn && skuInput) {
        scanBtn.addEventListener('click', function() {
            skuInput.focus();
            if (scanHint) {
                scanHint.style.display = 'block';
                setTimeout(function() { scanHint.style.display = 'none'; }, 4000);
            }
        });
    }

    if (generateBtn && skuInput) {
        generateBtn.addEventListener('click', function() {
            generateBtn.disabled = true;
            fetch('{{ route("v2.parts-inventory.catalog.next-barcode") }}', {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.barcode) {
                    skuInput.value = data.barcode;
                    skuInput.focus();
                } else if (data.error) {
                    alert(data.error || 'Could not generate barcode.');
                }
            })
            .catch(function() { alert('Request failed. Try again.'); })
            .finally(function() { generateBtn.disabled = false; });
        });
    }
});
</script>
@endsection
