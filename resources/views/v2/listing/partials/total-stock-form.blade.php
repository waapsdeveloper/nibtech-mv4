@php
    // Expected variables: $variationId, $totalStock, $process_id (optional)
@endphp

<div class="d-flex align-items-center gap-2">
    <form class="form-inline d-inline-flex gap-1 align-items-center" method="POST" id="add_qty_total_{{ $variationId }}" action="{{url('listing/add_quantity')}}/{{ $variationId }}">
        @csrf
        @if(isset($process_id) && $process_id)
            <input type="hidden" name="process_id" value="{{ $process_id }}">
        @endif
        <div class="form-floating">
            <input type="text" class="form-control" id="total_stock_{{ $variationId }}" value="{{ $totalStock }}" style="width:140px;" readonly disabled>
            <label for="">Total Stock</label>
        </div>
        <div class="form-floating" style="width: 60px;">
            <input type="number" class="form-control form-control-sm" name="stock" id="add_total_{{ $variationId }}" value="" style="width:60px; height: 31px;">
            <label for="" class="small">Add</label>
        </div>
        <button id="send_total_{{ $variationId }}" class="btn btn-sm btn-light d-none" style="height: 31px; line-height: 1;">Push</button>
        <span class="text-success small" id="success_total_{{ $variationId }}"></span>
    </form>
    <!-- Dropdown chevron for marketplace and stocks -->
    <button class="btn btn-sm btn-link p-0" type="button" data-bs-toggle="collapse" data-bs-target="#marketplace_stocks_dropdown_{{ $variationId }}" aria-expanded="false" aria-controls="marketplace_stocks_dropdown_{{ $variationId }}" style="min-width: 24px;">
        <i class="fas fa-chevron-down"></i>
    </button>
</div>

