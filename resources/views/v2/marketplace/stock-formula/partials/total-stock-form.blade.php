@php
    $variationId = $selectedVariation->id;
    $totalStock = $selectedVariation->listed_stock ?? 0;
@endphp

<form class="form-inline d-inline-flex gap-1 align-items-center" id="add_qty_total_formula_{{ $variationId }}" action="{{url('v2/listings/add_quantity')}}/{{ $variationId }}">
    @csrf
    <div class="form-floating">
        <input type="text" class="form-control" id="total_stock_stock_formula_{{ $variationId }}" value="{{ $totalStock }}" style="width:140px;" readonly disabled>
        <label for="" class="small">Total Stock</label>
    </div>
    <div class="form-floating" style="width: 60px;">
        <input type="number" class="form-control form-control-sm" name="stock" id="add_total_formula_{{ $variationId }}" value="" style="width:60px; height: 31px;">
        <label for="" class="small">Add</label>
    </div>
    <button id="send_total_formula_{{ $variationId }}" class="btn btn-sm btn-light d-none" style="height: 31px; line-height: 1;">Push</button>
    <span class="text-success small" id="success_total_stock_formula_{{ $variationId }}"></span>
</form>

