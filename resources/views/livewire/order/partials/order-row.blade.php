@php
    $items = $order->order_items;
    $items_count = $items->count();
    $customer = $order->customer;
    $replacement_items = [];
    $rowItemIndex = 0;
    $testerIndex = $rowCounter['tester_start'] ? $rowCounter['tester_start'] - 1 : null;
    $imeiIndex = $rowCounter['imei_start'] ? $rowCounter['imei_start'] - 1 : null;

    $getNextTesterId = function () use (&$testerIndex) {
        if ($testerIndex === null) {
            return null;
        }

        $testerIndex++;

        return 'tester' . $testerIndex;
    };

    $getNextImeiId = function () use (&$imeiIndex) {
        if ($imeiIndex === null) {
            return null;
        }

        $imeiIndex++;

        return 'imei' . $imeiIndex;
    };

    static $globalImeiTracker;
    if (! isset($globalImeiTracker)) {
        $globalImeiTracker = [];
    }
@endphp

@foreach ($items as $itemIndex => $item)
    @php
        $stock = $item->stock;
        $variation = $item->variation;
        $hide = false;

        if ($stock !== null && (request('missing_refund') || request('missing') || request('items'))) {
            $imeiKey = $stock->imei . $stock->serial_number;
            if ($imeiKey !== '') {
                if (in_array($imeiKey, $globalImeiTracker, true)) {
                    echo "Duplicate IMEI: " . $imeiKey;
                } else {
                    $globalImeiTracker[] = $imeiKey;
                }
            }
        }

        if (request('missing') == 'reimburse' && $item->replacement) {
            $replacement = $item->replacement;
            $itm = $replacement;
            while ($replacement != null) {
                $itm = $replacement;
                $replacement = $replacement->replacement;
            }
            if ($itm != null && $itm->stock->status == 2) {
                $hide = true;
                continue;
            } elseif ($itm != null && $itm->stock->status != 2) {
                echo "
                    <tr>
                        <td>
                Reimburse not in stock: " . $itm->stock->imei . $itm->stock->serial_number . " - " . $itm->stock->status . "
                        </td>
                    </tr>";
            }

            $exchange = $order->exchange_items;
            if ($exchange->count() > 0) {
                foreach ($exchange as $ex) {
                    $itm = $ex;
                    if ($itm != null && $itm->stock->status == 2) {
                        $hide = true;
                        continue;
                    }
                }
            }
            if ($hide) {
                continue;
            }
        }
        if (request('missing') == 'reimburse' && $stock != null) {
            $stock->availability();
        }

        $copyPayload = null;
        if ($order->status == 3 && $item->quantity > 1 && $item->stock_id !== null) {
            $copyPayload = "Hi, here are the IMEIs/Serial numbers for this order. \n";
            foreach ($items as $im) {
                if ($im->stock_id === null) {
                    continue;
                }
                $copyPayload .= $im->stock->imei . $im->stock->serial_number . "\n";
            }
            $copyPayload .= "Regards \n" . session('fname');
        }
        $rowTesterId = $getNextTesterId();
        $rowImeiId = $getNextImeiId();
    @endphp
    <tr wire:key="order-{{ $order->id }}-item-{{ $item->id ?? ($order->id . '-' . $itemIndex) }}" @if ($customer->orders->count() > 1) class="bg-light" @endif>
        @if ($itemIndex == 0)
            <td rowspan="{{ $items_count }}"><input type="checkbox" name="ids[]" value="{{ $order->id }}" form="pdf"></td>
            <td rowspan="{{ $items_count }}">{{ $rowNumber }}</td>
            <td rowspan="{{ $items_count }}">
                {{ $order->reference_id }}<br>
                {{ $customer->company }}<br>
                {{ $customer->first_name . ' ' . $customer->last_name }}

            </td>
        @endif
        <td>
            @if ($variation ?? false)
                <strong>{{ $variation->sku }}</strong> - {{ $variation->product->model ?? 'Model not defined' }} - {{ (isset($variation->storage) ? $storages[$variation->storage] . ' - ' : null) . (isset($variation->color) ? $colors[$variation->color] . ' - ' : null) }} <strong><u>{{ $grades[$variation->grade] ?? 'Issue wih Grade' }}</u></strong>
            @endif
            @if ($order->delivery_note_url == null || $order->label_url == null)
                <a class="" href="{{ url('order') }}/label/{{ $order->reference_id }}">
                @if ($order->delivery_note_url == null)
                    <strong class="text-danger">Missing Delivery Note</strong>
                @endif
                @if ($order->label_url == null)
                    <strong class="text-danger">Missing Label</strong>
                @endif
                </a>
            @endif
            @if ($item->care_id != null)
                <a class="" href="https://backmarket.fr/bo-seller/customer-care/help-requests/{{ $item->care_id }}" target="_blank"><strong class="text-danger">Conversation</strong></a>
            @endif
            <br>
            {{ $order->reference }}
        </td>
        <td>
            {{ $item->quantity }}
            @if (request('missing') == 'charge')
                {{ $order->payment_method->name }}
            @endif
        </td>
        @if (session('user')->hasPermission('view_profit') && $itemIndex == 0)
            <td rowspan="{{ $items_count }}">
                @if ($order->charges != null)
                    @if (in_array($order->status, [3, 6]))
                        {{ $currencies[$order->currency].amount_formatter($order->price, 2) . ' - ' . $currencies[$order->currency].amount_formatter($order->charges, 2) }}
                    @elseif ($order->status == 5)
                        - {{ $currencies[$order->currency].amount_formatter($order->charges, 2) }}
                    @endif
                @else
                    <strong class="text-info">Awaiting Charge</strong>
                @endif
            </td>
        @endif
        @if ($order->status == 3)
            <td
                style="width:240px"
                class="text-success text-uppercase{{ $copyPayload ? ' copy-imei-trigger' : '' }}"
                id="copy_imei_{{ $order->id }}"
                @if ($copyPayload)
                    data-copy-text="{{ e($copyPayload) }}"
                @endif
            >
                @isset($stock->imei) {{ $stock->imei }}&nbsp; @endisset
                @isset($stock->serial_number) {{ $stock->serial_number }}&nbsp; @endisset
                @isset($order->processed_by) | {{ $admins[$order->processed_by][0] }} | @endisset
                @isset($stock->tester) ({{ $stock->tester }}) @endisset

            </td>
        @endif
        @if ($itemIndex == 0 && $order->status != 3)
            <td style="width:240px" rowspan="{{ $items_count }}">
                @if ($item->status > 3)
                    <strong class="text-danger">{{ $order_statuses[$order->status] }}</strong>
                @endif
                @isset($stock->imei) {{ $stock->imei }}&nbsp; @endisset
                @isset($stock->serial_number) {{ $stock->serial_number }}&nbsp; @endisset

                @isset($order->processed_by) | {{ $admins[$order->processed_by][0] }} | @endisset
                @isset($stock->tester) ({{ $stock->tester }}) @endisset

                @if (request('invoice') && isset($stock) && $item->status == 2 && ! session()->has('refresh'))
                    @php
                        session()->put('refresh', true);
                    @endphp
                    <span class="d-none orders-refresh-trigger" data-refresh-url="{{ url('order') }}/refresh/{{ $order->reference_id }}"></span>
                @endif

                @if ($item->status == 2)
                    @if (count($items) < 2 && $item->quantity < 2)
                        @php
                            $currentTesterId = request('packing') ? null : ($rowTesterId ?? $getNextTesterId());
                            $currentImeiId = $rowImeiId ?? $getNextImeiId();
                            $dispatchFormId = 'dispatch_' . $rowNumber . '_' . $rowItemIndex;
                        @endphp
                        <form id="{{ $dispatchFormId }}" class="form-inline" method="post" action="{{ url('order') }}/dispatch/{{ $order->id }}"
                            @if (!request('packing'))
                                onsubmit="if($('#tracking_number_{{ $rowNumber }}_{{ $rowItemIndex }}').val() == 'J{{ $order->tracking_number }}') {return true;}else{event.stopPropagation(); event.preventDefault(); alert('Wrong Tracking');}"
                            @endif
                        >
                            @csrf
                            <input type="hidden" name="sort" value="{{ request('sort') }}">
                            <input type="hidden" name="packing" value="{{ request('packing') }}">
                            <div class="input-group">
                                @if (!request('packing'))
                                    <input type="text" id="{{ $currentTesterId }}" name="tester[]" placeholder="Tester" list="tester_list" class="form-control form-control-sm" style="max-width: 55px" maxlength="3" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'tester'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'tester', true); }">
                                @endif
                                <input type="text" id="{{ $currentImeiId }}" name="imei[]" placeholder="IMEI / Serial Number" class="form-control form-control-sm" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'imei'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'imei', true); }">

                                <input type="hidden" name="sku[]" value="{{ $variation->sku ?? 'Variation Issue' }}">

                                <div class="input-group-append">
                                    <input type="submit" name="imei_send" value=">" class="form-control form-control-sm" form="{{ $dispatchFormId }}">
                                </div>

                            </div>
                            @if (!request('packing'))
                                <div class="w-100">
                                    <input type="text" name="tracking_number" id="tracking_number_{{ $rowNumber }}_{{ $rowItemIndex }}" placeholder="Tracking Number" class="form-control form-control-sm w-100" required>
                                </div>
                            @endif
                        </form>
                    @elseif (count($items) < 2 && $item->quantity >= 2)
                        @php
                            $dispatchFormId = 'dispatch_' . $rowNumber . '_' . $rowItemIndex;
                        @endphp
                        <form id="{{ $dispatchFormId }}" class="form-inline" method="post" action="{{ url('order') }}/dispatch/{{ $order->id }}"
                            @if (!request('packing'))
                                onsubmit="if($('#tracking_number_{{ $rowNumber }}_{{ $rowItemIndex }}').val() == 'J{{ $order->tracking_number }}') {return true;}else{event.stopPropagation(); event.preventDefault();}"
                            @endif
                        >
                            @csrf
                            <input type="hidden" name="sort" value="{{ request('sort') }}">
                            <input type="hidden" name="packing" value="{{ request('packing') }}">

                            @for ($in = 1; $in <= $item->quantity; $in++)
                                @php
                                    $currentTesterId = request('packing') ? null : ($getNextTesterId)();
                                    $currentImeiId = $getNextImeiId();
                                @endphp
                                <div class="input-group">
                                    @if (!request('packing'))
                                        <input type="text" id="{{ $currentTesterId }}" name="tester[]" placeholder="Tester" list="tester_list" class="form-control form-control-sm" style="max-width: 55px" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'tester'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'tester', true); }">
                                    @endif
                                    <input type="text" id="{{ $currentImeiId }}" name="imei[]" placeholder="IMEI / Serial Number" class="form-control form-control-sm" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'imei'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'imei', true); }" required>
                                </div>
                                <input type="hidden" name="sku[]" value="{{ $variation->sku }}">
                            @endfor
                            @if (!request('packing'))
                                <div class="w-100">
                                    <input type="text" name="tracking_number" id="tracking_number_{{ $rowNumber }}_{{ $rowItemIndex }}" placeholder="Tracking Number" class="form-control form-control-sm w-100" required>
                                </div>
                            @endif
                            <div class="w-100">
                                <input type="submit" name="imei_send" value="Submit IMEIs" class="form-control form-control-sm w-100" form="{{ $dispatchFormId }}">
                            </div>
                        </form>
                    @elseif (count($items) >= 2)
                        @php
                            $dispatchFormId = 'dispatch_' . $rowNumber . '_' . $rowItemIndex;
                        @endphp
                        <form id="{{ $dispatchFormId }}" class="form-inline" method="post" action="{{ url('order') }}/dispatch/{{ $order->id }}"
                            @if (!request('packing'))
                                onsubmit="if($('#tracking_number_{{ $rowNumber }}_{{ $rowItemIndex }}').val() == 'J{{ $order->tracking_number }}') {return true;}else{event.stopPropagation(); event.preventDefault(); alert('Wrong Tracking');}"
                            @endif
                        >
                            @csrf
                            <input type="hidden" name="sort" value="{{ request('sort') }}">
                            <input type="hidden" name="packing" value="{{ request('packing') }}">
                            @foreach ($items as $itm)
                                @for ($in = 1; $in <= $itm->quantity; $in++)
                                    @php
                                        $currentTesterId = request('packing') ? null : ($getNextTesterId)();
                                        $currentImeiId = $getNextImeiId();
                                    @endphp
                                    <div class="input-group">
                                        @if (!request('packing'))
                                            <input type="text" id="{{ $currentTesterId }}" name="tester[]" list="tester_list" placeholder="Tester" class="form-control form-control-sm" style="max-width: 55px" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'tester'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'tester', true); }">
                                        @endif

                                        <input type="text" id="{{ $currentImeiId }}" name="imei[]" placeholder="IMEI / Serial Number" class="form-control form-control-sm" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'imei'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'imei', true); }" required title="for SKU:{{ $itm->variation->sku }}">
                                    </div>
                                    <input type="hidden" name="sku[]" value="{{ $itm->variation->sku }}">
                                @endfor
                            @endforeach
                            @if (!request('packing'))
                                <div class="w-100">
                                    <input type="text" name="tracking_number" id="tracking_number_{{ $rowNumber }}_{{ $rowItemIndex }}" placeholder="Tracking Number" class="form-control form-control-sm w-100" required>
                                </div>
                            @endif
                            <div class="w-100">
                                <input type="submit" name="imei_send" value="Submit IMEIs" class="form-control form-control-sm w-100" form="{{ $dispatchFormId }}">
                            </div>
                        </form>
                    @endif
                @endif
            </td>
        @endif
        <td style="width:220px">{{ $order->created_at }} <br> {{ $order->processed_at }}<br>
            @if ($order->tracking_number != null)
                <a href="https://www.dhl.com/us-en/home/tracking/tracking-express.html?submit=1&tracking-id={{ $order->tracking_number }}" target="_blank">{{ $order->tracking_number }}</a>
            @endif
        </td>
        <td>
            <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical  tx-18"></i></a>
            <div class="dropdown-menu">
                <a class="dropdown-item" href="{{ url('order') }}/refresh/{{ $order->reference_id }}">Refresh</a>
                @if (session('user')->hasPermission('change_order_tracking'))
                    <a class="dropdown-item" id="tracking_{{ $order->id }}" href="javascript:void(0);" data-bs-target="#tracking_model" data-bs-toggle="modal" data-bs-reference="{{ $order->reference_id }}" data-bs-order="{{ $order->id }}"> Change Tracking </a>
                @endif
                @if (session('user')->hasPermission('correction'))
                    <a class="dropdown-item" id="correction_{{ $item->id }}" href="javascript:void(0);" data-bs-target="#correction_model" data-bs-toggle="modal" data-bs-reference="{{ $order->reference_id }}" data-bs-item="{{ $item->id }}"> Correction </a>
                @endif
                @if (session('user')->hasPermission('correction_override'))
                    <a class="dropdown-item" id="correction_{{ $item->id }}" href="javascript:void(0);" data-bs-target="#correction_model" data-bs-toggle="modal" data-bs-reference="{{ $order->reference_id }}" data-bs-item="{{ $item->id }}" data-bs-override="true"> Correction (Override) </a>
                @endif
                @if (! $item->replacement)
                    <a class="dropdown-item" id="replacement_{{ $item->id }}" href="javascript:void(0);" data-bs-target="#replacement_model" data-bs-toggle="modal" data-bs-reference="{{ $order->reference_id }}" data-bs-item="{{ $item->id }}" data-bs-return="@if($item->check_return) 1 @endif"> Replacement </a>
                @endif
                @if ($order->status >= 3)
                    <a class="dropdown-item" href="{{ url('order') }}/recheck/{{ $order->reference_id }}/true" target="_blank">Invoice</a>
                @endif
                @if ($order->status == 6)
                    <a class="dropdown-item" href="{{ url('order') }}/export_refund_invoice/{{ $order->id }}" target="_blank">Refund Invoice</a>
                @endif
                @if (session('user')->hasPermission('view_api_data'))
                    <a class="dropdown-item" href="{{ url('order') }}/recheck/{{ $order->reference_id }}/false/false/null/true/true" target="_blank">Data</a>
                    <a class="dropdown-item" href="{{ url('order') }}/label/{{ $order->reference_id }}/true/true" target="_blank">Label Data</a>
                @endif
                <a class="dropdown-item" href="https://backmarket.fr/bo-seller/orders/all?orderId={{ $order->reference_id }}#order-details={{ $order->reference_id }}" target="_blank">View in Backmarket</a>
                <a class="dropdown-item" href="#" onclick="window.open('{{ url('order') }}/export_invoice_new/{{ $order->id }}','_blank','print_popup');">Invoice 2</a>
                @if (request('missing') == 'scan' && session('user')->hasPermission('mark_scanned'))
                    <a class="dropdown-item" href="{{ url('order') }}/mark_scanned/{{ $order->id }}">Mark Scanned</a>
                    <a class="dropdown-item" href="{{ url('order') }}/mark_scanned/{{ $order->id }}?force=1">Mark Scanned (Forced)</a>
                @endif
            </div>
        </td>
    </tr>
    @php
        $rowItemIndex++;
    @endphp
