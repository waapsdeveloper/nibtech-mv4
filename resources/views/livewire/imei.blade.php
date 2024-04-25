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
                {{-- <span class="main-content-title mg-b-0 mg-b-lg-1">Search Serial</span> --}}
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Search Serial</li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <div class="row">
            <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>Search</h4></center>
            </div>
        </div>
        <br>

        <div class="d-flex justify-content-between" style="border-bottom: 1px solid rgb(216, 212, 212);">

            <div class="p-2">
                <form action="{{ url('imei')}}" method="GET" id="search" class="form-inline">
                    <div class="form-floating">
                        <input type="text" class="form-control" name="imei" placeholder="Enter IMEI" value="@isset($_GET['imei']){{$_GET['imei']}}@endisset">
                        <label for="">IMEI</label>
                    </div>
                        <button class="btn btn-primary pd-x-20" type="submit">{{ __('locale.Search') }}</button>
                </form>
            </div>
            <div class="p-2">
                @if (isset($stock) && $stock->status == 1 && session('user')->hasPermission('change_stock_grade'))


                    <form class="form-inline" method="POST" target="_blank" action="{{url(session('url').'imei/change_grade')."/".$stock->id}}">
                        <div class="form-floating">
                            <input type="text" class="form-control pd-x-20" name="reason" placeholder="Reason" style="width: 370px;">
                            {{-- <input type="text" class="form-control" name="imei" placeholder="Enter IMEI" value="@isset($_GET['imei']){{$_GET['imei']}}@endisset"> --}}
                            <label for="">Change Grade Reason</label>
                        </div>
                        <select name="grade" class="form-control form-select" required>
                            <option value="">Grade</option>
                            @foreach ($grades as $id=>$name)
                                <option value="{{ $id }}" @if(isset($_GET['grade']) && $id == $_GET['grade']) {{'selected'}}@endif>{{ $name }}</option>
                            @endforeach
                        </select>
                        <input class="btn btn-secondary pd-x-20 " type="submit" value="Send">
                    </form>
                @endif
            </div>
        </div>
        <br>
        <div class="row">
            <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>Search Serial</h4></center>
            </div>
        </div>
        <br>
        @if (isset($stock))

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between">
                            <h4 class="card-title mg-b-0">
                                Stock Detail
                            </h4>
                        </div>
                    </div>
                    <div class="card-body"><div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>Model</b></small></th>
                                        <th><small><b>Color</b></small></th>
                                        <th><small><b>Storage</b></small></th>
                                        <th><small><b>Grade</b></small></th>
                                        <th><small><b>Status</b></small></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <form method="post" action="{{url(session('url').'imei/change_variant')}}/{{ $stock->id }}" class="row form-inline">
                                        @csrf
                                        <tr>
                                            <td>
                                                <input type="text" name="update[product_id]" list="models" class="form-select form-select-sm" required>
                                                <datalist id="models">
                                                    <option value="">None</option>
                                                    @foreach ($products as $prod)
                                                        <option value="{{ $prod->id }}" {{ $product->product_id == $prod->id ? 'selected' : '' }}>{{ $prod->series." ".$prod->model }}</option>
                                                    @endforeach
                                                </datalist>
                                            </td>
                                            <td>{{ $product->name }}</td>
                                            <td>{{ $product->sku }}</td>
                                            <td>
                                                <select name="update[color]" class="form-select form-select-sm">
                                                    <option value="">None</option>
                                                    @foreach ($colors as $color)
                                                        <option value="{{ $color->id }}" {{ $product->color == $color->id ? 'selected' : '' }}>{{ $color->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <select name="update[storage]" class="form-select form-select-sm">
                                                    <option value="">None</option>
                                                    @foreach ($storages as $storage)
                                                        <option value="{{ $storage->id }}" {{ $product->storage == $storage->id ? 'selected' : '' }}>{{ $storage->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <select name="update[grade]" class="form-select form-select-sm">
                                                    <option value="">None</option>
                                                    @foreach ($grades as $grade)
                                                        <option value="{{ $grade->id }}" {{ $product->grade == $grade->id ? 'selected' : '' }}>{{ $grade->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <input type="submit" value="Update" class="btn btn-success">
                                            </td>
                                        </tr>
                                    </form>

                                </tbody>
                            </table>
                    </div>
                    </div>
                </div>
            </div>
        </div>
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
                        @if (isset($orders))

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
                                                @elseif ($order->order_type_id == 5)
                                                    <td><a href="{{url(session('url').'wholesale/detail/'.$order->id)}}">{{ $order->reference_id }}</a></td>
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
                                                <td>
                                                    <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical  tx-18"></i></a>
                                                    <div class="dropdown-menu">

                                                        <a class="dropdown-item" href="{{url(session('url').'order')}}/delete_item/{{ $item->id }}">Delete</a>
                                                    </div>
                                                </td>
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

    @endsection

    @section('scripts')

    <script>
        $('#correction_model').on('show.bs.modal', function (event) {
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

    @endsection
