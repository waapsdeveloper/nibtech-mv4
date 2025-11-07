<div wire:init="loadOrders" class="orders-table-wrapper">
    <div wire:loading.delay.longer class="py-4 text-center text-muted">
        <span class="spinner-border spinner-border-sm align-middle" role="status"></span>
        <span class="ms-2 align-middle">Loading orders&hellip;</span>
    </div>

    <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    @php
                        $from = $readyToLoad ? $orders->firstItem() : null;
                        $to = $readyToLoad ? $orders->lastItem() : null;
                        $total = $readyToLoad ? $orders->total() : null;
                    @endphp
                        <div class="card-header pb-0 d-flex justify-content-between">
                            <h4 class="card-title mg-b-0">
                                <label>
                                    <input type="checkbox" id="checkAll" onclick="checkAll()"> Check All
                                </label>
                                @if(request('missing') == 'scan')
                                    <input type="hidden" name="missing" value="scan" form="pdf">
                                @endif
                                <input class="btn btn-sm btn-secondary" type="submit" value="Print Labels" form="pdf" onclick=" if($('.table-hover :checkbox:checked').length == 0){event.preventDefault();alert('No Order Selected');}">
                            </h4>
                            <h5 class="card-title mg-b-0">{{ __('locale.From') }} {{ $from ?? '--' }} {{ __('locale.To') }} {{ $to ?? '--' }} {{ __('locale.Out Of') }} {{ $total ?? '--' }} </h5>

                            <div class="row">
                                {{-- <div class="form-group"> --}}
                                    <label for="perPage" class="card-title inline">Sort:</label>
                                    <select name="sort" class="form-select form-select-sm" id="perPage" onchange="this.form.submit()" form="search">
                                        <option value="1" {{ Request::get('sort') == 1 ? 'selected' : '' }}>Order DESC</option>
                                        <option value="2" {{ Request::get('sort') == 2 ? 'selected' : '' }}>Order ASC</option>
                                        <option value="3" {{ Request::get('sort') == 3 ? 'selected' : '' }}>Name DESC</option>
                                        <option value="4" {{ Request::get('sort') == 4 ? 'selected' : '' }}>Name ASC</option>
                                    </select>
                                    {{-- <button type="submit">Apply</button> --}}
                                {{-- </div>
                                <div class="form-group"> --}}
                                    <label for="perPage" class="card-title inline">per page:</label>
                                    <select name="per_page" class="form-select form-select-sm" id="perPage" onchange="this.form.submit()" form="search">
                                        <option value="10" {{ Request::get('per_page') == 10 ? 'selected' : '' }}>10</option>
                                        <option value="20" {{ Request::get('per_page') == 20 ? 'selected' : '' }}>20</option>
                                        <option value="50" {{ Request::get('per_page') == 50 ? 'selected' : '' }}>50</option>
                                        <option value="100" {{ Request::get('per_page') == 100 ? 'selected' : '' }}>100</option>
                                    </select>
                                    {{-- <button type="submit">Apply</button> --}}
                                {{-- </div> --}}
                            </div>

                    </div>

                    <datalist id="tester_list">
                        @foreach ($testers as $tester)
                            <option value="{{ $tester }}">
                        @endforeach
                    </datalist>
                        @if (! $readyToLoad)
                        <div class="card-body py-5 text-center text-muted">
                            <span class="spinner-border spinner-border-sm align-middle" role="status"></span>
                            <span class="ms-2 align-middle">Preparing orders&hellip;</span>
                        </div>
                        @else
            <div class="card-body"><div class="table-responsive">
                <form id="pdf" method="POST" target="_blank" action="{{url('export_label')}}">
                    @csrf
                    <input type="hidden" name="sort" value="{{ Request::get('sort') }}">

                </form>
                <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                    <thead>
                        <tr>
                            <th></th>
                            <th><small><b>No</b></small></th>
                            <th><small><b>Order ID</b></small></th>
                            <th><small><b>Product</b></small></th>
                            <th><small><b>Qty</b></small></th>
                            @if (session('user')->hasPermission('view_profit'))
                                <th><small><b>Charge</b></small></th>
                            @endif
                            <th><small><b>IMEI</b></small></th>
                            <th><small><b>Creation Date | TN</b></small></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $i = $orders->firstItem() - 1;
                            $id = [];
                            $total_items = 0;
                            $replacement_items = [];
                            $imei_list = [];
                            $t = 0;
                            $ti = 0;
                        @endphp
                        @foreach ($orders as $index => $order)
                            @php
                                if(in_array($order->id,$id)){
                                    continue;
                                }else {
                                    $id[] = $order->id;
                                }
                                $items = $order->order_items;
                                $j = 0;
                                $items_count = count($items);
                                $total_items += $items_count;
                                $customer = $order->customer;
                            @endphp

                            @foreach ($items as $itemIndex => $item)
                                @php
                                    $stock = $item->stock;
                                    $variation = $item->variation;
                                    $hide = false;

                                    if($stock != null && (request('missing_refund') || request('missing') || request('items'))){
                                        if (in_array($stock->imei . $stock->serial_number, $imei_list)) {
                                            echo "Duplicate IMEI: " . $stock->imei . $stock->serial_number;
                                            # code...
                                        }
                                        $imei_list[] = $stock->imei . $stock->serial_number;
                                    }
                                    if (request('missing') == 'reimburse' && $item->replacement) {
                                        $replacement = $item->replacement;
                                        $itm = $replacement;
                                        while ($replacement != null) {
                                            # code...
                                            $itm = $replacement;
                                            $replacement = $replacement->replacement;
                                        }
                                        if ($itm != null && $itm->stock->status == 2) {
                                            $hide = true;
                                            continue;
                                        }elseif ($itm != null && $itm->stock->status != 2) {
                                            echo "
                                                <tr>
                                                    <td>
                                            Reimburse not in stock: " . $itm->stock->imei . $itm->stock->serial_number. " - " . $itm->stock->status. "
                                                    </td>
                                                </tr>";
                                            # code...
                                        }

                                        $exchange = $order->exchange_items;
                                        if($exchange->count() > 0){
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
                                            # code...
                                        }
                                    }
                                    if(request('missing') == 'reimburse' && $stock != null){
                                        $stock->availability();
                                    }
                                @endphp
                                        <tr wire:key="order-{{ $order->id }}-item-{{ $item->id ?? ($order->id . '-' . $itemIndex) }}" @if ($customer->orders->count() > 1) class="bg-light" @endif>
                                    @if ($itemIndex == 0)
                                        <td rowspan="{{ $items_count }}"><input type="checkbox" name="ids[]" value="{{ $order->id }}" form="pdf"></td>
                                        <td rowspan="{{ $items_count }}">{{ $i + 1 }}</td>
                                        <td rowspan="{{ $items_count }}">
                                            {{ $order->reference_id }}<br>
                                            {{ $customer->company }}<br>
                                            {{ $customer->first_name.' '.$customer->last_name }}

                                        </td>
                                    @endif
                                    <td>
                                        @if ($variation ?? false)
                                            <strong>{{ $variation->sku }}</strong> - {{$variation->product->model ?? "Model not defined"}} - {{(isset($variation->storage)?$storages[$variation->storage] . " - " : null) . (isset($variation->color)?$colors[$variation->color]. " - ":null)}} <strong><u>{{ $grades[$variation->grade] ?? "Issue wih Grade" }}</u></strong>
                                        @endif
                                        @if ($order->delivery_note_url == null || $order->label_url == null)
                                            <a class="" href="{{url('order')}}/label/{{ $order->reference_id }}">
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
                                        {{$order->reference}}
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

                                                @if (in_array($order->status, [3,6]))
                                                    {{ $currencies[$order->currency].amount_formatter($order->price,2).' - '.$currencies[$order->currency].amount_formatter($order->charges,2) }}
                                                @elseif ($order->status == 5)
                                                    - {{ $currencies[$order->currency].amount_formatter($order->charges,2) }}
                                                @endif
                                            @else
                                                <strong class="text-info">Awaiting Charge</strong>
                                            @endif
                                        </td>
                                    @endif
                                    @if ($order->status == 3)
                                        @php
                                            $copyPayload = null;
                                        @endphp
                                        <td
                                            style="width:240px"
                                            class="text-success text-uppercase{{ $copyPayload ? ' copy-imei-trigger' : '' }}"
                                            id="copy_imei_{{ $order->id }}"
                                            @if ($copyPayload)
                                                data-copy-text='@json($copyPayload)'
                                            @endif
                                        >
                                            @isset($stock->imei) {{ $stock->imei }}&nbsp; @endisset
                                            @isset($stock->serial_number) {{ $stock->serial_number }}&nbsp; @endisset
                                            @isset($order->processed_by) | {{ $admins[$order->processed_by][0] }} | @endisset
                                            @isset($stock->tester) ({{ $stock->tester }}) @endisset

                                        </td>
                                        @if ($item->quantity > 1 && $item->stock_id != null)
                                        @php
                                            $copyPayload = "Hi, here are the IMEIs/Serial numbers for this order. \n";
                                            foreach ($items as $im) {
                                                if($im->stock_id == null){
                                                    continue;
                                                }
                                                $copyPayload .= $im->stock->imei . $im->stock->serial_number . "\n";
                                            }
                                            $copyPayload .= "Regards \n" . session('fname');
                                        @endphp
                                        @endif


                                    @endif
                                    @if ($itemIndex == 0 && $order->status != 3)
                                    <td style="width:240px" rowspan="{{ count($items) }}">
                                        @if ($item->status > 3)
                                            <strong class="text-danger">{{ $order_statuses[$order->status] }}</strong>
                                        @endif
                                        @isset($stock->imei) {{ $stock->imei }}&nbsp; @endisset
                                        @isset($stock->serial_number) {{ $stock->serial_number }}&nbsp; @endisset

                                        @isset($order->processed_by) | {{ $admins[$order->processed_by][0] }} | @endisset
                                        @isset($stock->tester) ({{ $stock->tester }}) @endisset

                                        @if (request('invoice') && isset($stock) && $item->status == 2 && !session()->has('refresh'))
                                            @php
                                                session()->put('refresh', true);
                                            @endphp
                                            <span class="d-none orders-refresh-trigger" data-refresh-url="{{ url('order') }}/refresh/{{ $order->reference_id }}"></span>
                                        @endif

                                        @if ($item->status == 2)
                                            @if (count($items) < 2 && $item->quantity < 2)
            <form id="dispatch_{{ $i."_".$j }}" class="form-inline" method="post" action="{{url('order')}}/dispatch/{{ $order->id }}"
                @if (!request('packing'))
                 onsubmit="if($('#tracking_number_{{ $i }}_{{ $j }}').val() == 'J{{ $order->tracking_number }}') {return true;}else{event.stopPropagation(); event.preventDefault(); alert('Wrong Tracking');}"
                @endif
                 >
                @csrf
                <input type="hidden" name="sort" value="{{request('sort')}}">
                <input type="hidden" name="packing" value="{{request('packing')}}">
                <div class="input-group">
                    @if (!request('packing'))
                        <input type="text" id="tester{{++$t}}" name="tester[]" placeholder="Tester" list="tester_list" class="form-control form-control-sm" style="max-width: 55px" maxlength="3" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'tester'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'tester', true); }">
                    @endif
                    <input type="text" id="imei{{++$ti}}" name="imei[]" placeholder="IMEI / Serial Number" class="form-control form-control-sm" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'imei'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'imei', true); }">

                    <input type="hidden" name="sku[]" value="{{ $variation->sku ?? "Variation Issue" }}">

                    <div class="input-group-append">
                        <input type="submit" name="imei_send" value=">" class="form-control form-control-sm" form="dispatch_{{ $i."_".$j }}">
                    </div>

                </div>
                @if (!request('packing'))
                <div class="w-100">
                    <input type="text" name="tracking_number" id="tracking_number_{{ $i }}_{{ $j }}" placeholder="Tracking Number" class="form-control form-control-sm w-100" required>
                </div>
                @endif
            </form>
                                            @elseif (count($items) < 2 && $item->quantity >= 2)

            <form id="dispatch_{{ $i."_".$j }}" class="form-inline" method="post" action="{{url('order')}}/dispatch/{{ $order->id }}"
                @if (!request('packing'))
                 onsubmit="if($('#tracking_number_{{ $i }}_{{ $j }}').val() == 'J{{ $order->tracking_number }}') {return true;}else{event.stopPropagation(); event.preventDefault();}"
                @endif
                 >
                @csrf
                <input type="hidden" name="sort" value="{{request('sort')}}">
                <input type="hidden" name="packing" value="{{request('packing')}}">

                @for ($in = 1; $in <= $item->quantity; $in ++)

                    <div class="input-group">
                        @if (!request('packing'))
                        <input type="text" id="tester{{++$t}}" name="tester[]" placeholder="Tester" list="tester_list" class="form-control form-control-sm" style="max-width: 55px" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'tester'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'tester', true); }">
                        @endif
                        <input type="text" id="imei{{++$ti}}" name="imei[]" placeholder="IMEI / Serial Number" class="form-control form-control-sm" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'imei'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'imei', true); }" required>
                    </div>
                <input type="hidden" name="sku[]" value="{{ $variation->sku }}">
                @endfor
                @if (!request('packing'))
                <div class="w-100">
                    <input type="text" name="tracking_number" id="tracking_number_{{ $i."_".$j }}" placeholder="Tracking Number" class="form-control form-control-sm w-100" required>
                </div>
                @endif
                <div class="w-100">
                    <input type="submit" name="imei_send" value="Submit IMEIs" class="form-control form-control-sm w-100" form="dispatch_{{ $i."_".$j }}">
                </div>
            </form>
                                            @elseif (count($items) >= 2)
            <form id="dispatch_{{ $i."_".$j }}" class="form-inline" method="post" action="{{url('order')}}/dispatch/{{ $order->id }}"
                @if (!request('packing'))
                 onsubmit="if($('#tracking_number_{{ $i }}_{{ $j }}').val() == 'J{{ $order->tracking_number }}') {return true;}else{event.stopPropagation(); event.preventDefault(); alert('Wrong Tracking');}"
                @endif
                 >
                @csrf
                <input type="hidden" name="sort" value="{{request('sort')}}">
                <input type="hidden" name="packing" value="{{request('packing')}}">
                @foreach ($items as $itm)

                    @for ($in = 1; $in <= $itm->quantity; $in++)
                        <div class="input-group">
                            @if (!request('packing'))
                            <input type="text" id="tester{{++$t}}" name="tester[]" list="tester_list" placeholder="Tester" class="form-control form-control-sm" style="max-width: 55px" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'tester'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'tester', true); }">
                            @endif

                            <input type="text" id="imei{{++$ti}}" name="imei[]" placeholder="IMEI / Serial Number" class="form-control form-control-sm" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'imei'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'imei', true); }" required title="for SKU:{{ $itm->variation->sku }}">
                        </div>
                        <input type="hidden" name="sku[]" value="{{ $itm->variation->sku }}">
                    @endfor
                @endforeach
                @if (!request('packing'))
                <div class="w-100">
                    <input type="text" name="tracking_number" id="tracking_number_{{ $i }}_{{ $j }}" placeholder="Tracking Number" class="form-control form-control-sm w-100" required>
                </div>
                @endif
                <div class="w-100">
                    <input type="submit" name="imei_send" value="Submit IMEIs" class="form-control form-control-sm w-100" form="dispatch_{{ $i."_".$j }}">
                </div>
            </form>
                                            @endif
                                        @endif
                                    </td>
                                    @endif
                                    <td style="width:220px">{{ $order->created_at}} <br> {{ $order->processed_at}}<br>
                                        @if ($order->tracking_number != null)
                                        <a href="https://www.dhl.com/us-en/home/tracking/tracking-express.html?submit=1&tracking-id={{$order->tracking_number}}" target="_blank">{{$order->tracking_number}}</a>

                                    @endif</td>
                                    <td>
                                        <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical  tx-18"></i></a>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="{{url('order')}}/refresh/{{ $order->reference_id }}">Refresh</a>
                                            {{-- @if ($item->order->processed_at > $last_hour || $user_id == 1) --}}
                                            @if (session('user')->hasPermission('change_order_tracking'))
                                            <a class="dropdown-item" id="tracking_{{ $order->id }}" href="javascript:void(0);" data-bs-target="#tracking_model" data-bs-toggle="modal" data-bs-reference="{{ $order->reference_id }}" data-bs-order="{{ $order->id }}"> Change Tracking </a>
                                            @endif
                                            @if (session('user')->hasPermission('correction'))
                                            <a class="dropdown-item" id="correction_{{ $item->id }}" href="javascript:void(0);" data-bs-target="#correction_model" data-bs-toggle="modal" data-bs-reference="{{ $order->reference_id }}" data-bs-item="{{ $item->id }}"> Correction </a>
                                            @endif
                                            @if (session('user')->hasPermission('correction_override'))
                                            <a class="dropdown-item" id="correction_{{ $item->id }}" href="javascript:void(0);" data-bs-target="#correction_model" data-bs-toggle="modal" data-bs-reference="{{ $order->reference_id }}" data-bs-item="{{ $item->id }}" data-bs-override="true"> Correction (Override) </a>
                                            @endif
                                            {{-- @endif --}}
                                            @if (!$item->replacement)
                                            <a class="dropdown-item" id="replacement_{{ $item->id }}" href="javascript:void(0);" data-bs-target="#replacement_model" data-bs-toggle="modal" data-bs-reference="{{ $order->reference_id }}" data-bs-item="{{ $item->id }}" data-bs-return="@if($item->check_return) 1 @endif"> Replacement </a>
                                            @endif
                                            @if ($order->status >= 3)
                                            <a class="dropdown-item" href="{{url('order')}}/recheck/{{ $order->reference_id }}/true" target="_blank">Invoice</a>
                                            @endif
                                            @if ($order->status == 6)
                                            <a class="dropdown-item" href="{{url('order')}}/export_refund_invoice/{{ $order->id }}" target="_blank">Refund Invoice</a>
                                            @endif
                                            @if (session('user')->hasPermission('view_api_data'))
                                            <a class="dropdown-item" href="{{url('order')}}/recheck/{{ $order->reference_id }}/false/false/null/true/true" target="_blank">Data</a>
                                            <a class="dropdown-item" href="{{url('order')}}/label/{{ $order->reference_id }}/true/true" target="_blank">Label Data</a>
                                            @endif
                                            <a class="dropdown-item" href="https://backmarket.fr/bo-seller/orders/all?orderId={{ $order->reference_id }}#order-details={{ $order->reference_id }}" target="_blank">View in Backmarket</a>
                                            <a class="dropdown-item" href="#" onclick="window.open('{{url('order')}}/export_invoice_new/{{$order->id}}','_blank','print_popup');">Invoice 2</a>
                                            @if (request('missing') == 'scan' && session('user')->hasPermission('mark_scanned'))
                                                <a class="dropdown-item" href="{{url('order')}}/mark_scanned/{{ $order->id }}">Mark Scanned</a>
                                                <a class="dropdown-item" href="{{url('order')}}/mark_scanned/{{ $order->id }}?force=1">Mark Scanned (Forced)</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @php
                                    $j++;
                                @endphp
                            @endforeach
                            @if (!isset($hide) || !$hide)

                            @foreach ($items as $itemIndex => $item)
                                @if ($item->replacement)
                                    @php
                                        $replacement = $item->replacement;
                                    @endphp
                                    @while ($replacement != null)
                                        @php
                                            $itm = $replacement;
                                            $replacement = $replacement->replacement;
                                            if(in_array($itm->id,$replacement_items)){
                                                continue;
                                            }else {
                                                $replacement_items[] = $itm->id;
                                            }
                                        @endphp

                                        {{-- @foreach ($order->exchange_items as $ind => $itm) --}}

                                        <tr class="bg-secondary text-white">
                                            <td colspan="2">{{ $customer->first_name." ".$customer->last_name." ".$customer->phone }}</td>

                                            <td>Exchanged With</td>
                                            <td>

                                                @if ($itm->variation ?? false)
                                                    <strong>{{ $itm->variation->sku }}</strong>{{ " - " . $itm->variation->product->model . " - " . (isset($itm->variation->storage)?$storages[$itm->variation->storage] . " - " : null) . (isset($itm->variation->color)?$colors[$itm->variation->color]. " - ":null)}} <strong><u>{{ $grades[$itm->variation->grade] }}</u></strong>
                                                @endif

                                            </td>
                                            <td>{{ $itm->quantity }}</td>
                                            <td>
                                                {{ $order->order_status->name }}
                                                @isset($itm->stock->imei) {{ $itm->stock->imei }}&nbsp; @endisset
                                                @isset($itm->stock->serial_number) {{ $itm->stock->serial_number }}&nbsp; @endisset
                                            </td>

                                            <td title="{{$itm->id}}">{{ $itm->created_at }}</td>
                                            <td>
                                                <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical text-white tx-18"></i></a>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item" href="{{url('order/delete_replacement_item').'/'.$itm->id}}" onclick="return confirm('Are you sure?');" ><i class="fe fe-trash-2 me-2"></i>Delete</a>
                                                    <a class="dropdown-item" id="replacement_{{ $itm->id }}" href="javascript:void(0);" data-bs-target="#replacement_model" data-bs-toggle="modal" data-bs-reference="{{ $itm->order->reference_id }}" data-bs-item="{{ $itm->id }}" data-bs-return="@if($itm->check_return) 1 @endif"> Replacement </a>
                                                    <a class="dropdown-item" href="https://backmarket.fr/bo-seller/orders/all?orderId={{ $order->reference_id }}#order-details={{ $order->reference_id }}" target="_blank"><i class="fe fe-caret me-2"></i>View in Backmarket</a>
                                                    {{-- <a class="dropdown-item" href="javascript:void(0);"><i class="fe fe-trash-2 me-2"></i>Delete</a> --}}
                                                </div>
                                            </td>
                                        </tr>
                                    {{-- @endforeach --}}
                                    @endwhile
                                @elseif ($order->exchange_items->count() > 0)
                                    @foreach ($order->exchange_items as $ind => $itm)
                                        @php

                                            if(in_array($itm->id,$replacement_items)){
                                                continue;
                                            }else {
                                                $replacement_items[] = $itm->id;
                                            }
                                        @endphp
                                        <tr class="bg-secondary text-white">
                                                <td colspan="2">{{ $customer->first_name." ".$customer->last_name." ".$customer->phone }}</td>

                                            <td>Exchanged with</td>
                                            <td>

                                                @if ($itm->variation ?? false)
                                                    <strong>{{ $itm->variation->sku }}</strong>{{ " - " . $itm->variation->product->model . " - " . (isset($itm->variation->storage)?$storages[$itm->variation->storage] . " - " : null) . (isset($itm->variation->color)?$colors[$itm->variation->color]. " - ":null)}} <strong><u>{{ $grades[$itm->variation->grade] }}</u></strong>
                                                @endif

                                            </td>
                                            <td>{{ $itm->quantity }}</td>
                                            <td>
                                                {{ $order->order_status->name }}
                                                @isset($itm->stock->imei) {{ $itm->stock->imei }}&nbsp; @endisset
                                                @isset($itm->stock->serial_number) {{ $itm->stock->serial_number }}&nbsp; @endisset
                                            </td>

                                            <td title="{{$itm->id}}">{{ $itm->created_at }}</td>
                                            <td>
                                                <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical text-white tx-18"></i></a>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item" href="{{url('order/delete_replacement_item').'/'.$itm->id}}" onclick="return confirm('Are you sure?');"><i class="fe fe-trash-2 me-2"></i>Delete</a>
                                                    <a class="dropdown-item" id="replacement_{{ $itm->id }}" href="javascript:void(0);" data-bs-target="#replacement_model" data-bs-toggle="modal" data-bs-reference="{{ $itm->order->reference_id }}" data-bs-item="{{ $itm->id }}" data-bs-return="@if($itm->check_return) 1 @endif"> Replacement </a>
                                                    <a class="dropdown-item" href="https://backmarket.fr/bo-seller/orders/all?orderId={{ $order->reference_id }}#order-details={{ $order->reference_id }}" target="_blank"><i class="fe fe-caret me-2"></i>View in Backmarket</a>
                                                    {{-- <a class="dropdown-item" href="javascript:void(0);"><i class="fe fe-trash-2 me-2"></i>Delete</a> --}}
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                                @if (isset($itm) && $itm->replacement)
                                                @php
                                                    if ($item->replacement){
                                                        $replacement = $item->replacement;
                                                    }else{
                                                        $replacement = $itm->replacement;
                                                    }

                                                @endphp
                                                @while ($replacement != null)
                                                    @php
                                                        $itm = $replacement;
                                                        $replacement = $replacement->replacement;

                                                        if(in_array($itm->id,$replacement_items)){
                                                            continue;
                                                        }else {
                                                            $replacement_items[] = $itm->id;
                                                        }
                                                    @endphp

                                                    {{-- @foreach ($order->exchange_items as $ind => $itm) --}}

                                                    <tr class="bg-secondary text-white">
                                                        <td colspan="2">{{ $customer->first_name." ".$customer->last_name." ".$customer->phone }}</td>

                                                        <td>Exchanged With</td>
                                                        <td>

                                                            @if ($itm->variation ?? false)
                                                                <strong>{{ $itm->variation->sku }}</strong>{{ " - " . $itm->variation->product->model . " - " . (isset($itm->variation->storage)?$storages[$itm->variation->storage] . " - " : null) . (isset($itm->variation->color)?$colors[$itm->variation->color]. " - ":null)}} <strong><u>{{ $grades[$itm->variation->grade] }}</u></strong>
                                                            @endif

                                                        </td>
                                                        <td>{{ $itm->quantity }}</td>
                                                        <td>
                                                            {{ $order->order_status->name }}
                                                            @isset($itm->stock->imei) {{ $itm->stock->imei }}&nbsp; @endisset
                                                            @isset($itm->stock->serial_number) {{ $itm->stock->serial_number }}&nbsp; @endisset
                                                        </td>

                                                        <td title="{{$itm->id}}">{{ $itm->created_at }}</td>
                                                        <td>
                                                            <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical text-white tx-18"></i></a>
                                                            <div class="dropdown-menu">
                                                                <a class="dropdown-item" href="{{url('order/delete_replacement_item').'/'.$itm->id}}" onclick="return confirm('Are you sure?');"><i class="fe fe-trash-2 me-2"></i>Delete</a>
                                                                <a class="dropdown-item" id="replacement_{{ $itm->id }}" href="javascript:void(0);" data-bs-target="#replacement_model" data-bs-toggle="modal" data-bs-reference="{{ $itm->order->reference_id }}" data-bs-item="{{ $itm->id }}" data-bs-return="@if($itm->check_return) 1 @endif"> Replacement </a>
                                                                <a class="dropdown-item" href="https://backmarket.fr/bo-seller/orders/all?orderId={{ $order->reference_id }}#order-details={{ $order->reference_id }}" target="_blank"><i class="fe fe-caret me-2"></i>View in Backmarket</a>
                                                                {{-- <a class="dropdown-item" href="javascript:void(0);"><i class="fe fe-trash-2 me-2"></i>Delete</a> --}}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                {{-- @endforeach --}}
                                                @endwhile
                                @endif

                            @endforeach
                            @if ($customer->orders->count() > 1)
                                {{-- @if (session('user_id') == 1)

                                <script>
                                    $(document).ready(function(){
                                        data = get_customer_previous_orders({{ $customer->id }}, {{ $order->id }});

                                    });
                                </script>

                                @endif --}}
                                @php
                                    $def = 0;
                                @endphp
                                @foreach ($customer->orders as $ins => $ord)
                                    @if ($ord->id != $order->id)

                                        @foreach ($ord->order_items as $ind => $itm)

                                            <tr class="bg-secondary text-white">
                                                @if (!$def)
                                                    @php
                                                        $def = 1;
                                                    @endphp
                                                    <td rowspan="{{ count($customer->orders)-1 }}" colspan="2">{{ $ord->customer->first_name." ".$ord->customer->last_name." ".$ord->customer->phone }}</td>
                                                @endif
                                                <td>{{ $ord->reference_id }}</td>
                                                <td>

                                                    @if ($itm->variation ?? false)
                                                        <strong>{{ $itm->variation->sku }}</strong>{{ " - " . (isset($itm->variation->product)?$itm->variation->product->model: 'Model not defined') . " - " . (isset($itm->variation->storage)?$storages[$itm->variation->storage] . " - " : null) . (isset($itm->variation->color)?$colors[$itm->variation->color]. " - ":null)}} <strong><u>{{ $grades[$itm->variation->grade] }}</u></strong>
                                                    @endif

                                                    @if ($itm->care_id != null)
                                                        <a class="" href="https://backmarket.fr/bo-seller/customer-care/help-requests/{{ $itm->care_id }}" target="_blank"><strong class="text-white">Conversation</strong></a>
                                                    @endif
                                                </td>
                                                <td>{{ $itm->quantity }}</td>
                                                @if (session('user')->hasPermission('view_profit'))
                                                    <td>
                                                        @if ($ord->charges != null)
                                                            @if (in_array($ord->status, [3,6]))
                                                                {{ $currencies[$ord->currency].amount_formatter($ord->price,2).' - '.$currencies[$ord->currency].amount_formatter($ord->charges,2) }}
                                                            @elseif ($ord->status == 5)
                                                                -{{ $currencies[$ord->currency].amount_formatter($ord->charges,2) }}
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
                                    @php

                                    @endphp
                                    <tr class="bg-info text-white">
                                        <td colspan="2">{{ $transaction->transaction_type->name }}</td>
                                        <td colspan="3">{{ $transaction->description }}</td>
                                        <td>{{ $transaction->currency_id->sign. amount_formatter($transaction->amount) }}</td>
                                        <td></td>
                                        <td>{{ $transaction->date }}</td>
                                        <td></td>
                                    </tr>
                                @endforeach
                            @endif
                            @php
                                $i ++;
                            @endphp
                            @endif
                        @endforeach
                            @php
                            if (session()->has('refresh')){
                                session()->forget('refresh');
                            }
                            @endphp
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4">
                                {{ $orders->onEachSide(3)->links() }} {{ __('locale.From') }} {{$orders->firstItem()}} {{ __('locale.To') }} {{$orders->lastItem()}} {{ __('locale.Out Of') }} {{$orders->total()}}

                            </td>
                            @if (request('missing_refund') || request('missing_reimburse'))

                            <td>

                                {{-- <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical  tx-18"></i></a>
                                <div class="dropdown-menu"> --}}
                                <a
                                    class="dropdown-item"
                                    id="open_all_imei"
                                    href="#"
                                    data-imei-list='@json($imei_list)'
                                    data-imei-base="{{ url('imei') }}"
                                >Open All IMEI Details</a>
                                {{-- </div> --}}
                            </td>
                            @endif
                            <td colspan="4" align="right">
                                Total Items in this page: {{ $total_items }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
                <br>
            </div>

                    @endif
                    {{-- </div> --}}
                </div>
            </div>
        </div>
    </div>
</div>

