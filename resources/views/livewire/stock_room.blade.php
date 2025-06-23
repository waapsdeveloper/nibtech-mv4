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
                    <form method="get" action="" class="row form-inline">

                        <div class="form-floating">
                            <input class="form-control" id="start_date_input" name="start_date" id="datetimepicker" type="date" value="@isset($_GET['start_date']){{$_GET['start_date']}}@endisset" oninput="this.form.submit()">
                            <label for="start_date_input">{{ __('locale.Start Date') }}</label>
                        </div>
                        <div class="form-floating">
                            <input class="form-control" id="end_date_input" name="end_date" id="datetimepicker" type="date" value="@isset($_GET['end_date']){{$_GET['end_date']}}@endisset" oninput="this.form.submit()">
                            <label for="end_date_input">{{ __('locale.End Date') }}</label>
                        </div>
                        <input type="hidden" name="show" value="{{request('show')}}">
                        <input type="hidden" name="per_page" value="{{request('per_page')}}">
                    </form>
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

            <div class="">
                @if (session('user')->hasPermission('receive_stock'))

                <form action="{{ url('stock_room/receive')}}" method="POST" id="search" class="form-inline">
                    @csrf

                    <div class="form-floating">
                        <input type="text" class="form-control" name="imei" placeholder="Enter IMEI" value="@isset($_GET['imei']){{$_GET['imei']}}@endisset" id="imeiInput" onload="this.focus()" autofocus required>
                        <label for="">IMEI</label>
                    </div>&nbsp;&nbsp;&nbsp;&nbsp;
                    <div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="radio" id="com" name="description" value="Change of mind" required @if (session('description') == "Change of mind") {{'checked'}} @endif>
                            <label class="form-check-label" for="com">Change of mind</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="radio" id="replacement" name="description" value="Replacement" required @if (session('description') == "Replacement") {{'checked'}} @endif>
                            <label class="form-check-label" for="replacement">Replacement</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="radio" id="receive" name="description" value="Receive" required @if (session('description') == "Receive") {{'checked'}} @endif>
                            <label class="form-check-label" for="receive">Receive</label>
                        </div>
                    </div> &nbsp;&nbsp;&nbsp;&nbsp;
                    <button class="btn btn-secondary pd-x-20" type="submit">Receive</button>

                    {{-- @if (isset($stock))
                        &nbsp;&nbsp;&nbsp;&nbsp;Current Variation:&nbsp;&nbsp;&nbsp;&nbsp;<h5 class="mb-0">{{ $stock->variation->product->model ?? "Variation Issue"}}{{" - " . (isset($stock->variation->storage_id)?$stock->variation->storage_id->name . " - " : null) . (isset($stock->variation->color_id)?$stock->variation->color_id->name. " - ":null)}} <strong><u>{{ $stock->variation->grade_id->name ?? null }}</u></strong></h5>
                    @endif --}}
                </form>
                {{-- @else
                <form action="{{ url('stock_room/exit')}}" method="POST" id="search" class="form-inline">
                    @csrf
                    <div class="form-floating">
                        <input type="text" class="form-control" name="imei" placeholder="Enter IMEI" value="@isset($_GET['imei']){{$_GET['imei']}}@endisset" id="imeiInput" onload="this.focus()" autofocus>
                        <label for="">IMEI</label>
                    </div>
                        <button class="btn btn-primary pd-x-20" type="submit">Exit</button>
                </form> --}}
                @endif
            </div>
            <div class="">
                @if (session('user')->hasPermission('exit_stock'))

                <form class="form-inline" method="GET" target="print_popup" action="{{url('stock_room/exit_scan')}}" onsubmit="window.open('about:blank','print_popup','width=1600,height=800');">
                    @csrf
                    <select id="adm_input" name="admin_id" class="form-control form-select" data-bs-placeholder="Select Processed By">
                        <option value="">Exit To</option>
                        @foreach ($admins as $adm)
                            <option value="{{$adm->id}}" @if(isset($_GET['adm']) && $adm->id == $_GET['adm']) {{'selected'}}@endif>{{$adm->first_name." ".$adm->last_name}}</option>
                        @endforeach
                    </select>

                    <button class="btn btn-primary" type="submit" name="bp" value="1">Exit Stock</button>
                </form>
                @endif
            </div>
            <h6 class=" tx-right">
                @if (session('user')->hasPermission('view_all_stock_movements'))
                    @php
                        $admin = null;
                    @endphp
                    @foreach ($stock_count as $count)
                        @if ($admin != null && $admin != $count->admin->first_name." ".$count->admin->last_name)

                            @php
                                $admin = null;
                            @endphp
                            <br>
                        @endif
                        @if ($admin == null)

                            @php
                                $admin = $count->admin->first_name." ".$count->admin->last_name;
                            @endphp

                            <a href="{{url('stock_room')}}?show=1&admin_id={{$count->admin_id}}&start_date={{$start_date}}&end_date={{$end_date}}">{{ $admin }}</a>
                        @endif
                        &nbsp;&nbsp;&nbsp;&nbsp;
                        <a href="{{url('stock_room')}}?show=1&admin_id={{$count->admin_id}}&description={{$count->description}}&start_date={{$start_date}}&end_date={{$end_date}}">{{$count->description}}: <strong>{{ $count->available_count . '/' . $count->count }}</strong> </a>

                    @endforeach
                @endif
                <a href="{{url('stock_room')}}?show=1"> Pending Count: {{ $pending_count }} </a>
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
        <audio id="my_audio" autoplay>
            <source src="{{asset('assets/audio/beep.mp3')}}" type="audio/mpeg">
          Your browser does not support the audio element.
        </audio>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <span class="alert-inner--icon"><i class="fe fe-thumbs-up"></i></span>
            <span class="alert-inner--text"><strong>{{session('success')}}</strong></span>
            <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
        </div>
        <script>
            // $(document).ready(function() {
            //     $("#my_audio").get(0).play();
            // });
            var audio = new Audio("{{asset('assets/audio/beep.mp3')}}");
            audio.play();

        </script>
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
                                <input type="hidden" name="admin_id" value="{{request('admin_id')}}">
                                <input type="hidden" name="description" value="{{request('description')}}">
                                <input type="hidden" name="start_date" value="{{$start_date}}">
                                <input type="hidden" name="end_date" value="{{$end_date}}">
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
                                    <th><small><b>Purchase Batch</b></small></th>
                                    {{-- @if (session('user')->hasPermission('view_cost'))
                                    <th><small><b>Cost</b></small></th>
                                    @endif --}}
                                    <th><small><b>Exit At</b></small></th>
                                    <th><small><b>Exit By</b></small></th>
                                    <th><small><b>Description</b></small></th>
                                    <th><small><b>Received At</b></small></th>
                                    <th><small><b>Received By</b></small></th>
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
                                        @if ($stock == null)
                                            {{$stock_r->stock_id}}
                                            @continue

                                        @endif
                                        <td title="{{ $stock_r->stock_id }}">{{ $i + 1 }}</td>
                                        <td><a title="Filter this variation" href="{{url('inventory').'?product='.$stock->variation->product_id.'&storage='.$stock->variation->storage.'&grade[]='.$stock->variation->grade}}">{{ $stock->variation->product->model . " " . (isset($stock->variation->storage) ? $storages[$stock->variation->storage] . " " : null) . " " .
                                        (isset($stock->variation->color) ? $colors[$stock->variation->color] . " " : null) . $grades[$stock->variation->grade] }} </a></td>
                                        <td><a title="{{$stock_r->stock_id}} | Search Serial" href="{{url('imei')."?imei=".$stock->imei.$stock->serial_number}}" target="_blank"> {{$stock->imei.$stock->serial_number }} </a></td>
                                        <td>
                                            <a title="Purchase Order Details" href="{{url('purchase/detail').'/'.$stock->order_id}}?status=1" target="_blank"> {{ $stock->order->reference_id }} </a>
                                            @if ($stock->latest_return)
                                                &nbsp;<a title="Sales Return Details" href="{{url('return/detail').'/'.$stock->latest_return->order->id}}" target="_blank"> {{ $stock->latest_return->order->reference_id }} </a>
                                            @endif
                                            @if ($stock->latest_verification)
                                                &nbsp; {{ $stock->latest_verification->process->reference_id }}
                                            @endif
                                        </td>
                                        {{-- @if (session('user')->hasPermission('view_cost'))
                                        <td>{{ $stock->order->currency_id->sign ?? null }}{{$stock->purchase_item->price ?? null }}</td>
                                        @endif --}}
                                        <td>{{ $stock_r->exit_at }}</td>
                                        <td>{{ $stock_r->admin->first_name ?? null }}</td>
                                        <td>
                                            {{ $stock_r->description }}
                                        </td>
                                        <td>{{ $stock_r->received_at }}</td>
                                        <td>{{ $stock_r->receiver->first_name ?? null }}</td>

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
    @endsection

    @section('scripts')

		<!--Internal Sparkline js -->
		<script src="{{asset('assets/plugins/jquery-sparkline/jquery.sparkline.min.js')}}"></script>

		<!-- Internal Piety js -->
		<script src="{{asset('assets/plugins/peity/jquery.peity.min.js')}}"></script>

		<!-- Internal Chart js -->
		<script src="{{asset('assets/plugins/chartjs/Chart.bundle.min.js')}}"></script>

    @endsection
