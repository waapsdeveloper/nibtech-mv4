@extends('layouts.app')

    @section('styles')

		<!--- Internal Select2 css-->
		<link href="{{asset('assets/plugins/select2/css/select2.min.css')}}" rel="stylesheet">

    @endsection

    @section('content')

        <!-- breadcrumb -->
        <div class="breadcrumb-header justify-content-between">
            <div class="left-content">
                <span class="main-content-title mg-b-0 mg-b-lg-1">Customer Profile</span>
            </div>
            <div class="justify-content-center mt-2">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item tx-15"><a href="{{url('customer')}}">Customer</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Customer Profile</li>
                </ol>
            </div>
        </div>
        <!-- /breadcrumb -->
        <hr style="border-bottom: 1px solid #000">
        <!-- row -->
        <div class="card">
            <div class="card-body">
                <form action="{{url('update-customer')}}/{{$customer->id}}" method="POST">
                    @csrf
                    <div class="row">

                        <div class="col-md-4">
                            <div class="row row-xs align-items-center mg-b-20">
                                <div class="col-md-3">
                                    <label class="form-label mg-b-0">Company</label>
                                </div>
                                <div class="col-md-9 mg-t-5 mg-md-t-0">
                                    <input class="form-control" placeholder="Enter customer's company" name="customer[company]" value="{{$customer->company}}" type="text">
                                </div>
                            </div>
                            <div class="row row-xs align-items-center mg-b-20">
                                <div class="col-md-3">
                                    <label class="form-label mg-b-0">First name</label>
                                </div>
                                <div class="col-md-9 mg-t-5 mg-md-t-0">
                                    <input class="form-control" placeholder="Enter customer's firstname" name="customer[first_name]" value="{{$customer->first_name}}" type="text">
                                </div>
                            </div>
                            <div class="row row-xs align-items-center mg-b-20">
                                <div class="col-md-3">
                                    <label class="form-label mg-b-0">Last name</label>
                                </div>
                                <div class="col-md-9 mg-t-5 mg-md-t-0">
                                    <input class="form-control" placeholder="Enter customer's lastname" name="customer[last_name]" value="{{$customer->last_name}}" type="text">
                                </div>
                            </div>

                        </div>
                        <div class="col-md-4">
                            <div class="row row-xs align-items-center mg-b-20">
                                <div class="col-md-3">
                                    <label class="form-label mg-b-0">Street Addres</label>
                                </div>
                                <div class="col-md-9 mg-t-5 mg-md-t-0">
                                    <input class="form-control" placeholder="Enter customer's Street address" name="customer[street]" value="{{$customer->street}}" type="text">
                                    <input class="form-control" placeholder="Enter customer's Street address" name="customer[street2]" value="{{$customer->street2}}" type="text">
                                </div>
                            </div>
                            <div class="row row-xs align-items-center mg-b-20">
                                <div class="col-md-3">
                                    <label class="form-label mg-b-0">Post Code</label>
                                </div>
                                <div class="col-md-9 mg-t-5 mg-md-t-0">
                                    <input class="form-control" placeholder="Enter customer's post code" name="customer[postal_code]" value="{{$customer->postal_code}}" type="text">
                                </div>
                            </div>
                            <div class="row row-xs align-items-center mg-b-20">
                                <div class="col-md-3">
                                    <label class="form-label mg-b-0">City</label>
                                </div>
                                <div class="col-md-9 mg-t-5 mg-md-t-0">
                                    <input class="form-control" placeholder="Enter customer's city" name="customer[city]" value="{{$customer->city}}" type="text">
                                </div>
                            </div>

                        </div>
                        <div class="col-md-4">
                            <div class="row row-xs align-items-center mg-b-20">
                                <div class="col-md-3">
                                    <label class="form-label mg-b-0">Email</label>
                                </div>
                                <div class="col-md-9 mg-t-5 mg-md-t-0">
                                    <input class="form-control" placeholder="Enter customer's email" name="customer[email]" value="{{$customer->email}}" type="email">
                                </div>
                            </div>
                            <div class="row row-xs align-items-center mg-b-20">
                                <div class="col-md-3">
                                    <label class="form-label mg-b-0">Phone</label>
                                </div>
                                <div class="col-md-9 mg-t-5 mg-md-t-0">
                                    <input class="form-control" placeholder="Enter customer's phone" name="customer[phone]" value="{{$customer->phone}}" type="text">
                                </div>
                            </div>
                            <div class="row row-xs align-items-center mg-b-20">
                                <div class="col-md-3">
                                    <label class="form-label mg-b-0">Country</label>
                                </div>
                                <div class="col-md-9 mg-t-5 mg-md-t-0">

                                    <select class="form-select" name="customer[country]">
                                        <option>Select</option>
                                        @foreach ($countries as $country)
                                            <option value="{{ $country->id }}" @if($country->id == $customer->country) selected @endif>{{ $country->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="row row-xs align-items-center mg-b-20">
                                <div class="col-md-3">
                                    <label class="form-label mg-b-0">VAT Number</label>
                                </div>
                                <div class="col-md-9 mg-t-5 mg-md-t-0">
                                    <input class="form-control" placeholder="Enter customer's VAT Number" name="customer[vat]" value="{{$customer->vat}}" type="text">
                                </div>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-primary pd-x-30 mg-r-5 mg-t-5" >Update</button>
                </form>
            </div>
        </div>
        <!-- /row -->

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
                                        <th><small><b>Order ID</b></small></th>
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
                                    @foreach ($orders as $index => $order)
                                        @php
                                            if(in_array($order->id,$id)){
                                                continue;
                                            }else {
                                                $id[] = $order->id;
                                            }
                                            $items = $order->order_items;
                                            $j = 0;
                                        @endphp

                                        @foreach ($items as $itemIndex => $item)
                                            <tr>
                                                @if ($itemIndex == 0)
                                                    <td rowspan="{{ count($items) }}">{{ $i + 1 }}</td>
                                                    <td rowspan="{{ count($items) }}">{{ $order->reference_id }}</td>
                                                @endif
                                                <td>
                                                    @if ($item->variation ?? false)
                                                        <strong>{{ $item->variation->sku }}</strong>{{ " - " . $item->variation->product->model . " - " . (isset($item->variation->storage_id)?$item->variation->storage_id->name . " - " : null) . (isset($item->variation->color_id)?$item->variation->color_id->name. " - ":null)}} <strong><u>{{ $item->variation->grade_id->name }}</u></strong>
                                                    @endif
                                                    @if ($order->delivery_note_url == null || $order->label_url == null)
                                                        <a class="" href="{{url('order')}}/label/{{ $order->reference_id }}">
                                                        @if ($order->delivery_note_url == null)
                                                            <strong class="text-danger">Missing Delivery Note</strong>
                                                        @endif
                                                        @if ($order->label_url == null)

                                                            <strong class="text-danger">Missing Label</strong>
                                                        @endif
                                                        </a>
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
                                                @if ($itemIndex == 0 && $order->status != 3)
                                                <td style="width:240px" rowspan="{{ count($items) }}">
                                                    @if ($item->status >= 5)
                                                        <strong class="text-danger">{{ $order->order_status->name }}</strong>
                                                    @else
                                                        @if(!isset($item->stock->imei) && !isset($item->stock->serial_number) && $item->status > 2 && $item->quantity == 1)


                                                            <a class="dropdown-item" href="https://backmarket.fr/bo_merchant/orders/all?orderId={{ $order->reference_id }}&see-order-details={{ $order->reference_id }}" target="_blank"><i class="fe fe-caret me-2"></i>View in Backmarket</a>
                                                            <a class="dropdown-item" href="{{url('order')}}/refresh/{{ $order->reference_id }}"><i class="fe fe-arrows-rotate me-2 "></i>Refresh</a>
                                                        @endif
                                                    @endif
                                                    @isset($item->stock->imei) {{ $item->stock->imei }}&nbsp; @endisset
                                                    @isset($item->stock->serial_number) {{ $item->stock->serial_number }}&nbsp; @endisset

                                                    @isset($order->processed_by) | {{ $order->admin->first_name[0] }} | @endisset
                                                    @isset($item->stock->tester) ({{ $item->stock->tester }}) @endisset
                                                    @if ($item->status == 2)
                                                        @if (count($items) < 2 && $item->quantity < 2)
                                                            <form id="dispatch_{{ $i."_".$j }}" class="form-inline" method="post" action="{{url('order')}}/dispatch/{{ $order->id }}">
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

                                                            <form id="dispatch_{{ $i."_".$j }}" class="form-inline" method="post" action="{{url('order')}}/dispatch/{{ $order->id }}">
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
                                                            <form id="dispatch_{{ $i."_".$j }}" class="form-inline" method="post" action="{{url('order')}}/dispatch/{{ $order->id }}">
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
                                                        <a class="dropdown-item" href="{{url('order')}}/refresh/{{ $order->reference_id }}">Refresh</a>
                                                        @if ($order->status == 3)

                                                        <a class="dropdown-item" href="{{url('order')}}/recheck/{{ $order->reference_id }}/true" target="_blank">Invoice</a>
                                                        @endif
                                                        <a class="dropdown-item" href="https://backmarket.fr/bo_merchant/orders/all?orderId={{ $order->reference_id }}&see-order-details={{ $order->reference_id }}" target="_blank">View in Backmarket</a>
                                                    </div>
                                                </td>
                                            </tr>
                                            @php
                                                $j++;
                                            @endphp
                                        @endforeach
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

        @endsection
    @section('scripts')

		<!-- Form-layouts js -->
		<script src="{{asset('assets/js/form-layouts.js')}}"></script>

		<!--Internal  Select2 js -->
		<script src="{{asset('assets/plugins/select2/js/select2.min.js')}}"></script>

    @endsection
