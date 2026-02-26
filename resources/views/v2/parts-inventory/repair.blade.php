@extends('layouts.app')

@section('content')
<div class="breadcrumb-header justify-content-between">
    <div class="left-content">
        <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Repair' }}</span>
    </div>
    <div class="justify-content-center mt-2">
        <ol class="breadcrumb">
            <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
            <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
            <li class="breadcrumb-item tx-15"><a href="{{ route('v2.parts-inventory.dashboard') }}">Parts Inventory</a></li>
            <li class="breadcrumb-item active" aria-current="page">Repair</li>
        </ol>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header pb-0">
                <h4 class="card-title mg-b-0">Line item – carry forward</h4>
            </div>
            <div class="card-body">
                @if (!$stock)
                    <p class="text-muted mb-0">No stock found for the given IMEI. <a href="{{ route('internal_repair') }}">Go to Internal Repair</a> or use the search there.</p>
                    @if (!empty($imei_param))
                        <p class="small mt-2 mb-0">Searched for: <code>{{ $imei_param }}</code></p>
                    @endif
                @else
                    <p class="text-muted small mb-4">Information for this stock item. Refined options will be added here.</p>
                    <table class="table table-bordered table-sm">
                        <tbody>
                            <tr>
                                <th style="width:180px">Variation</th>
                                <td>
                                    @if ($stock->variation ?? false)
                                        <strong>{{ $stock->variation->sku }}</strong>
                                        {{ ' - ' . ($stock->variation->product->model ?? '') }}
                                        @if(isset($stock->variation->storage_id)) {{ ' - ' . $stock->variation->storage_id->name }} @endif
                                        @if(isset($stock->variation->color_id)) {{ ' - ' . $stock->variation->color_id->name }} @endif
                                        @if(isset($stock->variation->grade_id)) <strong><u>{{ $stock->variation->grade_id->name }}</u></strong> @endif
                                    @else
                                        –
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>IMEI / Serial</th>
                                <td>{{ ($stock->imei ?? '') . ($stock->serial_number ?? '') ?: '–' }}</td>
                            </tr>
                            <tr>
                                <th>Vendor</th>
                                <td>
                                    @if ($stock->order_id != null && ($stock->order ?? false))
                                        {{ $stock->order->customer->first_name ?? '' }} {{ $stock->order->customer->last_name ?? '' }} {{ $stock->order->reference_id ?? '' }}
                                    @else
                                        Item not purchased yet
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Reason / Description</th>
                                <td>{{ $stock->latest_operation->description ?? '–' }}</td>
                            </tr>
                            <tr>
                                <th>Creation / Updated</th>
                                <td>{{ $stock->latest_operation->created_at ?? $stock->created_at ?? '–' }} {{ $stock->latest_operation->updated_at ?? $stock->updated_at ?? '' }}</td>
                            </tr>
                        </tbody>
                    </table>
                @endif
                @if ($stock)
                <hr class="my-4">
                <h5 class="mb-3">Use part from inventory</h5>
                <form id="repair-part-form" method="POST" action="{{ route('v2.parts-inventory.repair.submit') }}" class="row g-3">
                    @csrf
                    <input type="hidden" name="imei" value="{{ $imei_param ?? '' }}">
                    <div class="col-md-4">
                        <label for="reference_id" class="form-label">Reference ID</label>
                        <input type="text" name="reference_id" id="reference_id" class="form-control" value="{{ $next_reference ?? '' }}" readonly>
                    </div>
                    <div class="col-md-4">
                        <label for="repairer_id" class="form-label">Repairer</label>
                        <select name="repairer_id" id="repairer_id" class="form-select" required>
                            <option value="">— Select repairer —</option>
                            @foreach ($repairers ?? [] as $id => $company)
                                <option value="{{ $id }}">{{ $company }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="repair_part_id" class="form-label">Select a part from parts-inventory</label>
                        <select name="repair_part_id" id="repair_part_id" class="form-select" required>
                            <option value="">— Select part —</option>
                            @foreach ($parts ?? [] as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} @if($p->sku)({{ $p->sku }})@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="batch_id" class="form-label">Batch</label>
                        <select id="batch_id" class="form-select" disabled>
                            <option value="">— Select part first —</option>
                        </select>
                        <input type="hidden" name="batch_id" id="batch_id_hidden" value="">
                    </div>
                    <div class="col-md-4">
                        <label for="unit_cost" class="form-label">Unit cost</label>
                        <input type="number" name="unit_cost" id="unit_cost" class="form-control" step="0.01" min="0" placeholder="From batch" readonly>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Submit repair</button>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@if ($stock ?? false)
@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var partSelect = document.getElementById('repair_part_id');
    var batchSelect = document.getElementById('batch_id');
    var unitCostInput = document.getElementById('unit_cost');
    var batchesUrl = '{{ url("v2/parts-inventory/parts") }}';

    partSelect.addEventListener('change', function() {
        var partId = partSelect.value;
        batchSelect.disabled = true;
        batchSelect.innerHTML = '<option value="">— Loading —</option>';
        unitCostInput.value = '';
        document.getElementById('batch_id_hidden').value = '';

        if (!partId) {
            batchSelect.innerHTML = '<option value="">— Select part first —</option>';
            return;
        }

        fetch(batchesUrl + '/' + partId + '/batches-json')
            .then(function(r) { return r.json(); })
            .then(function(batches) {
                batchSelect.innerHTML = '<option value="">— Select batch —</option>';
                batches.forEach(function(b) {
                    var opt = document.createElement('option');
                    opt.value = b.id;
                    opt.textContent = b.batch_number + ' (remaining: ' + (b.quantity_remaining ?? 0) + ', cost: ' + (b.unit_cost != null ? parseFloat(b.unit_cost) : '–') + ')';
                    opt.dataset.unitCost = b.unit_cost != null ? b.unit_cost : '';
                    batchSelect.appendChild(opt);
                });
                batchSelect.disabled = false;
            })
            .catch(function() {
                batchSelect.innerHTML = '<option value="">— Error loading batches —</option>';
                batchSelect.disabled = false;
            });
    });

    batchSelect.addEventListener('change', function() {
        var opt = batchSelect.selectedOptions[0];
        var unitCost = opt && opt.dataset.unitCost !== undefined && opt.dataset.unitCost !== '' ? opt.dataset.unitCost : '';
        unitCostInput.value = unitCost;
        document.getElementById('batch_id_hidden').value = batchSelect.value || '';
    });
});
</script>
@endsection
@endif
