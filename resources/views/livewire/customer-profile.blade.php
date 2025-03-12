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
            <div class="align-self-center">
                @foreach($totals as $total)
                    <h6 class="text-center">{{ $total['type'] }}:
                        €{{ amount_formatter($total['total_price']) }},
                        Items: {{ $total['total_items'] }},
                        Orders: {{ $total['total_orders'] }}
                    </h6>
                @endforeach
            </div>
            <div class="justify-content-center mt-2 position-relative">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                    <li class="breadcrumb-item tx-15"><a href="{{ session('previous')}}">Customers</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $customer->company }}</li>
                </ol>
                <form method="GET" action="" id="search" class="form-inline align-self-center mt-2">
                    <div class="form-floating">
                        <input type="date" class="form-control" name="start_date" value="{{ request('start_date') }}">
                        <label for="start_date">Start Date</label>
                    </div>
                    <div class="form-floating">
                        <input type="date" class="form-control" name="end_date" value="{{ request('end_date') }}">
                        <label for="end_date">End Date</label>
                    </div>
                    <input type="hidden" name="page" value="{{ request('page') }}">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
                <br>
                <br>
                <br>
                <div class="btn-group position-absolute bottom-0 end-0" role="group" aria-label="Basic example">
                    <button type="submit" form="search" name="page" value="orders" class="btn btn-link @if (request('page') == 'orders') bg-white @endif ">All&nbsp;Orders</button>
                    {{-- <a href="{{url('customer/profile').'/'.$customer->id}}?page=orders" class="btn btn-link @if (request('page') == 'orders') bg-white @endif ">All&nbsp;Orders</a> --}}
                    <button type="submit" form="search" name="page" value="transactions" class="btn btn-link @if (request('page') == 'transactions') bg-white @endif ">Accounts</button>
                    {{-- <a href="{{url('customer/profile').'/'.$customer->id}}?page=transactions" class="btn btn-link @if (request('page') == 'transactions') bg-white @endif ">Accounts</a> --}}
                    @if (session('user')->hasPermission('view_customer_repairs') && $repairs->count() > 0)
                        <button type="submit" form="search" name="page" value="sent_repair_summery" class="btn btn-link @if (request('page') == 'sent_repair_summery') bg-white @endif ">Sent&nbsp;Repair&nbsp;Summery</button>
                        {{-- <a href="{{url('customer/profile').'/'.$customer->id}}?page=sent_repair_summery" class="btn btn-link @if (request('page') == 'sent_repair_summery') bg-white @endif ">Sent&nbsp;Repair&nbsp;Summery</a> --}}

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
                    <a href="{{url('customer/export_pending_repairs').'/'.$customer->id}}" class="btn btn-primary">Export</a>
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
                                $total_quantity += $summery['quantity'];
                                $total_cost += $summery['total_cost'];
                                $stock_imeis = $summery['stock_imeis'];
                                $temp_array = array_unique($stock_imeis);
                                $duplicates = sizeof($temp_array) != sizeof($stock_imeis);
                                $duplicate_count = sizeof($stock_imeis) - sizeof($temp_array);

                            @endphp
                                <tr>
                                    <td>{{ ++$i }}</td>
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
        @elseif (session('user')->hasPermission('view_customer_transactions') && request('page') == 'transactions')
            <div class="row">
                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-header pb-0 d-flex justify-content-between">
                            <h5 class="card-title mg-b-0"> Customer Transactions </h5>
                            <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#record_payment">Record Payment</button>
                            <form method="GET" action="{{ url('customer/export_reports/'.$customer->id) }}" target="_BLANK" class="form-inline">
                                <input type="hidden" name="start_date" value="{{ request('start_date') }}">
                                <input type="hidden" name="end_date" value="{{ request('end_date') }}">
                                <button type="submit" name="statement" value="1" class="btn btn-primary">Statement</button>
                            </form>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                    <thead>
                                        <tr>
                                            <th><small><b>No</b></small></th>
                                            <th><small><b>Ref ID</b></small></th>
                                            <th><small><b>Type</b></small></th>
                                            <th><small><b>Batch</b></small></th>
                                            <th><small><b>Batch Type</b></small></th>
                                            <th><small><b>Description</b></small></th>
                                            <th><small><b>Value</b></small></th>
                                            <th><small><b>Creator</b></small></th>
                                            <th><small><b>Creation Date</b></small></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $i = 0;
                                        @endphp
                                        @foreach ($transactions as $index => $transaction)
                                            @php
                                                $order = $transaction->order;
                                                $process = $transaction->process;

                                                $batch = $order ?? $process;
                                            @endphp
                                            <tr @if ($transaction->status == 2) class="bg-warning" @elseif ($transaction->status == 3) class="bg-success" @endif>
                                                <td>{{ $i += 1 }}</td>
                                                <td>{{ $transaction->reference_id }}</td>
                                                <td>{{ $transaction->transaction_type->name }}</td>
                                                <td>
                                                    @if ($batch)

                                                    @if ($batch->order_type_id == 1)
                                                        <a href="{{url('purchase/detail/'.$batch->id)}}?status=1">{{ $batch->reference_id }}</a>
                                                    @elseif ($batch->order_type_id == 2)
                                                        <a href="{{url('rma/detail/'.$batch->id)}}">{{ $batch->reference_id }}</a>
                                                    @elseif ($batch->order_type_id == 5 && $batch->reference_id != 999)
                                                        <a href="{{url('wholesale/detail/'.$batch->id)}}">{{ $batch->reference_id }}</a>
                                                    @elseif ($batch->order_type_id == 5 && $batch->reference_id == 999)
                                                        <a href="https://www.backmarket.fr/bo_merchant/orders/all?orderId={{ $item->reference_id }}" target="_blank">Replacement <br> {{ $item->reference_id }}</a>
                                                    @elseif ($batch->order_type_id == 4)
                                                        <a href="{{url('return/detail/'.$batch->id)}}">{{ $batch->reference_id }}</a>
                                                    @elseif ($batch->order_type_id == 6)
                                                        <a href="{{url('wholesale_return/detail/'.$batch->id)}}">{{ $batch->reference_id }}</a>
                                                    @elseif ($batch->order_type_id == 3)
                                                        <a href="https://www.backmarket.fr/bo_merchant/orders/all?orderId={{ $batch->reference_id }}" target="_blank">{{ $batch->reference_id }}</a>
                                                    @elseif ($batch->process_type_id == 9)
                                                        <a href="{{url('repair/detail/'.$batch->id)}}">{{ $batch->reference_id }}</a>
                                                    @endif
                                                    @endif
                                                </td>
                                                {{-- <td>{{ $batch->reference_id }}</td> --}}
                                                <td>{{ $batch->order_type->name ?? null }}{{$batch->process_type->name ?? null}}
                                                    {{ $transaction->payment_method->name ?? null }}
                                                </td>
                                                <td title="{{ $transaction->description }}" class="wd-250">{{ Str::limit($transaction->description, 27) }}</td>
                                                <td>€{{ amount_formatter($transaction->amount,2) }}</td>
                                                <td>{{ $transaction->creator->first_name }}</td>
                                                <td>{{ $transaction->created_at }}</td>
                                                <td>

                                                    <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical  tx-18"></i></a>
                                                    <div class="dropdown-menu">
                                                        @if ($transaction->payment_method_id == null)

                                                            <a href="javascript:void(0);"  data-bs-toggle="modal" data-bs-target="#record_payment" class="dropdown-item" data-transaction_id="{{ $transaction->id }}" data-transaction_ref="{{ $transaction->reference_id }}" data-customer_id="{{ $customer->id }}" data-type="1" data-amount="{{ $transaction->amount }}" data-description="{{ $transaction->description }}" data-date="{{ $transaction->date }}"  data-currency="{{ $transaction->currency }}" data-exchange_rate="{{ $transaction->exchange_rate }}">Record Payment</a>
                                                        @endif

                                                        <a href="{{url('transaction/delete/'.$transaction->id)}}" class="dropdown-item">Delete</a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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

        <div class="modal fade" id="record_payment" tabindex="-1" role="dialog" aria-labelledby="record_payment" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <form method="POST" action="{{url('transaction/add_payment')}}" id="record_payment_form">
                        @csrf
                        <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                        <input type="hidden" name="type" value="1">
                        <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel2">Record Payment for {{ $customer->company }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">&times;</button>
                        </div>
                        <div class="modal-body pd-20">
                        <input type="hidden" name="transaction_id" id="transaction_id">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="type">Payment Type</label>
                                        <select class="form-control form-select" name="type" id="type" required>
                                            <option value="1">Receive</option>
                                            <option value="2">Send</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="method">Payment Method</label>
                                        <select class="form-control form-select" name="payment_method" id="method" required>
                                            <option value="1">Cash</option>
                                            <option value="2">Bank Transfer</option>
                                            <option value="3">Credit Card</option>
                                            <option value="4">Cheque</option>
                                            <option value="5">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">

                                    <div class="form-group">
                                        <label for="currency">Currency</label>
                                        <select class="form-control form-select" name="currency" id="currency" required>
                                            @foreach ($currencies as $currency)
                                                <option value="{{ $currency->id }}" @if ($currency->id == 4) selected @endif>{{ $currency->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="exchange_rate">Exchange Rate</label>
                                        <input type="number" class="form-control" name="exchange_rate" value="1" id="exchange_rate" step="0.0001" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">

                                    <div class="form-group">
                                        <label for="amount">Amount</label>
                                        <input type="number" class="form-control" name="amount" id="amount" step="0.01" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="date">Date</label>
                                        <input type="date" class="form-control" name="date" id="date" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea class="form-control" name="description" id="description" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Record Payment</button>

                        </div>
                    </form>
                </div>
            </div>
        </div>

    @endsection

    @section('scripts')

        <script>
            $(document).ready(function(){
                $('#record_payment').on('show.bs.modal', function (event) {
                    var button = $(event.relatedTarget);
                    var type = button.data('type');
                    var amount = button.data('amount');
                    var description = button.data('description');
                    var date = button.data('date');
                    var currency = button.data('currency');
                    var exchange_rate = button.data('exchange_rate');
                    var customer_id = button.data('customer_id');
                    var transaction_id = button.data('transaction_id');
                    var transaction_ref = button.data('transaction_ref');
                    var modal = $(this);
                    modal.find('.modal-title').text('Record Payment for ' + transaction_ref);
                    modal.find('.modal-body #type').val(type);
                    modal.find('.modal-body #amount').val(amount);
                    modal.find('.modal-body #description').val(description);
                    modal.find('.modal-body #date').val(date.split(' ')[0]);
                    modal.find('.modal-body #currency').val(currency).change();
                    modal.find('.modal-body #exchange_rate').val(exchange_rate);
                    modal.find('.modal-body #customer_id').val(customer_id);
                    modal.find('.modal-body #transaction_id').val(transaction_id);
                });

            })
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
