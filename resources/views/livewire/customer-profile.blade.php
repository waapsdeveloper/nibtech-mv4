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
                <span class="main-content-title mg-b-0 mg-b-lg-1">Customer Profile</span>
                <h5>{{ $customer->company }}</h5>
                <h5>{{ $customer->first_name." ".$customer->last_name }}</h5>
                <h5>{{ $customer->email }}</h5>
                <h5>{{ $customer->phone }}</h5>
                <h5>{{ $customer->street }} {{ $customer->street2 }}, {{ $customer->city }}</h5>
                <h5>{{ $customer->postal_code }} {{ $customer->country_id->title ?? null }}</h5>
                <h5>{{ $customer->vat }}</h5>
            </div>
            <div class="justify-content-center mt-2">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                    <li class="breadcrumb-item tx-15"><a href="{{ session('previous')}}">Customers</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $customer->company }}</li>
                </ol>
            </div>
        </div>
        <!-- /breadcrumb -->
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
            <script>
                alert("{{session('error')}}");
            </script>
        <br>
        @php
        session()->forget('error');
        @endphp
        @endif

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

                                                @if ($order->order_type_id == 1)
                                                    <td><a href="{{url('purchase/detail/'.$order->id)}}?status=1">{{ $order->reference_id }}</a></td>
                                                @elseif ($order->order_type_id == 2)
                                                    <td><a href="{{url('rma/detail/'.$order->id)}}">{{ $order->reference_id }}</a></td>
                                                @elseif ($order->order_type_id == 4)
                                                    <td><a href="{{url('return/detail/'.$order->id)}}">{{ $order->reference_id }}</a></td>
                                                @elseif ($order->order_type_id == 5)
                                                    <td><a href="{{url('wholesale/detail/'.$order->id)}}">{{ $order->reference_id }}</a></td>
                                                @elseif ($order->order_type_id == 6)
                                                    <td><a href="{{url('wholesale_return/detail/'.$order->id)}}">{{ $order->reference_id }}</a></td>
                                                @endif
                                                <td>{{ $order->order_type->name }}</td>
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
                                                <td><a href="{{url('repair/detail/'.$order->id)}}">{{ $order->reference_id }}</a></td>
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
