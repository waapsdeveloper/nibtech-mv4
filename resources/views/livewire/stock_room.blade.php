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
        </style>
    @endsection
<br>
    @section('content')


        <!-- breadcrumb -->
            <div class="breadcrumb-header justify-content-between">
                <div class="left-content">
                {{-- <span class="main-content-title mg-b-0 mg-b-lg-1">Stock Exit & Receive</span> --}}
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Stock Exit & Receive</li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <div class="row">
            <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>Stock Exit & Receive</h4></center>
            </div>
        </div>
        <br>

        <div class="d-flex justify-content-between" style="border-bottom: 1px solid rgb(216, 212, 212);">

            <div class="p-2">
                @if (session('user')->hasPermission('receive_stock'))

                <form action="{{ url('stock_room/receive')}}" method="POST" id="search" class="form-inline">
                    @csrf
                    <div class="form-floating">
                        <input type="text" class="form-control" name="imei" placeholder="Enter IMEI" value="@isset($_GET['imei']){{$_GET['imei']}}@endisset" id="imeiInput" onload="this.focus()" autofocus>
                        <label for="">IMEI</label>
                    </div>
                        <button class="btn btn-secondary pd-x-20" type="submit">Receive</button>
                    {{-- @if (isset($stock))
                        &nbsp;&nbsp;&nbsp;&nbsp;Current Variation:&nbsp;&nbsp;&nbsp;&nbsp;<h5 class="mb-0">{{ $stock->variation->product->model ?? "Variation Issue"}}{{" - " . (isset($stock->variation->storage_id)?$stock->variation->storage_id->name . " - " : null) . (isset($stock->variation->color_id)?$stock->variation->color_id->name. " - ":null)}} <strong><u>{{ $stock->variation->grade_id->name ?? null }}</u></strong></h5>
                    @endif --}}
                </form>
                @else
                <form action="{{ url('stock_room/exit')}}" method="POST" id="search" class="form-inline">
                    @csrf
                    <div class="form-floating">
                        <input type="text" class="form-control" name="imei" placeholder="Enter IMEI" value="@isset($_GET['imei']){{$_GET['imei']}}@endisset" id="imeiInput" onload="this.focus()" autofocus>
                        <label for="">IMEI</label>
                    </div>
                        <button class="btn btn-primary pd-x-20" type="submit">Exit</button>
                    {{-- @if (isset($stock))
                        &nbsp;&nbsp;&nbsp;&nbsp;Current Variation:&nbsp;&nbsp;&nbsp;&nbsp;<h5 class="mb-0">{{ $stock->variation->product->model ?? "Variation Issue"}}{{" - " . (isset($stock->variation->storage_id)?$stock->variation->storage_id->name . " - " : null) . (isset($stock->variation->color_id)?$stock->variation->color_id->name. " - ":null)}} <strong><u>{{ $stock->variation->grade_id->name ?? null }}</u></strong></h5>
                    @endif --}}
                </form>
                @endif
            </div>
            <h6>
                @if (session('user')->hasPermission('view_all_stock_movements'))
                    @foreach ($stock_count as $count)
                        <a href="{{url('stock_room')}}?show=1&admin_id={{$count->admin_id}}"> {{$count->admin->first_name}}: {{ $count->count }} </a> &nbsp;&nbsp;&nbsp;&nbsp;

                    @endforeach
                @else
                <a href="{{url('stock_room')}}?show=1"> Count: {{ $stock_count }} </a>
                @endif
            </h6>
            <script>

                window.onload = function() {
                    document.getElementById('imeiInput').focus();
                };
                document.addEventListener('DOMContentLoaded', function() {
                    var input = document.getElementById('imeiInput');
                    input.focus();
                    input.select();
                });
            </script>
        </div>
        {{-- <br>
        <div class="row">
            <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>Orders History</h4></center>
            </div>
        </div> --}}
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

        @if (isset($stocks))
        <div>
            <div class="card">
                <div class="card-header pb-0">
                    <div class="d-flex justify-content-between">
                        <h5 class="card-title mg-b-0">{{ __('locale.From') }} {{$stocks->firstItem()}} {{ __('locale.To') }} {{$stocks->lastItem()}} {{ __('locale.Out Of') }} {{$stocks->total()}} </h5>

                        <div class=" mg-b-0">
                            <form method="get" action="" class="row form-inline">
                                <label for="perPage" class="card-title inline">per page:</label>
                                <select name="per_page" class="form-select form-select-sm" id="perPage" onchange="this.form.submit()">
                                    <option value="10" {{ Request::get('per_page') == 10 ? 'selected' : '' }}>10</option>
                                    <option value="20" {{ Request::get('per_page') == 20 ? 'selected' : '' }}>20</option>
                                    <option value="50" {{ Request::get('per_page') == 50 ? 'selected' : '' }}>50</option>
                                    <option value="100" {{ Request::get('per_page') == 100 ? 'selected' : '' }}>100</option>
                                </select>
                                <input type="hidden" name="show" value="1">
                            </form>
                        </div>

                    </div>
                </div>
                <div class="card-body"><div class="table-responsive">



                        <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                            <thead>
                                <tr>
                                    <th><small><b>No</b></small></th>
                                    <th><small><b>Product</b></small></th>
                                    <th><small><b>IMEI / Serial Number</b></small></th>
                                    <th><small><b>Vendor</b></small></th>
                                    <th><small><b>Reference</b></small></th>
                                    @if (session('user')->hasPermission('view_cost'))
                                    <th><small><b>Cost</b></small></th>
                                    @endif
                                    <th><small><b>Datetime</b></small></th>
                                    <th><small><b>Added By</b></small></th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $i = $stocks->firstItem() - 1;
                                @endphp
                                @foreach ($stocks as $stock_r)
                                    @php
                                        $stock = $stock_r->stock;
                                    @endphp
                                    <tr>
                                        <td title="{{ $stock->id }}">{{ $i + 1 }}</td>
                                        <td><a title="Filter this variation" href="{{url('inventory').'?product='.$stock->variation->product_id.'&storage='.$stock->variation->storage.'&grade[]='.$stock->variation->grade}}">{{ $stock->variation->product->model . " " . (isset($stock->variation->storage) ? $storages[$stock->variation->storage] . " " : null) . " " .
                                        (isset($stock->variation->color) ? $colors[$stock->variation->color] . " " : null) . $grades[$stock->variation->grade] }} </a></td>
                                        <td><a title="{{$stock->id}} | Search Serial" href="{{url('imei')."?imei=".$stock->imei.$stock->serial_number}}" target="_blank"> {{$stock->imei.$stock->serial_number }} </a></td>
                                        <td><a title="Vendor Profile" href="{{url('edit-customer').'/'.$stock->order->customer_id}}" target="_blank"> {{ $stock->order->customer->first_name ?? null}} </a></td>
                                        <td>
                                            <a title="Purchase Order Details" href="{{url('purchase/detail').'/'.$stock->order_id}}?status=1" target="_blank"> {{ $stock->order->reference_id }} </a>
                                            @if ($stock->latest_return)
                                                &nbsp;<a title="Sales Return Details" href="{{url('return/detail').'/'.$stock->latest_return->order->id}}" target="_blank"> {{ $stock->latest_return->order->reference_id }} </a>
                                            @endif
                                            @if ($stock->latest_verification)
                                                &nbsp; {{ $stock->latest_verification->process->reference_id }}
                                            @endif
                                        </td>
                                        @if (session('user')->hasPermission('view_cost'))
                                        <td>{{ $stock->order->currency_id->sign ?? null }}{{$stock->purchase_item->price ?? null }}</td>
                                        @endif
                                        <td>{{ $stock_r->exit_at }}</td>
                                        @if ($stock->latest_operation)
                                        <td>{{ $stock->latest_operation->admin->first_name ?? null }}</td>
                                        <td>
                                            {{ $stock_r->description }}
                                        </td>
                                        @else
                                        <td>{{ $stock_r->admin->first_name ?? null }}</td>

                                        @endif
                                    </tr>

                                    @php
                                        $i ++;
                                    @endphp
                                @endforeach
                            </tbody>
                        </table>
                    <br>
                    {{ $stocks->onEachSide(1)->links() }} {{ __('locale.From') }} {{$stocks->firstItem()}} {{ __('locale.To') }} {{$stocks->lastItem()}} {{ __('locale.Out Of') }} {{$stocks->total()}}
                </div>

                </div>
            </div>
        </div>
        @endif
        {{-- @if (isset($stock))

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between">
                            <h4 class="card-title mg-b-0">
                                External Movement
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
                                        <th width="100px"><small><b>Order ID</b></small></th>
                                        <th><small><b>Type</b></small></th>
                                        <th><small><b>Customer / Vendor</b></small></th>
                                        <th><small><b>Product</b></small></th>
                                        <th><small><b>Qty</b></small></th>
                                        <th><small><b>Price</b></small></th>
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
                                                    <td><a href="{{url('purchase/detail/'.$order->id)}}?status=1">{{ $order->reference_id."\n\r".$item->reference_id }}</a></td>
                                                @elseif ($order->order_type_id == 2)
                                                    <td><a href="{{url('rma/detail/'.$order->id)}}">{{ $order->reference_id."\n\r".$item->reference_id }}</a></td>
                                                @elseif ($order->order_type_id == 5 && $order->reference_id != 999)
                                                    <td><a href="{{url('wholesale/detail/'.$order->id)}}">{{ $order->reference_id."\n\r".$item->reference_id }}</a></td>
                                                @elseif ($order->order_type_id == 5 && $order->reference_id == 999)
                                                    <td><a href="https://www.backmarket.fr/bo_merchant/orders/all?orderId={{ $item->reference_id }}" target="_blank">Replacement <br> {{ $item->reference_id }}</a></td>
                                                @elseif ($order->order_type_id == 4)
                                                    <td><a href="{{url('return/detail/'.$order->id)}}">{{ $order->reference_id."\n\r".$item->reference_id }}</a></td>
                                                @elseif ($order->order_type_id == 3)
                                                    <td><a href="https://www.backmarket.fr/bo_merchant/orders/all?orderId={{ $order->reference_id }}" target="_blank">{{ $order->reference_id."\n\r".$item->reference_id }}</a></td>
                                                @endif
                                                <td>{{ $order->order_type->name }}</td>
                                                <td>@if ($order->customer)
                                                    {{ $order->customer->first_name." ".$order->customer->last_name }}
                                                @endif</td>
                                                <td>
                                                    @if ($item->variation ?? false)
                                                        <strong>{{ $item->variation->sku }}</strong>{{ " - " . $item->variation->product->model . " - " . (isset($item->variation->storage_id)?$item->variation->storage_id->name . " - " : null) . (isset($item->variation->color_id)?$item->variation->color_id->name. " - ":null)}} <strong><u>{{ $item->variation->grade_id->name ?? "Missing Grade" }}</u></strong>
                                                    @endif
                                                    @if ($item->care_id != null && $order->order_type_id == 3)
                                                        <a class="" href="https://backmarket.fr/bo_merchant/customer-request/{{ $item->care_id }}" target="_blank"><strong class="text-danger">Conversation</strong></a>
                                                    @endif
                                                </td>
                                                <td>{{ $item->quantity }}</td>
                                                <td>
                                                @if ($order->order_type_id == 1 && session('user')->hasPermission('view_cost'))
                                                    {{ $order->currency_id->sign.number_format($item->price,2) }}
                                                @elseif (session('user')->hasPermission('view_price'))
                                                    {{ $order->currency_id->sign.number_format($item->price,2) }}
                                                @endif
                                                </td>
                                                @if ($order->status == 3)
                                                <td style="width:240px" class="text-success text-uppercase" title="{{ $item->stock_id }}" id="copy_imei_{{ $order->id }}">
                                                    @isset($item->stock->imei) {{ $item->stock->imei }}&nbsp; @endisset
                                                    @isset($item->stock->serial_number) {{ $item->stock->serial_number }}&nbsp; @endisset
                                                    @isset($item->admin_id) | {{ $item->admin->first_name }} |
                                                    @else
                                                    @isset($order->processed_by) | {{ $order->admin->first_name }} | @endisset
                                                    @endisset
                                                    @isset($item->stock->tester) ({{ $item->stock->tester }}) @endisset
                                                </td>

                                                @endif
                                                @if ($order->status != 3)
                                                <td style="width:240px" title="{{ $item->stock_id }}">
                                                        <strong class="text-danger">{{ $order->order_status->name }}</strong>
                                                    @isset($item->stock->imei) {{ $item->stock->imei }}&nbsp; @endisset
                                                    @isset($item->stock->serial_number) {{ $item->stock->serial_number }}&nbsp; @endisset
                                                    @isset($item->admin_id) | {{ $item->admin->first_name }} |
                                                    @else
                                                    @isset($order->processed_by) | {{ $order->admin->first_name }} | @endisset
                                                    @endisset
                                                    @isset($item->stock->tester) ({{ $item->stock->tester }}) @endisset
                                                </td>
                                                @endif
                                                <td style="width:220px">{{ $item->created_at}} <br> {{ $order->processed_at." ".$order->tracking_number }}</td>
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

        @if (isset($process_stocks) && $process_stocks->count() > 0)

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between">
                            <h4 class="card-title mg-b-0">
                                Repair History
                            </h4>

                            <div class=" mg-b-0">
                            </div>

                        </div>
                    </div>
                    <div class="card-body"><div class="table-responsive">

                        <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                            <thead>
                                <tr>
                                    <th><small><b>No</b></small></th>
                                    <th><small><b>Reference ID</b></small></th>
                                    <th><small><b>Repairer</b></small></th>
                                    <th><small><b>Price</b></small></th>
                                    <th><small><b>IMEI</b></small></th>
                                    <th><small><b>Status</b></small></th>
                                    <th><small><b>Creation Date | TN</b></small></th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $i = 0;
                                    $id = [];
                                @endphp
                                @foreach ($process_stocks as $index => $p_stock)
                                    @php
                                        $process = $p_stock->process;
                                        $j = 0;
                                    @endphp

                                        <tr>
                                            <td title="{{ $p_stock->id }}">{{ $i + 1 }}</td>
                                            <td><a href="{{url('repair/detail/'.$process->id)}}?status=1">{{ $process->reference_id }}</a></td>
                                            <td>@if ($process->customer)
                                                {{ $process->customer->first_name." ".$process->customer->last_name }}
                                            @endif</td>
                                            <td>
                                                {{ $process->currency_id->sign.number_format($p_stock->price,2) }}
                                            </td>
                                            <td style="width:240px" class="text-success text-uppercase" title="{{ $p_stock->stock_id }}" id="copy_imei_{{ $process->id }}">
                                                @isset($p_stock->stock->imei) {{ $p_stock->stock->imei }}&nbsp; @endisset
                                                @isset($p_stock->stock->serial_number) {{ $p_stock->stock->serial_number }}&nbsp; @endisset
                                                @isset($p_stock->admin_id) | {{ $p_stock->admin->first_name }} |
                                                @else
                                                @isset($process->processed_by) | {{ $process->admin->first_name }} | @endisset
                                                @endisset
                                            </td>
                                            <td>@if ($p_stock->status == 1)
                                                Sent
                                                @else
                                                Received
                                            @endif</td>
                                            <td style="width:220px">{{ $p_stock->created_at}} <br> {{ $process->tracking_number }}</td>
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
                                        <th><small><b>Added By</b></small></th>
                                        <th><small><b>DateTime</b></small></th>
                                        @if (session('user')->hasPermission('delete_move'))
                                        <th><small><b>Delete</b></small></th>
                                        @endif
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
                                                        <strong>{{ $operation->new_variation->sku }}</strong>{{ " - " . $operation->new_variation->product->model . " - " . (isset($operation->new_variation->storage_id)?$operation->new_variation->storage_id->name . " - " : null) . (isset($operation->new_variation->color_id)?$operation->new_variation->color_id->name. " - ":null)}} <strong><u>{{ $operation->new_variation->grade_id->name ?? "Missing Grade" }}</u></strong>
                                                    @endif
                                                </td>
                                                <td>{{ $operation->stock->imei.$operation->stock->serial_number }}</td>
                                                <td>{{ $operation->stock->order->customer->first_name." | ".$operation->stock->order->reference_id }}</td>
                                                <td>{{ $operation->description }}</td>
                                                <td>{{ $operation->admin->first_name ?? null }}</td>
                                                <td>{{ $operation->created_at }}</td>
                                                @if (session('user')->hasPermission('delete_move') && $i == 0)

                                                <td>
                                                    <form method="POST" action="{{url('move_inventory/delete_move')}}">
                                                        @csrf
                                                        <input type="hidden" name="id" value="{{ $operation->id }}">
                                                        <button type="submit" class="btn btn-link"><i class="fa fa-trash"></i></button>
                                                    </form>

                                                </td>
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

        @endif
        @if (isset($test_results) && $test_results->count() > 0)
        <br>

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between">
                            <h4 class="card-title mg-b-0">
                                Testing Report
                            </h4>


                        </div>
                    </div>
                    <div class="card-body"><div class="table-responsive">
                        <pre>
                        @foreach ($test_results as $result)
                            @php

                                $data = $result->request;
                                $datas = json_decode(json_decode(preg_split('/(?<=\}),(?=\{)/', $data)[0]));
                                echo "Test DateTime: ".$result->created_at."<br>";
                                print_r($datas);
                            @endphp
                            @php
                                $i ++;
                            @endphp
                        @endforeach
                        </pre>
                    </div>

                    </div>
                </div>
            </div>
        </div>

        @endif --}}

    @endsection

    @section('scripts')

		<!--Internal Sparkline js -->
		<script src="{{asset('assets/plugins/jquery-sparkline/jquery.sparkline.min.js')}}"></script>

		<!-- Internal Piety js -->
		<script src="{{asset('assets/plugins/peity/jquery.peity.min.js')}}"></script>

		<!-- Internal Chart js -->
		<script src="{{asset('assets/plugins/chartjs/Chart.bundle.min.js')}}"></script>

    @endsection