@endforeach

@if (!isset($hide) || ! $hide)
    @foreach ($items as $itemIndex => $item)
        @if ($item->replacement)
            @php
                $replacement = $item->replacement;
            @endphp
            @while ($replacement != null)
                @php
                    $itm = $replacement;
                    $replacement = $replacement->replacement;
                    if (in_array($itm->id, $replacement_items)) {
                        continue;
                    } else {
                        $replacement_items[] = $itm->id;
                    }
                @endphp
                <tr class="bg-secondary text-white">
                    <td colspan="2">{{ $customer->first_name . ' ' . $customer->last_name . ' ' . $customer->phone }}</td>
                    <td>Exchanged With</td>
                    <td>
                        @if ($itm->variation ?? false)
                            <strong>{{ $itm->variation->sku }}</strong>{{ ' - ' . $itm->variation->product->model . ' - ' . (isset($itm->variation->storage) ? $storages[$itm->variation->storage] . ' - ' : null) . (isset($itm->variation->color) ? $colors[$itm->variation->color] . ' - ' : null) }} <strong><u>{{ $grades[$itm->variation->grade] }}</u></strong>
                        @endif
                    </td>
                    <td>{{ $itm->quantity }}</td>
                    <td>
                        {{ $order->order_status->name }}
                        @isset($itm->stock->imei) {{ $itm->stock->imei }}&nbsp; @endisset
                        @isset($itm->stock->serial_number) {{ $itm->stock->serial_number }}&nbsp; @endisset
                    </td>
                    <td title="{{ $itm->id }}">{{ $itm->created_at }}</td>
                    <td>
                        <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical text-white tx-18"></i></a>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="{{ url('order/delete_replacement_item') . '/' . $itm->id }}" onclick="return confirm('Are you sure?');"><i class="fe fe-trash-2 me-2"></i>Delete</a>
                            <a class="dropdown-item" id="replacement_{{ $itm->id }}" href="javascript:void(0);" data-bs-target="#replacement_model" data-bs-toggle="modal" data-bs-reference="{{ $itm->order->reference_id }}" data-bs-item="{{ $itm->id }}" data-bs-return="@if($itm->check_return) 1 @endif"> Replacement </a>
                            <a class="dropdown-item" href="https://backmarket.fr/bo-seller/orders/all?orderId={{ $order->reference_id }}#order-details={{ $order->reference_id }}" target="_blank"><i class="fe fe-caret me-2"></i>View in Backmarket</a>
                        </div>
                    </td>
                </tr>
            @endwhile
        @elseif ($order->exchange_items->count() > 0)
            @foreach ($order->exchange_items as $ind => $itm)
                @php
                    if (in_array($itm->id, $replacement_items)) {
                        continue;
                    } else {
                        $replacement_items[] = $itm->id;
                    }
                @endphp
                <tr class="bg-secondary text-white">
                    <td colspan="2">{{ $customer->first_name . ' ' . $customer->last_name . ' ' . $customer->phone }}</td>
                    <td>Exchanged with</td>
                    <td>
                        @if ($itm->variation ?? false)
                            <strong>{{ $itm->variation->sku }}</strong>{{ ' - ' . $itm->variation->product->model . ' - ' . (isset($itm->variation->storage) ? $storages[$itm->variation->storage] . ' - ' : null) . (isset($itm->variation->color) ? $colors[$itm->variation->color] . ' - ' : null) }} <strong><u>{{ $grades[$itm->variation->grade] }}</u></strong>
                        @endif
                    </td>
                    <td>{{ $itm->quantity }}</td>
                    <td>
                        {{ $order->order_status->name }}
                        @isset($itm->stock->imei) {{ $itm->stock->imei }}&nbsp; @endisset
                        @isset($itm->stock->serial_number) {{ $itm->stock->serial_number }}&nbsp; @endisset
                    </td>
                    <td title="{{ $itm->id }}">{{ $itm->created_at }}</td>
                    <td>
                        <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical text-white tx-18"></i></a>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="{{ url('order/delete_replacement_item') . '/' . $itm->id }}" onclick="return confirm('Are you sure?');"><i class="fe fe-trash-2 me-2"></i>Delete</a>
                            <a class="dropdown-item" id="replacement_{{ $itm->id }}" href="javascript:void(0);" data-bs-target="#replacement_model" data-bs-toggle="modal" data-bs-reference="{{ $itm->order->reference_id }}" data-bs-item="{{ $itm->id }}" data-bs-return="@if($itm->check_return) 1 @endif"> Replacement </a>
                            <a class="dropdown-item" href="https://backmarket.fr/bo-seller/orders/all?orderId={{ $order->reference_id }}#order-details={{ $order->reference_id }}" target="_blank"><i class="fe fe-caret me-2"></i>View in Backmarket</a>
                        </div>
                    </td>
                </tr>
            @endforeach
        @endif
        @if (isset($itm) && $itm->replacement)
            @php
                if ($item->replacement) {
                    $replacement = $item->replacement;
                } else {
                    $replacement = $itm->replacement;
                }
            @endphp
            @while ($replacement != null)
                @php
                    $itm = $replacement;
                    $replacement = $replacement->replacement;

                    if (in_array($itm->id, $replacement_items)) {
                        continue;
                    } else {
                        $replacement_items[] = $itm->id;
                    }
                @endphp
                <tr class="bg-secondary text-white">
                    <td colspan="2">{{ $customer->first_name . ' ' . $customer->last_name . ' ' . $customer->phone }}</td>
                    <td>Exchanged With</td>
                    <td>
                        @if ($itm->variation ?? false)
                            <strong>{{ $itm->variation->sku }}</strong>{{ ' - ' . $itm->variation->product->model . ' - ' . (isset($itm->variation->storage) ? $storages[$itm->variation->storage] . ' - ' : null) . (isset($itm->variation->color) ? $colors[$itm->variation->color] . ' - ' : null) }} <strong><u>{{ $grades[$itm->variation->grade] }}</u></strong>
                        @endif
                    </td>
                    <td>{{ $itm->quantity }}</td>
                    <td>
                        {{ $order->order_status->name }}
                        @isset($itm->stock->imei) {{ $itm->stock->imei }}&nbsp; @endisset
                        @isset($itm->stock->serial_number) {{ $itm->stock->serial_number }}&nbsp; @endisset
                    </td>
                    <td title="{{ $itm->id }}">{{ $itm->created_at }}</td>
                    <td>
                        <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical text-white tx-18"></i></a>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="{{ url('order/delete_replacement_item') . '/' . $itm->id }}" onclick="return confirm('Are you sure?');"><i class="fe fe-trash-2 me-2"></i>Delete</a>
                            <a class="dropdown-item" id="replacement_{{ $itm->id }}" href="javascript:void(0);" data-bs-target="#replacement_model" data-bs-toggle="modal" data-bs-reference="{{ $itm->order->reference_id }}" data-bs-item="{{ $itm->id }}" data-bs-return="@if($itm->check_return) 1 @endif"> Replacement </a>
                            <a class="dropdown-item" href="https://backmarket.fr/bo-seller/orders/all?orderId={{ $order->reference_id }}#order-details={{ $order->reference_id }}" target="_blank"><i class="fe fe-caret me-2"></i>View in Backmarket</a>
                        </div>
                    </td>
                </tr>
            @endwhile
        @endif
    @endforeach
    @if ($customer->orders->count() > 1)
        @php
            $def = 0;
        @endphp
        @foreach ($customer->orders as $ins => $ord)
            @if ($ord->id != $order->id)
                @foreach ($ord->order_items as $ind => $itm)
                    <tr class="bg-secondary text-white">
                        @if (! $def)
                            @php
                                $def = 1;
                            @endphp
                            <td rowspan="{{ count($customer->orders) - 1 }}" colspan="2">{{ $ord->customer->first_name . ' ' . $ord->customer->last_name . ' ' . $ord->customer->phone }}</td>
                        @endif
                        <td>{{ $ord->reference_id }}</td>
                        <td>
                            @if ($itm->variation ?? false)
                                <strong>{{ $itm->variation->sku }}</strong>{{ ' - ' . (isset($itm->variation->product) ? $itm->variation->product->model : 'Model not defined') . ' - ' . (isset($itm->variation->storage) ? $storages[$itm->variation->storage] . ' - ' : null) . (isset($itm->variation->color) ? $colors[$itm->variation->color] . ' - ' : null) }} <strong><u>{{ $grades[$itm->variation->grade] }}</u></strong>
                            @endif

                            @if ($itm->care_id != null)
                                <a class="" href="https://backmarket.fr/bo-seller/customer-care/help-requests/{{ $itm->care_id }}" target="_blank"><strong class="text-white">Conversation</strong></a>
                            @endif
                        </td>
                        <td>{{ $itm->quantity }}</td>
                        @if (session('user')->hasPermission('view_profit'))
                            <td>
                                @if ($ord->charges != null)
                                    @if (in_array($ord->status, [3, 6]))
                                        {{ $currencies[$ord->currency].amount_formatter($ord->price, 2) . ' - ' . $currencies[$ord->currency].amount_formatter($ord->charges, 2) }}
                                    @elseif ($ord->status == 5)
                                        -{{ $currencies[$ord->currency].amount_formatter($ord->charges, 2) }}
                                    @endif
                                @else
                                    <strong class="text-info">Awaiting Charge</strong>
                                @endif
                            </td>
                        @endif
                        <td>
                            {{ $ord->order_status->name }}
                            @isset($itm->stock->imei) {{ $itm->stock->imei }}&nbsp; @endisset
                            @isset($itm->stock->serial_number) {{ $itm->stock->serial_number }}&nbsp; @endisset
                        </td>
                        <td>{{ $ord->created_at }}</td>
                        <td>
                            <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical text-white tx-18"></i></a>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="https://backmarket.fr/bo-seller/orders/all?orderId={{ $ord->reference_id }}#order-details={{ $ord->reference_id }}" target="_blank"><i class="fe fe-caret me-2"></i>View in Backmarket</a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            @endif
        @endforeach
    @endif
    @if (request('transaction') == 1 || request('missing'))
        @php
            echo $order->merge_transaction_charge();
        @endphp
        @foreach ($order->transactions as $transaction)
            <tr class="bg-info text-white">
                <td colspan="2">{{ $transaction->transaction_type->name }}</td>
                <td colspan="3">{{ $transaction->description }}</td>
                <td>{{ $transaction->currency_id->sign . amount_formatter($transaction->amount) }}</td>
                <td></td>
                <td>{{ $transaction->date }}</td>
                <td></td>
            </tr>
        @endforeach
    @endif
@endif
