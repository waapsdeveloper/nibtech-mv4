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
        <div class="breadcrumb-header justify-content-between mb-0">
            <div class="left-content">
                <span class="main-content-title mg-b-0 mg-b-lg-1">Customer Profile</span>
                <h5>{{ $customer->company }}</h5>
                <h6>{{ $customer->first_name." ".$customer->last_name }}</h6>
                <h6>{{ $customer->email }} {{ $customer->phone }}</h6>
                <h6>{{ $customer->street }} {{ $customer->street2 }} {{ $customer->city }}</h6>
                <h6>{{ $customer->postal_code }} {{ $customer->country_id->title ?? null }}</h6>
                <h6>{{ $customer->vat }}</h6>
            </div>
            <div>
                @foreach($totals as $total)
                    <div>{{ $total['type'] }}:
                        Price: {{ $total['total_price'] }},
                        Items: {{ $total['total_items'] }},
                        Orders: {{ $total['total_orders'] }}
                    </div>
                @endforeach
            </div>
            <div class="justify-content-center mt-2 position-relative">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                    <li class="breadcrumb-item tx-15"><a href="{{ session('previous')}}">Customers</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $customer->company }}</li>
                </ol>

                <div class="btn-group position-absolute bottom-0 end-0" role="group" aria-label="Basic example">
                    <a href="{{url('customer/profile').'/'.$customer->id}}?page=orders" class="btn btn-link @if (request('page') == 'orders') bg-white @endif ">All&nbsp;Orders</a>
                    @if (session('user')->hasPermission('view_customer_repairs') && $repairs->count() > 0)

                        <a href="{{url('customer/profile').'/'.$customer->id}}?page=sent_repair_summery" class="btn btn-link @if (request('page') == 'sent_repair_summery') bg-white @endif ">Sent&nbsp;Repair&nbsp;Summery</a>
                    @endif
                </div>
            </div>
        </div>
        <!-- /breadcrumb -->
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
            <script>
                alert("{{session('error')}}");
            </script>
        <br>
        @php
        session()->forget('error');
        @endphp
        @endif

        @if (session('user')->hasPermission('view_customer_repairs') && request('page') == 'sent_repair_summery')
            <div class="card" id="print_inv">
                <div class="card-header pb-0 d-flex justify-content-between">
                    <h4 class="card-title">Repair Sent Stock Summery</h4>
                </div>
                <div class="card-body"><div class="table-responsive">
                    <form method="GET" action="" target="_blank" id="search_summery">
                        <input type="hidden" name="category" value="{{ Request::get('category') }}">
                        <input type="hidden" name="brand" value="{{ Request::get('brand') }}">
                        <input type="hidden" name="color" value="{{ Request::get('color') }}">
                        @if (Request::get('grade'))

                        @foreach (Request::get('grade') as $grd)

                            <input type="hidden" name="grade[]" value="{{ $grd }}">
                        @endforeach
                        @endif
                        <input type="hidden" name="replacement" value="{{ Request::get('replacement') }}">
                        <input type="hidden" name="per_page" value="{{ Request::get('per_page') }}">
                        <input type="hidden" name="status" value="{{ Request::get('status') }}">
                        <input type="hidden" name="vendor" value="{{ Request::get('vendor') }}">
                    </form>
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
                                $total_quantity = 0;
                                $total_cost = 0;
                            @endphp
                            @foreach ($sent_stock_summery as $summery)

                            @php
                                // print_r($summery);
                                // continue;
                                // if($summery['storage'] > 0){
                                //     $storage = $storages[$summery['storage']];
                                // }else{
                                //     $storage = null;
                                // }
                                $total_quantity += $summery['quantity'];
                                $total_cost += $summery['total_cost'];
                                $stock_imeis = $summery['stock_imeis'];
                                $temp_array = array_unique($stock_imeis);
                                $duplicates = sizeof($temp_array) != sizeof($stock_imeis);
                                $duplicate_count = sizeof($stock_imeis) - sizeof($temp_array);

                            @endphp
                                <tr>
                                    <td>{{ ++$i }}</td>
                                    {{-- <td>{{ $products[$summery['product_id']]." ".$storage }}</td> --}}
                                    {{-- <td><button class="btn py-0 btn-link" type="submit" form="search_summery" name="pss" value="{{$summery['pss_id']}}">{{ $summery['model'] }}</button></td> --}}
                                    <td>{{ $summery['model'] }}</td>
                                    <td title="{{json_encode($summery['stock_ids'])}}"><a id="test{{$i}}" href="javascript:void(0)">{{ $summery['quantity'] }}</a>
                                    @if ($duplicates)
                                        <span class="badge badge-danger">{{ $duplicate_count }} Duplicate</span>
                                    @endif
                                    <td
                                    title="{{ amount_formatter($summery['total_cost']/$summery['quantity']) }}"
                                    >{{ amount_formatter($summery['total_cost'],2) }}</td>
                                </tr>

                                <script type="text/javascript">


                                    document.getElementById("test{{$i}}").onclick = function(){
                                        @php
                                            foreach ($stock_imeis as $val) {

                                                echo "window.open('".url("imei")."?imei=".$val."','_blank');
                                                ";
                                            }

                                        @endphp
                                    }
                                </script>
                                {{-- @endif --}}
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2"><b>Total</b></td>
                                <td><b>{{ $total_quantity }}</b></td>
                                <td title="{{ amount_formatter($total_cost/$total_quantity,2) }}"><b>{{ amount_formatter($total_cost,2) }}</b></td>
                            </tr>
                        </tfoot>

                    </table>
                </div>
            </div>
        {{-- @elseif (session('user')->hasPermission('view_customer_orders') && request('page') == 'orders') --}}
        @else
            <div class="row">
                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-header pb-0">
                            <h5 class="card-title mg-b-0"> Customer Orders </h5>
                        </div>
                        <div class="card-body"><div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                    <thead>
                                        <tr>
                                            <th><small><b>No</b></small></th>
                                            <th><small><b>Batch ID</b></small></th>
                                            <th><small><b>Type</b></small></th>
                                            <th><small><b>Reference</b></small></th>
                                            <th><small><b>Qty</b></small></th>
                                            <th><small><b>Value</b></small></th>
                                            <th><small><b>Creation Date</b></small></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $i = 0;
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
                                                $price = $order->order_items_sum_price;
                                            @endphp

                                                <tr>
                                                    <td>{{ $i += 1 }}</td>
                                                    <td>
                                                        @if ($order->order_type_id == 1)
                                                            <a href="{{url('purchase/detail/'.$order->id)}}?status=1">{{ $order->reference_id }}</a>
                                                        @elseif ($order->order_type_id == 2)
                                                            <a href="{{url('rma/detail/'.$order->id)}}">{{ $order->reference_id }}</a>
                                                        @elseif ($order->order_type_id == 4)
                                                            <a href="{{url('return/detail/'.$order->id)}}">{{ $order->reference_id }}</a>
                                                        @elseif ($order->order_type_id == 5)
                                                            <a href="{{url('wholesale/detail/'.$order->id)}}">{{ $order->reference_id }}</a>
                                                        @elseif ($order->order_type_id == 6)
                                                            <a href="{{url('wholesale_return/detail/'.$order->id)}}">{{ $order->reference_id }}</a>
                                                        @endif
                                                        @if ($order->status == 2)
                                                            <span class="badge badge-warning">Pending</span>

                                                        @endif
                                                    </td>
                                                    <td>{{ $order->order_type->name }}</td>
                                                    <td title="{{ $order->reference }}" class="wd-250">{{ Str::limit($order->reference, 30) }}</td>
                                                    <td>{{ $order->order_items_count }}</td>
                                                    @if (session('user')->hasPermission('view_price'))
                                                    <td>€{{ amount_formatter($price,2) }}</td>
                                                    @endif
                                                    <td style="width:220px">{{ $order->created_at }}</td>
                                                </tr>

                                        @endforeach
                                    </tbody>
                                </table>

                        </div>

                        </div>
                    </div>
                </div>
            </div>

            @if ($repairs->count() > 0)

            <div class="row">
                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-header pb-0">
                            <h5 class="card-title mg-b-0"> Customer Repairs </h5>
                        </div>
                        <div class="card-body"><div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                    <thead>
                                        <tr>
                                            <th><small><b>No</b></small></th>
                                            <th><small><b>Batch ID</b></small></th>
                                            @if (session('user')->hasPermission('view_cost'))
                                            <th><small><b>Cost</b></small></th>
                                            @endif
                                            <th><small><b>Remaining Qty</b></small></th>
                                            <th><small><b>Creation Date</b></small></th>
                                            <th><small><b>Last Update</b></small></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $i = 0;
                                            $id = [];
                                        @endphp
                                        @foreach ($repairs as $index => $order)
                                            @php
                                                if(in_array($order->id,$id)){
                                                    continue;
                                                }else {
                                                    $id[] = $order->id;
                                                }
                                                $items = $order->process_stocks;
                                                $j = 0;
                                                // print_r($order);
                                            @endphp

                                            {{-- @foreach ($items as $itemIndex => $item) --}}
                                                <tr>
                                                    <td>{{ $i + 1 }}</td>
                                                    <td><a href="{{url('repair/detail/'.$order->id)}}">{{ $order->reference_id }}</a>
                                                        @if ($order->status == 1)
                                                            <span class="badge badge-warning">Not Sent</span>
                                                        @endif
                                                    </td>
                                                    @if ((!request('status') || request('status') == 3) && session('user')->hasPermission('view_cost'))
                                                    <td>Є{{ amount_formatter($order->process_stocks->sum('price'),2) }}</td>
                                                    @endif
                                                    <td>{{ $items->where('status',1)->count()."/".$items->count() }}@if ($order->status == 2)
                                                        (Pending)
                                                    @endif</td>
                                                    <td >{{ $order->created_at }}</td>
                                                    <td >{{ $order->updated_at }}</td>
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

                        </div>

                        </div>
                    </div>
                </div>
            </div>

            @endif
        @endif
    @endsection

    @section('scripts')

        <script>

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
