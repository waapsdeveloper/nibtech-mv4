@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Batch Receive' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/parts-inventory/dashboard') }}">Parts Inventory</a></li>
                <li class="breadcrumb-item active" aria-current="page">Batch Receive</li>
            </ol>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
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
                    <h5 class="card-title mb-4">Receive a new batch (same columns as bulk import)</h5>
                    <p class="text-muted small">Batch ref is system-generated (e.g. BR-{{ date('Ymd') }}-0001). <strong>Scan barcode</strong> (manufacturer SKU) into SKU field, or use the system-generated SKU below. Existing SKU = use that part; new SKU = create part (name required).</p>
                    <form method="POST" action="{{ route('v2.parts-inventory.batch-receive.store') }}" id="batch-receive-form">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">SKU <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" name="sku" id="sku-input" class="form-control" value="{{ old('sku', $suggestedSku ?? '') }}" required placeholder="Scan barcode or enter / use suggested SKU" maxlength="255" autofocus>
                                    <button type="button" class="btn btn-outline-secondary" id="sku-scan-trigger" title="Click then scan barcode with gun" aria-label="Scan barcode">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="4" x2="4" y2="20"/><line x1="8" y1="4" x2="8" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/><line x1="16" y1="4" x2="16" y2="20"/><line x1="20" y1="4" x2="20" y2="20"/></svg>
                                    </button>
                                </div>
                                <small class="form-text text-muted">Click the barcode icon then scan, or type/use the system-generated SKU. Existing SKU = use that part; new SKU = create part (enter name below).</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Name <span class="text-danger">*</span> <small class="text-muted">(when new part)</small></label>
                                <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="Required when SKU is new" maxlength="255">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Quantity received <span class="text-danger">*</span></label>
                                <input type="number" name="quantity_received" class="form-control" value="{{ old('quantity_received') }}" required min="1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Unit cost <span class="text-danger">*</span></label>
                                <input type="number" name="unit_cost" class="form-control" value="{{ old('unit_cost') }}" required min="0" step="0.01">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Received date</label>
                                <input type="date" name="received_at" class="form-control" value="{{ old('received_at', date('Y-m-d')) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Purchase date</label>
                                <input type="date" name="purchase_date" class="form-control" value="{{ old('purchase_date') }}">
                                <small class="form-text text-muted">If blank, received date is used.</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Supplier</label>
                                <input type="text" name="supplier" class="form-control" value="{{ old('supplier') }}" maxlength="255">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Notes</label>
                                <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" maxlength="500">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Receive batch</button>
                                <a href="{{ route('v2.parts-inventory.dashboard') }}" class="btn btn-secondary">Back to dashboard</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var skuInput = document.getElementById('sku-input');
    var scanTrigger = document.getElementById('sku-scan-trigger');
    var form = document.getElementById('batch-receive-form');
    var defaultPlaceholder = skuInput ? skuInput.placeholder : '';

    if (scanTrigger && skuInput) {
        scanTrigger.addEventListener('click', function() {
            skuInput.focus();
            skuInput.select();
            skuInput.placeholder = 'Listening for barcode… scan now';
            skuInput.classList.add('border-primary');
            var clearListening = function() {
                skuInput.placeholder = defaultPlaceholder;
                skuInput.classList.remove('border-primary');
            };
            var onEnter = function(e) {
                if (e.key === 'Enter') {
                    skuInput.removeEventListener('keydown', onEnter);
                    skuInput.removeEventListener('blur', onBlur);
                    clearListening();
                    e.preventDefault();
                }
            };
            var onBlur = function() {
                setTimeout(function() {
                    skuInput.removeEventListener('keydown', onEnter);
                    skuInput.removeEventListener('blur', onBlur);
                    clearListening();
                }, 200);
            };
            skuInput.addEventListener('keydown', onEnter);
            skuInput.addEventListener('blur', onBlur);
        });
    }

    if (skuInput) {
        setTimeout(function() { skuInput.focus(); }, 100);
        skuInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var next = form.querySelector('[name="name"]');
                if (next) next.focus();
            }
        });
    }
});
</script>
@endsection
