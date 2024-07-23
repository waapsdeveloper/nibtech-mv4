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

<div class="toast-container position-fixed top-0 end-0 p-5" style="z-index: 1000;">
    @if (session('error'))
            <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header text-danger">
                    <strong class="me-auto">Error</strong>
                    <button type="button" class="btn" data-bs-dismiss="toast" aria-label="Close">x</button>
                </div>
                <div class="toast-body">{{ session('error') }}</div>
            </div>
        @php
        session()->forget('error');
        @endphp
    @endif

    @if (session('success'))
            <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header text-success bg-light">
                    <strong class="me-auto">Success</strong>
                    <button type="button" class="btn" data-bs-dismiss="toast" aria-label="Close">x</button>
                </div>
                <div class="toast-body">{{ session('success') }}</div>
            </div>
        @php
        session()->forget('success');
        @endphp
    @endif

</div>


        <!-- breadcrumb -->
            <div class="breadcrumb-header justify-content-between">
                <div class="left-content">
                {{-- <span class="main-content-title mg-b-0 mg-b-lg-1">Issues</span> --}}
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Issues</li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <div class="row">
            <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>Issues</h4></center>
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
                            </div>

                        </div>
                    </div>
                    <div class="card-body"><div class="table-responsive">

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
                                    @foreach ($items as $item)
                                    @php
                                        $order = $item->order;
                                        $j = 0;
                                    @endphp

                                        <tr>
                                            <td title="{{ $item->id }}">{{ $i + 1 }}</td>
                                            @if ($order->order_type_id == 1)

                                                <td><a href="{{url('purchase/detail/'.$order->id)}}">{{ $order->reference_id }}</a></td>
                                            @elseif ($order->order_type_id == 2)
                                                <td><a href="{{url('rma/detail/'.$order->id)}}">{{ $order->reference_id }}</a></td>
                                            @elseif ($order->order_type_id == 5)
                                                <td><a href="{{url('wholesale/detail/'.$order->id)}}">{{ $order->reference_id }}</a></td>
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
                                            $item = $item->linked;
                                            $order = $item->order;
                                            $j = 0;
                                        @endphp

                                            <tr>
                                                <td title="{{ $item->id }}">{{ $i + 1 }}</td>
                                                @if ($order->order_type_id == 1)

                                                    <td><a href="{{url('purchase/detail/'.$order->id)}}">{{ $order->reference_id }}</a></td>
                                                @elseif ($order->order_type_id == 2)
                                                    <td><a href="{{url('rma/detail/'.$order->id)}}">{{ $order->reference_id }}</a></td>
                                                @elseif ($order->order_type_id == 5)
                                                    <td><a href="{{url('wholesale/detail/'.$order->id)}}">{{ $order->reference_id }}</a></td>
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
                                                <td style="width:220px">{{ $item->created_at}} <br> {{ $order->processed_at." ".$order->tracking_number }}</td>
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

    @endsection

    @section('scripts')

		<!--Internal Sparkline js -->
		<script src="{{asset('assets/plugins/jquery-sparkline/jquery.sparkline.min.js')}}"></script>

		<!-- Internal Piety js -->
		<script src="{{asset('assets/plugins/peity/jquery.peity.min.js')}}"></script>

		<!-- Internal Chart js -->
		<script src="{{asset('assets/plugins/chartjs/Chart.bundle.min.js')}}"></script>

    @endsection
