@php
    $items = $order->order_items;
    $items_count = $items->count();
    $customer = $order->customer;
    $customerName = $customer ? trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) : '';
    $careTickets = $items->filter(function ($item) {
        return ! empty($item->care_id);
    })->map(function ($item) {
        return [
            'id' => $item->care_id,
            'url' => conversation_url_for_order_item($item),
        ];
    })->filter(function ($ticket) {
        return ! empty($ticket['url']);
    })->unique('id')->values()->all();
    $shouldFlagTickets = count($careTickets) > 0;
    $replacement_items = [];
    $rowItemIndex = 0;
    $testerIndex = $rowCounter['tester_start'] ? $rowCounter['tester_start'] - 1 : null;
    $imeiIndex = $rowCounter['imei_start'] ? $rowCounter['imei_start'] - 1 : null;
    $anchor = isset($inputAnchor) && $inputAnchor !== null ? $inputAnchor : ('order-' . $order->id);
    $isRefurbed = (int) $order->marketplace_id === 4;
    $refurbedDefaults = $refurbedShippingDefaults ?? [];
    $refurbedAddressInput = request('refurbed_merchant_address_id');
    $refurbedAddressDefault = ($refurbedAddressInput !== null && $refurbedAddressInput !== '')
        ? $refurbedAddressInput
        : ($refurbedDefaults['default_merchant_address_id'] ?? '');
    $firstOrderItem = $items->first();
    $categoryModel = $firstOrderItem && $firstOrderItem->variation && $firstOrderItem->variation->product
        ? $firstOrderItem->variation->product->category_id
        : null;
    $refurbedCategoryWeight = null;
    if ($isRefurbed && $categoryModel) {
        $weightFields = ['default_shipping_weight', 'default_weight', 'shipping_weight', 'weight'];
        foreach ($weightFields as $field) {
            $value = $categoryModel->{$field} ?? null;
            if ($value !== null && $value !== '' && is_numeric($value) && (float) $value > 0) {
                $refurbedCategoryWeight = (float) $value;
                break;
            }
        }
    }
    $refurbedWeightInput = request('refurbed_parcel_weight');
    $refurbedWeightDefault = ($refurbedWeightInput !== null && $refurbedWeightInput !== '')
        ? $refurbedWeightInput
        : ($refurbedCategoryWeight ?? $refurbedDefaults['default_weight'] ?? 0.5);
    $refurbedCarrierDefault = request('refurbed_carrier', $refurbedDefaults['default_carrier'] ?? 'DHL_EXPRESS');
    if (! empty($refurbedCarrierDefault)) {
        $refurbedCarrierDefault = strtoupper(str_replace(' ', '_', trim($refurbedCarrierDefault)));
    }
    $refurbedSupport = config('services.refurbed.support', []);
    $refurbedZendeskChatUrl = $refurbedSupport['chat_url'] ?? null;
    $refurbedZendeskDocsUrl = $refurbedSupport['docs_url'] ?? null;
    $refurbedZendeskHint = $refurbedSupport['chat_hint'] ?? 'Use Zendesk chat for Refurbed escalations.';
    $refurbedZendeskTemplate = $refurbedSupport['chat_context_template']
        ?? "Refurbed order :reference_id\nMarketplace reference: :reference\nCustomer: :customer";
    $primarySku = $firstOrderItem && $firstOrderItem->variation ? ($firstOrderItem->variation->sku ?? '') : '';
    $refurbedSupportContext = strtr($refurbedZendeskTemplate, [
        ':reference_id' => $order->reference_id ?? '',
        ':reference' => $order->reference ?? '',
        ':customer' => $customerName,
        ':sku' => $primarySku,
        ':marketplace' => 'Refurbed',
    ]);

    $getNextTesterId = function () use (&$testerIndex, $anchor) {
        if ($testerIndex === null) {
            return null;
        }

        $testerIndex++;

        return 'tester-' . $anchor . '-' . $testerIndex;
    };

    $getNextImeiId = function () use (&$imeiIndex, $anchor) {
        if ($imeiIndex === null) {
            return null;
        }

        $imeiIndex++;

        return 'imei-' . $anchor . '-' . $imeiIndex;
    };

    static $globalImeiTracker;
    if (! isset($globalImeiTracker)) {
        $globalImeiTracker = [];
    }

    if (! is_numeric($refurbedWeightDefault)) {
        $refurbedWeightDefault = $refurbedDefaults['default_weight'] ?? 0.5;
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
                @if ($shouldFlagTickets)
                    <div class="mt-2 alert alert-warning py-1 px-2 mb-1">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-1">
                            <span class="badge bg-warning text-dark text-uppercase">Check Tickets</span>
                            <small class="text-muted">{{ count($careTickets) }} open {{ count($careTickets) === 1 ? 'conversation' : 'conversations' }}</small>
                        </div>
                        <div class="mt-1 d-flex flex-wrap gap-2">
                            @foreach ($careTickets as $ticket)
                                <a
                                    href="{{ $ticket['url'] }}"
                                    target="_blank"
                                    class="small text-decoration-underline"
                                >Ticket #{{ $ticket['id'] }}</a>
                            @endforeach
                        </div>
                        <small class="text-muted d-block">Review marketplace ticket before dispatch.</small>
                    </div>
                @endif

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
            @php $conversationUrl = conversation_url_for_order_item($item); @endphp
            @if ($conversationUrl)
                <a class="" href="{{ $conversationUrl }}" target="_blank"><strong class="text-danger">Conversation</strong></a>
            @endif
            @if ($isRefurbed && $itemIndex === 0)
                <div class="mt-1 small refurbed-support-hint">
                    <span class="badge bg-info text-uppercase me-1">Zendesk</span>
                    @if ($refurbedZendeskChatUrl)
                        <a
                            class="fw-semibold"
                            href="{{ $refurbedZendeskChatUrl }}"
                            target="_blank"
                            rel="noopener"
                        >Open Chat</a>
                    @else
                        <span class="text-danger">Set Zendesk URL</span>
                    @endif
                    <button
                        type="button"
                        class="btn btn-link btn-sm p-0 align-baseline copy-support-context"
                        data-copy-text="{{ $refurbedSupportContext }}"
                        data-reset-label="Copy chat context"
                    >Copy chat context</button>
                    <span class="text-muted d-block">{{ $refurbedZendeskHint }}</span>
                    @if ($refurbedZendeskDocsUrl)
                        <a
                            class="d-inline-block text-decoration-underline"
                            href="{{ $refurbedZendeskDocsUrl }}"
                            target="_blank"
                            rel="noopener"
                        >Zendesk playbook</a>
                    @endif
                </div>
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
                            @if (!request('packing') && ! $isRefurbed)
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
                            @if ($isRefurbed)
                                <div class="w-100 mt-2 refurbed-shipping-fields">
                                    <input type="text" name="refurbed_merchant_address_id" value="{{ $refurbedAddressDefault }}" placeholder="Merchant Address ID" class="form-control form-control-sm mb-1" required>
                                    <input type="number" step="0.01" min="0.01" name="refurbed_parcel_weight" value="{{ $refurbedWeightDefault }}" placeholder="Parcel Weight (kg)" class="form-control form-control-sm mb-1" required>
                                    <input type="text" name="refurbed_carrier" value="{{ $refurbedCarrierDefault }}" placeholder="Carrier (optional)" class="form-control form-control-sm">
                                </div>
                            @endif
                            @if (!request('packing') && ! $isRefurbed)
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
                            @if (!request('packing') && ! $isRefurbed)
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
                            @if ($isRefurbed)
                                <div class="w-100 mt-2 refurbed-shipping-fields">
                                    <input type="text" name="refurbed_merchant_address_id" value="{{ $refurbedAddressDefault }}" placeholder="Merchant Address ID" class="form-control form-control-sm mb-1" required>
                                    <input type="number" step="0.01" min="0.01" name="refurbed_parcel_weight" value="{{ $refurbedWeightDefault }}" placeholder="Parcel Weight (kg)" class="form-control form-control-sm mb-1" required>
                                    <input type="text" name="refurbed_carrier" value="{{ $refurbedCarrierDefault }}" placeholder="Carrier (optional)" class="form-control form-control-sm">
                                </div>
                            @endif
                            @if (!request('packing') && ! $isRefurbed)
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
                            @if (!request('packing') && ! $isRefurbed)
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
                            @if ($isRefurbed)
                                <div class="w-100 mt-2 refurbed-shipping-fields">
                                    <input type="text" name="refurbed_merchant_address_id" value="{{ $refurbedAddressDefault }}" placeholder="Merchant Address ID" class="form-control form-control-sm mb-1" required>
                                    <input type="number" step="0.01" min="0.01" name="refurbed_parcel_weight" value="{{ $refurbedWeightDefault }}" placeholder="Parcel Weight (kg)" class="form-control form-control-sm mb-1" required>
                                    <input type="text" name="refurbed_carrier" value="{{ $refurbedCarrierDefault }}" placeholder="Carrier (optional)" class="form-control form-control-sm">
                                </div>
                            @endif
                            @if (!request('packing') && ! $isRefurbed)
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
                    <a class="dropdown-item" href="{{ url('order') }}/recheck/{{ $order->reference_id }}/true?marketplace={{ $order->marketplace_id }}" target="_blank">Invoice</a>
                @endif
                @if ($order->status == 6)
                    <a class="dropdown-item" href="{{ url('order') }}/export_refund_invoice/{{ $order->id }}" target="_blank">Refund Invoice</a>
                @endif
                @if (session('user')->hasPermission('view_api_data'))
                    <a class="dropdown-item" href="{{ url('order') }}/recheck/{{ $order->reference_id }}/false/false/null/true/true?marketplace={{ $order->marketplace_id }}" target="_blank">Data</a>
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

                            @php $conversationUrl = conversation_url_for_order_item($itm); @endphp
                            @if ($conversationUrl)
                                <a class="" href="{{ $conversationUrl }}" target="_blank"><strong class="text-white">Conversation</strong></a>
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
