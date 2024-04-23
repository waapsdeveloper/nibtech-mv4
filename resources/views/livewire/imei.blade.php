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

<div class="toast-container position-fixed top-0 end-0 p-3">
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
        <form action="{{ url('imei')}}" method="GET" id="search">
            <div class="row">
                <div class="col-lg-2 col-xl-2 col-md-4 col-sm-6">
                    <div class="card-header">
                        <h4 class="card-title mb-1">IMEI</h4>
                    </div>
                    <input type="text" class="form-control" name="imei" placeholder="Enter IMEI" value="@isset($_GET['imei']){{$_GET['imei']}}@endisset">
                </div>
            </div>
            <div class=" p-2">
                <button class="btn btn-primary pd-x-20" type="submit">{{ __('locale.Search') }}</button>
            </div>

        </form>
        <br>
        <div class="row">
            <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>Search Serial</h4></center>
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
                        @if (isset($orders))

                            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>No</b></small></th>
                                        <th><small><b>Type</b></small></th>
                                        <th><small><b>Order ID</b></small></th>
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
                                                <td>{{ $i + 1 }}</td>
                                                <td>{{ $order->reference_id }}</td>
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
                                                <td style="width:240px" class="text-success text-uppercase" id="copy_imei_{{ $order->id }}">
                                                    @isset($item->stock->imei) {{ $item->stock->imei }}&nbsp; @endisset
                                                    @isset($item->stock->serial_number) {{ $item->stock->serial_number }}&nbsp; @endisset
                                                    @isset($order->processed_by) | {{ $order->admin->first_name[0] }} | @endisset
                                                    @isset($item->stock->tester) ({{ $item->stock->tester }}) @endisset
                                                </td>

                                                @endif
                                                @if ($order->status != 3)
                                                <td style="width:240px" rowspan="{{ count($items) }}">
                                                    @if ($item->status >= 5)
                                                        <strong class="text-danger">{{ $order->order_status->name }}</strong>
                                                    @else
                                                        @if(!isset($item->stock->imei) && !isset($item->stock->serial_number) && $item->status > 2 && $item->quantity == 1)


                                                            <a class="dropdown-item" href="https://backmarket.fr/bo_merchant/orders/all?orderId={{ $order->reference_id }}&see-order-details={{ $order->reference_id }}" target="_blank"><i class="fe fe-caret me-2"></i>View in Backmarket</a>
                                                            <a class="dropdown-item" href="{{url(session('url').'order')}}/refresh/{{ $order->reference_id }}"><i class="fe fe-arrows-rotate me-2 "></i>Refresh</a>
                                                        @endif
                                                    @endif
                                                    @isset($item->stock->imei) {{ $item->stock->imei }}&nbsp; @endisset
                                                    @isset($item->stock->serial_number) {{ $item->stock->serial_number }}&nbsp; @endisset

                                                    @isset($order->processed_by) | {{ $order->admin->first_name[0] }} | @endisset
                                                    @isset($item->stock->tester) ({{ $item->stock->tester }}) @endisset
                                                    @if ($item->status == 2)
                                                        @if (count($items) < 2 && $item->quantity < 2)
                                                            <form id="dispatch_{{ $i."_".$j }}" class="form-inline" method="post" action="{{url(session('url').'order')}}/dispatch/{{ $order->id }}">
                                                                @csrf
                                                                <div class="input-group">
                                                                    <input type="text" name="tester[]" placeholder="Tester" class="form-control form-control-sm" style="max-width: 50px">
                                                                    <input type="text" name="imei[]" placeholder="IMEI / Serial Number" class="form-control form-control-sm">

                                                                    <input type="hidden" name="sku[]" value="{{ $item->variation->sku }}">

                                                                    <div class="input-group-append">
                                                                        <input type="submit" name="imei_send" value=">" class="form-control form-control-sm" form="dispatch_{{ $i."_".$j }}">
                                                                    </div>

                                                                </div>
                                                            </form>
                                                        @elseif (count($items) < 2 && $item->quantity >= 2)

                                                            <form id="dispatch_{{ $i."_".$j }}" class="form-inline" method="post" action="{{url(session('url').'order')}}/dispatch/{{ $order->id }}">
                                                                @csrf
                                                                @for ($in = 1; $in <= $item->quantity; $in ++)

                                                                    <div class="input-group">
                                                                        <input type="text" name="tester[]" placeholder="Tester" class="form-control form-control-sm" style="max-width: 50px">
                                                                        <input type="text" name="imei[]" placeholder="IMEI / Serial Number" class="form-control form-control-sm" required>
                                                                    </div>
                                                                <input type="hidden" name="sku[]" value="{{ $item->variation->sku }}">
                                                                @endfor
                                                                <div class="w-100">
                                                                    <input type="submit" name="imei_send" value="Submit IMEIs" class="form-control form-control-sm w-100" form="dispatch_{{ $i."_".$j }}">
                                                                </div>
                                                            </form>
                                                        @elseif (count($items) >= 2 && $item->quantity == 1)
                                                            <form id="dispatch_{{ $i."_".$j }}" class="form-inline" method="post" action="{{url(session('url').'order')}}/dispatch/{{ $order->id }}">
                                                                @csrf
                                                                @for ($in = 1; $in <= count($items); $in ++)

                                                                    <div class="input-group">
                                                                        <input type="text" name="tester[]" placeholder="Tester" class="form-control form-control-sm" style="max-width: 50px">
                                                                        <input type="text" name="imei[]" placeholder="IMEI / Serial Number" class="form-control form-control-sm" required title="for SKU:{{ $items[$in-1]->variation->sku }}">
                                                                    </div>
                                                                <input type="hidden" name="sku[]" value="{{ $items[$in-1]->variation->sku }}">
                                                                @endfor
                                                                <div class="w-100">
                                                                    <input type="submit" name="imei_send" value="Submit IMEIs" class="form-control form-control-sm w-100" form="dispatch_{{ $i."_".$j }}">
                                                                </div>
                                                            </form>
                                                        @endif
                                                    @endif
                                                </td>
                                                @endif
                                                <td style="width:220px">{{ $order->created_at}} <br> {{ $order->processed_at." ".$order->tracking_number }}</td>
                                                <td>
                                                    <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical  tx-18"></i></a>
                                                    <div class="dropdown-menu">
                                                        <a class="dropdown-item" href="{{url(session('url').'order')}}/refresh/{{ $order->reference_id }}">Refresh</a>
                                                        @if ($item->order->processed_at > $last_hour)
                                                        <a class="dropdown-item" id="correction_{{ $item->id }}" href="javascript:void(0);" data-bs-target="#correction_model" data-bs-toggle="modal" data-bs-reference="{{ $order->reference_id }}" data-bs-item="{{ $item->id }}"> Correction </a>
                                                        @endif
                                                        @if ($order->status == 3)

                                                        <a class="dropdown-item" href="{{url(session('url').'order')}}/recheck/{{ $order->reference_id }}/true" target="_blank">Invoice</a>
                                                        @endif
                                                        <a class="dropdown-item" href="https://backmarket.fr/bo_merchant/orders/all?orderId={{ $order->reference_id }}&see-order-details={{ $order->reference_id }}" target="_blank">View in Backmarket</a>
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

        <div class="modal" id="correction_model">
            <div class="modal-dialog wd-xl-400" role="document">
                <div class="modal-content">
                    <div class="modal-body pd-sm-40">
                        <button aria-label="Close" class="close pos-absolute t-15 r-20 tx-26" data-bs-dismiss="modal"
                            type="button"><span aria-hidden="true">&times;</span></button>
                        <h5 class="modal-title mg-b-5">Update Order</h5>
                        <hr>
                        <form action="{{ url('order/correction') }}" method="POST">
                            @csrf
                            <div class="form-group">
                                <label for="">Order Number</label>
                                <input class="form-control" name="correction[id]" type="text" id="order_reference" disabled>
                            </div>
                            <div class="form-group">
                                <label for="">Tester</label>
                                <input class="form-control" placeholder="input Tester Initial" name="correction[tester]" type="text">
                            </div>
                            <div class="form-group">
                                <label for="">IMEI / Serial Number</label>
                                <input class="form-control" placeholder="input IMEI / Serial Number" name="correction[imei]" type="text" required>
                            </div>
                            <div class="form-group">
                                <label for="">Reason</label>
                                <textarea class="form-control" name="correction[reason]">Wrong Dispatch</textarea>
                            </div>
                            <input type="hidden" id="item_id" name="correction[item_id]" value="">

                            <button class="btn btn-primary btn-block">{{ __('locale.Submit') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        @script
        <script>
            setInterval(() => {
                $wire.$refresh()
            }, 2000)
        </script>
        @endscript
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
