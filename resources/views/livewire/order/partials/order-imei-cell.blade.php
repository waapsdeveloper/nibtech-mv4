{{-- Inner content of the IMEI cell only (used for AJAX refresh). Expects: $order (with order_items, relations), $order_statuses, $admins --}}
@php
    $items = $order->order_items;
    $item = $items->first();
    $stock = $item ? $item->stock : null;
    $variation = $item ? $item->variation : null;
    $orderAboveDispatched = false;
@endphp
@if ($item && $item->status > 3)
    <strong class="text-danger">{{ $order_statuses[$order->status] ?? "Unknown Status" }}</strong>
@endif
@isset($stock->imei) {{ $stock->imei }}&nbsp; @endisset
@isset($stock->serial_number) {{ $stock->serial_number }}&nbsp; @endisset
@isset($order->processed_by) | {{ $admins[$order->processed_by][0] ?? '' }} | @endisset
@isset($stock->tester) ({{ $stock->tester }}) @endisset

@if ($item && $item->status == 2)
    @if (count($items) < 2 && $item->quantity < 2)
        <form id="dispatch_order_{{ $order->id }}" class="form-inline dispatch-form" method="post" action="{{ url('order') }}/dispatch/{{ $order->id }}"
            @if (!request('packing'))
             onsubmit="if(document.getElementById('tracking_number_order_{{ $order->id }}') && document.getElementById('tracking_number_order_{{ $order->id }}').value == 'J{{ $order->tracking_number }}') {return true;}else{event.stopPropagation(); event.preventDefault(); alert('Wrong Tracking');}"
            @endif
             >
            @csrf
            <input type="hidden" name="sort" value="{{ request('sort') }}">
            <input type="hidden" name="packing" value="{{ request('packing') }}">
            <input type="hidden" name="no_invoice" value="{{ request('no_invoice') }}">
            <div class="input-group">
                @if (!request('packing'))
                    <input type="text" name="tester[]" placeholder="Tester" list="tester_list" class="form-control form-control-sm" style="max-width: 55px" maxlength="3" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'tester'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'tester', true); }">
                @endif
                <input type="text" name="imei[]" placeholder="IMEI / Serial Number" class="form-control form-control-sm" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'imei'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'imei', true); }">
                <input type="hidden" name="sku[]" value="{{ $variation->sku ?? 'Variation Issue' }}">
                <div class="input-group-append">
                    <input type="submit" name="imei_send" value=">" class="form-control form-control-sm" form="dispatch_order_{{ $order->id }}">
                </div>
            </div>
            @if (!request('packing'))
            <div class="w-100">
                <input type="text" name="tracking_number" id="tracking_number_order_{{ $order->id }}" placeholder="Tracking Number" class="form-control form-control-sm w-100" required>
            </div>
            @endif
        </form>
    @elseif (count($items) < 2 && $item->quantity >= 2)
        <form id="dispatch_order_{{ $order->id }}" class="form-inline dispatch-form" method="post" action="{{ url('order') }}/dispatch/{{ $order->id }}"
            @if (!request('packing'))
             onsubmit="if(document.getElementById('tracking_number_order_{{ $order->id }}').value == 'J{{ $order->tracking_number }}') {return true;}else{event.stopPropagation(); event.preventDefault();}"
            @endif
             >
            @csrf
            <input type="hidden" name="sort" value="{{ request('sort') }}">
            <input type="hidden" name="packing" value="{{ request('packing') }}">
            <input type="hidden" name="no_invoice" value="{{ request('no_invoice') }}">
            @for ($in = 1; $in <= $item->quantity; $in++)
                <div class="input-group">
                    @if (!request('packing'))
                        <input type="text" name="tester[]" placeholder="Tester" list="tester_list" class="form-control form-control-sm" style="max-width: 55px" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'tester'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'tester', true); }">
                    @endif
                    <input type="text" name="imei[]" placeholder="IMEI / Serial Number" class="form-control form-control-sm" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'imei'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'imei', true); }" required>
                </div>
                <input type="hidden" name="sku[]" value="{{ $variation->sku }}">
            @endfor
            @if (!request('packing'))
            <div class="w-100">
                <input type="text" name="tracking_number" id="tracking_number_order_{{ $order->id }}" placeholder="Tracking Number" class="form-control form-control-sm w-100" required>
            </div>
            @endif
            <div class="w-100">
                <input type="submit" name="imei_send" value="Submit IMEIs" class="form-control form-control-sm w-100" form="dispatch_order_{{ $order->id }}">
            </div>
        </form>
    @elseif (count($items) >= 2)
        <form id="dispatch_order_{{ $order->id }}" class="form-inline dispatch-form" method="post" action="{{ url('order') }}/dispatch/{{ $order->id }}"
            @if (!request('packing'))
             onsubmit="if(document.getElementById('tracking_number_order_{{ $order->id }}').value == 'J{{ $order->tracking_number }}') {return true;}else{event.stopPropagation(); event.preventDefault(); alert('Wrong Tracking');}"
            @endif
             >
            @csrf
            <input type="hidden" name="sort" value="{{ request('sort') }}">
            <input type="hidden" name="packing" value="{{ request('packing') }}">
            <input type="hidden" name="no_invoice" value="{{ request('no_invoice') }}">
            @foreach ($items as $itm)
                @for ($in = 1; $in <= $itm->quantity; $in++)
                    <div class="input-group">
                        @if (!request('packing'))
                            <input type="text" name="tester[]" list="tester_list" placeholder="Tester" class="form-control form-control-sm" style="max-width: 55px" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'tester'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'tester', true); }">
                        @endif
                        <input type="text" name="imei[]" placeholder="IMEI / Serial Number" class="form-control form-control-sm" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'imei'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'imei', true); }" required title="for SKU:{{ $itm->variation->sku ?? '' }}">
                    </div>
                    <input type="hidden" name="sku[]" value="{{ $itm->variation->sku ?? '' }}">
                @endfor
            @endforeach
            @if (!request('packing'))
            <div class="w-100">
                <input type="text" name="tracking_number" id="tracking_number_order_{{ $order->id }}" placeholder="Tracking Number" class="form-control form-control-sm w-100" required>
            </div>
            @endif
            <div class="w-100">
                <input type="submit" name="imei_send" value="Submit IMEIs" class="form-control form-control-sm w-100" form="dispatch_order_{{ $order->id }}">
            </div>
        </form>
    @endif
@endif
