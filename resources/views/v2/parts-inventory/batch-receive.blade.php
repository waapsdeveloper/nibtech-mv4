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
                    <h5 class="card-title mb-4">Receive a new batch</h5>
                    <p class="text-muted small">Batch ref is system-generated (e.g. BR-{{ date('Ymd') }}-0001). <strong>Scan barcode</strong> (manufacturer SKU) into SKU field, or use the system-generated SKU. Existing SKU = use that part; new SKU = create part (name required).</p>
                    <form method="POST" action="{{ route('v2.parts-inventory.batch-receive.store') }}" id="batch-receive-form">
                        @csrf
                        <div class="row g-3">
                            {{-- Left column: main form fields --}}
                            <div class="col-lg-6">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Name <span class="text-danger">*</span> <small class="text-muted">(when new part)</small></label>
                                        <input type="text" name="name" class="form-control" value="{{ old('name', $prefillName ?? '') }}" placeholder="Required when SKU is new" maxlength="255">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Quantity received <span class="text-danger">*</span></label>
                                        <input type="number" name="quantity_received" class="form-control" value="{{ old('quantity_received') }}" required min="1">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Unit cost <span class="text-danger">*</span></label>
                                        <input type="number" name="unit_cost" class="form-control" value="{{ old('unit_cost') }}" required min="0" step="0.01">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Received date</label>
                                        <input type="date" name="received_at" class="form-control" value="{{ old('received_at', date('Y-m-d')) }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Purchase date</label>
                                        <input type="date" name="purchase_date" class="form-control" value="{{ old('purchase_date') }}">
                                        <small class="form-text text-muted">If blank, received date is used.</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Supplier</label>
                                        <input type="text" name="supplier" class="form-control" value="{{ old('supplier') }}" maxlength="255">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Notes</label>
                                        <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" maxlength="500">
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check mb-2">
                                            <input type="checkbox" class="form-check-input" name="create_purchase_order" id="create_purchase_order" value="1" {{ old('create_purchase_order', $createPoChecked ?? false) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="create_purchase_order">Create purchase order and open it after receive</label>
                                        </div>
                                        <small class="form-text text-muted">When checked, a purchase order (Parts Batch Receive) is created and linked to this batch; you are redirected to the purchase order detail. You can also create a purchase order later from the Batches list.</small>
                                        <button type="submit" class="btn btn-primary">Update</button>
                                    </div>
                                </div>
                            </div>
                            {{-- Right column: SKU + barcode --}}
                            <div class="col-lg-6">
                                <label class="form-label">SKU <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" name="sku" id="sku-input" class="form-control" value="{{ old('sku', $suggestedSku ?? '') }}" required placeholder="Scan barcode or enter / use suggested SKU" maxlength="255" autofocus>
                                    <button type="button" class="btn btn-outline-secondary" id="sku-scan-trigger" title="Click then scan barcode with gun" aria-label="Scan barcode">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="4" x2="4" y2="20"/><line x1="8" y1="4" x2="8" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/><line x1="16" y1="4" x2="16" y2="20"/><line x1="20" y1="4" x2="20" y2="20"/></svg>
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" id="sku-regenerate-trigger" title="Generate new system SKU (e.g. PRT-20250302-XXXX)" aria-label="Regenerate system SKU">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg> Generate SKU
                                    </button>
                                </div>
                                <small class="form-text text-muted">Click the barcode icon then scan, or use <strong>Generate SKU</strong> for a new system code (e.g. PRT-{{ date('Ymd') }}-XXXX).</small>
                                {{-- Runtime barcode (Code 128) --}}
                                <div class="mt-3 p-3 bg-light rounded" id="barcode-preview-wrap">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="small fw-semibold text-secondary">Barcode preview</span>
                                        <span class="badge bg-secondary">Code 128</span>
                                    </div>
                                    <div id="barcode-preview" class="min-height-60 d-flex align-items-center justify-content-center">
                                        <span class="text-muted small" id="barcode-placeholder">Enter or scan SKU to see barcode</span>
                                        <svg id="barcode-svg" class="d-none" style="max-width: 100%; height: auto;"></svg>
                                    </div>
                                    <small class="form-text text-muted">Standard: <strong>Code 128</strong> — alphanumeric, widely used for parts and logistics.</small>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<style>
#barcode-preview.min-height-60 { min-height: 60px; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var skuInput = document.getElementById('sku-input');
    var scanTrigger = document.getElementById('sku-scan-trigger');
    var form = document.getElementById('batch-receive-form');
    var defaultPlaceholder = skuInput ? skuInput.placeholder : '';
    var barcodeSvg = document.getElementById('barcode-svg');
    var barcodePlaceholder = document.getElementById('barcode-placeholder');

    function updateBarcode() {
        var sku = (skuInput && skuInput.value) ? String(skuInput.value).trim() : '';
        if (!barcodeSvg || !barcodePlaceholder) return;
        if (sku === '') {
            barcodeSvg.classList.add('d-none');
            barcodePlaceholder.classList.remove('d-none');
            barcodePlaceholder.textContent = 'Enter or scan SKU to see barcode';
            barcodeSvg.innerHTML = '';
            return;
        }
        try {
            JsBarcode(barcodeSvg, sku, {
                format: 'CODE128',
                width: 2,
                height: 50,
                displayValue: true,
                margin: 5,
                fontSize: 12
            });
            barcodeSvg.classList.remove('d-none');
            barcodePlaceholder.classList.add('d-none');
        } catch (e) {
            barcodeSvg.classList.add('d-none');
            barcodePlaceholder.classList.remove('d-none');
            barcodePlaceholder.textContent = 'Enter a valid SKU to see barcode';
        }
    }

    if (skuInput) {
        skuInput.addEventListener('input', updateBarcode);
        skuInput.addEventListener('change', updateBarcode);
        updateBarcode();
    }

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

    var regenerateTrigger = document.getElementById('sku-regenerate-trigger');
    if (regenerateTrigger && skuInput) {
        regenerateTrigger.addEventListener('click', function() {
            var btn = regenerateTrigger;
            var origText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
            fetch('{{ route("v2.parts-inventory.catalog.next-barcode") }}', {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.barcode) {
                    skuInput.value = data.barcode;
                    updateBarcode();
                    skuInput.focus();
                } else {
                    alert(data.error || 'Could not generate SKU.');
                }
            }).catch(function() {
                alert('Could not generate SKU.');
            }).finally(function() {
                btn.disabled = false;
                btn.innerHTML = origText;
            });
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
