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
                    @if ($order->status == 1)
                    <form class="form-inline" method="POST" action="{{url('return/approve').'/'.$order->id}}">
                        @csrf
                        <div class="form-floating">
                            <input type="text" class="form-control" id="tracking_number" name="tracking_number" placeholder="Enter Tracking Number" required>
                            <label for="tracking_number">Tracking Number</label>
                        </div>
                        <button type="submit" class="btn btn-success">Ship</button>
                    </form>
                    @else
                    Tracking Number: <a href="https://www.dhl.com/gb-en/home/tracking/tracking-express.html?submit=1&tracking-id={{$order->tracking_number}}" target="_blank"> {{$order->tracking_number}}</a>
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
                <center><h4>@if ($order->status == 1)<small>(Pending)</small>@endif @if ($order->status == 2)<small>(Awaiting Approval)</small>@endif Return Order Detail</h4></center>
                <h5>Reference: {{ $order->reference_id }} | Total Items: {{ $order->order_items->count() }}</h5>
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
            @if (isset($stock) && $stock->status == 1)

                <div class="p-2">
                    <form action="{{url('order/refresh/')}}" method="GET" id="search" class="form-inline">
                        <div class="form-floating">
                            <input type="text" class="form-control" name="reference_id" placeholder="Enter Order Number" value="@isset($_GET['reference_id']){{$_GET['reference_id']}}@endisset">
                            <label for="">Order Number</label>
                        </div>
                            <button class="btn btn-primary pd-x-20" type="submit">Get Sales Order</button>
                    </form>
                </div>
            @endif
            @if (session('user')->hasPermission('add_refund_items') && isset($restock))
                <div class="p-2">
                    <form action="{{ url('add_return_item').'/'.$order_id}}" method="POST" class="form-inline">
                        @csrf
                        <select name="return[product]" class="form-control form-select" style="width: 150px;">
                            <option value="">Model</option>
                            @foreach ($products as $id => $model)
                                <option value="{{ $id }}"@if($id == $stock->variation->product_id) {{'selected'}}@endif>{{ $model }}</option>
                            @endforeach
                        </select>
                        <select name="return[storage]" class="form-control form-select">
                            <option value="">Storage</option>
                            @foreach ($storages as $id => $name)
                                <option value="{{ $id }}"@if($id == $stock->variation->storage) {{'selected'}}@endif>{{ $name }}</option>
                            @endforeach
                        </select>
                        <select name="return[color]" class="form-control form-select" style="width: 150px;">
                            <option value="">Color</option>
                            @foreach ($colors as $id => $name)
                                <option value="{{ $id }}"@if($id == $stock->variation->color) {{'selected'}}@endif>{{ $name }}</option>
                            @endforeach
                        </select>
                        <select name="return[grade]" class="form-control form-select">
                            <option value="">Move to</option>
                            @foreach ($grades as $id => $name)
                                @if($id > 5)
                                <option value="{{ $id }}">{{ $name }}</option>
                                @endif
                            @endforeach
                        </select>

                        <div class="form-floating">
                            <input type="text" class="form-control pd-x-20" name="return[description]" placeholder="Reason" style="width: 270px;">
                            {{-- <input type="text" class="form-control" name="return[imei]" placeholder="Enter IMEI" value="@isset($_GET['imei']){{$_GET['imei']}}@endisset"> --}}
                            <label for="">Reason</label>
                        </div>

                        <input type="hidden" name="return[order_id]" value="{{ $restock['order_id'] }}">
                        <input type="hidden" name="return[reference_id]" value="{{ $restock['reference_id'] }}">
                        <input type="hidden" name="return[stock_id]" value="{{ $restock['stock_id'] }}">
                        <input type="hidden" name="return[price]" value="{{ $restock['price'] }}">
                        <input type="hidden" name="return[linked_id]" value="{{ $restock['linked_id'] }}">
                        <button class="btn btn-secondary pd-x-20" type="submit">Restock</button>
                    </form>
                </div>
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


        @php
        session()->forget('error');
        @endphp
        @endif
        @if (request('imei') && (!isset($stock) || !$stock->purchase_item))

        <form action="{{ url('add_purchase_item').'/8441' }}" method="POST">
            @csrf
            <div class="row">
                <div class="col-md col-sm-6">
                    <div class="form-floating">
                        <select type="text" id="order" name="order" class="form-control" required>
                            <option value="">Select</option>
                            <option value="4739">Sunstrike</option>
                            <option value="1">Mobi</option>
                            <option value="5">Mudassir</option>
                            <option value="8">PCS Wireless</option>
                            <option value="9">PCS Wireless UAE</option>
                            <option value="12">PCS Wireless UK</option>
                            <option value="13">Cenwood</option>
                            <option value="14">US Mobile</option>
                            <option value="185">Waqas</option>
                            <option value="263">Wize</option>
                        </select>
                        <label for="order">Vendor</label>
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    <div class="form-floating">
                        <input type="text" list="variations" id="variation" name="variation" class="form-control" required>
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
                </div>
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
                <div class="col-md col-sm-6 align-self-end mb-1 tx-center">
                    <button class="btn btn-primary pd-x-20" type="submit">Insert</button>
                </div>
                <br>
                <br>
            </div>
        </form>
        <br>
        @endif

        @if (isset($stock))
        {{-- External Movement --}}

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between">
                            <h4 class="card-title mg-b-0">
                                External Movement
                            </h4>

                            <div class=" mg-b-0">

                                @if (session('user')->hasPermission('add_refund_items') && isset($restock))
                                    <form action="{{ url('move_inventory/change_grade')}}" method="POST" class="form-inline">
                                        @csrf
                                        AfterSale Movement
                                        <div class="form-floating">
                                            <input type="text" class="form-control pd-x-20" name="description" placeholder="Reason" style="width: 270px;">
                                            {{-- <input type="text" class="form-control" name="return[imei]" placeholder="Enter IMEI" value="@isset($_GET['imei']){{$_GET['imei']}}@endisset"> --}}
                                            <label for="">Reason</label>
                                        </div>

                                        <input type="hidden" name="imei" value="@isset($_GET['imei']){{$_GET['imei']}}@endisset">
                                        <button class="btn btn-secondary pd-x-20" name="grade" value="8" type="submit">Repair</button>
                                        <button class="btn btn-secondary pd-x-20" name="grade" value="12" type="submit">Hold</button>
                                    </form>
                                @endif
                            </div>
                            <div>

                                @if (session('user')->hasPermission('add_refund_items') && isset($restock))
                                <a class="btn btn-primary" id="replacement" href="javascript:void(0);" data-bs-target="#replacement_model" data-bs-toggle="modal" data-bs-reference="{{ $restock['reference_id'] }}" data-bs-item="{{ $restock['linked_id'] }}"> Replacement </a>
                                @endif
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
                                        <th><small><b>Customer</b></small></th>
                                        <th><small><b>Type</b></small></th>
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
                                                @elseif ($order->order_type_id == 4)
                                                    <td><a href="{{url(session('url').'return/detail/'.$order->id)}}">{{ $order->reference_id }}</a></td>
                                                @elseif ($order->order_type_id == 5)
                                                    <td><a href="{{url(session('url').'wholesale/detail/'.$order->id)}}">{{ $order->reference_id }}</a></td>
                                                @elseif ($order->order_type_id == 3)
                                                    <td>{{ $order->reference_id }}</td>
                                                @endif
                                                <td>
                                                    @if ($order->customer != null)
                                                    <a title="Vendor Profile" href="{{url('edit-customer').'/'.$order->customer_id}}" target="_blank">{{ $order->customer->first_name." ".$order->customer->last_name }}</a></td>
                                                    @endif
                                                <td>{{ $order->order_type->name }}</td>
                                                <td>
                                                    @if ($item->variation ?? false)
                                                        <strong>{{ $item->variation->sku }}</strong>{{ " - " . $item->variation->product->model . " - " . (isset($item->variation->storage_id)?$item->variation->storage_id->name . " - " : null) . (isset($item->variation->color_id)?$item->variation->color_id->name. " - ":null)}} <strong><u>{{ $item->variation->grade_id->name }}</u></strong>
                                                    @endif
                                                    @if ($item->care_id != null)
                                                        <a class="" href="https://backmarket.fr/bo_merchant/customer-request/{{ $item->care_id }}" target="_blank"><strong class="text-danger">Conversation</strong></a>
                                                    @endif
                                                </td>
                                                <td>{{ $item->quantity }}</td>
                                                @if ($order->status <= 3)
                                                <td style="width:240px" class="text-success text-uppercase" title="{{ $item->stock_id }}" id="copy_imei_{{ $order->id }}">
                                                    @isset($item->stock->imei) {{ $item->stock->imei }}&nbsp; @endisset
                                                    @isset($item->stock->serial_number) {{ $item->stock->serial_number }}&nbsp; @endisset
                                                    @isset($order->processed_by) | {{ $order->admin->first_name[0] }} | @endisset
                                                    @isset($item->stock->tester) ({{ $item->stock->tester }}) @endisset
                                                </td>

                                                @endif
                                                @if ($order->status > 3)
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
        {{-- Internal Movement --}}
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between">
                            <h4 class="card-title mg-b-0">
                                Internal Movement
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


        <br>
        @if (isset($graded_stocks))


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
                                            @if (session('user')->hasPermission('delete_return_item'))
                                        <th></th>
                                            @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = 0;
                                    @endphp
                                    @foreach ($last_ten as $item)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $products[$item->variation->product_id]}} {{$storages[$item->variation->storage] ?? null}} {{$colors[$item->variation->color] ?? null}} {{$grades[$item->variation->grade] }}</td>
                                            <td>{{ $item->stock->imei.$item->stock->serial_number }}</td>
                                            <td>{{ $item->stock->order->customer->first_name }}</td>
                                            @if (session('user')->hasPermission('view_cost'))
                                            <td>{{ $currency.number_format($item->price,2) }}</td>
                                            @endif
                                            <td style="width:220px">{{ $item->created_at }}</td>
                                            @if (session('user')->hasPermission('delete_return_item'))
                                            <td><a href="{{ url('delete_return_item').'/'.$item->id }}"><i class="fa fa-trash"></i></a></td>
                                            @endif
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
        <div class="row">

            @foreach ($graded_stocks as $graded_stock)
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header pb-0">
                        {{ $graded_stock->name}}

                    </div>
                            {{-- {{ $stock_operation }} --}}
                    <div class="card-body"><div class="table-responsive">

                            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>No</b></small></th>
                                        <th><small><b>Variation</b></small></th>
                                        <th><small><b>IMEI/Serial</b></small></th>
                                        @if (session('user')->hasPermission('view_cost'))
                                        <th><small><b>Cost</b></small></th>
                                        @endif
                                        <th><small><b>Reason</b></small></th>
                                        <th><small><b>Member</b></small></th>
                                        <th><small><b>Date</b></small></th>
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
                                        // $stocks = $stock_operation->stocks;
                                        // $items = $stocks->order_item;
                                        $j = 0;
                                        $prices = [];
                                        // print_r($stocks);
                                    @endphp

                                    @foreach ($graded_stock->variations as $variation)
                        @php
                            isset($variation->color_id)?$color = $variation->color_id->name:$color = null;
                            isset($variation->storage)?$storage = $storages[$variation->storage]:$storage = null;
                        @endphp

                                    @foreach ($variation->stocks as $stock)
                                        @php
                                        $row = $stock->latest_operation;
                                        $i ++;
                                    @endphp
                                        <tr>
                                            <td>{{ $i }}</td>
                                            <td>{{ $variation->product->model." ".$storage." ".$color." ".$variation->grade_id->name }}</td>
                                            <td data-stock="{{ $stock->id }}">{{ $stock->imei.$stock->serial_number }}</td>
                                            @if (session('user')->hasPermission('view_cost'))
                                            <td>{{ $currency.$stock->sale_item($order_id)->price }}</td>
                                            @endif
                                            <td>{{ $row->description ?? null }}</td>
                                            <td>{{ $row->admin->first_name ?? null }}</td>
                                            <td>{{ $row->updated_at ?? null }}</td>
                                            @if (session('user')->hasPermission('delete_return_item'))
                                            <td><a href="{{ url('delete_return_item').'/'.$stock->sale_item($order_id)->id }}"><i class="fa fa-trash"></i></a></td>
                                            @endif
                                        </tr>
                                        {{-- @endif --}}
                                    @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        <br>
                    </div>
                    <div class="text-end"> Total: {{$i }}</div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        @endif



        <div class="modal" id="replacement_model">
            <div class="modal-dialog wd-xl-400" role="document">
                <div class="modal-content">
                    <div class="modal-body pd-sm-40">
                        <button aria-label="Close" class="close pos-absolute t-15 r-20 tx-26" data-bs-dismiss="modal"
                            type="button"><span aria-hidden="true">&times;</span></button>
                        <h3 class="modal-title mg-b-5">Update Order</h3>
                        <hr>
                        <form action="{{ url('order/replacement') }}" method="POST">
                            @csrf
                            <div class="form-group">
                                <label for="">Order Number</label>
                                <input class="form-control" name="replacement[id]" type="text" id="order_reference" readonly>
                            </div>
                            <h4>Replace</h4>
                            <div class="form-group">
                                <label for="">Move to</label>
                                <select name="replacement[grade]" class="form-control form-select" required>
                                    <option value="">Move to</option>
                                    @foreach ($grades as $id=>$name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
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
    @endsection

    @section('scripts')
        <script>
            $(document).ready(function() {
                $('.test').select2();
            });

        </script>
    <script>

        $('#replacement_model').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget) // Button that triggered the modal
            var reference = button.data('bs-reference') // Extract info from data-* attributesv
            var item = button.data('bs-item') // Extract info from data-* attributes
            // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
            // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
            var modal = $(this)
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

		<!-- INTERNAL Select2 js -->
		<script src="{{asset('assets/plugins/select2/js/select2.full.min.js')}}"></script>
		<script src="{{asset('assets/js/select2.js')}}"></script>
    @endsection
