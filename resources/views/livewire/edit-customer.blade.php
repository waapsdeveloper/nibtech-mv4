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
                            <div class="row row-xs align-items-center mg-b-20">
                                <div class="col-md-3">
                                    <label class="form-label mg-b-0">Type</label>
                                </div>
                                <div class="col-md-9 mg-t-5 mg-md-t-0">

                                    <select name="customer[type]" class="form-select">
                                        <option value="" {{ $customer->type == Null ? 'selected' : '' }}>Customers</option>
                                        <option value="1" {{ $customer->type == 1 ? 'selected' : '' }}>Vendors</option>
                                        <option value="2" {{ $customer->type == 2 ? 'selected' : '' }}>BulkSale Purchasers</option>
                                        <option value="3" {{ $customer->type == 3 ? 'selected' : '' }}>Repairer</option>
                                    </select>
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
                                        <option value="">Select</option>
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
                    @if ($customer->orders->count() == 0)
                        <a href="{{url('customer/delete')}}/{{$customer->id}}">Delete</a>
                    @endif
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
                                            $price = $order->order_items_sum_price;
                                            $j = 0;
                                        @endphp
                                        @if ($order->order_type_id != 3)

                                            <tr>
                                                <td>{{ $i + 1 }}</td>

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
                                                <td>€{{ number_format($price,2) }}</td>
                                                @endif
                                                <td style="width:220px">{{ $order->created_at }}</td>
                                                <td>
                                                    <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical  tx-18"></i></a>
                                                    <div class="dropdown-menu">
                                                        <a class="dropdown-item" href="{{url('delete_wholesale') . "/" . $order->id }}"><i class="fe fe-arrows-rotate me-2 "></i>Delete</a>
                                                        {{-- <a class="dropdown-item" href="{{ $order->delivery_note_url }}" target="_blank"><i class="fe fe-arrows-rotate me-2 "></i>Delivery Note</a>
                                                        <a class="dropdown-item" href="https://backmarket.fr/bo_merchant/orders/all?orderId={{ $order->reference_id }}&see-order-details={{ $order->reference_id }}" target="_blank"><i class="fe fe-caret me-2"></i>View in Backmarket</a> --}}
                                                        {{-- <a class="dropdown-item" href="javascript:void(0);"><i class="fe fe-trash-2 me-2"></i>Delete</a> --}}
                                                    </div>
                                                </td>
                                            </tr>
                                        @else
                                        @foreach ($items as $itemIndex => $item)
                                            <tr>
                                                {{-- @if ($itemIndex == 0)
                                                    <td rowspan="{{ count($items) }}">{{ $i + 1 }}</td>
                                                    <td rowspan="{{ count($items) }}">{{ $order->reference_id }}</td>
                                                @endif --}}
                                                    <td>{{ $i+1 }}</td>
                                                    <td>{{ $order->reference_id }}</td>
                                                <td>
                                                    @if ($item->variation ?? false)
                                                        <strong>{{ $item->variation->sku }}</strong>{{ " - " . $item->variation->product->model . " - " . (isset($item->variation->storage_id)?$item->variation->storage_id->name . " - " : null) . (isset($item->variation->color_id)?$item->variation->color_id->name. " - ":null)}} <strong><u>{{ $item->variation->grade_id->name }}</u></strong>
                                                    @endif
                                                    @if ($item->care_id != null)
                                                        <a class="" href="https://backmarket.fr/bo_merchant/customer-request/{{ $item->care_id }}" target="_blank"><strong class="text-danger">Conversation</strong></a>
                                                    @endif
                                                </td>
                                                <td>{{ $item->quantity }}</td>
                                                {{-- @if ($order->status == 3) --}}
                                                <td style="width:240px" class="text-success text-uppercase" id="copy_imei_{{ $order->id }}">
                                                    @isset($item->stock->imei) {{ $item->stock->imei }}&nbsp; @endisset
                                                    @isset($item->stock->serial_number) {{ $item->stock->serial_number }}&nbsp; @endisset
                                                    @isset($order->processed_by) | {{ $order->admin->first_name[0] }} | @endisset
                                                    @isset($item->stock->tester) ({{ $item->stock->tester }}) @endisset
                                                </td>


                                                {{-- @endif
                                                @if ($itemIndex == 0 && $order->status != 3) --}}
                                                {{-- <td style="width:240px" rowspan="{{ count($items) }}">
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
                                                </td>
                                                @endif --}}
                                                <td style="width:220px">{{ $order->created_at}} <br> {{ $order->processed_at." ".$order->tracking_number }}</td>
                                                <td>
                                                    <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical  tx-18"></i></a>
                                                    <div class="dropdown-menu">
                                                        <a class="dropdown-item" href="{{url('order')}}/refresh/{{ $order->reference_id }}">Refresh</a>
                                                        @if ($order->status == 3)

                                                        <a class="dropdown-item" href="{{url('order')}}/export_invoice_new/{{ $order->id }}" target="_blank">Invoice</a>
                                                        @endif
                                                        <a class="dropdown-item" href="https://backmarket.fr/bo_merchant/orders/all?orderId={{ $order->reference_id }}&see-order-details={{ $order->reference_id }}" target="_blank">View in Backmarket</a>
                                                    </div>
                                                </td>
                                            </tr>
                                            @php
                                                $j++;
                                            @endphp
                                        @endforeach
                                        @endif
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
