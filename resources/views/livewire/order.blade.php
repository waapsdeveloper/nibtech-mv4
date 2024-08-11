@extends('layouts.app')

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


        <!-- breadcrumb -->
            <div class="breadcrumb-header justify-content-between">
                <div class="left-content">
                {{-- <span class="main-content-title mg-b-0 mg-b-lg-1">Orders</span> --}}
                <a href="{{url('refresh_order')}}" target="_blank" class="mg-b-0 mg-b-lg-1 btn btn-primary">Recheck All</a>
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
                            @foreach ($order_statuses as $status)
                                <option value="{{$status->id}}" @if(isset($_GET['status']) && $status->id == $_GET['status']) {{'selected'}}@endif>{{$status->name}}</option>
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
                <div class="col-lg-2 col-xl-2 col-md-4 col-sm-6">
                    <div class="form-floating">
                        <input type="text" class="form-control" name="imei" placeholder="Enter IMEI" value="@isset($_GET['imei']){{$_GET['imei']}}@endisset">
                        <label for="">IMEI</label>
                    </div>
                </div>
                <div class="col-lg-2 col-xl-2 col-md-4 col-sm-6">
                    {{-- <div class="form-floating"> --}}
                        <select id="adm_input" name="adm" class="form-control form-select" data-bs-placeholder="Select Processed By">
                            <option value="">Processed by</option>
                            <option value="0">None</option>
                            @foreach ($admins as $adm)
                                <option value="{{$adm->id}}" @if(isset($_GET['adm']) && $adm->id == $_GET['adm']) {{'selected'}}@endif>{{$adm->first_name." ".$adm->last_name}}</option>
                            @endforeach
                        </select>
                        {{-- <label for="adm_input">Processed By</label> --}}
                    {{-- </div> --}}
                </div>
                <div class="col-lg-2 col-xl-2 col-md-4 col-sm-6">
                    <div class="form-floating">
                        <input type="text" class="form-control" name="tracking_number" placeholder="Enter Tracking Number" value="@isset($_GET['tracking_number']){{$_GET['tracking_number']}}@endisset">
                        <label for="">Tracking Number</label>
                    </div>
                </div>

                <input type="hidden" name="page" value="{{ Request::get('page') }}">
                <input type="hidden" name="per_page" value="{{ Request::get('per_page') }}">
                <input type="hidden" name="sort" value="{{ Request::get('sort') }}">
                @if (Request::get('care') == 1)
                    <input type="hidden" name="care" value="{{ Request::get('care') }}">
                @endif
            </div>

        </form>
        <div class="d-flex justify-content-between">
            <div class="">
                <a href="{{url('order')}}" class="btn btn-link">All Order</a>
                <a href="{{url('order')}}?status=2" class="btn btn-link">Pending Order ({{ $pending_orders_count }})</a>
                <a href="{{url('order')}}?care=1" class="btn btn-link">Conversation</a>
            </div>
            <div class="d-flex">

                <input type="text" class="form-control pd-x-20" name="last_order" placeholder="Last Order (Optional)" value="" form="picklist" style="width: 170px;">
                <button class="btn btn-sm btn-secondary pd-x-20 " type="submit" form="picklist" name="order" value="1">Order List</button>
                <button class="btn btn-sm btn-secondary pd-x-20 " type="submit" form="picklist" name="picklist" value="1">Pick List</button>
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
            <input type="hidden" name="page" value="{{ Request::get('page') }}">
            <input type="hidden" name="per_page" value="{{ Request::get('per_page') }}">
            @if (Request::get('care') == 1)
                <input type="hidden" name="care" value="{{ Request::get('care') }}">
            @endif
        </form>
        <br>
        <div class="row">
            <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>Orders</h4></center>
            </div>
        </div>
        <br>

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
        session()->forget('error');
        @endphp
        @endif
        <script>
            function checkAll() {
                var checkboxes = document.querySelectorAll('input[type="checkbox"]');
                var checkAllCheckbox = document.getElementById('checkAll');

                checkboxes.forEach(function(checkbox) {
                    checkbox.checked = checkAllCheckbox.checked;
                });
            }

            document.addEventListener('DOMContentLoaded', function() {
                    var input = document.getElementById('skuInput');
                    input.focus();
                    input.select();
                });
        </script>
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between">
                            <h4 class="card-title mg-b-0">
                                <label>
                                    <input type="checkbox" id="checkAll" onclick="checkAll()"> Check All
                                </label>
                                <input class="btn btn-sm btn-secondary" type="submit" value="Print Labels" form="pdf">
                            </h4>
                            <h5 class="card-title mg-b-0">{{ __('locale.From') }} {{$orders->firstItem()}} {{ __('locale.To') }} {{$orders->lastItem()}} {{ __('locale.Out Of') }} {{$orders->total()}} </h5>

                            <div class=" mg-b-0">
                                <form method="get" action="" class="row form-inline">
                                    <label for="perPage" class="card-title inline">Sort:</label>
                                    <select name="sort" class="form-select form-select-sm" id="perPage" onchange="this.form.submit()">
                                        <option value="1" {{ Request::get('sort') == 1 ? 'selected' : '' }}>Order DESC</option>
                                        <option value="2" {{ Request::get('sort') == 2 ? 'selected' : '' }}>Order ASC</option>
                                        <option value="3" {{ Request::get('sort') == 3 ? 'selected' : '' }}>Name DESC</option>
                                        <option value="4" {{ Request::get('sort') == 4 ? 'selected' : '' }}>Name ASC</option>
                                    </select>
                                    {{-- <button type="submit">Apply</button> --}}
                                    <input type="hidden" name="start_date" value="{{ Request::get('start_date') }}">
                                    <input type="hidden" name="start_time" value="{{ Request::get('start_time') }}">
                                    <input type="hidden" name="end_date" value="{{ Request::get('end_date') }}">
                                    <input type="hidden" name="end_time" value="{{ Request::get('end_time') }}">
                                    <input type="hidden" name="status" value="{{ Request::get('status') }}">
                                    <input type="hidden" name="adm" value="{{ Request::get('adm') }}">
                                    <input type="hidden" name="order_id" value="{{ Request::get('order_id') }}">
                                    <input type="hidden" name="sku" value="{{ Request::get('sku') }}">
                                    <input type="hidden" name="imei" value="{{ Request::get('imei') }}">
                                    <input type="hidden" name="page" value="{{ Request::get('page') }}">
                                    <input type="hidden" name="per_page" value="{{ Request::get('per_page') }}">
                                    <input type="hidden" name="care" value="{{ Request::get('care') }}">
                                </form>
                                <form method="get" action="" class="row form-inline">
                                    <label for="perPage" class="card-title inline">per page:</label>
                                    <select name="per_page" class="form-select form-select-sm" id="perPage" onchange="this.form.submit()">
                                        <option value="10" {{ Request::get('per_page') == 10 ? 'selected' : '' }}>10</option>
                                        <option value="20" {{ Request::get('per_page') == 20 ? 'selected' : '' }}>20</option>
                                        <option value="50" {{ Request::get('per_page') == 50 ? 'selected' : '' }}>50</option>
                                        <option value="100" {{ Request::get('per_page') == 100 ? 'selected' : '' }}>100</option>
                                    </select>
                                    {{-- <button type="submit">Apply</button> --}}
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
                                    <input type="hidden" name="page" value="{{ Request::get('page') }}">
                                    <input type="hidden" name="sort" value="{{ Request::get('sort') }}">
                                    @if (Request::get('care') == 1)
                                        <input type="hidden" name="care" value="{{ Request::get('care') }}">
                                    @endif
                                </form>
                            </div>

                        </div>
                    </div>
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
                                        <th><small><b>IMEI</b></small></th>
                                        <th><small><b>Creation Date | TN</b></small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = $orders->firstItem() - 1;
                                        $id = [];
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
                                        @endphp

                                        @foreach ($items as $itemIndex => $item)
                                            <tr @if ($order->customer->orders->count() > 1) class="bg-light" @endif>
                                                @if ($itemIndex == 0)
                                                    <td rowspan="{{ count($items) }}"><input type="checkbox" name="ids[]" value="{{ $order->id }}" form="pdf"></td>
                                                    <td rowspan="{{ count($items) }}">{{ $i + 1 }}</td>
                                                    <td rowspan="{{ count($items) }}">{{ $order->reference_id }}</td>
                                                @endif
                                                <td>
                                                    @if ($item->variation ?? false)
                                                        <strong>{{ $item->variation->sku }}</strong> - {{$item->variation->product->model ?? "Model not defined"}} - {{(isset($item->variation->storage)?$storages[$item->variation->storage] . " - " : null) . (isset($item->variation->color)?$colors[$item->variation->color]. " - ":null)}} <strong><u>{{ $grades[$item->variation->grade] }}</u></strong>
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
                                                        <a class="" href="https://backmarket.fr/bo_merchant/customer-request/{{ $item->care_id }}" target="_blank"><strong class="text-danger">Conversation</strong></a>
                                                    @endif
                                                </td>
                                                <td>{{ $item->quantity }}</td>
                                                @if ($order->status == 3)
                                                <td style="width:240px" class="text-success text-uppercase" id="copy_imei_{{ $order->id }}">
                                                    @isset($item->stock->imei) {{ $item->stock->imei }}&nbsp; @endisset
                                                    @isset($item->stock->serial_number) {{ $item->stock->serial_number }}&nbsp; @endisset
                                                    @isset($order->processed_by) | {{ $order->admin->first_name[0] }} | @endisset
                                                    @isset($item->stock->tester) ({{ $item->stock->tester }}) @endisset

                                                </td>
                                                @if ($item->quantity > 1 && $item->stock_id != null)
                                                @php
                                                    $content2 = "Hi, here are the IMEIs/Serial numbers for this order. \n";
                                                    foreach ($items as $im) {
                                                        if($im->stock_id == null){ continue;}
                                                        $content2 .= $im->stock->imei . $im->stock->serial_number . " " . $im->stock->tester . "\n";
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
                                                        <strong class="text-danger">{{ $order->order_status->name }}</strong>
                                                    {{-- @else
                                                        @if(!isset($item->stock->imei) && !isset($item->stock->serial_number) && $item->status > 2 && $item->quantity == 1)


                                                            <a class="dropdown-item" href="https://backmarket.fr/bo_merchant/orders/all?orderId={{ $order->reference_id }}&see-order-details={{ $order->reference_id }}" target="_blank"><i class="fe fe-caret me-2"></i>View in Backmarket</a>
                                                            <a class="dropdown-item" href="{{url('order')}}/refresh/{{ $order->reference_id }}"><i class="fe fe-arrows-rotate me-2 "></i>Refresh</a>
                                                        @endif --}}
                                                    @endif
                                                    @isset($item->stock->imei) {{ $item->stock->imei }}&nbsp; @endisset
                                                    @isset($item->stock->serial_number) {{ $item->stock->serial_number }}&nbsp; @endisset

                                                    @isset($order->processed_by) | {{ $order->admin->first_name[0] }} | @endisset
                                                    @isset($item->stock->tester) ({{ $item->stock->tester }}) @endisset


                                                    @if ($item->status == 2)
                                                        @if (count($items) < 2 && $item->quantity < 2)
                                                            <form id="dispatch_{{ $i."_".$j }}" class="form-inline" method="post" action="{{url('order')}}/dispatch/{{ $order->id }}" @if (request('sort') == 4) @endif>
                                                                @csrf
                                                                <input type="hidden" name="sort" value="{{request('sort')}}">
                                                                <div class="input-group">
                                                                    <input type="text" name="tester[]" placeholder="Tester" class="form-control form-control-sm" style="max-width: 50px">
                                                                    <input type="text" name="imei[]" placeholder="IMEI / Serial Number" class="form-control form-control-sm">

                                                                    <input type="hidden" name="sku[]" value="{{ $item->variation->sku ?? "Variation Issue" }}">

                                                                    <div class="input-group-append">
                                                                        <input type="submit" name="imei_send" value=">" class="form-control form-control-sm" form="dispatch_{{ $i."_".$j }}">
                                                                    </div>

                                                                </div>
                                                            </form>
                                                        @elseif (count($items) < 2 && $item->quantity >= 2)

                                                            <form id="dispatch_{{ $i."_".$j }}" class="form-inline" method="post" action="{{url('order')}}/dispatch/{{ $order->id }}">
                                                                @csrf
                                                                @for ($in = 1; $in <= $item->quantity; $in ++)

                                                                    <div class="input-group">
                                                                        <input type="text" name="tester[]" placeholder="Tester" class="form-control form-control-sm" style="max-width: 50px">
                                                                        <input type="text" name="imei[]" placeholder="IMEI / Serial Number" class="form-control form-control-sm" required>
                                                                    </div>
                                                                <input type="hidden" name="sku[]" value="{{ $item->variation->sku }}">
                                                                @endfor
                                                                <div class="w-100">
                                                                    <input type="submit" name="imei_send" value="Submit IMEIs" class="form-control form-control-sm w-100" form="dispatch_{{ $i."_".$j }}">
                                                                </div>
                                                            </form>
                                                        @elseif (count($items) >= 2)
                                                            <form id="dispatch_{{ $i."_".$j }}" class="form-inline" method="post" action="{{url('order')}}/dispatch/{{ $order->id }}">
                                                                @csrf
                                                                @foreach ($items as $itm)

                                                                    @for ($in = 1; $in <= $itm->quantity; $in++)

                                                                        <div class="input-group">
                                                                            <input type="text" name="tester[]" placeholder="Tester" class="form-control form-control-sm" style="max-width: 50px">
                                                                            <input type="text" name="imei[]" placeholder="IMEI / Serial Number" class="form-control form-control-sm" required title="for SKU:{{ $itm->variation->sku }}">
                                                                        </div>
                                                                        <input type="hidden" name="sku[]" value="{{ $itm->variation->sku }}">
                                                                    @endfor
                                                                @endforeach
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
                                                    <a href="{{url('order/track/').'/'.$order->id}}" target="_blank">{{$order->tracking_number}}</a>

                                                @endif</td>
                                                <td>
                                                    <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical  tx-18"></i></a>
                                                    <div class="dropdown-menu">
                                                        <a class="dropdown-item" href="{{url('order')}}/refresh/{{ $order->reference_id }}">Refresh</a>
                                                        {{-- @if ($item->order->processed_at > $last_hour || $user_id == 1) --}}
                                                        <a class="dropdown-item" id="correction_{{ $item->id }}" href="javascript:void(0);" data-bs-target="#correction_model" data-bs-toggle="modal" data-bs-reference="{{ $order->reference_id }}" data-bs-item="{{ $item->id }}"> Correction </a>
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
                                                        @if ($user_id == 1)

                                                        <a class="dropdown-item" href="{{url('order')}}/recheck/{{ $order->reference_id }}/false/false/null/true" target="_blank">Data</a>
                                                        @endif
                                                        <a class="dropdown-item" href="https://backmarket.fr/bo_merchant/orders/all?orderId={{ $order->reference_id }}&see-order-details={{ $order->reference_id }}" target="_blank">View in Backmarket</a>
                                                    </div>
                                                </td>
                                            </tr>
                                            @php
                                                $j++;
                                            @endphp
                                        @if ($item->replacement)
                                            @php
                                                $replacement = $item->replacement;
                                            @endphp
                                            @while ($replacement != null)
                                                @php
                                                    $itm = $replacement;
                                                    $replacement = $replacement->replacement;

                                                @endphp

                                                {{-- @foreach ($order->exchange_items as $ind => $itm) --}}

                                                <tr class="bg-secondary text-white">
                                                    <td colspan="2">{{ $order->customer->first_name." ".$order->customer->last_name." ".$order->customer->phone }}</td>

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
                                                            <a class="dropdown-item" href="{{url('order/delete_replacement_item').'/'.$itm->id}}"><i class="fe fe-trash-2 me-2"></i>Delete</a>
                                                            <a class="dropdown-item" id="replacement_{{ $itm->id }}" href="javascript:void(0);" data-bs-target="#replacement_model" data-bs-toggle="modal" data-bs-reference="{{ $itm->order->reference_id }}" data-bs-item="{{ $itm->id }}" data-bs-return="@if($itm->check_return) 1 @endif"> Replacement </a>
                                                            <a class="dropdown-item" href="https://backmarket.fr/bo_merchant/orders/all?orderId={{ $order->reference_id }}&see-order-details={{ $order->reference_id }}" target="_blank"><i class="fe fe-caret me-2"></i>View in Backmarket</a>
                                                            {{-- <a class="dropdown-item" href="javascript:void(0);"><i class="fe fe-trash-2 me-2"></i>Delete</a> --}}
                                                        </div>
                                                    </td>
                                                </tr>
                                            {{-- @endforeach --}}
                                            @endwhile
                                        @elseif ($order->exchange_items->count() > 0)
                                            @foreach ($order->exchange_items as $ind => $itm)

                                                <tr class="bg-secondary text-white">
                                                        <td colspan="2">{{ $order->customer->first_name." ".$order->customer->last_name." ".$order->customer->phone }}</td>

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
                                                            <a class="dropdown-item" href="{{url('order/delete_replacement_item').'/'.$itm->id}}"><i class="fe fe-trash-2 me-2"></i>Delete</a>
                                                            <a class="dropdown-item" id="replacement_{{ $itm->id }}" href="javascript:void(0);" data-bs-target="#replacement_model" data-bs-toggle="modal" data-bs-reference="{{ $itm->order->reference_id }}" data-bs-item="{{ $itm->id }}" data-bs-return="@if($itm->check_return) 1 @endif"> Replacement </a>
                                                            <a class="dropdown-item" href="https://backmarket.fr/bo_merchant/orders/all?orderId={{ $order->reference_id }}&see-order-details={{ $order->reference_id }}" target="_blank"><i class="fe fe-caret me-2"></i>View in Backmarket</a>
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

                                                            @endphp

                                                            {{-- @foreach ($order->exchange_items as $ind => $itm) --}}

                                                            <tr class="bg-secondary text-white">
                                                                <td colspan="2">{{ $order->customer->first_name." ".$order->customer->last_name." ".$order->customer->phone }}</td>

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
                                                                        <a class="dropdown-item" href="{{url('order/delete_replacement_item').'/'.$itm->id}}"><i class="fe fe-trash-2 me-2"></i>Delete</a>
                                                                        <a class="dropdown-item" id="replacement_{{ $itm->id }}" href="javascript:void(0);" data-bs-target="#replacement_model" data-bs-toggle="modal" data-bs-reference="{{ $itm->order->reference_id }}" data-bs-item="{{ $itm->id }}" data-bs-return="@if($itm->check_return) 1 @endif"> Replacement </a>
                                                                        <a class="dropdown-item" href="https://backmarket.fr/bo_merchant/orders/all?orderId={{ $order->reference_id }}&see-order-details={{ $order->reference_id }}" target="_blank"><i class="fe fe-caret me-2"></i>View in Backmarket</a>
                                                                        {{-- <a class="dropdown-item" href="javascript:void(0);"><i class="fe fe-trash-2 me-2"></i>Delete</a> --}}
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        {{-- @endforeach --}}
                                                        @endwhile
                                        @endif
                                        @endforeach
                                        @if ($order->customer->orders->count() > 1)
                                            @php
                                                $def = 0;
                                            @endphp
                                            @foreach ($order->customer->orders as $ins => $ord)
                                                @if ($ord->id != $order->id)

                                                    @foreach ($ord->order_items as $ind => $itm)

                                                        <tr class="bg-secondary text-white">
                                                            @if (!$def)
                                                                @php
                                                                    $def = 1;
                                                                @endphp
                                                                <td rowspan="{{ count($order->customer->orders)-1 }}" colspan="2">{{ $ord->customer->first_name." ".$ord->customer->last_name." ".$ord->customer->phone }}</td>
                                                            @endif
                                                            <td>{{ $ord->reference_id }}</td>
                                                            <td>

                                                                @if ($itm->variation ?? false)
                                                                    <strong>{{ $itm->variation->sku }}</strong>{{ " - " . $itm->variation->product->model . " - " . (isset($itm->variation->storage)?$storages[$itm->variation->storage] . " - " : null) . (isset($itm->variation->color)?$colors[$itm->variation->color]. " - ":null)}} <strong><u>{{ $grades[$itm->variation->grade] }}</u></strong>
                                                                @endif

                                                                @if ($itm->care_id != null)
                                                                    <a class="" href="https://backmarket.fr/bo_merchant/customer-request/{{ $itm->care_id }}" target="_blank"><strong class="text-white">Conversation</strong></a>
                                                                @endif
                                                            </td>
                                                            <td>{{ $itm->quantity }}</td>
                                                            <td>
                                                                {{ $ord->order_status->name }}
                                                                @isset($itm->stock->imei) {{ $itm->stock->imei }}&nbsp; @endisset
                                                                @isset($itm->stock->serial_number) {{ $itm->stock->serial_number }}&nbsp; @endisset
                                                            </td>

                                                            <td>{{ $ord->created_at }}</td>
                                                            <td>
                                                                <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical text-white tx-18"></i></a>
                                                                <div class="dropdown-menu">
                                                                    <a class="dropdown-item" href="https://backmarket.fr/bo_merchant/orders/all?orderId={{ $ord->reference_id }}&see-order-details={{ $ord->reference_id }}" target="_blank"><i class="fe fe-caret me-2"></i>View in Backmarket</a>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                @endif
                                            @endforeach
                                        @endif
                                        @php
                                            $i ++;
                                        @endphp
                                    @endforeach
                                </tbody>
                            </table>
                        <br>
                        {{ $orders->onEachSide(1)->links() }} {{ __('locale.From') }} {{$orders->firstItem()}} {{ __('locale.To') }} {{$orders->lastItem()}} {{ __('locale.Out Of') }} {{$orders->total()}}
                    </div>

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
                        <h5 class="modal-title mg-b-5">Update Order</h5>
                        <hr>
                        <form action="{{ url('order/correction') }}" method="POST">
                            @csrf
                            <div class="form-group">
                                <label for="">Order Number</label>
                                <input class="form-control" name="correction[id]" type="text" id="order_reference" disabled>
                            </div>
                            <div class="form-group">
                                <label for="">Tester</label>
                                <input class="form-control" placeholder="input Tester Initial" name="correction[tester]" type="text">
                            </div>
                            <div class="form-group">
                                <label for="">IMEI / Serial Number</label>
                                <input class="form-control" placeholder="input IMEI / Serial Number" name="correction[imei]" type="text" required>
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
                            if(session('user')->role_id == 4){
                                $replacement_url = url('order/replacement');
                            }else {
                                $replacement_url = url('order/replacement/1');
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
                                <input class="form-control" placeholder="input Tester Initial" name="replacement[tester]" type="text">
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
        @script
        <script>
            setInterval(() => {
                $wire.$refresh()
            }, 2000)
        </script>
        @endscript
    @endsection

    @section('scripts')

    <script>
        $('#correction_model').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget) // Button that triggered the modal
            var reference = button.data('bs-reference') // Extract info from data-* attributesv
            var item = button.data('bs-item') // Extract info from data-* attributes
            // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
            // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
            var modal = $(this)
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
    </script>
		<!--Internal Sparkline js -->
		<script src="{{asset('assets/plugins/jquery-sparkline/jquery.sparkline.min.js')}}"></script>

		<!-- Internal Piety js -->
		<script src="{{asset('assets/plugins/peity/jquery.peity.min.js')}}"></script>

		<!-- Internal Chart js -->
		<script src="{{asset('assets/plugins/chartjs/Chart.bundle.min.js')}}"></script>

    @endsection
