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
                    {{-- <span class="ms-3 form-check form-switch ms-4">
                        <input type="checkbox" value="1" name="bypass_check" class="form-check-input" form="rma_item" @if (session('bypass_check') == 1) checked @endif>
                        <label class="form-check-label" for="bypass_check">Bypass Wholesale check</label>
                    </span> --}}
                <span class="main-content-title mg-b-0 mg-b-lg-1">RMA Order Detail</span><br>
                @if ($order->status == 2)
                <form class="form-inline" method="POST" action="{{url('wholesale/approve').'/'.$order->id}}">
                    @csrf
                    <div class="form-floating">
                        <input type="text" class="form-control" id="reference" name="reference" placeholder="Enter Vendor Reference" required>
                        <label for="reference">Vendor Reference</label>
                    </div>
                    <div class="form-floating">
                        <input type="text" class="form-control" id="tracking_number" name="tracking_number" placeholder="Enter Tracking Number" required>
                        <label for="tracking_number">Tracking Number</label>
                    </div>
                    <button type="submit" class="btn btn-success">Approve</button>
                    <a class="btn btn-danger" href="{{url('delete_wholesale') . "/" . $order->id }}">Delete</a>
                </form>
                @else
                Tracking Number: <a href="https://www.dhl.com/gb-en/home/tracking/tracking-express.html?submit=1&tracking-id={{$order->tracking_number}}" target="_blank"> {{$order->tracking_number}}</a>
                <br>
                Reference: {{ $order->reference }}
                @endif

                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                        <li class="breadcrumb-item active" aria-current="page">RMA Detail</li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <div class="text-center" style="border-bottom: 1px solid rgb(216, 212, 212);">
                {{-- <center><h4>RMA Order Detail</h4></center> --}}
                <h5>Reference: {{ $order->reference_id }} | Purchaser: {{ $order->customer->first_name }} | Total Items: {{ $order->order_items->count() }} | Total Price: {{ $order->currency_id->sign.number_format($order->order_items->sum('price'),2) }}</h5>

        </div>
        <br>

                <h4>Add RMA Item</h4>
        <div class="d-flex justify-content-between" style="border-bottom: 1px solid rgb(216, 212, 212);">

            <div class="p-2">
                <span class="form-check form-switch ms-4" title="Bypass Wholesale check" onclick="$('#bypass_check').check()">
                    <input type="checkbox" value="1" id="bypass_check" name="bypass_check" class="form-check-input" form="rma_item" @if (session('bypass_check') == 1) checked @endif>
                    <label class="form-check-label" for="bypass_check">Bypass check</label>
                </span>

            </div>
            <div class="p-1">
                <form class="form-inline" action="{{ url('check_rma_item').'/'.$order_id }}" method="POST" id="rma_item">
                @csrf

                        <label for="imei" class="">IMEI | Serial Number: &nbsp;</label>
                        <input type="text" class="form-control form-control-sm" name="imei" id="imei" placeholder="Enter IMEI" onloadeddata="$(this).focus()" autofocus required>
                        <button class="btn-sm btn-primary pd-x-20" type="submit">Insert</button>

                </form>
            </div>
            <div class="p-2">
                <a href="{{url(session('url').'export_rma_invoice')}}/{{ $order->id }}" target="_blank"><button class="btn-sm btn-secondary">Invoice</button></a>

                <div class="btn-group" role="group">
                    <button type="button" class="btn-sm btn-secondary dropdown-toggle" id="pack_sheet" data-bs-toggle="dropdown" aria-expanded="false">
                    Pack Sheet
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="pack_sheet">
                        <li><a class="dropdown-item" href="{{url(session('url').'export_rma_invoice')}}/{{ $order->id }}?packlist=2&id={{ $order->id }}">.xlsx</a></li>
                        <li><a class="dropdown-item" href="{{url(session('url').'export_rma_invoice')}}/{{ $order->id }}?packlist=1" target="_blank">.pdf</a></li>
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
                                        <th><small><b>#</b></small></th>
                                        {{-- <th><small><b>Vendor</b></small></th> --}}
                                        <th><small><b>IMEI/Serial</b></small></th>
                                        {{-- @if (session('user')->hasPermission('view_cost')) --}}
                                        <th><small><b>Vendor Price</b></small></th>
                                        {{-- @endif --}}
                                        @if (session('user')->hasPermission('delete_rma_item'))
                                        <th></th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    <form method="POST" action="{{url(session('url').'rma')}}/update_prices" id="update_prices_{{ $variation->id }}">
                                        @csrf
                                    @php
                                        $i = 0;
                                        $id = [];
                                    @endphp
                                    @php
                                        $stocks = $variation->stocks;
                                        // $items = $stocks->order_item;
                                        $j = 0;
                                        // print_r($variation);
                                    @endphp

                                    @foreach ($stocks as $item)
                                        {{-- @dd($item->sale_item) --}}
                                        @if($item->sale_item($order_id)->order_id == $order_id)
                                        @php
                                            $i ++;
                                        @endphp
                                        <tr>
                                            <td>{{ $i }}</td>
                                            {{-- <td>{{ $item->order->customer->first_name }}</td> --}}
                                            <td>{{ $item->imei.$item->serial_number }}</td>
                                            <td @if (session('user')->hasPermission('view_cost')) title="Cost Price: {{ $currency.$item->purchase_item->price }}" @endif>
                                                {{ $item->order->customer->first_name }} {{ $currency.$item->sale_item($order_id)->price }}
                                            </td>

                                            @if (session('user')->hasPermission('delete_rma_item'))
                                            <td><a href="{{ url('delete_rma_item').'/'.$item->sale_item($order_id)->id }}"><i class="fa fa-trash"></i></a></td>
                                            @endif
                                            <input type="hidden" name="item_ids[]" value="{{ $item->sale_item($order_id)->id }}">
                                        </tr>
                                        @endif
                                    @endforeach
                                    </form>
                                </tbody>
                            </table>
                        <br>
                    </div>
                    <div class="d-flex justify-content-between">
                        <div>
                            <label for="unit-price" class="">Change Unit Price: </label>
                            <input type="number" name="unit_price" id="unit_price" class="w-50 border-0" placeholder="Input Unit price" form="update_prices_{{ $variation->id }}">
                        </div>
                        <div>Total: {{$i }}</div>
                    </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

    @endsection

    @section('scripts')
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
