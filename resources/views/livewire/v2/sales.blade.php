{{-- @extends('layouts.app') --}}

    @section('styles')
        <style>
            .rows{
                border: 1px solid #016a5949;
            }
            .columns{
                background-color:#016a5949;
                padding-top:5px
            }
            .childs{
                padding-top:5px
            }
            .form-floating>.form-control,
            .form-floating>.form-control-plaintext {
            padding: 0rem 0.75rem;
            }

            .form-floating>.form-control,
            .form-floating>.form-control-plaintext,
            .form-floating>.form-select {
            height: calc(2.5rem + 2px);
            line-height: 1;
            }

            .form-floating>label {
            padding: 0.5rem 0.75rem;
            }

            body.tracking-verify-active {
                overflow: hidden;
            }

            .tracking-verify-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                display: none;
                align-items: center;
                justify-content: center;
                background: rgba(15, 23, 42, 0.55);
                z-index: 2000;
                padding: 16px;
            }

            .tracking-verify-overlay.show {
                display: flex;
            }

            .tracking-verify-dialog {
                width: min(440px, 100%);
                background: #ffffff;
                border-radius: 12px;
                padding: 24px 28px;
                box-shadow: 0 18px 48px rgba(15, 23, 42, 0.35);
                font-family: Arial, sans-serif;
            }

            .tracking-verify-dialog h2 {
                font-size: 20px;
                margin-bottom: 12px;
            }

            .tracking-verify-dialog p {
                margin-bottom: 14px;
                font-size: 15px;
            }

            .tracking-verify-input {
                width: 100%;
                padding: 12px 14px;
                font-size: 18px;
                border: 1px solid #cbd5f5;
                border-radius: 8px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }

            .tracking-verify-feedback {
                margin-top: 10px;
                font-size: 14px;
            }

            .tracking-verify-feedback.tracking-verify-mismatch {
                color: #b91c1c;
            }
            .tracking-verify-feedback.tracking-verify-match {
                color: #166534;
            }
        </style>
    @endsection
<br>
    @section('content')

<div class="toast-container position-fixed top-0 end-0 p-5" style="z-index: 1000">

    @if (session('copy'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Function to copy text to clipboard
                function copyToClipboard(text) {
                    var tempInput = document.createElement('textarea');
                    tempInput.value = text;
                    document.body.appendChild(tempInput);
                    tempInput.select();
                    document.execCommand('copy');
                    document.body.removeChild(tempInput);
                }

                // Check if there is a copy message in the session
                var copiedText = "{{ session('copy') }}";
                if (copiedText) {
                    // Copy the IMEI number to the clipboard
                    copyToClipboard(copiedText);

                    // Show success toast
                    var toastContainer = document.querySelector('.toast-container');
                    var toastBody = document.querySelector('.toast-body');
                    toastBody.innerText = "Message copied to clipboard: \n" + copiedText;
                    var toast = new bootstrap.Toast(document.querySelector('.toast'));
                    toast.show();
                }
            });
        </script>
        @php
        session()->forget('copy');
        @endphp
    @endif
</div>


<div id="tracking-verify-overlay" class="tracking-verify-overlay" aria-hidden="true">
    <div class="tracking-verify-dialog" role="dialog" aria-modal="true" aria-labelledby="tracking-verify-title">
        <h2 id="tracking-verify-title">Confirm Tracking Number</h2>
        <p class="tracking-verify-instructions">Enter the tracking number <span id="tracking-verify-expected" class="fw-bold"></span> to confirm dispatch.</p>
        <input id="tracking-verify-input" class="tracking-verify-input" type="text" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="Type tracking number" inputmode="text">
        <p id="tracking-verify-feedback" class="tracking-verify-feedback text-muted">The popup closes automatically once the number matches.</p>
    </div>
