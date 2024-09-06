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
                {{-- <span class="main-content-title mg-b-0 mg-b-lg-1">BulkSale Return</span> --}}
                    @if ($order->status == 1)
                    <form class="form-inline" method="POST" action="{{url('wholesale_return/ship').'/'.$order->id}}">
                        @csrf
                        <div class="">
                            <select name="customer_id" class="form-select">
                                <option value="" disabled selected>Select Vendor</option>
                                @foreach ($vendors as $id=>$vendor)
                                    <option value="{{ $id }}" {{ $order->customer_id == $id ? 'selected' : '' }}>{{ $vendor }}</option>

                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success">Start</button>
                    </form>
                    @else
                        @if ($order->status == 2)
                        <form class="form-inline" method="POST" action="{{url('wholesale_return/approve').'/'.$order->id}}">
                            @csrf
                            <div class="form-floating">
                                <input type="text" class="form-control" id="reference" name="reference" placeholder="Enter Reference Message" value="{{$order->reference}}" required>
                                <label for="reference">Reference Message</label>
                            </div>
                            <div class="form-floating">
                                <input type="text" class="form-control" id="tracking_number" name="tracking_number" value="{{$order->tracking_number}}" placeholder="Enter Tracking Number" required>
                                <label for="tracking_number">Tracking Number</label>
                            </div>
                            <button type="submit" class="btn btn-success">Accept</button>
                        </form>
                        @else
                        Reference: {{$order->reference}}<br>
                        Tracking Number: <a href="https://www.dhl.com/gb-en/home/tracking/tracking-express.html?submit=1&tracking-id={{$order->tracking_number}}" target="_blank"> {{$order->tracking_number}}</a>
                        <br>
                        @endif

                        @if (session('user')->hasPermission('wholesale_return_revert_status'))
                            <a href="{{url('wholesale_return/revert_status').'/'.$order->id}}">Revert Back to Pending</a>
                        @endif
                    @endif
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                        <li class="breadcrumb-item active" aria-current="page">BulkSale Return Detail</li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <div class="row">
            <div class="col-md-12 tx-center" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>@if ($order->status == 1)<small>(Pending)</small>@endif @if ($order->status == 2)<small>(Awaiting Approval)</small>@endif BulkSale Return Order Detail</h4></center>
                <h5>Customer: {{ $vendors[$order->customer_id] ?? null }} | Reference: {{ $order->reference_id }} | Total Items: {{ $order->order_items->count() }}</h5>
            </div>
        </div>
        <br>

        <div class="d-flex justify-content-between" style="border-bottom: 1px solid rgb(216, 212, 212);">

            <div class="p-2">

                @if ($order->status == 2)
                <form class="form-inline" action="{{ url('add_wholesale_return_item').'/'.$order_id }}" method="POST" id="wholesale_return_item">
                    @csrf
                    <div class="form-floating">
                        <input type="text" class="form-control" id="imeiInput" name="imei" placeholder="Enter IMEI" value="@isset($_GET['imei']){{$_GET['imei']}}@endisset" onloadeddata="$(this).focus()" autofocus required>
                        <label for="">IMEI | Serial Number:</label>
                    </div>
                    {{-- <label for="imei" class="">IMEI | Serial Number: &nbsp;</label>
                    <input type="text" class="form-control form-control-sm" name="imei" id="imei" placeholder="Enter IMEI"> --}}
                    <select name="grade" class="form-control form-select" required>
                        <option value="">Move to</option>
                        @foreach ($grades as $id => $name)
                            @if($id > 5)
                            <option value="{{ $id }}" @if(session('grade') && $id == session('grade')) {{'selected'}}@endif @if(request('grade') && $id == request('grade')) {{'selected'}}@endif>{{ $name }}</option>
                            @endif
                        @endforeach
                    </select>

                    <div class="form-floating">
                        <input type="text" class="form-control pd-x-20" name="description" placeholder="Reason" style="width: 270px;" value="{{session('description')}}">
                        {{-- <input type="text" class="form-control" name="wholesale_return[imei]" placeholder="Enter IMEI" value="@isset($_GET['imei']){{$_GET['imei']}}@endisset"> --}}
                        <label for="">Reason</label>
                    </div>
                    <button class="btn-sm btn-primary pd-x-20" type="submit">Receive</button>

                </form>
                <script>

                    window.onload = function() {
                        document.getElementById('imeiInput').focus();
                        document.getElementById('imeiInput').click();
                        setTimeout(function(){ document.getElementById('imeiInput').focus();$('#imeiInput').focus(); }, 500);
                    };
                    document.addEventListener('DOMContentLoaded', function() {
                        var input = document.getElementById('imeiInput');
                        input.focus();
                        input.select();
                        document.getElementById('imeiInput').click();
                        setTimeout(function(){ document.getElementById('imeiInput').focus();$('#imeiInput').focus(); }, 500);
                    });
                </script>
                @endif
            </div>
            @if (session('user')->hasPermission('add_refund_items') && isset($restock))
                <div class="p-2">
                    <form action="{{ url('add_wholesale_return_item').'/'.$order_id}}" method="POST" class="form-inline">
                        @csrf
                        <select name="wholesale_return[product]" class="form-control form-select" style="width: 150px;">
                            <option value="">Model</option>
                            @foreach ($products as $id => $model)
                                <option value="{{ $id }}"@if($id == $stock->variation->product_id) {{'selected'}}@endif>{{ $model }}</option>
                            @endforeach
                        </select>
                        <select name="wholesale_return[storage]" class="form-control form-select">
                            <option value="">Storage</option>
                            @foreach ($storages as $id => $name)
                                <option value="{{ $id }}"@if($id == $stock->variation->storage) {{'selected'}}@endif>{{ $name }}</option>
                            @endforeach
                        </select>
                        <select name="wholesale_return[color]" class="form-control form-select" style="width: 150px;">
                            <option value="">Color</option>
                            @foreach ($colors as $id => $name)
                                <option value="{{ $id }}"@if($id == $stock->variation->color) {{'selected'}}@endif>{{ $name }}</option>
                            @endforeach
                        </select>
                        <select name="wholesale_return[grade]" class="form-control form-select">
                            <option value="">Move to</option>
                            @foreach ($grades as $id => $name)
                                @if($id > 5)
                                <option value="{{ $id }}">{{ $name }}</option>
                                @endif
                            @endforeach
                        </select>

                        <div class="form-floating">
                            <input type="text" class="form-control pd-x-20" name="wholesale_return[description]" placeholder="Reason" style="width: 270px;">
                            {{-- <input type="text" class="form-control" name="wholesale_return[imei]" placeholder="Enter IMEI" value="@isset($_GET['imei']){{$_GET['imei']}}@endisset"> --}}
                            <label for="">Reason</label>
                        </div>

                        <input type="hidden" name="wholesale_return[order_id]" value="{{ $restock['order_id'] }}">
                        <input type="hidden" name="wholesale_return[reference_id]" value="{{ $restock['reference_id'] }}">
                        <input type="hidden" name="wholesale_return[stock_id]" value="{{ $restock['stock_id'] }}">
                        <input type="hidden" name="wholesale_return[price]" value="{{ $restock['price'] }}">
                        <input type="hidden" name="wholesale_return[linked_id]" value="{{ $restock['linked_id'] }}">
                        <button class="btn btn-secondary pd-x-20" type="submit">Restock</button>
                    </form>
                </div>
            @endif
            <div class="p-2">

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
                                            @if (session('user')->hasPermission('delete_wholesale_return_item'))
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
                                            @if ($item->stock == null)
                                                {{$item->stock_id}}
                                                @continue
                                            @endif
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $products[$item->variation->product_id] ?? "Variation Model Not added"}} {{$storages[$item->variation->storage] ?? null}} {{$colors[$item->variation->color] ?? null}} {{$grades[$item->variation->grade] ?? "Variation Grade Not added Reference: ".$item->variation->reference_id }}</td>
                                            <td>{{ $item->stock->imei.$item->stock->serial_number }}</td>
                                            <td>{{ $item->stock->order->customer->first_name }}</td>
                                            @if (session('user')->hasPermission('view_cost'))
                                            <td>{{ $currency.number_format($item->price,2) }}</td>
                                            @endif
                                            <td style="width:220px">{{ $item->created_at }}</td>
                                            @if (session('user')->hasPermission('delete_wholesale_return_item') && $order->status != 3)
                                            <td><a href="{{ url('delete_wholesale_return_item').'/'.$item->id }}"><i class="fa fa-trash"></i></a></td>
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

            <div>
                @foreach ($graded_stocks as $graded_stock)
                @php
                    if($graded_stock->variations->count() == 0){
                        continue;
                    }
                @endphp
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
                                            <th><small><b>Vendor</b></small></th>
                                            @if (session('user')->hasPermission('view_cost'))
                                            <th><small><b>Cost</b></small></th>
                                            @endif
                                            <th><small><b>Reason</b></small></th>
                                            <th><small><b>Member</b></small></th>
                                            <th><small><b>Date</b></small></th>
                                            @if (session('user')->hasPermission('delete_wholesale_return_item'))
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

                                        @foreach ($variation->stocks->sortByDesc('stocks.updated_at') as $stock)
                                            @php
                                            $row = $stock->latest_operation;
                                            $i ++;
                                            if(str_contains($row->description, "Replacement")){
                                                if($stock->status != 2){

                                                    $stock->availability();
                                                }
                                            }
                                        @endphp
                                            <tr>
                                                <td>{{ $i }}</td>
                                                <td>{{ $variation->product->model." ".$storage." ".$color." ".$variation->grade_id->name ?? "Not Given" }}</td>
                                                <td data-stock="{{ $stock->id }}">{{ $stock->imei.$stock->serial_number }}</td>
                                                <td>{{ $stock->order->customer->first_name." ".$stock->order->reference_id }}</td>
                                                @if (session('user')->hasPermission('view_cost'))
                                                <td>{{ $currency.$stock->sale_item($order_id)->price }}</td>
                                                @endif
                                                <td>{{ $row->description ?? null }}</td>
                                                <td>{{ $row->admin->first_name ?? null }}</td>
                                                <td>{{ $row->updated_at ?? null }}</td>
                                                @if (session('user')->hasPermission('delete_wholesale_return_item'))
                                                <td><a href="{{ url('delete_wholesale_return_item').'/'.$stock->sale_item($order_id)->id }}"><i class="fa fa-trash"></i></a></td>
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
                @endforeach
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
