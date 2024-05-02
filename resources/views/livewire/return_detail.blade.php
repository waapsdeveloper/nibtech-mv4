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
            <div class="breadcrumb-header justify-content-between">
                <div class="left-content">
                {{-- <span class="main-content-title mg-b-0 mg-b-lg-1">Return</span> --}}
                    @if ($order->status == 2)
                    <form class="form-inline" method="POST" action="{{url('return/approve').'/'.$order->id}}">
                        @csrf
                        <div class="form-floating">
                            <input type="text" class="form-control" id="tracking_number" name="tracking_number" placeholder="Enter Tracking Number" required>
                            <label for="tracking_number">Tracking Number</label>
                        </div>
                        <button type="submit" class="btn btn-success">Approve</button>
                        <a class="btn btn-danger" href="{{url('delete_order') . "/" . $order->id }}">Delete</a>
                    </form>
                    @endif
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Return Detail</li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <div class="row">
            <div class="col-md-12 tx-center" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>@if ($order->status == 2)<small>(Pending)</small>@endif Return Order Detail</h4></center>
                <h5>Reference: {{ $order->reference_id }} | Total Items: {{ $order->order_items->count() }} | Total Cost: {{ $order->currency_id->sign.number_format($order->order_items->sum('price'),2) }}</h5>
            </div>
        </div>
        <br>

        <div class="d-flex justify-content-between" style="border-bottom: 1px solid rgb(216, 212, 212);">

            <div class="p-2">
                <form action="" method="GET" id="search" class="form-inline">
                    <div class="form-floating">
                        <input type="text" class="form-control" name="imei" placeholder="Enter IMEI" value="@isset($_GET['imei']){{$_GET['imei']}}@endisset">
                        <label for="">IMEI</label>
                    </div>
                        <button class="btn btn-primary pd-x-20" type="submit">{{ __('locale.Search') }}</button>
                </form>
            </div>
            @if (session('user')->hasPermission('delete'))
                {{-- <a href="{{ url('imei')}}?imei={{$imei}}&delete=YES">DELETE</a> --}}
            @endif
        </div>
        <hr style="border-bottom: 1px solid rgb(62, 45, 45);">
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

        @if (isset($stock))

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between">
                            <h4 class="card-title mg-b-0">
                            </h4>

                            <div class=" mg-b-0">
                            </div>

                        </div>
                    </div>
                    <div class="card-body"><div class="table-responsive">
                        @if (isset($orders))

                            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>No</b></small></th>
                                        <th><small><b>Order ID</b></small></th>
                                        <th><small><b>Type</b></small></th>
                                        <th><small><b>Customer / Vendor</b></small></th>
                                        <th><small><b>Product</b></small></th>
                                        <th><small><b>Qty</b></small></th>
                                        <th><small><b>IMEI</b></small></th>
                                        <th><small><b>Creation Date | TN</b></small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = 0;
                                        $id = [];
                                    @endphp
                                    @foreach ($orders as $index => $item)
                                        @php
                                            $order = $item->order;
                                            $j = 0;
                                        @endphp

                                            <tr>
                                                <td title="{{ $item->id }}">{{ $i + 1 }}</td>
                                                @if ($order->order_type_id == 1)

                                                    <td><a href="{{url(session('url').'purchase/detail/'.$order->id)}}">{{ $order->reference_id }}</a></td>
                                                @elseif ($order->order_type_id == 2)
                                                    <td><a href="{{url(session('url').'rma/detail/'.$order->id)}}">{{ $order->reference_id }}</a></td>
                                                @elseif ($order->order_type_id == 5)
                                                    <td><a href="{{url(session('url').'wholesale/detail/'.$order->id)}}">{{ $order->reference_id }}</a></td>
                                                @elseif ($order->order_type_id == 3)
                                                    <td>{{ $order->reference_id }}</td>
                                                @endif
                                                <td>{{ $order->order_type->name }}</td>
                                                <td>{{ $order->customer->first_name." ".$order->customer->last_name }}</td>
                                                <td>
                                                    @if ($item->variation ?? false)
                                                        <strong>{{ $item->variation->sku }}</strong>{{ " - " . $item->variation->product->model . " - " . (isset($item->variation->storage_id)?$item->variation->storage_id->name . " - " : null) . (isset($item->variation->color_id)?$item->variation->color_id->name. " - ":null)}} <strong><u>{{ $item->variation->grade_id->name }}</u></strong>
                                                    @endif
                                                    @if ($item->care_id != null)
                                                        <a class="" href="https://backmarket.fr/bo_merchant/customer-request/{{ $item->care_id }}" target="_blank"><strong class="text-danger">Conversation</strong></a>
                                                    @endif
                                                </td>
                                                <td>{{ $item->quantity }}</td>
                                                @if ($order->status == 3)
                                                <td style="width:240px" class="text-success text-uppercase" title="{{ $item->stock_id }}" id="copy_imei_{{ $order->id }}">
                                                    @isset($item->stock->imei) {{ $item->stock->imei }}&nbsp; @endisset
                                                    @isset($item->stock->serial_number) {{ $item->stock->serial_number }}&nbsp; @endisset
                                                    @isset($order->processed_by) | {{ $order->admin->first_name[0] }} | @endisset
                                                    @isset($item->stock->tester) ({{ $item->stock->tester }}) @endisset
                                                </td>

                                                @endif
                                                @if ($order->status != 3)
                                                <td style="width:240px" title="{{ $item->stock_id }}">
                                                        <strong class="text-danger">{{ $order->order_status->name }}</strong>
                                                    @isset($item->stock->imei) {{ $item->stock->imei }}&nbsp; @endisset
                                                    @isset($item->stock->serial_number) {{ $item->stock->serial_number }}&nbsp; @endisset

                                                    @isset($order->processed_by) | {{ $order->admin->first_name[0] }} | @endisset
                                                    @isset($item->stock->tester) ({{ $item->stock->tester }}) @endisset
                                                </td>
                                                @endif
                                                <td style="width:220px">{{ $order->created_at}} <br> {{ $order->processed_at." ".$order->tracking_number }}</td>
                                            </tr>
                                        @php
                                            $i ++;
                                        @endphp
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                        <br>
                    </div>

                    </div>
                </div>
            </div>
        </div>

        @endif
        @if (isset($stocks))
        <div class="row">
            <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>Moved Inventory</h4></center>
            </div>
        </div>
        <br>

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between">
                            <h4 class="card-title mg-b-0">

                            </h4>

                            <div class=" mg-b-0">
                                Today's count: {{ count($stocks) }}
                            </div>

                        </div>
                    </div>
                    <div class="card-body"><div class="table-responsive">

                            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>No</b></small></th>
                                        <th><small><b>Old Variation</b></small></th>
                                        <th><small><b>New Variation</b></small></th>
                                        <th><small><b>IMEI</b></small></th>
                                        <th><small><b>Vendor | Lot</b></small></th>
                                        <th><small><b>Reason</b></small></th>
                                        <th><small><b>DateTime</b></small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = 0;
                                    @endphp
                                    @foreach ($stocks as $operation)

                                            <tr>
                                                <td title="{{ $operation->id }}">{{ $i + 1 }}</td>
                                                <td>
                                                    @if ($operation->old_variation ?? false)
                                                        <strong>{{ $operation->old_variation->sku }}</strong>{{ " - " . $operation->old_variation->product->model . " - " . (isset($operation->old_variation->storage_id)?$operation->old_variation->storage_id->name . " - " : null) . (isset($operation->old_variation->color_id)?$operation->old_variation->color_id->name. " - ":null)}} <strong><u>{{ (isset($operation->old_variation->grade_id)?$operation->old_variation->grade_id->name:null)}} </u></strong>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($operation->new_variation ?? false)
                                                        <strong>{{ $operation->new_variation->sku }}</strong>{{ " - " . $operation->new_variation->product->model . " - " . (isset($operation->new_variation->storage_id)?$operation->new_variation->storage_id->name . " - " : null) . (isset($operation->new_variation->color_id)?$operation->new_variation->color_id->name. " - ":null)}} <strong><u>{{ $operation->new_variation->grade_id->name }}</u></strong>
                                                    @endif
                                                </td>
                                                <td>{{ $operation->stock->imei.$operation->stock->serial_number }}</td>
                                                <td>{{ $operation->stock->order->customer->first_name." | ".$operation->stock->order->reference_id }}</td>
                                                <td>{{ $operation->description }}</td>
                                                <td>{{ $operation->created_at }}</td>
                                            </tr>
                                        @php
                                            $i ++;
                                        @endphp
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
                                                <form id="order_issues_{{$j+=1}}" method="POST" action="{{ url('return/remove_issues') }}" class="form-inline">
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
                                                    <td title="{{ $key }}">{{ $value }}</td>
                                                @endforeach
                                                <td>{{ $row->message }}</td>
                                                <td>{{ $row->created_at }}</td>
                                                {{-- <td>
                                                    <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical  tx-18"></i></a>
                                                    <div class="dropdown-menu">
                                                        <a class="dropdown-item" href="">link</a>
                                                        <a class="dropdown-item" href="" target="_blank">link</a>
                                                    </div>
                                                </td> --}}
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
        @if (count($missing_stock)>0)

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between">
                            <h4 class="card-title mg-b-0">Missing IMEI Items</h4>
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
                                        @if (session('user')->hasPermission('view_cost'))
                                        <th><small><b>Cost</b></small></th>
                                        @endif
                                        <th><small><b>Creation Date</b></small></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = 0;
                                    @endphp
                                    @foreach ($missing_stock as $item)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>

                                                @php
                                                isset($item->variation->color_id)?$color = $item->variation->color_id->name:$color = null;
                                                isset($item->variation->storage)?$storage = $storages[$item->variation->storage]:$storage = null;
                                                @endphp
                                                {{ $item->variation->product->model." ".$storage." ".$color." ".$item->variation->grade_id->name }}
                                            </td>
                                            <td data-stock="{{ $item->stock_id }}">{{ $item->stock->imei.$item->stock->serial_number }}</td>
                                            <td>{{ $item->stock->order->customer->first_name }}</td>
                                            @if (session('user')->hasPermission('view_cost'))
                                            <td>{{ $currency.number_format($item->price,2) }}</td>
                                            @endif
                                            <td style="width:220px">{{ $item->created_at }}</td>
                                            <td><a href="{{ url('delete_rma_item').'/'.$item->id }}"><i class="fa fa-trash"></i></a></td>
                                        </tr>
                                        @php
                                            $i ++;
                                        @endphp
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
        <br>

        <div class="row">

            @foreach ($variations as $variation)
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header pb-0">
                        @php
                            isset($variation->color_id)?$color = $variation->color_id->name:$color = null;
                            isset($variation->storage)?$storage = $storages[$variation->storage]:$storage = null;
                        @endphp
                        {{ $variation->product->model." ".$storage." ".$color." ".$variation->grade_id->name }}
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
                                        @if (session('user')->hasPermission('delete_return_item'))
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
                                        $prices[] = $item->return_item->price;
                                    @endphp
                                        <tr>
                                            <td>{{ $i }}</td>
                                            <td data-stock="{{ $item->id }}">{{ $item->imei.$item->serial_number }}</td>
                                            @if (session('user')->hasPermission('view_cost'))
                                            <td>{{ $currency.$item->return_item->price }}</td>
                                            @endif
                                            @if (session('user')->hasPermission('delete_return_item'))
                                            <td><a href="{{ url('delete_order_item').'/'.$item->return_item->id }}"><i class="fa fa-trash"></i></a></td>
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
