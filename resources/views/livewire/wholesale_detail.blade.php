@extends('layouts.app')

    @section('styles')
    <!-- INTERNAL Select2 css -->
    <link href="{{asset('assets/plugins/select2/css/select2.min.css')}}" rel="stylesheet" />
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
        </style>
    @endsection
    @section('content')


        <!-- breadcrumb -->
            <div class="breadcrumb-header justify-content-between" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <div class="">
                    {{-- <span class="ms-3 form-check form-switch ms-4">
                        <input type="checkbox" value="1" name="bypass_check" class="form-check-input" form="wholesale_item" @if (session('bypass_check') == 1) checked @endif>
                        <label class="form-check-label" for="bypass_check">Bypass Wholesale check</label>
                    </span> --}}
                {{-- <span class="main-content-title mg-b-0 mg-b-lg-1">BulkSale Order Detail</span><br> --}}
                @if ($order->status == 2)
                <form class="form-inline" style="max-width: 600px" method="POST" action="{{url('wholesale/approve').'/'.$order->id}}">
                    @csrf
                    <div class="">
                        <select name="customer_id" class="form-select">
                            @foreach ($vendors as $id=>$vendor)
                                <option value="{{ $id }}" {{ $order->customer_id == $id ? 'selected' : '' }}>{{ $vendor }}</option>

                            @endforeach
                        </select>
                    </div>
                    <div class="form-floating">
                        <input type="text" list="currencies" id="currency" name="currency" class="form-control" value="{{$order->currency_id->code}}">
                        <datalist id="currencies">
                            @foreach ($exchange_rates as $target_currency => $rate)
                                <option value="{{$target_currency}}" data-rate="{{$rate}}"></option>
                            @endforeach
                        </datalist>
                        <label for="currency">Currency</label>
                    </div>
                    <div class="form-floating">
                        <input type="text" class="form-control" id="rate" name="rate" placeholder="Enter Exchange Rate" value="{{$order->exchange_rate}}" >
                        <label for="rate">Exchange Rate</label>
                    </div>
                    <div class="form-floating">
                        <input type="text" class="form-control" id="reference" name="reference" placeholder="Enter Reference" value="{{$order->reference}}" required>
                        <label for="reference">Reference</label>
                    </div>
                    <div class="form-floating">
                        <input type="text" class="form-control" id="tracking_number" name="tracking_number" placeholder="Enter Tracking Number" value="{{$order->tracking_number}}" required>
                        <label for="tracking_number">Tracking Number</label>
                    </div>
                    <button type="submit" class="btn btn-success">Approve</button>
                    <a class="btn btn-danger" href="{{url('delete_wholesale') . "/" . $order->id }}">Delete</a>
                </form>
                @else
                Tracking Number: <a href="https://www.dhl.com/gb-en/home/tracking/tracking-express.html?submit=1&tracking-id={{$order->tracking_number}}" target="_blank"> {{$order->tracking_number}}</a>
                <br>
                Reference: {{ $order->reference }}
                <br>

                @if (session('user')->hasPermission('wholesale_revert_status'))
                    <a href="{{url('wholesale/revert_status').'/'.$order->id}}">Revert Back to Pending</a>
                @endif
                @endif

                </div>
                <div class="text-center">
                        <h4>BulkSale Order Detail</h4>
                        <h5>Reference: {{ $order->reference_id }} | Purchaser: {{ $order->customer->first_name }} | Total Items: {{ $order->order_items->count() }} @if (session('user')->hasPermission('view_price')) | Total Price: {{ $order->currency_id->sign.amount_formatter($order->order_items->sum('price'),2) }} @endif</h5>

                </div>
                <div class="">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                        <li class="breadcrumb-item active" aria-current="page">BulkSale Detail</li>
                    </ol>
                    <br>
                    Creation Date: {{ $order->created_at }}<br>
                    Approval Date: {{ $order->processed_at }}
                </div>
            </div>
        <!-- /breadcrumb -->

        <div class="d-flex justify-content-between" style="border-bottom: 1px solid rgb(216, 212, 212);">

            <div class="p-2">
                @if ($order->status < 3)
                <h4>Add BulkSale Item</h4>
                <span class="form-check form-switch ms-4 p-2" title="Bypass Wholesale check" onclick="$('#bypass_check').check()">
                    <input type="checkbox" value="1" id="bypass_check" name="bypass_check" class="form-check-input" form="wholesale_item" @if (session('bypass_check') == 1) checked @endif>
                    <label class="form-check-label" for="bypass_check">Bypass check</label>
                </span>
                @endif
            </div>
            <div class="p-1">
                @if ($order->status == 1)
                <form class="form-inline" action="{{ url('wholesale_item_po').'/'.$order_id }}" method="POST" id="wholesale_item">
                    @csrf
                    {{-- <label for="imei" class="">IMEI | Serial Number: &nbsp;</label>
                    <input type="text" class="form-control form-control-sm" name="imei" id="imei" placeholder="Enter IMEI" onloadeddata="$(this).focus()" autofocus required> --}}

                    <div class="form-floating">
                        <input type="text" name="product" value="{{ Request::get('product') }}" class="form-control" data-bs-placeholder="Select Model" list="product-menu">
                        <label for="product">Product</label>
                    </div>
                    <datalist id="product-menu">
                        <option value="">Select</option>
                        @foreach ($products as $id => $model)
                            <option value="{{ $id }}" @if(isset($_GET['product']) && $id == $_GET['product']) {{'selected'}}@endif>{{ $model }}</option>
                        @endforeach
                    </datalist>
                    <select name="storage" class="form-control form-select">
                        <option value="">Storage</option>
                        @foreach ($storages as $id=>$name)
                            <option value="{{ $id }}" @if(isset($_GET['storage']) && $id == $_GET['storage']) {{'selected'}}@endif>{{ $name }}</option>
                        @endforeach
                    </select>
                    <select name="color" class="form-control form-select">
                        <option value="">Color</option>
                        @foreach ($colors as $id=>$name)
                            <option value="{{ $id }}" @if(isset($_GET['color']) && $id == $_GET['color']) {{'selected'}}@endif>{{ $name }}</option>
                        @endforeach
                    </select>
                    <select name="grade[]" class="form-control form-select select2" multiple>
                        <option value="">Grade</option>
                        @foreach ($grades as $id=>$name)
                            <option value="{{ $id }}" @if(isset($_GET['grade']) && in_array($id,$_GET['grade'])) {{'selected'}}@endif>{{ $name }}</option>
                        @endforeach
                    </select>
                    <button class="btn-sm btn-primary pd-x-20" type="submit">Insert</button>

                </form>
                @endif
                @if ($order->status == 2)
                <form class="form-inline" action="{{ url('check_wholesale_item').'/'.$order_id }}" method="POST" id="wholesale_item">
                    @csrf
                    <label for="imei" class="">IMEI | Serial Number: &nbsp;</label>
                    <input type="text" class="form-control form-control-sm" name="imei" id="imei" placeholder="Enter IMEI" onloadeddata="$(this).focus()" autofocus required>
                    <button class="btn-sm btn-primary pd-x-20" type="submit">Insert</button>

                </form>
                @endif
                <script>

                    window.onload = function() {
                        document.getElementById('imei').focus();
                        document.getElementById('imei').click();
                        setTimeout(function(){ document.getElementById('imei').focus();$('#imei').focus(); }, 500);
                    };
                    document.addEventListener('DOMContentLoaded', function() {
                        var input = document.getElementById('imei');
                        input.focus();
                        input.select();
                        document.getElementById('imei').click();
                        setTimeout(function(){ document.getElementById('imei').focus();$('#imei').focus(); }, 500);
                    });
                </script>
            </div>
            <div class="p-2 tx-right">
                @if ($order->status < 3)
                <form method="POST" enctype="multipart/form-data" action="{{ url('wholesale/add_wholesale_sheet').'/'.$order_id}}" class="form-inline p-1">
                    @csrf
                    <input type="file" class="form-control form-control-sm" name="sheet">
                    <button type="submit" class="btn btn-sm btn-primary">Upload Sheet</button>
                </form>
                @endif
                @if ($order->customer->email == null)
                    Customer Email Not Added
                @else
                <a href="{{url('bulksale_email')}}/{{ $order->id }}" target="_blank"><button class="btn-sm btn-secondary">Send Email</button></a>
                @endif
                <a href="{{url('export_bulksale_invoice')}}/{{ $order->id }}" target="_blank"><button class="btn-sm btn-secondary">Invoice</button></a>
                @if ($order->exchange_rate != null)
                <a href="{{url('export_bulksale_invoice')}}/{{ $order->id }}/1" target="_blank"><button class="btn-sm btn-secondary">{{$order->currency_id->sign}} Invoice</button></a>

                @endif

                <div class="btn-group p-1" role="group">
                    <button type="button" class="btn-sm btn-secondary dropdown-toggle" id="pack_sheet" data-bs-toggle="dropdown" aria-expanded="false">
                    Pack Sheet
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="pack_sheet">
                        <li><a class="dropdown-item" href="{{url('export_bulksale_invoice')}}/{{ $order->id }}?packlist=2&id={{ $order->id }}">.xlsx</a></li>
                        <li><a class="dropdown-item" href="{{url('export_bulksale_invoice')}}/{{ $order->id }}?packlist=1" target="_blank">.pdf</a></li>
                    </ul>
                </div>
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
        @if (count($order_issues)>0)

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between">
                            <h4 class="card-title mg-b-0">Order Issues List</h4>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" style="max-height: 500px">
                            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                @php
                                    $col = 4;
                                @endphp
                                <thead>
                                    <tr>
                                        <th><small><b>No</b></small></th>
                                        {{-- @foreach (json_decode($order_issues[0]->all_rows)[0]->data as $key => $value) --}}
                                        @foreach (json_decode(json_decode(preg_split('/(?<=\}),(?=\{)/', $order_issues[0]->all_rows)[0])->data) as $key => $value)

                                        @php
                                            $col ++;
                                        @endphp
                                        <th><small><b>{{ $key }}</b></small></th>
                                        @endforeach
                                        <th><small><b>Message</b></small></th>
                                        <th><small><b>Creation Date</b></small></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = 0;
                                        $j = 0;
                                    @endphp
                                    @foreach ($order_issues as $grouped_issue)
                                        @php
                                            // $array = explode('},{',$grouped_issue->all_rows);
                                            // Split the JSON string into individual JSON objects
                                            $all_rows = preg_split('/(?<=\}),(?=\{)/', $grouped_issue->all_rows);

                                            // $array[0][0] = '';
                                            // print_r($array);
                                            // echo "<br>";
                                            // echo "<br>";
                                        @endphp
                                        <tr class="bg-light tx-center">
                                            <td colspan="3" >{{ $grouped_issue->name }}</td>
                                            <td colspan="{{ $col-5 }}">{{ $grouped_issue->message }}</td>
                                            <td colspan="2">
                                                <form id="order_issues_{{$j+=1}}" method="POST" action="{{ url('wholesale/remove_issues') }}">
                                                    @csrf
                                                @switch($grouped_issue->message)
                                                @case("Item Already added in this order")
                                                <button class="btn btn-sm btn-danger m-0" name="remove_entries" value="1">Remove Entries</button>

                                                    @break
                                                @case("Product Name Not Found")

                                                <div class="form-floating">
                                                    <input type="text" list="variations" id="variation" name="variation" class="form-control" value="{{ $grouped_issue->name }}" required>
                                                    <datalist id="variations">
                                                        <option value="">Select</option>
                                                        @foreach ($all_variations as $variation)
                                                            @php
                                                                if($variation->storage){
                                                                    $storage = $storages[$variation->storage];
                                                                }else{
                                                                    $storage = null;
                                                                }
                                                            @endphp
                                                            <option value="{{$variation->id}}" @if(isset($_GET['variation']) && $variation->id == $_GET['variation']) {{'selected'}}@endif>{{$variation->product->model." ".$storage}}</option>
                                                        @endforeach
                                                    </datalist>
                                                    <label for="variation">Variation</label>
                                                </div>
                                                <button class="btn btn-primary m-0" name="insert_variation" value="1">Insert Variation</button>

                                                        @break

                                                    @default

                                                @endswitch
                                                </form>
                                            </td>
                                        </tr>
                                        @foreach ($all_rows as $row)
                                            @php
                                                $row = json_decode($row);
                                            // print_r($row);
                                            @endphp
                                            @if ($row != null)
                                            @php
                                            // echo "<br>";
                                            // echo "<br>";
                                                $data = json_decode($row->data);
                                            @endphp

                                        {{-- @if (json_decode($grouped_issue->all_rows) != null) --}}

                                        {{-- @foreach (json_decode($grouped_issue->all_rows) as $key => $issue) --}}
                                        {{-- @foreach ($grouped_issue->all_rows ? json_decode($grouped_issue->all_rows) : [] as $issue)
                                        @foreach ($grouped_issue->all_rows ? json_decode($grouped_issue->all_rows) : [] as $issue) --}}
                                            <input type="hidden" name="ids[]" value="{{$row->id}}" form="order_issues_{{$j}}">
                                            <tr>
                                                <td>{{ $i + 1 }}</td>
                                                @foreach ($data as $key => $value)
                                                    <td title="{{ $key }}">{{ $value }}</td>
                                                @endforeach
                                                <td>{{ $row->message }}</td>
                                                <td>{{ $row->created_at }}</td>
                                                <td>
                                                    <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical  tx-18"></i></a>
                                                    <div class="dropdown-menu">
                                                        <a class="dropdown-item" href="">link</a>
                                                        <a class="dropdown-item" href="" target="_blank">link</a>
                                                    </div>
                                                </td>
                                            </tr>

                                            @php
                                            // print_r($issue);
                                            // echo " | ";
                                                $i++;
                                            @endphp
                                            @endif
                                            {{-- @endforeach --}}
                                        {{-- @endif --}}
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                            <br>
                        </div>
                    </div>

                    </div>
                </div>
            </div>
        </div>

        @endif
        <br>

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between">
                            <h4 class="card-title mg-b-0">Latest Added Items</h4>
                        </div>
                    </div>
                    <div class="card-body"><div class="table-responsive" style="max-height: 250px">
                            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>No</b></small></th>
                                        <th><small><b>Variation</b></small></th>
                                        <th><small><b>IMEI | Serial Number</b></small></th>
                                        <th><small><b>Vendor</b></small></th>
                                        @if (session('user')->hasPermission('view_price'))
                                        <th><small><b>Price</b></small></th>
                                        @endif
                                        <th><small><b>Creation Date</b></small></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = 0;
                                    @endphp
                                    @foreach ($last_ten as $item)

                                        @php
                                            $i ++;
                                            $variation = $item->variation;
                                            $stock = $item->stock;
                                            $customer = $item->stock->order->customer;

                                        @endphp
                                        <tr>
                                            <td>{{ $i }}</td>
                                            <td>{{ $products[$variation->product_id]}} {{$storages[$variation->storage] ?? null}} {{$colors[$variation->color] ?? null}} {{$grades[$variation->grade] }}</td>
                                            <td>{{ $stock->imei.$stock->serial_number }}</td>
                                            <td>{{ $customer->first_name }}</td>
                                            @if (session('user')->hasPermission('view_price'))
                                            <td>€{{ amount_formatter($item->price,2) }}</td>
                                            @endif
                                            <td style="width:220px">{{ $item->created_at }}</td>
                                            <td><a href="{{ url('delete_wholesale_item').'/'.$item->id }}"><i class="fa fa-trash"></i></a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        <br>
                    </div>

                    </div>
                </div>
            </div>
        </div>
        <br>

        <div class="row">

            @foreach ($variations as $key=>$vars)
            @foreach ($vars as $key2=>$var)
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header pb-0">
                        @php
                            $varss = $vars->toArray();
                        @endphp
                        {{ $products[$key] }} {{ $storages[$key2] ?? null }}
                        {{-- @dd($vars) --}}
                        {{-- @php
                            isset($variation->color_id)?$color = $variation->color_id->name:$color = null;
                            isset($variation->storage)?$storage = $storages[$variation->storage]:$storage = null;
                        @endphp
                        {{ $products[$key]." ".$storage." ".$color." ".$variation->grade_id->name }} --}}
                    </div>
                            {{-- {{ $variation }} --}}
                    <div class="card-body"><div class="table-responsive" style="max-height: 400px">

                            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>#</b></small></th>
                                        {{-- <th><small><b>Vendor</b></small></th> --}}
                                        <th><small><b>Color - Grade</b></small></th>
                                        <th><small><b>IMEI/Serial</b></small></th>
                                        @if (session('user')->hasPermission('view_price'))
                                        <th><small><b>Vendor Price</b></small></th>
                                        @endif
                                        @if (session('user')->hasPermission('delete_wholesale_item'))
                                        <th></th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = 0;
                                        $total = 0;
                                    @endphp
                                    <form method="POST" action="{{url('wholesale')}}/update_prices" id="update_prices_{{ $key."_".$key2 }}">
                                        @csrf
                                    @foreach ($var as $variation)
                                    {{-- @dd($variation) --}}
                                    @php
                                            # code...
                                        $stocks = $variation->stocks;
                                        // $items = $stocks->order_item;
                                        // print_r($variation);
                                    @endphp

                                    @foreach ($stocks as $item)
                                        {{-- @dd($item->sale_item) --}}
                                        {{-- @if($item->sale_item($order_id)->order_id == $order_id) --}}
                                        @php
                                            $i ++;
                                            $sale_item = $item->sale_item($order_id);
                                            $purchase_item = $item->purchase_item;
                                            $price = $sale_item->price;
                                            if($order->exchange_rate != null){
                                                $ex_price = $price * $order->exchange_rate;
                                            }
                                            $total += $price;
                                        @endphp
                                        <tr @if($purchase_item->price != $price) style="background: LightGreen" @endif>
                                            <td>{{ $i }}</td>
                                            <td>{{ $colors[$variation->color] ?? null }} - {{ $grades[$variation->grade] ?? null }}</td>
                                            {{-- <td>{{ $item->order->customer->first_name }}</td> --}}
                                            <td>{{ $item->imei.$item->serial_number }}</td>
                                            @if (session('user')->hasPermission('view_price'))
                                            <td @if (session('user')->hasPermission('view_cost')) title="Cost Price: €{{ $purchase_item->price }}" @endif>
                                                {{ $item->order->customer->first_name }} €{{ amount_formatter($price,2) }}
                                            </td>
                                            @endif
                                            @if (session('user')->hasPermission('delete_wholesale_item'))
                                            <td><a href="{{ url('delete_wholesale_item').'/'.$sale_item->id }}"><i class="fa fa-trash"></i></a></td>
                                            @endif
                                            <input type="hidden" name="item_ids[]" value="{{ $sale_item->id }}">
                                        </tr>
                                        {{-- @endif --}}
                                    @endforeach
                                    @endforeach
                                    </form>
                                </tbody>
                            </table>
                        <br>
                    </div>
                    <div class="d-flex justify-content-between">
                        @if (session('user')->hasPermission('view_price'))

                        <div>
                            <label for="unit-price" class="">Change Unit Price: </label>
                            <input type="number" name="unit_price" id="unit_price" step="0.01" class="w-50 border-0" placeholder="Input Unit price" form="update_prices_{{ $key."_".$key2 }}">
                        </div>
                        <div>Average: {{$total/$i }}</div>
                        @endif
                        <div>Total: {{$i }}</div>
                    </div>
                    </div>
                </div>
            </div>
            @endforeach
            @endforeach
        </div>

    @endsection

    @section('scripts')

    <script>
        $(document).ready(function() {
            $('#currency').on('input', function() {
                var selectedCurrency = $(this).val();
                var rate = $('#currencies').find('option[value="' + selectedCurrency + '"]').data('rate');
                if (rate !== undefined) {
                    $('#rate').val(rate);
                } else {
                    $('#rate').val(''); // Clear the rate field if the currency is not in the list
                }
            });
        });
    </script>
		<!--Internal Sparkline js -->
		<script src="{{asset('assets/plugins/jquery-sparkline/jquery.sparkline.min.js')}}"></script>

		<!-- Internal Piety js -->
		<script src="{{asset('assets/plugins/peity/jquery.peity.min.js')}}"></script>

		<!-- Internal Chart js -->
		<script src="{{asset('assets/plugins/chartjs/Chart.bundle.min.js')}}"></script>

		<!-- INTERNAL Select2 js -->
		<script src="{{asset('assets/plugins/select2/js/select2.full.min.js')}}"></script>
		<script src="{{asset('assets/js/select2.js')}}"></script>
    @endsection
