@extends('layouts.app')

    @section('styles')
    <!-- INTERNAL Select2 css -->
    {{-- <link href="{{asset('assets/plugins/select2/css/select2.min.css')}}" rel="stylesheet" /> --}}
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
                {{-- <span class="main-content-title mg-b-0 mg-b-lg-1">Repair</span> --}}
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Internal Repair</li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
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
            @if (session('user')->hasPermission('internal_repair') && isset($stock) && $stock->variation->grade == 8)
                <div class="p-2">
                    <form action="{{ url('add_internal_repair_item')}}" method="POST" class="form-inline">
                        @csrf
                        <select name="repair[grade]" class="form-control form-select">
                            <option value="">Move to</option>
                            @foreach ($grades as $grade)
                                @if($grade->id > 7)
                                <option value="{{ $grade->id }}">{{ $grade->name }}</option>
                                @endif
                            @endforeach
                        </select>

                        <div class="form-floating">
                            <input type="text" class="form-control pd-x-20" name="repair[description]" placeholder="Reason" style="width: 270px;">
                            {{-- <input type="text" class="form-control" name="repair[imei]" placeholder="Enter IMEI" value="@isset($_GET['imei']){{$_GET['imei']}}@endisset"> --}}
                            <label for="">Reason</label>
                        </div>
                        <input type="hidden" name="repair[stock_id]" value="{{$stock_id}}">
                        <button class="btn btn-secondary pd-x-20" type="submit">Move</button>
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
                                        <th><small><b>Order ID</b></small></th>
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
                                                    <td><a href="{{url(session('url').'order/detail/'.$order->id)}}">{{ $order->reference_id }}</a></td>
                                                @elseif ($order->order_type_id == 5)
                                                    <td><a href="{{url(session('url').'wholesale/detail/'.$order->id)}}">{{ $order->reference_id }}</a></td>
                                                @elseif ($order->order_type_id == 3)
                                                    <td>{{ $order->reference_id }}</td>
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
                                                <td style="width:240px" class="text-success text-uppercase" title="{{ $item->stock_id }}">
                                                    @isset($item->stock->imei) {{ $item->stock->imei }}&nbsp; @endisset
                                                    @isset($item->stock->serial_number) {{ $item->stock->serial_number }}&nbsp; @endisset
                                                    @isset($item->order->processed_by) | {{ $item->order->admin->first_name[0] }} | @endisset
                                                    @isset($item->stock->tester) ({{ $item->stock->tester }}) @endisset
                                                </td>

                                                @endif
                                                <td style="width:220px">{{ $item->order->created_at}} <br> {{ $item->order->processed_at." ".$item->order->tracking_number }}</td>
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

        <div class="row">
            <div class="col-xl-12">

            <div class="card">
                <div class="card-header pb-0">
                    <div class="d-flex justify-content-between">
                        <h4 class="card-title mg-b-0">Repaired</h4>
                        <div class=" mg-b-0">
                            Count: {{ count($repaired_stocks)}}
                        </div>

                    </div>
                </div>
                <div class="card-body"><div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                            <thead>
                                <tr>
                                    <th><small><b>No</b></small></th>
                                    <th><small><b>Variation</b></small></th>
                                    <th><small><b>IMEI</b></small></th>
                                    <th><small><b>Reason</b></small></th>
                                    <th><small><b>Creation Date</b></small></th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $i = 0;
                                    $id = [];
                                @endphp
                                @foreach ($repaired_stocks as $r_stock)
                                    @php
                                        $stock = $r_stock->stock;
                                    @endphp
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>
                                                @if ($stock->variation ?? false)
                                                    <strong>{{ $stock->variation->sku }}</strong>{{ " - " . $stock->variation->product->model . " - " . (isset($stock->variation->storage_id)?$stock->variation->storage_id->name . " - " : null) . (isset($stock->variation->color_id)?$stock->variation->color_id->name. " - ":null)}} <strong><u>{{ $stock->variation->grade_id->name }}</u></strong>
                                                @endif
                                            </td>
                                            <td><a href="{{url('repair/internal').'?imei='.$stock->imei.$stock->serial_number}}">{{$stock->imei.$stock->serial_number }}</a></td>
                                            <td>{{$r_stock->description ?? null }}</td>
                                            <td style="width:180px">{{ $r_stock->created_at."  ".$r_stock->updated_at }}</td>
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

        <br>

        <div class="d-flex justify-content-between">
            <div>
                <a href="{{url(session('url').'repair/internal')}}?stock_status=1" class="btn btn-link @if (request('stock_status') == 1) bg-white @endif ">Inventory</a>
                <a href="{{url(session('url').'repair/internal')}}?stock_status=2" class="btn btn-link @if (request('stock_status') == 2) bg-white @endif ">AfterSale</a>
                <a href="{{url(session('url').'repair/internal')}}" class="btn btn-link @if (!request('stock_status')) bg-white @endif ">All</a>
            </div>
            <div class="">
            </div>
        </div>
        <div class="row">
            <div class="col-xl-12">

            <div class="card">
                <div class="card-header pb-0">
                    <div class="d-flex justify-content-between">
                        <h4 class="card-title mg-b-0">Awaiting Repair</h4>
                        <div class=" mg-b-0">
                            Count: {{ count($repair_stocks)}}
                        </div>

                    </div>
                </div>
                <div class="card-body"><div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                            <thead>
                                <tr>
                                    <th><small><b>No</b></small></th>
                                    <th><small><b>Variation</b></small></th>
                                    <th><small><b>IMEI</b></small></th>
                                    <th><small><b>Vendor</b></small></th>
                                    <th><small><b>Reason</b></small></th>
                                    <th><small><b>Creation Date</b></small></th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $i = 0;
                                    $id = [];
                                @endphp
                                @foreach ($repair_stocks as $stock)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>
                                                @if ($stock->variation ?? false)
                                                    <strong>{{ $stock->variation->sku }}</strong>{{ " - " . $stock->variation->product->model . " - " . (isset($stock->variation->storage_id)?$stock->variation->storage_id->name . " - " : null) . (isset($stock->variation->color_id)?$stock->variation->color_id->name. " - ":null)}} <strong><u>{{ $stock->variation->grade_id->name }}</u></strong>
                                                @endif
                                            </td>
                                            <td><a href="{{url('repair/internal').'?imei='.$stock->imei.$stock->serial_number}}">{{$stock->imei.$stock->serial_number }}</a></td>
                                            <td>
                                                @if ($stock->order_id != null)
                                                    {{$stock->order->customer->first_name." ".$stock->order->reference_id }}
                                                @else
                                                    Item Not Purchased Yet
                                                @endif
                                            </td>
                                            <td>{{$stock->latest_operation->description }}</td>
                                            <td style="width:180px">{{ $stock->latest_operation->created_at."  ".$stock->latest_operation->updated_at }}</td>
                                        </tr>
                                        {{-- @php
                                            $j++;
                                        @endphp
                                    @endforeach --}}
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
		{{-- <script src="{{asset('assets/plugins/select2/js/select2.full.min.js')}}"></script>
		<script src="{{asset('assets/js/select2.js')}}"></script> --}}
    @endsection
