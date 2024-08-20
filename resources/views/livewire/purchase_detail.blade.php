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
                <div class="left-content">
                {{-- <span class="main-content-title mg-b-0 mg-b-lg-1">Purchase</span> --}}
                    @if ($order->status == 2)
                    <form class="form-inline" method="POST" id="approveform" action="{{url('purchase/approve').'/'.$order->id}}">
                        @csrf
                        <div class="form-floating">
                            <input type="text" class="form-control" id="reference" name="reference" placeholder="Enter Vendor Reference" value="{{$order->reference}}" onchange="submitForm()">
                            <label for="reference">Vendor Reference</label>
                        </div>
                        <div class="form-floating">
                            <input type="text" class="form-control" id="tracking_number" name="tracking_number" placeholder="Enter Tracking Number" value="{{$order->tracking_number}}" required>
                            <label for="tracking_number">Tracking Number</label>
                        </div>
                        {{-- <button type="submit" class="btn" name="save" value="1">Save</button> --}}
                        <button type="submit" class="btn btn-success" name="approve" value="1">Approve</button>
                        <a class="btn btn-danger" href="{{url('delete_order') . "/" . $order->id }}">Delete</a>
                    </form>

                    {{-- <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script> --}}
                    <script>
                        function submitForm() {
                            var form = $("#approveform");
                            var actionUrl = form.attr('action');

                            $.ajax({
                                type: "POST",
                                url: actionUrl,
                                data: form.serialize(), // serializes the form's elements.
                                success: function(data) {
                                    alert("Success: " + data); // show response from the PHP script.
                                },
                                error: function(jqXHR, textStatus, errorThrown) {
                                    alert("Error: " + textStatus + " - " + errorThrown);
                                }
                            });
                        }

                    </script>
                    @else
                    Tracking Number: <a href="https://www.dhl.com/gb-en/home/tracking/tracking-express.html?submit=1&tracking-id={{$order->tracking_number}}" target="_blank"> {{$order->tracking_number}}</a>
                    <br>
                    V Reference: {{ $order->reference }}
                    <br>

                    @if (session('user')->hasPermission('purchase_revert_status'))
                        <a href="{{url('purchase/revert_status').'/'.$order->id}}">Revert Back to Pending</a>
                    @endif
                    @endif

                </div>
            <div class="tx-center">
                <center><h4>@if ($order->status == 2)<small>(Pending)</small>@endif Purchase Order Detail</h4></center>
                <h5>Reference: {{ $order->reference_id }} | Vendor: {{ $order->customer->first_name }} | Total Items: {{ $order->order_items->count() }} | Total Cost: {{ $order->currency_id->sign.number_format($order->order_items->sum('price'),2) }}</h5>
            </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Purchase Detail</li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->

        <form action="{{ url('add_purchase_item').'/'.$order_id }}" method="POST">
            @csrf
            <div class="row">
                <div class="col-md col-sm-6">
                    <div class="form-floating">
                        <input type="text" list="variations" id="variation" name="variation" class="form-control" onload="this.focus()" required autofocus>
                        <datalist id="variations">
                            <option value="">Select</option>
                            @foreach ($all_variations as $variation)
                                @php
                                    if($variation->product_id){
                                        $product = $products[$variation->product_id];
                                    }else{
                                        $product = null;
                                    }
                                    if($variation->storage){
                                        $storage = $storages[$variation->storage];
                                    }else{
                                        $storage = null;
                                    }
                                    if($variation->color){
                                        $color = $colors[$variation->color];
                                    }else{
                                        $color = null;
                                    }
                                @endphp
                                <option value="{{$variation->id}}" @if(isset($_GET['variation']) && $variation->id == $_GET['variation']) {{'selected'}}@endif>{{$product." ".$storage." ".$color}}</option>
                            @endforeach
                        </datalist>
                        <label for="variation">Variation</label>
                    </div>
                </div>
                <script>
                    window.onload = function() {
                        document.getElementById('variation').focus();
                    };
                </script>
                <div class="col-md col-sm-6">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="imei" name="imei" placeholder="Enter IMEI" value="@isset($_GET['imei']){{$_GET['imei']}}@endisset" required>
                        <label for="imei">IMEI</label>
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="price" name="price" placeholder="Enter Price" value="@isset($_GET['price']){{$_GET['price']}}@endisset" required>
                        <label for="price">Cost</label>
                    </div>
                </div>
                <button class="btn btn-primary pd-x-20" type="submit">Insert</button>
                <div class="">
                    <h6>Creation Date: {{ $order->created_at }}</h6>
                    @if ($order->status == 3)

                    <h6>Approval Date: {{ $order->processed_at }}</h6>
                    @endif
                </div>
            </div>
        </form>
        <hr style="border-bottom: 1px solid rgb(62, 45, 45);">
        {{-- Sold Stocks:-
        @foreach ($sold_summery as $sold_stock)

        @endforeach --}}
        {{-- <br> --}}
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

        <div class="d-flex justify-content-between">
            <div>
                <a href="{{url('purchase/detail')."/".$order->id}}?status=1" class="btn btn-link @if (request('status') == 1) bg-white @endif ">Available</a>
                <a href="{{url('purchase/detail')."/".$order->id}}?status=2" class="btn btn-link @if (request('status') == 2) bg-white @endif ">Sold</a>
                <a href="{{url('purchase/detail')."/".$order->id}}" class="btn btn-link @if (!request('status')) bg-white @endif " >All</a>
                @if (session('user')->hasPermission('view_purchase_summery'))
                <a href="{{url('purchase/detail')."/".$order->id}}?summery=1" class="btn btn-link @if (request('summery') == 1) bg-white @endif ">Summery</a>

                @endif
            </div>
            <div class="">
            </div>
        </div>
        @if (session('user')->hasPermission('view_purchase_summery') && request('summery') && request('summery') == 1)
        <div class="card">
            <div class="card-header pb-0">
                Sold Stock Summery
            </div>
            <div class="card-body"><div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                    <thead>
                        <tr>
                            <th><small><b>No</b></small></th>
                            <th><small><b>Model</b></small></th>
                            <th><small><b>Quantity</b></small></th>
                            <th><small><b>Cost</b></small></th>
                            <th><small><b>Price</b></small></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $i = 0;
                        @endphp
                        @foreach ($sold_stock_summery as $summery)
                            <tr>
                                <td>{{ $i++ }}</td>
                                <td>{{ $products[$summery['product_id']]." ".$storages[$summery['storage']] }}</td>
                                <td>{{ $summery['quantity'] }}</td>
                                <td title="{{ $summery['average_cost'] }}">{{ number_format($summery['total_cost'],2) }}</td>
                                <td title="{{ $summery['average_price'] }}">{{ number_format($summery['total_price'],2) }}</td>
                            </tr>
                            {{-- @endif --}}
                        @endforeach
                    </tbody>

                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header pb-0">
                Available Stock Summery
            </div>
            <div class="card-body"><div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                    <thead>
                        <tr>
                            <th><small><b>No</b></small></th>
                            <th><small><b>Model</b></small></th>
                            <th><small><b>Quantity</b></small></th>
                            <th><small><b>Cost</b></small></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $i = 0;
                        @endphp
                        @foreach ($available_stock_summery as $summery)
                            <tr>
                                <td>{{ ++$i }}</td>
                                <td>{{ $products[$summery['product_id']]." ".$storages[$summery['storage']] }}</td>
                                <td>{{ $summery['quantity'] }}</td>
                                <td title="{{ $summery['average_cost'] }}">{{ number_format($summery['total_cost'],2) }}</td>
                            </tr>
                            {{-- @endif --}}
                        @endforeach
                    </tbody>

                </table>
            </div>
        </div>
        @else
        @if (session('user')->hasPermission('view_issues'))
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
                                    $col = 3;
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
                                                <form id="order_issues_{{$j+=1}}" method="POST" action="{{ url('purchase/remove_issues') }}" class="form-inline">
                                                    @csrf
                                                @switch($grouped_issue->message)
                                                    @case("Additional Item")
                                                        @break
                                                    @case("IMEI not Provided")
                                                        @break
                                                    @case("IMEI/Serial Not Found")
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

                                                    <button class="btn btn-sm btn-danger m-0" name="remove_entries" value="1">Remove Entries</button>

                                                        @break
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
                                            // print_r($data);
                                            @endphp


                                        {{-- @if (json_decode($grouped_issue->all_rows) != null) --}}

                                        {{-- @foreach (json_decode($grouped_issue->all_rows) as $key => $issue) --}}
                                        {{-- @foreach ($grouped_issue->all_rows ? json_decode($grouped_issue->all_rows) : [] as $issue)
                                        @foreach ($grouped_issue->all_rows ? json_decode($grouped_issue->all_rows) : [] as $issue) --}}
                                            <input type="hidden" name="ids[]" value="{{$row->id}}" form="order_issues_{{$j}}">
                                            <tr>
                                                <td>{{ $i + 1 }}</td>
                                                @foreach ($data as $key => $value)
                                                    @if ($key == 'variation')
                                                        @php
                                                            $variation = $all_variations->where('id',$value)->first();
                                                            if($variation->storage){
                                                                $storage = $storages[$variation->storage];
                                                            }else{
                                                                $storage = null;
                                                            }
                                                        @endphp
                                                        <td title="{{ $key }}">{{$variation->product->model." ".$storage}}</td>
                                                    @else
                                                        <td title="{{ $key }}">{{ $value }}</td>

                                                    @endif
                                                @endforeach
                                                <td>
                                                    @if ($row->message == "IMEI not Provided" || $row->message == "IMEI/Serial Not Found")
                                                    <form id="order_issues_{{$row->id}}" method="POST" action="{{ url('purchase/remove_issues') }}" class="form-inline">
                                                        @csrf
                                                        <input type="hidden" name="id" value="{{$row->id}}">
                                                        <div class="form-floating">
                                                            <input type="text" class="form-control" id="imei" name="imei" placeholder="Enter IMEI" required>
                                                            <label for="imei">IMEI</label>
                                                        </div>
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
                                                        <button class="btn btn-primary m-0" name="add_imei" value="1">Insert</button>

                                                    </form>
                                                    @elseif ($row->message == "Additional Item")
                                                    <form id="order_issues_{{$row->id}}" method="POST" action="{{ url('purchase/remove_issues') }}" class="form-inline">
                                                        @csrf
                                                        <input type="hidden" name="id" value="{{$row->id}}">
                                                        <div class="form-floating">
                                                            <input type="text" class="form-control" id="imei" name="imei" placeholder="Enter IMEI">
                                                            <label for="imei">IMEI</label>
                                                        </div>
                                                        <button class="btn btn-primary m-0" name="change_imei" value="1">Insert</button>

                                                        @else
                                                            {{ $row->message }}
                                                        @endif
                                                        <button class="btn btn-danger m-0" name="remove_entry" value="1" form="order_issues_{{$row->id}}">Remove Entry</button>
                                                    </form>
                                                </td>
                                                <td>{{ $row->created_at }}</td>
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

        @endif
        @endif
        <div class="row">
            <div class="col-lg-10">
                @if (isset($variations) && (!request('status') || request('status') == 1))
                <div class="row">

                    @foreach ($variations as $variation)
                    <div class="col-md-4">
                        <div class="card @if ($variation->grade == 9)
                            highlight
                        @endif">
                            <div class="card-header pb-0">
                                @php
                                    isset($variation->color_id)?$color = $variation->color_id->name:$color = null;
                                    isset($variation->storage)?$storage = $storages[$variation->storage]:$storage = null;
                                    isset($variation->grade)?$grade = $grades[$variation->grade]:$grade = null;
                                @endphp
                                {{ $variation->product->model." ".$storage." ".$color." ".$grade }}
                            </div>
                                    {{-- {{ $variation }} --}}
                            <div class="card-body"><div class="table-responsive" style="max-height: 400px">

                                    <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                        <thead>
                                            <tr>
                                                <th><small><b>No</b></small></th>
                                                <th><small><b>IMEI/Serial</b></small></th>
                                                @if (session('user')->hasPermission('view_cost'))
                                                <th><small><b>Cost</b></small></th>
                                                @endif
                                                @if (session('user')->hasPermission('delete_purchase_item'))
                                                <th></th>
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $i = 0;
                                                $id = [];
                                            @endphp
                                            @php
                                                $stocks = $variation->stocks;
                                                // $items = $stocks->order_item;
                                                $j = 0;
                                                $prices = [];
                                                // print_r($stocks);
                                            @endphp

                                            @foreach ($stocks as $item)
                                                {{-- @dd($item) --}}
                                                {{-- @if($item->order_item[0]->order_id == $order_id) --}}
                                                @php
                                                $i ++;
                                                $purchase_item = $item->purchase_item;
                                                $prices[] = $purchase_item->price ?? 0;
                                                if($item->variation->grade == 9 && count($item->stock_operations) == 0){
                                                    $class = "text-danger"
                                                }else {
                                                    $class = "";
                                                }
                                            @endphp
                                                <tr>
                                                    <td>{{ $i }}</td>
                                                    <td data-stock="{{ $item->id }}" class="{{$class}}">{{ $item->imei.$item->serial_number }}</td>
                                                    @if (session('user')->hasPermission('view_cost'))
                                                    <td>{{ $currency}}{{$purchase_item->price ?? "Error in Purchase Entry" }}</td>
                                                    @endif
                                                    @if (session('user')->hasPermission('delete_purchase_item'))
                                                    <td><a href="{{ url('delete_order_item').'/'}}{{$purchase_item->id ?? null }}"><i class="fa fa-trash"></i></a></td>
                                                    @endif
                                                </tr>
                                                {{-- @endif --}}
                                            @endforeach
                                        </tbody>
                                    </table>
                                <br>
                            </div>
                            <div class="text-end">Average Cost: {{array_sum($prices)/count($prices) }} &nbsp;&nbsp;&nbsp; Total: {{$i }}</div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
                @if (isset($sold_stocks) && count($sold_stocks)>0 && (!request('status') || request('status') == 2))

                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header pb-0">
                                <div class="d-flex justify-content-between">
                                    <h4 class="card-title mg-b-0">Sold Stock Items</h4>
                                    <h5>Total: {{count($sold_stocks)}}</h5>
                                </div>
                            </div>
                            <div class="card-body"><div class="table-responsive">
                                    <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                        <thead>
                                            <tr>
                                                <th><small><b>No</b></small></th>
                                                <th><small><b>Variation</b></small></th>
                                                <th><small><b>IMEI | Serial Number</b></small></th>
                                                <th><small><b>Customer</b></small></th>
                                                @if (session('user')->hasPermission('view_cost'))
                                                <th><small><b>Cost</b></small></th>
                                                @endif
                                                @if (session('user')->hasPermission('view_price'))
                                                <th><small><b>Price</b></small></th>
                                                @endif
                                                <th><small><b>Creation Date</b></small></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $i = 0;
                                            @endphp
                                            @foreach ($sold_stocks as $stock)
                                                @php
                                                    $item = $stock->last_item();
                                                    $variation = $item->variation;
                                                    if(in_array($item->order->order_type_id,[1,4,6])){
                                                        $stock->status = 1;
                                                        $stock->save();
                                                        continue;
                                                    }
                                                @endphp
                                                <tr>
                                                    <td>{{ $i + 1 }}</td>
                                                    <td>

                                                        @php
                                                        isset($variation->product_id)?$product = $products[$variation->product_id]:$product = null;
                                                        isset($variation->color)?$color = $colors[$variation->color]:$color = null;
                                                        isset($variation->storage)?$storage = $storages[$variation->storage]:$storage = null;
                                                        isset($variation->grade)?$grade = $grades[$variation->grade]:$grade = null;
                                                        @endphp
                                                        {{ $product." ".$storage." ".$color}} {{$grade }}
                                                    </td>
                                                    <td title="Double click to change" data-stock="{{ $stock->id }}">{{ $stock->imei.$stock->serial_number }}</td>
                                                    <td>{{ $item->order->customer->first_name }}</td>
                                                    @if (session('user')->hasPermission('view_cost'))
                                                    <td>{{ $currency.number_format($stock->purchase_item->price,2) }}</td>
                                                    @endif
                                                    @if (session('user')->hasPermission('view_cost'))
                                                    <td>{{ $currency.number_format($item->price,2) }}</td>
                                                    @endif
                                                    <td style="width:220px">{{ $item->created_at }}</td>
                                                </tr>
                                                @php
                                                    $i ++;
                                                @endphp
                                            @endforeach
                                            {{-- @foreach ($sold_stock_order_items as $item)
                                                @php
                                                    // $item = $stock->last_item();
                                                    $stock = $item->stock;
                                                    $variation = $item->variation;
                                                    if(in_array($item->order->order_type_id,[1,4])){
                                                        $stock->status = 1;
                                                        $stock->save();
                                                        continue;
                                                    }
                                                @endphp
                                                <tr>
                                                    <td>{{ $i + 1 }}</td>
                                                    <td>

                                                        @php
                                                        isset($variation->product_id)?$product = $products[$variation->product_id]:$product = null;
                                                        isset($variation->color)?$color = $colors[$variation->color]:$color = null;
                                                        isset($variation->storage)?$storage = $storages[$variation->storage]:$storage = null;
                                                        isset($variation->grade)?$grade = $grades[$variation->grade]:$grade = null;
                                                        @endphp
                                                        {{ $product." ".$storage." ".$color}} {{$grade }}
                                                    </td>
                                                    <td title="Double click to change" data-stock="{{ $stock->id }}">{{ $stock->imei.$stock->serial_number }}</td>
                                                    <td>{{ $item->order->customer->first_name }}</td>
                                                    @if (session('user')->hasPermission('view_cost'))
                                                    <td>{{ $currency.number_format($stock->purchase_item->price,2) }}</td>
                                                    @endif
                                                    @if (session('user')->hasPermission('view_cost'))
                                                    <td>{{ $currency.number_format($item->price,2) }}</td>
                                                    @endif
                                                    <td style="width:220px">{{ $item->created_at }}</td>
                                                </tr>
                                                @php
                                                    $i ++;
                                                @endphp
                                            @endforeach --}}
                                        </tbody>
                                    </table>
                                <br>
                            </div>

                            </div>
                        </div>
                    </div>
                </div>

                @endif
            </div>
            <div class="col-lg-2">

                <div class="card">
                    <div class="card-header pb-0">
                        Graded Total
                    </div>
                    <div class="card-body"><div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                            <thead>
                                <tr>
                                    {{-- <th><small><b>No</b></small></th> --}}
                                    <th><small><b>Grade</b></small></th>
                                    <th><small><b>Count</b></small></th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $total = 0;
                                @endphp
                                @foreach ($graded_count as $count)

                                        @php
                                            $total += $count->quantity;
                                        @endphp
                                    <tr>
                                        {{-- <td>{{ $i }}</td> --}}
                                        <td data-stock="{{ $item->id }}">{{ $count->grade }}</td>
                                        <td data-stock="{{ $item->id }}">{{ $count->quantity }}</td>
                                    </tr>
                                    {{-- @endif --}}
                                @endforeach
                            </tbody>

                            <tfoot>
                                <tr>
                                    {{-- <th><small><b>No</b></small></th> --}}
                                    <th><b>Total</b></th>
                                    <th><b>{{ $total }}</b></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @endif
    @endsection

    @section('scripts')
        <script>
            $(document).ready(function() {
                $('.test').select2();
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
