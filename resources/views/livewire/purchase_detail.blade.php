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
        @if (session('error'))
            <script>
                window.alert('{{ session()->get("error") }}');
            </script>
            @php
            session()->forget('error');
            @endphp
        @endif

        <!-- breadcrumb -->
            <div class="breadcrumb-header justify-content-between">
                <div class="left-content">
                {{-- <span class="main-content-title mg-b-0 mg-b-lg-1">Purchase</span> --}}
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Purchase Detail</li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <div class="row">
            <div class="col-md-12 tx-center" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>Purchase Order Detail</h4></center>
                <h5>Reference: {{ $order->reference_id }} | Vendor: {{ $order->customer->first_name }} | Total Items: {{ $order->order_items->count() }} | Total Cost: {{ $order->currency_id->sign.number_format($order->order_items->sum('price'),2) }}</h5>
            </div>
        </div>
        <br>

        <form action="{{ url('add_purchase_item').'/'.$order_id }}" method="POST">
            @csrf
            <div class="row">
                <div class="col-lg-3 col-xl-3 col-md-4 col-sm-6">
                    <div class="card-header">
                        <h4 class="card-title">Variation</h4>
                    </div>
                    <input type="text" list="variations" name="variation" class="form-control">
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
                </div>
                <div class="col-lg-3 col-xl-3 col-md-4 col-sm-6">
                    <div class="card-header">
                        <h4 class="card-title">IMEI</h4>
                    </div>
                    <input type="text" class="form-control" name="imei" placeholder="Enter IMEI" value="@isset($_GET['imei']){{$_GET['imei']}}@endisset">
                </div>
                <div class="col-lg-3 col-xl-3 col-md-4 col-sm-6">
                    <div class="card-header">
                        <h4 class="card-title">Cost</h4>
                    </div>
                    <input type="text" class="form-control" name="price" placeholder="Enter Price" value="@isset($_GET['price']){{$_GET['price']}}@endisset">
                </div>
                <div class="col-lg-3 col-xl-3 col-md-4 col-sm-6 align-self-end mb-1 tx-center">
                    <h4>Add Purchased Item</h4>
                    <button class="btn btn-primary pd-x-20" type="submit">Insert</button>
                </div>
            </div>
        </form>

        <br>
        @if (count($missing_stock)>0)

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
                                    @foreach ($missing_stock as $item)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $products[$item->variation->product_id]}} {{$storages[$item->variation->storage] ?? null}} {{$colors[$item->variation->color] ?? null}} {{$grades[$item->variation->grade] }}</td>
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
                                        // print_r($variation);
                                    @endphp

                                    @foreach ($stocks as $item)
                                        {{-- @dd($item) --}}
                                        {{-- @if($item->order_item[0]->order_id == $order_id) --}}
                                        @php
                                        $i ++;
                                    @endphp
                                        <tr>
                                            <td>{{ $i }}</td>
                                            <td data-stock="{{ $item->stock_id }}">{{ $item->imei.$item->serial_number }}</td>
                                            @if (session('user')->hasPermission('view_cost'))
                                            <td>{{ $currency.$item->purchase_item->price }}</td>
                                            @endif
                                            @if (session('user')->hasPermission('delete_purchase_item'))
                                            <td><a href="{{ url('delete_order_item').'/'.$item->purchase_item->id }}"><i class="fa fa-trash"></i></a></td>
                                            @endif
                                        </tr>
                                        {{-- @endif --}}
                                    @endforeach
                                </tbody>
                            </table>
                        <br>
                    </div>
                    <div class="text-end">Total: {{$i }}</div>
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