</div>


        <!-- breadcrumb -->
            <div class="breadcrumb-header justify-content-between">
                <div class="left-content">
                {{-- <span class="main-content-title mg-b-0 mg-b-lg-1">Orders</span> --}}
                @php
                    $refreshMarketplaceParam = request()->has('marketplace') ? request('marketplace') : null;
                    $refreshMarketplace = ($refreshMarketplaceParam === null || $refreshMarketplaceParam === '') ? 1 : $refreshMarketplaceParam;
                @endphp
                <a href="{{ url('refresh_order') }}?marketplace={{ $refreshMarketplace }}" target="_blank" class="mg-b-0 mg-b-lg-1 btn btn-primary">Recheck All</a>
                <a href="{{url('check_new')}}" class="mg-b-0 mg-b-lg-1 btn btn-primary">Check for New</a>
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Orders</li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <div class="row">
            <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>Search</h4></center>
            </div>
        </div>
        <br>
        <form action="" method="GET" id="search">
            <div class="row">
                <div class="col-md col-sm-6">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="order_id_input" name="order_id" placeholder="Enter ID" value="@isset($_GET['order_id']){{$_GET['order_id']}}@endisset">
                        <label for="order_id_input">Order Number</label>
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    <div class="form-floating">
                        <input class="form-control" id="start_date_input" name="start_date" id="datetimepicker" type="date" value="@isset($_GET['start_date']){{$_GET['start_date']}}@endisset">
                        <label for="start_date_input">{{ __('locale.Start Date') }}</label>
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    <div class="form-floating">
                        <input class="form-control" id="start_time_input" name="start_time" id="timetimepicker" type="time" value="@isset($_GET['start_time']){{$_GET['start_time']}}@endisset">
                        <label for="start_time_input">Time</label>
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    <div class="form-floating">
                        <input class="form-control" id="end_date_input" name="end_date" id="datetimepicker" type="date" value="@isset($_GET['end_date']){{$_GET['end_date']}}@endisset">
                        <label for="end_date_input">{{ __('locale.End Date') }}</label>
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    <div class="form-floating">
                        <input class="form-control" id="end_time_input" name="end_time" id="timetimepicker" type="time" value="@isset($_GET['end_time']){{$_GET['end_time']}}@endisset">
                        <label for="end_time_input">Time</label>
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    <div class="form-floating">
                        <input type="text" class="form-control focused" id="sku_input" name="sku" placeholder="Enter SKU" value="@isset($_GET['sku']){{$_GET['sku']}}@endisset" autofocus>
                        <label for="sku_input" class="">SKU</label>
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    {{-- <div class="form-floating"> --}}
                        <select id="status_input" name="status" class="form-control form-select" data-bs-placeholder="Select Status">
                            <option value="">Status</option>
                            @foreach ($order_statuses as $id => $status)
                                <option value="{{$id}}" @if(isset($_GET['status']) && $id == $_GET['status']) {{'selected'}}@endif>{{$status}}</option>
                            @endforeach
                        </select>
                        {{-- <label for="status_input">Status</label>
                    </div> --}}
                </div>
                <button class="btn btn-primary" type="submit">{{ __('locale.Search') }}</button>
                <a href="{{url('order')}}?per_page=10" class="btn btn-default">Reset</a>
            </div>
                <br>
            <div class="row">
                <div class="col-md col-sm-6">
                    <div class="form-floating">
                        <input type="text" class="form-control" name="imei" placeholder="Enter IMEI" value="@isset($_GET['imei']){{$_GET['imei']}}@endisset">
                        <label for="">IMEI</label>
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    {{-- <div class="form-floating"> --}}
                        <select id="adm_input" name="adm" class="form-control form-select" data-bs-placeholder="Select Processed By">
                            <option value="">Processed by</option>
                            <option value="0">None</option>
                            @foreach ($admins as $id => $adm)
                                <option value="{{$id}}" @if(isset($_GET['adm']) && $id == $_GET['adm']) {{'selected'}}@endif>{{$adm }}</option>
                            @endforeach
                        </select>
                        {{-- <label for="adm_input">Processed By</label> --}}
                    {{-- </div> --}}
                </div>
                <div class="col-md col-sm-6">
                    <div class="form-floating">
                        <input type="text" class="form-control" name="tracking_number" placeholder="Enter Tracking Number" value="@isset($_GET['tracking_number']){{$_GET['tracking_number']}}@endisset">
                        <label for="">Tracking Number</label>
                    </div>
                </div>

                <div class="col-md col-sm-6">
                    <select name="with_stock" class="form-control form-select" data-bs-placeholder="Select With Stock">
                        <option value="">With & Without Stock</option>
                        <option value="1" @if(isset($_GET['with_stock']) && $_GET['with_stock'] == 1) {{'selected'}}@endif>With Stock</option>
                        <option value="2" @if(isset($_GET['with_stock']) && $_GET['with_stock'] == 2) {{'selected'}}@endif>Without Stock</option>
                    </select>
                </div>

                <div class="col-md col-sm-6">
                    <select name="exclude_topup[]" class="form-control form-select select2" multiple data-bs-placeholder="Exclude Topups">
                        <option value="">Exclude Topups</option>
                        @foreach ($topups as $id => $name)
                            <option value="{{ $id }}" @if (isset($_GET['exclude_topup']) && in_array($id, $_GET['exclude_topup'])) {{ 'selected' }} @endif>
                                {{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md col-sm-6">
                    <select id="marketplace_input" name="marketplace" class="form-control form-select" data-bs-placeholder="Select Marketplace">
                        <option value="0" @if (request('marketplace') == 0) {{'selected'}} @endif>All Marketplace</option>
                        @foreach ($marketplaces as $id => $name)
                            <option value="{{$id}}" @if(isset($_GET['marketplace']) && $id == $_GET['marketplace']) {{'selected'}} @elseif (!isset($_GET['marketplace']) && $id == 1) {{'selected'}} @endif>{{$name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md col-sm-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="invoice" name="invoice" value="1" @if (request('invoice') == "1") {{'checked'}} @endif>
                        <label class="form-check-label" for="invoice">Invoice Mode</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="packing" name="packing" value="1" @if (request('packing') == "1") {{'checked'}} @endif>
                        <label class="form-check-label" for="packing">Packing Mode</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="no_invoice" name="no_invoice" value="1" @if (request('no_invoice') == "1") {{'checked'}} @endif>
                        <label class="form-check-label" for="no_invoice">Email Invoice</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="bypass_missing" name="bypass_missing" value="1" @if (request('bypass_missing') == "1") {{'checked'}} @endif>
                        <label class="form-check-label" for="bypass_missing">Bypass Missing</label>
                    </div>
                </div>
                <input type="hidden" name="page" value="{{ Request::get('page') }}">
                @if (Request::get('care') == 1)
                    <input type="hidden" name="care" value="{{ Request::get('care') }}">
                @endif
                @if (Request::get('missing'))
                    <input type="hidden" name="missing" value="{{ Request::get('missing') }}">
                @endif
                @if (Request::get('transaction'))
                    <input type="hidden" name="transaction" value="{{ Request::get('transaction') }}">
                @endif
            </div>

        </form>
        <div class="d-flex justify-content-between">
            <div class="">
                <a href="{{url('order')}}" class="btn btn-link">All Order</a>
                <a href="{{url('order')}}?status=2" class="btn btn-link">Pending Order ({{ $pending_orders_count }})</a>
                <a href="{{url('order')}}?care=1" class="btn btn-link">Conversation</a>
                <a href="{{url('order')}}?missing=refund" class="btn btn-link">Missing Refund</a>
                <a href="{{url('order')}}?missing=reimburse" class="btn btn-link">Missing Reimburse</a>
                <a href="{{url('order')}}?missing=purchase" class="btn btn-link">Missing Purchase</a>
                @if ($missing_charge_count > 0)
                    <a href="{{url('order')}}?missing=charge" class="btn btn-link">Missing Charge ({{ $missing_charge_count }})</a>

                @endif
                <a href="{{url('order')}}?missing=scan" class="btn btn-link">Missing Scan</a>
                @if ($missing_processed_at_count > 0)
                    <a href="{{url('order')}}?missing=processed_at" class="btn btn-link">Missing Invoiced At ({{ $missing_processed_at_count }})</a>

                @endif
                <a href="{{url('order')}}?transaction=1" class="btn btn-link">Transaction</a>
            </div>
            <div class="d-flex">

                <input type="text" class="form-control pd-x-20" name="last_order" placeholder="Last Order (Optional)" value="" form="picklist" style="width: 170px;">
                <button class="btn btn-sm btn-secondary pd-x-20 " type="submit" form="picklist" name="order" value="1">Order List</button>
                @if (session('user')->hasPermission('order_picklist'))
                <button class="btn btn-sm btn-secondary pd-x-20 " type="submit" form="picklist" name="picklist" value="1">Pick List</button>

                @endif
                <button class="btn btn-sm btn-secondary pd-x-20 " type="submit" form="picklist" name="ordersheet" value="1">Order Sheet</button>
                @if (session('user')->hasPermission('send_bulk_invoice'))
                <button class="btn btn-sm btn-primary pd-x-20 " type="submit" form="search" name="bulk_invoice" value="1">Send Bulk Invoice Email</button>
                @endif
            </div>
        </div>
        <form id="picklist" method="POST" target="_blank" action="{{url('export_order')}}">
            @csrf
            <input type="hidden" name="start_date" value="{{ Request::get('start_date') }}">
            <input type="hidden" name="start_time" value="{{ Request::get('start_time') }}">
            <input type="hidden" name="end_date" value="{{ Request::get('end_date') }}">
            <input type="hidden" name="end_time" value="{{ Request::get('end_time') }}">
            <input type="hidden" name="status" value="{{ Request::get('status') }}">
            <input type="hidden" name="adm" value="{{ Request::get('adm') }}">
            <input type="hidden" name="order_id" value="{{ Request::get('order_id') }}">
            <input type="hidden" name="sku" value="{{ Request::get('sku') }}">
            <input type="hidden" name="imei" value="{{ Request::get('imei') }}">
            <input type="hidden" name="tracking_number" value="{{ Request::get('tracking_number') }}">
            <input type="hidden" name="marketplace" value="{{ Request::get('marketplace') }}">
            <input type="hidden" name="page" value="{{ Request::get('page') }}">
            <input type="hidden" name="per_page" value="{{ Request::get('per_page') }}">
            @if (Request::get('exclude_topup'))
                @foreach (Request::get('exclude_topup') as $topup)
                    <input type="hidden" name="exclude_topup[]" value="{{ $topup }}">
                @endforeach

            @endif
            @if (Request::get('care') == 1)
                <input type="hidden" name="care" value="{{ Request::get('care') }}">
            @endif
            @if (Request::get('missing'))
                <input type="hidden" name="missing" value="{{ Request::get('missing') }}">
            @endif
            @if (Request::get('with_stock'))
                <input type="hidden" name="with_stock" value="{{ Request::get('with_stock') }}">
            @endif
            @if (Request::get('transaction'))
                <input type="hidden" name="transaction" value="{{ Request::get('transaction') }}">
            @endif

        </form>
        <br>
        <div class="row">
            <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>Orders</h4></center>
            </div>
        </div>
        <br>

        <div class="card mb-3" id="qz-printer-preferences-card">
            <div class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
                <div>
                    <div class="fw-semibold mb-1">QZ Tray Printers</div>
                    <div class="small text-muted">Status: <span id="qz-order-status" class="fw-semibold text-muted">Checking...</span></div>
                    <div class="small text-muted">Invoice Printer: <span id="invoice-printer-display" class="fw-semibold text-secondary">{{ session('a4_printer') ?? 'Not set' }}</span></div>
                    <div class="small text-muted">Label Printer: <span id="label-printer-display" class="fw-semibold text-secondary">{{ session('label_printer') ?? 'Not set' }}</span></div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="change-invoice-printer-btn">Change Invoice Printer</button>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="change-label-printer-btn">Change Label Printer</button>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <span class="alert-inner--icon"><i class="fe fe-thumbs-up"></i></span>
                <span class="alert-inner--text"><strong>{{session('success')}}</strong></span>
                <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
            </div>
            <br>
            @php
            session()->forget('success');
            @endphp
            @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <span class="alert-inner--icon"><i class="fe fe-thumbs-down"></i></span>
                <span class="alert-inner--text"><strong>{{session('error')}}</strong></span>
                <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
            </div>
            <br>
            @php
            $error = session('error');
            session()->forget('error');
            @endphp
            <script>
                alert("{{$error}}");
            </script>
        @endif
        <script>
            function checkAll() {
                var checkboxes = document.querySelectorAll('input[type="checkbox"]');
                var checkAllCheckbox = document.getElementById('checkAll');

                checkboxes.forEach(function(checkbox) {
                    checkbox.checked = checkAllCheckbox.checked;
                });
            }

            // document.addEventListener('DOMContentLoaded', function() {
            //         var input = document.getElementById('sku_input');
            //         input.focus();
            //         input.select();
            //     });
        </script>
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
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
                            <h5 class="card-title mg-b-0">{{ __('locale.From') }} {{$orders->firstItem()}} {{ __('locale.To') }} {{$orders->lastItem()}} {{ __('locale.Out Of') }} {{$orders->total()}} </h5>

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
                            $previousOrderDispatched = false;
                            $orderAboveDispatched = false;
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
                                if ($orderAboveDispatched == false && request('bypass_missing') == 1) {
                                    $orderAboveDispatched = $order->status === 3;
                                }
                                $isCurrentOrderDispatched = ($order->status == 3);
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
                                <tr @if (($customer?->orders?->count() ?? 0) > 1) class="bg-light" @endif>
                                    @if ($itemIndex == 0)
                                        <td rowspan="{{ $items_count }}"><input type="checkbox" name="ids[]" value="{{ $order->id }}" form="pdf"></td>
                                        <td rowspan="{{ $items_count }}">{{ $i + 1 }}</td>
                                        <td rowspan="{{ $items_count }}">
                                            {{ $order->reference_id }}<br>
                                            {{ $customer?->company ?? '' }}<br>
                                            {{ trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? '')) ?: 'Unknown customer' }}

                                        </td>
                                    @endif
                                    <td>
                                        @if ($variation ?? false)
                                            <strong>{{ $variation->sku }}</strong> - {{$variation->product->model ?? "Model not defined"}} - {{(isset($variation->storage)?$storages[$variation->storage] . " - " : null) . (isset($variation->color)?$colors[$variation->color]. " - ":null)}} <strong><u>{{ $grades[$variation->grade] ?? "Issue wih Grade" }}</u></strong>
                                        @endif
                                        @if ($order->delivery_note_url == null || $order->label_url == null)
                                            <a class="" href="
                                            @if (request('marketplace') == 4)
                                                {{ route('order.refurbed_reprint_label', ['order' => $order->id]) }}
                                            @else
                                                {{ url('order')}}/label/{{ $order->reference_id }}
                                            @endif

                                            ">
                                            @if ($order->delivery_note_url == null && request('marketplace') != 4)
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
                                        <br>
                                        {{$order->reference}}
                                        @if (!empty($order->label_url) || (int) $order->marketplace_id === 4)
                                            <div class="mt-2 d-flex flex-wrap gap-2">
                                                @if (!empty($order->label_url))
                                                <a href="{{ $order->label_url }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
                                                    Open Label PDF
                                                </a>
                                                @endif
                                                @if ((int) request('marketplace') === 4)
                                                <a href="{{ route('order.refurbed_reprint_label', ['order' => $order->id]) }}" target="_blank" rel="noopener" class="btn btn-outline-warning btn-sm">
                                                    Recreate Refurbed Label
                                                </a>
                                                @endif
                                            </div>
                                        @endif
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
                                        <td style="width:240px" class="text-success text-uppercase" id="copy_imei_{{ $order->id }}">
                                            @isset($stock->imei) {{ $stock->imei }}&nbsp; @endisset
                                            @isset($stock->serial_number) {{ $stock->serial_number }}&nbsp; @endisset
                                            @isset($order->processed_by) | {{ $admins[$order->processed_by][0] }} | @endisset
                                            @isset($stock->tester) ({{ $stock->tester }}) @endisset

                                        </td>
                                        @if ($item->quantity > 1 && $item->stock_id != null)
                                        @php
                                            $content2 = "Hi, here are the IMEIs/Serial numbers for this order. \n";
                                            foreach ($items as $im) {
                                                if($im->stock_id == null){ continue;}
                                                $content2 .= $im->stock->imei . $im->stock->serial_number . "\n";
                                            }
                                            $content2 .= "Regards \n".session('fname');
                                        @endphp

                                        <script>
                                            document.addEventListener('DOMContentLoaded', function() {
                                                var imeiElement = document.getElementById('copy_imei_{{ $order->id }}');

                                                // Add event listener to the IMEI element
                                                imeiElement.addEventListener('click', function() {
                                                    // Create a temporary input element to hold the text
                                                    var tempInput2 = document.createElement('textarea');
                                                    tempInput2.value = `{{ $content2 }}`; // Properly escape PHP content

                                                    // Append the input element to the body
                                                    document.body.appendChild(tempInput2);

                                                    // Select the text inside the input element
                                                    tempInput2.select();

                                                    // Copy the selected text to the clipboard
                                                    document.execCommand('copy');

                                                    // Remove the temporary input element
                                                    document.body.removeChild(tempInput2);

                                                    // Optionally, provide feedback to the user
                                                    alert('IMEI numbers copied to clipboard:\n' + tempInput2.value);
                                                });
                                            });
                                        </script>
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

                                        @if (isset($stock) && $item->status == 2 && !session()->has('refresh'))
                                            @if (request('marketplace') == 4)
                                            @else
                                            @php
                                                session()->put('refresh', true);
                                            @endphp
                                            <script>
                                                document.addEventListener('DOMContentLoaded', function() {
                                                    window.location.href = "{{url('order')}}/refresh/{{ $order->reference_id }}";
                                                });
                                            </script>
                                            @endif
                                        @endif

                                        @if ($item->status == 2)
                                            @if (count($items) < 2 && $item->quantity < 2)
            <form id="dispatch_{{ $i."_".$j }}" class="form-inline dispatch-form" method="post" action="{{url('order')}}/dispatch/{{ $order->id }}"
                @if (!request('packing'))
                 onsubmit="if($('#tracking_number_{{ $i }}_{{ $j }}').val() == 'J{{ $order->tracking_number }}') {return true;}else{event.stopPropagation(); event.preventDefault(); alert('Wrong Tracking');}"
                @endif
                 >
                @csrf
                <input type="hidden" name="sort" value="{{request('sort')}}">
                <input type="hidden" name="packing" value="{{request('packing')}}">
                <input type="hidden" name="no_invoice" value="{{request('no_invoice')}}">
                <div class="input-group">
                    @if (!request('packing'))
                        <input type="text" id="tester{{++$t}}" name="tester[]" placeholder="Tester" list="tester_list" class="form-control form-control-sm" style="max-width: 55px" maxlength="3" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'tester'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'tester', true); }">
                    @endif
                    @php $imeiInputId = !$orderAboveDispatched ? 'imei'.(++$ti) : null; @endphp
                    <input type="text" @if($imeiInputId) id="{{ $imeiInputId }}" @endif name="imei[]" placeholder="IMEI / Serial Number" class="form-control form-control-sm" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'imei'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'imei', true); }">

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

            <form id="dispatch_{{ $i."_".$j }}" class="form-inline dispatch-form" method="post" action="{{url('order')}}/dispatch/{{ $order->id }}"
                @if (!request('packing'))
                 onsubmit="if($('#tracking_number_{{ $i }}_{{ $j }}').val() == 'J{{ $order->tracking_number }}') {return true;}else{event.stopPropagation(); event.preventDefault();}"
                @endif
                 >
                @csrf
                <input type="hidden" name="sort" value="{{request('sort')}}">
                <input type="hidden" name="packing" value="{{request('packing')}}">
                <input type="hidden" name="no_invoice" value="{{request('no_invoice')}}">

                @for ($in = 1; $in <= $item->quantity; $in ++)

                    <div class="input-group">
                        @if (!request('packing'))
                        <input type="text" id="tester{{++$t}}" name="tester[]" placeholder="Tester" list="tester_list" class="form-control form-control-sm" style="max-width: 55px" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'tester'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'tester', true); }">
                        @endif
                        @php $imeiInputId = !$orderAboveDispatched ? 'imei'.(++$ti) : null; @endphp
                        <input type="text" @if($imeiInputId) id="{{ $imeiInputId }}" @endif name="imei[]" placeholder="IMEI / Serial Number" class="form-control form-control-sm" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'imei'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'imei', true); }" required>
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
            <form id="dispatch_{{ $i."_".$j }}" class="form-inline dispatch-form" method="post" action="{{url('order')}}/dispatch/{{ $order->id }}"
                @if (!request('packing'))
                 onsubmit="if($('#tracking_number_{{ $i }}_{{ $j }}').val() == 'J{{ $order->tracking_number }}') {return true;}else{event.stopPropagation(); event.preventDefault(); alert('Wrong Tracking');}"
                @endif
                 >
                @csrf
                <input type="hidden" name="sort" value="{{request('sort')}}">
                <input type="hidden" name="packing" value="{{request('packing')}}">
                <input type="hidden" name="no_invoice" value="{{request('no_invoice')}}">
                @foreach ($items as $itm)

                    @for ($in = 1; $in <= $itm->quantity; $in++)
                        <div class="input-group">
                            @if (!request('packing'))
                            <input type="text" id="tester{{++$t}}" name="tester[]" list="tester_list" placeholder="Tester" class="form-control form-control-sm" style="max-width: 55px" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'tester'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'tester', true); }">
                            @endif

                            @php $imeiInputId = !$orderAboveDispatched ? 'imei'.(++$ti) : null; @endphp
                            <input type="text" @if($imeiInputId) id="{{ $imeiInputId }}" @endif name="imei[]" placeholder="IMEI / Serial Number" class="form-control form-control-sm" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'imei'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'imei', true); }" required title="for SKU:{{ $itm->variation->sku }}">
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
                                            @if ((int) $order->marketplace_id === 4)
                                            <a class="dropdown-item" href="{{ route('order.refurbed_refresh', ['order' => $order->id]) }}">Refresh Refurbed Order</a>
                                            <a class="dropdown-item" href="{{ route('order.refurbed_sync_identifiers', ['id' => $order->id]) }}" onclick="return confirm('Push IMEI data to Refurbed for this order?');">Sync Refurbed IMEIs</a>
                                            <a class="dropdown-item" href="{{ route('order.refurbed_resend_shipped', ['id' => $order->id]) }}" onclick="return confirm('Resend Refurbed SHIPPED request for this order?');">Resend Refurbed Shipped</a>
                                            <a class="dropdown-item" href="{{ route('order.refurbed_reprint_label', ['order' => $order->id]) }}" target="_blank" rel="noopener">Reprint Refurbed Label</a>
                                            @else
                                            <a class="dropdown-item" href="{{url('order')}}/refresh/{{ $order->reference_id }}">Refresh</a>
                                            @endif
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
                                            <a class="dropdown-item" href="{{ url('order') }}/recheck/{{ $order->reference_id }}/true?marketplace={{ $order->marketplace_id }}" target="_blank">Invoice</a>
                                            @endif
                                            @if (in_array($order->status, [4,5,6]))
                                            <a class="dropdown-item" href="{{url('order')}}/export_refund_invoice/{{ $order->id }}" target="_blank">Refund Invoice</a>
                                            @endif
                                            @if (request('packing') == '1')
                                            <a class="dropdown-item" href="{{ route('order.packing_reprint', ['id' => $order->id]) }}@if(request()->filled('sort')){{ '?sort='.request('sort') }}@endif">Reprint Packing Docs</a>
                                            @endif
                                            @if (session('user')->hasPermission('view_api_data'))
                                            <a class="dropdown-item" href="{{ url('order') }}/recheck/{{ $order->reference_id }}/false/false/null/true/true?marketplace={{ $order->marketplace_id }}" target="_blank">Data</a>
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

                            {{-- V2: Stock Locks Display for Marketplace Orders --}}
                            @if($order->order_type_id == 3 && isset($order->id))
                                <tr>
                                    <td colspan="9" class="p-0">
                                        <div class="p-3 bg-light">
                                            @livewire('v2.stock-locks', ['orderId' => $order->id, 'showAll' => false], key('stock-locks-'.$order->id))
                                        </div>
                                    </td>
                                </tr>
                            @endif

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
                                            <td colspan="2">{{ trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? '').' '.($customer?->phone ?? '')) ?: 'Unknown customer' }}</td>

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
                                                <td colspan="2">{{ trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? '').' '.($customer?->phone ?? '')) ?: 'Unknown customer' }}</td>

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
                                                        <td colspan="2">{{ trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? '').' '.($customer?->phone ?? '')) ?: 'Unknown customer' }}</td>

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
                            @if (($customer?->orders?->count() ?? 0) > 1)
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
                                @foreach (($customer?->orders ?? collect()) as $ins => $ord)
                                    @if ($ord->id != $order->id)

                                        @foreach ($ord->order_items as $ind => $itm)

                                            <tr class="bg-secondary text-white">
                                                @if (!$def)
                                                    @php
                                                        $def = 1;
                                                    @endphp
                                                    <td rowspan="{{ count($customer->orders)-1 }}" colspan="2">{{ trim(optional($ord->customer)->first_name.' '.optional($ord->customer)->last_name.' '.optional($ord->customer)->phone) ?: 'Unknown customer' }}</td>
                                                @endif
                                                <td>{{ $ord->reference_id }}</td>
                                                <td>

                                                    @if ($itm->variation ?? false)
                                                        <strong>{{ $itm->variation->sku }}</strong>{{ " - " . (isset($itm->variation->product)?$itm->variation->product->model: 'Model not defined') . " - " . (isset($itm->variation->storage)?$storages[$itm->variation->storage] . " - " : null) . (isset($itm->variation->color)?$colors[$itm->variation->color]. " - ":null)}} <strong><u>{{ $grades[$itm->variation->grade] }}</u></strong>
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
                                    $order_charges = $order->order_charges;
                                @endphp
                                @foreach ($order->transactions as $transaction)
                                    @php
                                        $charge_amount = $order_charges->where('transaction_id', $transaction->id)->sum('amount');
                                    @endphp
                                    <tr class="bg-info text-white">
                                        <td colspan="2">{{ $transaction->transaction_type->name }}</td>
                                        <td colspan="3">{{ $transaction->description }}</td>
                                        <td>{{ $transaction->currency_id->sign. amount_formatter($transaction->amount) }}</td>
                                        <td>{{ $order_charges->where('transaction_id', $transaction->id)->sum('amount') }}</td>
                                        <td>{{ $transaction->date }}</td>
                                        <td></td>
                                    </tr>
                                @endforeach
                                @php
                                    $orphanCharges = $order_charges->whereNull('transaction_id');
                                @endphp
                                @foreach ($orphanCharges as $charge)
                                    @php
                                        $chargeCurrencySign = '';
                                        if (!empty($charge->currency_id) && is_object($charge->currency_id) && property_exists($charge->currency_id, 'sign')) {
                                            $chargeCurrencySign = $charge->currency_id->sign;
                                        } elseif (isset($currencies[$order->currency])) {
                                            $chargeCurrencySign = $currencies[$order->currency];
                                        }
                                    @endphp
                                    <tr class="bg-warning text-dark">
                                        <td colspan="2">{{ $charge->type ?? 'Charge' }}</td>
                                        <td colspan="3">{{ $charge->charge->name ?? 'Charge without transaction' }}</td>
                                        <td></td>
                                        <td>{{ $chargeCurrencySign . amount_formatter($charge->amount) }}</td>
                                        <td>{{ $charge->created_at ?? '' }}</td>
                                        <td></td>
                                    </tr>
                                @endforeach
                            @endif
                            @php
                                $i ++;
                            @endphp
                            @endif
                            @php
                                $previousOrderDispatched = $isCurrentOrderDispatched;
                            @endphp
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
                                <a class="dropdown-item" id="open_all_imei" href="#">Open All IMEI Details</a>
                                {{-- </div> --}}
                                <script type="text/javascript">


                                    document.getElementById("open_all_imei").onclick = function(){
                                        @php
                                            foreach ($imei_list as $imei) {
                                                echo "window.open('".url("imei")."?imei=".$imei."','_blank');
                                                ";
                                            }

                                        @endphp
                                    }
                                </script>
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

                    {{-- </div> --}}
                </div>
            </div>
        </div>

        <div class="modal" id="tracking_model">
            <div class="modal-dialog wd-xl-400" role="document">
                <div class="modal-content">
                    <div class="modal-body pd-sm-40">
                        <button aria-label="Close" class="close pos-absolute t-15 r-20 tx-26" data-bs-dismiss="modal"
                            type="button"><span aria-hidden="true">&times;</span></button>
                        <h5 class="modal-title mg-b-5">Update Order Tracking</h5>
                        <hr>
                        <form action="{{ url('order/tracking') }}" method="POST">
                            @csrf
                            <div class="form-group">
                                <label for="">Order Number</label>
                                <input class="form-control" name="tracking[id]" type="text" id="order_reference" disabled>
                            </div>
                            <div class="form-group">
                                <label for="">New Tracking Number</label>
                                <input class="form-control" placeholder="input New Tracking Number" id="tracking_number" name="tracking[number]" type="text" min="16" max="17" required>
                            </div>
                            <div class="form-group">
                                <label for="">Reason</label>
                                <textarea class="form-control" name="tracking[reason]">Address changed from</textarea>
                            </div>
                            <input type="hidden" id="order_id" name="tracking[order_id]" value="">

                            <button class="btn btn-primary btn-block">{{ __('locale.Submit') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal" id="correction_model">
            <div class="modal-dialog wd-xl-400" role="document">
                <div class="modal-content">
                    <div class="modal-body pd-sm-40">
                        <button aria-label="Close" class="close pos-absolute t-15 r-20 tx-26" data-bs-dismiss="modal"
                            type="button"><span aria-hidden="true">&times;</span></button>
                        <h5 class="modal-title mg-b-5">Update Order <strong id="override"></strong></h5>
                        <hr>
                        <form action="{{ url('order/correction') }}" method="POST" onsubmit="if ($('#correction_imei').val() == ''){ if (confirm('Remove IMEI from Order')){return true;}else{event.stopPropagation(); event.preventDefault();};};">
                            @csrf
                            <div class="form-group">
                                <label for="">Order Number</label>
                                <input class="form-control" name="correction[id]" type="text" id="order_reference" disabled>
                            </div>
                            <div class="form-group">
                                <label for="">Tester</label>
                                <input class="form-control" placeholder="input Tester Initial" name="correction[tester]" type="text" list="tester_list">
                            </div>
                            <div class="form-group">
                                <label for="">IMEI / Serial Number</label>
                                <input class="form-control" placeholder="input IMEI / Serial Number" id="correction_imei" name="correction[imei]" type="text">
                            </div>
                            <div class="form-group">
                                <label for="">Reason</label>
                                <textarea class="form-control" name="correction[reason]">Wrong Dispatch</textarea>
                            </div>
                            <input type="hidden" id="item_id" name="correction[item_id]" value="">

                            <button class="btn btn-primary btn-block">{{ __('locale.Submit') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal" id="replacement_model">
            <div class="modal-dialog wd-xl-400" role="document">
                <div class="modal-content">
                    <div class="modal-body pd-sm-40">
                        <button aria-label="Close" class="close pos-absolute t-15 r-20 tx-26" data-bs-dismiss="modal"
                            type="button"><span aria-hidden="true">&times;</span></button>
                        <h3 class="modal-title mg-b-5">Update Order</h3>
                        <hr>
                        @php
                            $sessionUser = session('user');
                            $replacement_url = url('order/replacement/1');
                            if ($sessionUser && isset($sessionUser->role_id) && (int) $sessionUser->role_id === 4) {
                                $replacement_url = url('order/replacement');
                            }
                        @endphp
                        <form action="{{ $replacement_url }}" method="POST">
                            @csrf
                            <div class="form-group">
                                <label for="">Order Number</label>
                                <input class="form-control" name="replacement[id]" type="text" id="order_reference" readonly>
                            </div>
                            <h4>Replace</h4>
                            <div class="form-group bs_hide">
                                <label for="">Move to</label>
                                <select name="replacement[grade]" id="move_grade" class="form-control form-select" required>
                                    <option value="">Move to</option>
                                    @foreach ($grades as $id=>$grade)
                                        <option value="{{ $id }}">{{ $grade }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group bs_hide">
                                <label for="">Reason</label>
                                <textarea class="form-control" name="replacement[reason]"></textarea>
                            </div>
                            <h4>With</h4>
                            <div class="form-group">
                                <label for="">Tester</label>
                                <input class="form-control" placeholder="input Tester Initial" name="replacement[tester]" list="tester_list" type="text">
                            </div>
                            <div class="form-group">
                                <label for="">IMEI / Serial Number</label>
                                <input class="form-control" placeholder="input IMEI / Serial Number" name="replacement[imei]" type="text" required>
                            </div>
                            <input type="hidden" id="item_id" name="replacement[item_id]" value="">

                            <button class="btn btn-primary btn-block">{{ __('locale.Submit') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        {{-- @if (session('user_id') == 1)
            @dd($orders)

        @endif --}}
    @endsection

    @section('scripts')

    <script>

        document.addEventListener('DOMContentLoaded', function () {
            try {
                if (!window.sessionStorage) {
                    return;
                }

                const storedTracking = window.sessionStorage.getItem('packing_tracking_verify');
                const packingModeActive = document.getElementById('packing')?.checked === true;

                if (!storedTracking || !packingModeActive) {
                    return;
                }

                const overlay = document.getElementById('tracking-verify-overlay');
                const input = document.getElementById('tracking-verify-input');
                const expected = document.getElementById('tracking-verify-expected');
                const feedback = document.getElementById('tracking-verify-feedback');

                if (!overlay || !input || !expected || !feedback) {
                    window.sessionStorage.removeItem('packing_tracking_verify');
                    return;
                }

                const normalizedTarget = storedTracking.trim().toUpperCase();
                let isCurrentlyMatched = false;

                function closeOverlay() {
                    overlay.classList.remove('show');
                    overlay.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('tracking-verify-active');
                    window.sessionStorage.removeItem('packing_tracking_verify');
                    input.removeEventListener('input', handleInput);
                    document.removeEventListener('keydown', handleEscape, true);
                }

                function handleEscape(event) {
                    if (event.key === 'Escape') {
                        if (!isCurrentlyMatched) {
                            event.preventDefault();
                            event.stopPropagation();
                            feedback.textContent = '⚠ Please scan the correct tracking number before closing.';
                            feedback.classList.remove('tracking-verify-match', 'text-success', 'text-muted');
                            feedback.classList.add('tracking-verify-mismatch');
                            feedback.style.fontWeight = 'bold';
                            input.focus();
                            return false;
                        }
                        closeOverlay();
                    }
                }

                function handleInput() {
                    let value = (input.value || '').trim().toUpperCase();

                    // If scanned value starts with JJ, remove one J
                    if (value.startsWith('JJ')) {
                        value = value.substring(1);
                    }

                    if (!value.length) {
                        feedback.textContent = 'The popup closes automatically once the number matches.';
                        feedback.classList.remove('tracking-verify-mismatch', 'tracking-verify-match', 'text-success');
                        feedback.classList.add('text-muted');
                        feedback.style.fontWeight = '';
                        isCurrentlyMatched = false;
                        return;
                    }

                    // Accept exact match OR if expected tracking starts with "JJ" (any scan is valid)
                    const isMatch = value === normalizedTarget || normalizedTarget.startsWith('JJ');

                    if (isMatch) {
                        isCurrentlyMatched = true;
                        feedback.textContent = '✓ Tracking number matched. Great job!';
                        feedback.classList.remove('tracking-verify-mismatch', 'text-muted');
                        feedback.classList.add('tracking-verify-match', 'text-success');
                        feedback.style.fontWeight = 'bold';
                        feedback.style.fontSize = '16px';
                        input.style.borderColor = '#22c55e';
                        input.style.borderWidth = '2px';
                        input.readOnly = true;

                        setTimeout(() => {
                            closeOverlay();
                            // Focus the last IMEI input after overlay closes
                            setTimeout(() => {
                                const imeiInputs = document.querySelectorAll('input[id^="imei"]');
                                if (imeiInputs.length > 0) {
                                    const lastImeiInput = imeiInputs[imeiInputs.length - 1];
                                    lastImeiInput.focus();
                                    try {
                                        lastImeiInput.select();
                                    } catch (e) {
                                        // Ignore selection errors
                                    }
                                }
                            }, 100);
                        }, 800);
                    } else {
                        isCurrentlyMatched = false;
                        feedback.textContent = 'Tracking number does not match. Please try again.';
                        feedback.classList.remove('tracking-verify-match', 'text-success', 'text-muted');
                        feedback.classList.add('tracking-verify-mismatch');
                        feedback.style.fontWeight = '';
                        feedback.style.fontSize = '';
                        input.style.borderColor = '';
                        input.style.borderWidth = '';
                    }
                }

                expected.textContent = storedTracking;
                overlay.classList.add('show');
                overlay.setAttribute('aria-hidden', 'false');
                document.body.classList.add('tracking-verify-active');
                input.value = '';

                // Focus input after a small delay to ensure overlay is fully rendered
                setTimeout(() => {
                    try {
                        input.focus({ preventScroll: false });
                        input.select();
                    } catch (focusError) {
                        input.focus();
                    }
                }, 100);

                input.addEventListener('input', handleInput);
                document.addEventListener('keydown', handleEscape, true);
                overlay.addEventListener('click', function (event) {
                    if (event.target === overlay) {
                        if (!isCurrentlyMatched) {
                            event.preventDefault();
                            event.stopPropagation();
                            feedback.textContent = '⚠ Please scan the correct tracking number before closing.';
                            feedback.classList.remove('tracking-verify-match', 'text-success', 'text-muted');
                            feedback.classList.add('tracking-verify-mismatch');
                            feedback.style.fontWeight = 'bold';
                            input.focus();
                            return false;
                        }
                        closeOverlay();
                    }
                });
            } catch (error) {
                console.debug('Tracking verification prompt failed to initialize.', error);
                if (window.sessionStorage) {
                    window.sessionStorage.removeItem('packing_tracking_verify');
                }
            }
        });

        function moveToNextInput(currentInput, prefix, moveUp = false) {
            const inputs = document.querySelectorAll(`input[id^="${prefix}"]`);
            const currentIndex = Array.from(inputs).indexOf(currentInput);
            if (currentIndex !== -1) {
                if (moveUp && currentIndex > 0) {
                    inputs[currentIndex - 1].focus();
                } else if (!moveUp && currentIndex < inputs.length - 1) {
                    inputs[currentIndex + 1].focus();
                }
            }
        }
        @if (request('invoice'))

            var id = `imei{{$ti}}`;
            window.onload = function() {
                var elem = document.getElementById(id);
                if (elem) {
                    elem.focus();
                    elem.click();
                    setTimeout(function(){
                        if (elem) elem.focus();
                        $('#imei').focus();
                    }, 500);
                }
            };
            document.addEventListener('DOMContentLoaded', function() {
                var input = document.getElementById(id);
                if (input) {
                    input.focus();
                    input.select();
                    input.click();
                    setTimeout(function(){
                        if (input) input.focus();
                        $('#imei').focus();
                    }, 500);
                }
            });
            if (id == 'imei0') {
                var prevBtn = document.querySelector('[rel="prev"]');
                if (prevBtn) prevBtn.click();
            }
        @endif

        @if (request('packing'))

            var id = `imei{{$ti}}`;
            window.onload = function() {
                // Check if tracking verification overlay is active
                var trackingOverlay = document.getElementById('tracking-verify-overlay');
                var trackingInput = document.getElementById('tracking-verify-input');

                if (trackingOverlay && trackingOverlay.classList.contains('show') && trackingInput) {
                    // If tracking overlay is active, focus on tracking input instead
                    setTimeout(function() {
                        trackingInput.focus();
                        try {
                            trackingInput.select();
                        } catch (e) {
                            // Ignore selection errors
                        }
                    }, 200);
                    return;
                }

                // Find the first empty IMEI input and focus on it
                var allImeiInputs = document.querySelectorAll('input[id^="imei"]');
                var firstEmptyInput = null;

                for (var i = 0; i < allImeiInputs.length; i++) {
                    if (allImeiInputs[i].value === '') {
                        firstEmptyInput = allImeiInputs[i];
                        break;
                    }
                }

                if (firstEmptyInput) {
                    firstEmptyInput.focus();
                    firstEmptyInput.click();
                    setTimeout(function(){
                        if (firstEmptyInput) firstEmptyInput.focus();
                    }, 500);
                }

                document.addEventListener('DOMContentLoaded', function() {
                    // Check again for tracking overlay in DOMContentLoaded
                    var trackingOverlay = document.getElementById('tracking-verify-overlay');
                    var trackingInput = document.getElementById('tracking-verify-input');

                    if (trackingOverlay && trackingOverlay.classList.contains('show') && trackingInput) {
                        setTimeout(function() {
                            trackingInput.focus();
                            try {
                                trackingInput.select();
                            } catch (e) {
                                // Ignore selection errors
                            }
                        }, 200);
                        return;
                    }

                    // Find the first empty IMEI input and focus on it
                    var allImeiInputs = document.querySelectorAll('input[id^="imei"]');
                    var firstEmptyInput = null;

                    for (var i = 0; i < allImeiInputs.length; i++) {
                        if (allImeiInputs[i].value === '') {
                            firstEmptyInput = allImeiInputs[i];
                            break;
                        }
                    }

                    if (firstEmptyInput) {
                        firstEmptyInput.focus();
                        firstEmptyInput.select();
                        firstEmptyInput.click();
                        setTimeout(function(){
                            if (firstEmptyInput) firstEmptyInput.focus();
                        }, 500);
                    }
                });
            };

            if (id == 'imei0') {
                var prevBtn = document.querySelector('[rel="prev"]');
                if (prevBtn) prevBtn.click();
            }
        @endif
        $('#tracking_model').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget) // Button that triggered the modal
            var reference = button.data('bs-reference') // Extract info from data-* attributesv
            var order = button.data('bs-order') // Extract info from data-* attributes
            // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
            // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
            var modal = $(this)
            modal.find('.modal-body #order_reference').val(reference)
            modal.find('.modal-body #order_id').val(order)
            })
        $('#correction_model').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget) // Button that triggered the modal
            var reference = button.data('bs-reference') // Extract info from data-* attributesv
            var item = button.data('bs-item') // Extract info from data-* attributes
            // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
            // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
            var override = button.data('bs-override') // Extract info from data-* attributes
            var modal = $(this)
            if(override){
                modal.find('.modal-title #override').text('(Override)')
                // change form action
                modal.find('form').attr('action', "{{ url('order/correction/true') }}")
            }
            modal.find('.modal-body #order_reference').val(reference)
            modal.find('.modal-body #item_id').val(item)
            })
        $('#replacement_model').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget) // Button that triggered the modal
            var reference = button.data('bs-reference') // Extract info from data-* attributesv
            var retun = button.data('bs-return') // Extract info from data-* attributesv
            var item = button.data('bs-item') // Extract info from data-* attributes
            // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
            // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
            var modal = $(this)
            if(retun == 1){
                modal.find('.modal-body .bs_hide').addClass('d-none')
                modal.find('.modal-body #move_grade').removeAttr('required')
            }

            modal.find('.modal-body #order_reference').val(reference)
            modal.find('.modal-body #item_id').val(item)
            })

        if ($.fn.select2) {
            $('.select2').select2({
                placeholder: "Exclude Topups",
            });
        } else {
            console.warn('select2 is not loaded');
        }

        document.addEventListener('DOMContentLoaded', function () {
            const dispatchForms = document.querySelectorAll('form.dispatch-form');
            dispatchForms.forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    const imeiInputs = Array.from(form.querySelectorAll('input[name="imei[]"]'));
                    if (imeiInputs.length <= 1) {
                        return;
                    }

                    const seen = new Set();
                    for (const input of imeiInputs) {
                        const value = (input.value || '').trim();
                        if (value === '') {
                            continue;
                        }

                        if (seen.has(value)) {
                            event.preventDefault();
                            event.stopPropagation();
                            alert('Duplicate IMEI/Serial detected: ' + value + '. Please ensure each item has a unique identifier.');
                            try {
                                input.focus();
                                input.select();
                            } catch (e) {
                                input.focus();
                            }
                            return false;
                        }

                        seen.add(value);
                    }
                });
            });
        });

        document.addEventListener('click', function (event) {
            const trigger = event.target.closest('.copy-support-context');
            if (!trigger) {
                return;
            }

            event.preventDefault();
            const payload = trigger.getAttribute('data-copy-text') || '';
            if (!payload.length) {
                return;
            }

            copySupportContext(payload)
                .then(function () {
                    supportCopyFeedback(trigger, false);
                })
                .catch(function () {
                    supportCopyFeedback(trigger, true);
                });
        });

        function copySupportContext(text) {
            const canUseClipboard = typeof navigator !== 'undefined'
                && navigator.clipboard
                && (typeof window.isSecureContext === 'undefined' || window.isSecureContext);

            if (canUseClipboard) {
                return navigator.clipboard.writeText(text);
            }

            return new Promise(function (resolve, reject) {
                try {
                    const scratch = document.createElement('textarea');
                    scratch.value = text;
                    scratch.setAttribute('readonly', '');
                    scratch.style.position = 'absolute';
                    scratch.style.left = '-9999px';
                    document.body.appendChild(scratch);
                    scratch.select();
                    const successful = document.execCommand('copy');
                    document.body.removeChild(scratch);

                    if (!successful) {
                        reject(new Error('Copy command unsuccessful'));
                        return;
                    }

                    resolve();
                } catch (error) {
                    reject(error);
                }
            });
        }

        function supportCopyFeedback(trigger, errored) {
            if (!trigger) {
                return;
            }

            const originalLabel = trigger.getAttribute('data-reset-label') || trigger.textContent || 'Copy chat context';
            trigger.setAttribute('data-reset-label', originalLabel);

            const feedbackClass = errored ? 'text-danger' : 'text-success';
            const feedbackLabel = errored ? 'Copy failed' : 'Copied';

            trigger.textContent = feedbackLabel;
            trigger.classList.add(feedbackClass);

            setTimeout(function () {
                trigger.textContent = trigger.getAttribute('data-reset-label') || originalLabel;
                trigger.classList.remove(feedbackClass);
            }, 1400);
        }

        // function get_customer_previous_orders(customer_id, order_id){
        //     let url = "{{ url('order/get_b2c_orders_by_customer_json') }}/".concat(customer_id).concat('/').concat(order_id);
        //     $.ajax({
        //         url: url,
        //         type: 'GET',
        //         success: function(data){
        //             console.log(data)
        //             return data;
        //         }
        //     })
        // }
    </script>


    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const preferenceCard = document.getElementById('qz-printer-preferences-card');
            if (!preferenceCard) {
                return;
            }

            const printerEndpoint = @json(route('order.store_printer_preferences'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const qzStatusEl = document.getElementById('qz-order-status');
            const invoiceDisplay = document.getElementById('invoice-printer-display');
            const labelDisplay = document.getElementById('label-printer-display');
            const toneClasses = ['text-success', 'text-danger', 'text-warning', 'text-muted'];

            const storageProviders = [];
            try { storageProviders.push(window.localStorage); } catch (error) { /* ignore */ }
            try { storageProviders.push(window.sessionStorage); } catch (error) { /* ignore */ }

            const preferences = {
                invoice: {
                    display: invoiceDisplay,
                    button: document.getElementById('change-invoice-printer-btn'),
                    storageKeys: ['Invoice_Printer', 'A4_Printer', 'Default_Printer'],
                    payloadKey: 'a4_printer',
                    dialogTitle: 'Select Invoice Printer',
                    dialogDescription: 'Choose the printer QZ Tray should use for invoices and delivery notes.',
                    current: @json(session('a4_printer')),
                },
                label: {
                    display: labelDisplay,
                    button: document.getElementById('change-label-printer-btn'),
                    storageKeys: ['Label_Printer', 'Sticker_Printer', 'Shipping_Printer', 'DHL_Printer'],
                    payloadKey: 'label_printer',
                    dialogTitle: 'Select Label Printer',
                    dialogDescription: 'Choose the printer QZ Tray should use for shipping labels.',
                    current: @json(session('label_printer')),
                }
            };

            function updateDisplay(pref, value) {
                if (!pref.display) {
                    return;
                }

                const resolved = value || null;
                pref.display.textContent = resolved || 'Not set';
                pref.display.classList.toggle('text-secondary', Boolean(resolved));
                pref.display.classList.toggle('text-danger', !resolved);
                pref.current = resolved;
            }

            function resolveFromStorage(keys) {
                for (const store of storageProviders) {
                    if (!store) {
                        continue;
                    }
                    for (const key of keys) {
                        try {
                            const value = store.getItem(key);
                            if (value) {
                                return value;
                            }
                        } catch (error) {
                            console.debug('Printer storage read failed', key, error);
                        }
                    }
                }
                return null;
            }

            Object.values(preferences).forEach(pref => {
                updateDisplay(pref, pref.current || resolveFromStorage(pref.storageKeys));
            });

            function updateQzStatus(message, tone = 'muted') {
                if (!qzStatusEl) {
                    return;
                }
                qzStatusEl.textContent = message;
                toneClasses.forEach(cls => qzStatusEl.classList.remove(cls));
                const toneMap = {
                    success: 'text-success',
                    danger: 'text-danger',
                    warning: 'text-warning',
                    muted: 'text-muted'
                };
                qzStatusEl.classList.add(toneMap[tone] || 'text-muted');
            }

            function qzIsReady() {
                try {
                    if (typeof window.isQzConnected === 'function' && window.isQzConnected()) {
                        return true;
                    }
                } catch (error) {
                    console.debug('QZ status check failed.', error);
                }
                return typeof qz !== 'undefined' && qz.websocket && qz.websocket.isActive();
            }

            function refreshQzStatus() {
                const connected = qzIsReady();
                updateQzStatus(connected ? 'Connected ✓' : 'Not connected', connected ? 'success' : 'warning');
            }

            refreshQzStatus();
            const qzStatusInterval = setInterval(refreshQzStatus, 6000);
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    refreshQzStatus();
                }
            });
            window.addEventListener('beforeunload', function () {
                clearInterval(qzStatusInterval);
            });

            async function ensureQzConnection(timeout = 7000) {
                if (typeof qz === 'undefined' || !qz.websocket) {
                    throw new Error('QZ Tray libraries have not loaded yet.');
                }

                if (typeof window.ensureQzConnection === 'function') {
                    await window.ensureQzConnection(timeout);
                    return;
                }

                if (qz.websocket.isActive()) {
                    return;
                }

                try {
                    qz.websocket.connect();
                } catch (error) {
                    console.debug('Immediate QZ connect attempt failed.', error);
                }

                await new Promise((resolve, reject) => {
                    const start = Date.now();
                    const timer = setInterval(() => {
                        if (qz.websocket.isActive()) {
                            clearInterval(timer);
                            resolve();
                        } else if (Date.now() - start > timeout) {
                            clearInterval(timer);
                            reject(new Error('Timed out waiting for QZ Tray connection'));
                        }
                    }, 250);
                });
            }

            function promptForPrinterDialog(options) {
                return new Promise(resolve => {
                    const overlay = document.createElement('div');
                    overlay.style.position = 'fixed';
                    overlay.style.top = '0';
                    overlay.style.left = '0';
                    overlay.style.width = '100vw';
                    overlay.style.height = '100vh';
                    overlay.style.background = 'rgba(15, 23, 42, 0.45)';
                    overlay.style.zIndex = '2000';
                    overlay.style.display = 'flex';
                    overlay.style.alignItems = 'center';
                    overlay.style.justifyContent = 'center';
                    overlay.style.padding = '16px';

                    const dialog = document.createElement('div');
                    dialog.style.background = '#ffffff';
                    dialog.style.borderRadius = '12px';
                    dialog.style.padding = '24px';
                    dialog.style.width = 'min(480px, 100%)';
                    dialog.style.maxHeight = '85vh';
                    dialog.style.overflow = 'auto';
                    dialog.style.boxShadow = '0 18px 48px rgba(15,23,42,0.35)';
                    dialog.style.fontFamily = 'Arial, sans-serif';

                    const title = document.createElement('h2');
                    title.textContent = options.title;
                    title.style.margin = '0 0 10px';

                    const description = document.createElement('p');
                    description.textContent = options.description;
                    description.style.margin = '0 0 16px';
                    description.style.fontSize = '14px';
                    description.style.color = '#475569';

                    const select = document.createElement('select');
                    select.style.width = '100%';
                    select.style.padding = '10px 12px';
                    select.style.border = '1px solid #cbd5f5';
                    select.style.borderRadius = '8px';
                    select.style.marginBottom = '20px';
                    select.style.fontSize = '15px';

                    options.printers.forEach(printer => {
                        const option = document.createElement('option');
                        option.value = printer;
                        option.textContent = printer;
                        select.appendChild(option);
                    });

                    if (options.preselect && options.printers.includes(options.preselect)) {
                        select.value = options.preselect;
                    }

                    const actions = document.createElement('div');
                    actions.style.display = 'flex';
                    actions.style.justifyContent = 'flex-end';
                    actions.style.gap = '12px';

                    function closeDialog(value) {
                        document.body.removeChild(overlay);
                        document.removeEventListener('keydown', handleEscape, true);
                        resolve(value);
                    }

                    const cancelBtn = document.createElement('button');
                    cancelBtn.type = 'button';
                    cancelBtn.textContent = 'Cancel';
                    cancelBtn.className = 'btn btn-light';
                    cancelBtn.addEventListener('click', () => closeDialog(null));

                    const confirmBtn = document.createElement('button');
                    confirmBtn.type = 'button';
                    confirmBtn.textContent = 'Use Printer';
                    confirmBtn.className = 'btn btn-primary';
                    confirmBtn.addEventListener('click', () => closeDialog(select.value || null));

                    function handleEscape(event) {
                        if (event.key === 'Escape') {
                            closeDialog(null);
                        }
                    }

                    overlay.addEventListener('click', event => {
                        if (event.target === overlay) {
                            closeDialog(null);
                        }
                    });

                    actions.appendChild(cancelBtn);
                    actions.appendChild(confirmBtn);

                    dialog.appendChild(title);
                    dialog.appendChild(description);
                    dialog.appendChild(select);
                    dialog.appendChild(actions);

                    overlay.appendChild(dialog);
                    document.body.appendChild(overlay);

                    document.addEventListener('keydown', handleEscape, true);
                    setTimeout(() => select.focus(), 60);
                });
            }

            function persistPreference(pref, printerName) {
                storageProviders.forEach(store => {
                    if (!store) {
                        return;
                    }
                    pref.storageKeys.forEach(key => {
                        try {
                            store.setItem(key, printerName);
                        } catch (error) {
                            console.debug('Unable to persist printer preference', key, error);
                        }
                    });
                });

                if (!printerEndpoint) {
                    return;
                }

                const payload = {};
                payload[pref.payloadKey] = printerName;

                fetch(printerEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                }).catch(error => console.debug('Failed to sync printer preference with server.', error));
            }

            async function handlePrinterChange(pref) {
                if (!pref.button) {
                    return;
                }

                if (typeof qz === 'undefined') {
                    alert('QZ Tray libraries are still loading. Please try again in a moment.');
                    return;
                }

                pref.button.disabled = true;
                updateQzStatus('Connecting...', 'warning');

                try {
                    await ensureQzConnection();
                    refreshQzStatus();

                    const printers = await qz.printers.find();
                    if (!printers || !printers.length) {
                        updateQzStatus('No printers detected', 'danger');
                        alert('No printers detected by QZ Tray.');
                        return;
                    }

                    const selection = await promptForPrinterDialog({
                        title: pref.dialogTitle,
                        description: pref.dialogDescription,
                        printers,
                        preselect: pref.current,
                    });

                    if (!selection) {
                        return;
                    }

                    persistPreference(pref, selection);
                    updateDisplay(pref, selection);
                    updateQzStatus('Printer saved ✓', 'success');
                } catch (error) {
                    console.error('Unable to update printer preference', error);
                    updateQzStatus('Update failed', 'danger');
                    alert(error.message || 'Unable to update printer preference.');
                } finally {
                    pref.button.disabled = false;
                }
            }

            Object.values(preferences).forEach(pref => {
                if (pref.button) {
                    pref.button.addEventListener('click', () => handlePrinterChange(pref));
                }
            });
        });
    </script>


    @endsection
