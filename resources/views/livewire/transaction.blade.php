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
            {{-- <span class="main-content-title mg-b-0 mg-b-lg-1">BulkSale</span> --}}
            <a href="javascript:void(0);" class="btn btn-success float-right" data-bs-target="#modaldemo"
            data-bs-toggle="modal"><i class="mdi mdi-plus"></i> Add Transaction </a>
            </div>
            <div class="justify-content-center mt-2">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Transactions</li>
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
        <form action="" method="GET" id="search">
            <div class="row">
                <div class="col-lg-3 col-xl-3 col-md-3 col-sm-6">
                    <div class="card-header">
                        <h4 class="card-title mb-1">Transaction ID</h4>
                    </div>
                    <input type="text" class="form-control" name="order_id" placeholder="Enter ID" value="@isset($_GET['order_id']){{$_GET['order_id']}}@endisset">
                </div>
                <div class="col-lg-3 col-xl-3 col-md-3 col-sm-6">
                    <div class="card-header">
                        <h4 class="card-title mb-1">Customer</h4>
                    </div>
                    <select name="customer_id" class="form-control form-select" data-bs-placeholder="Select Customer">
                        <option value="">Customer</option>
                        @foreach ($vendors as $id => $vendor)
                            <option value="{{$id}}" @if(isset($_GET['customer_id']) && $id == $_GET['customer_id']) {{'selected'}}@endif>{{$vendor}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 col-xl-3 col-md-3 col-sm-6">
                    <div class="card-header">
                        <h4 class="card-title mb-1">{{ __('locale.Start Date') }}</h4>
                    </div>
                    <input class="form-control" name="start_date" id="datetimepicker" type="date" value="@isset($_GET['start_date']){{$_GET['start_date']}}@endisset">
                </div>
                <div class="col-lg-3 col-xl-3 col-md-3 col-sm-6">
                    <div class="card-header">
                        <h4 class="card-title mb-1">{{ __('locale.End Date') }}</h4>
                    </div>
                    <input class="form-control" name="end_date" id="datetimepicker" type="date" value="@isset($_GET['end_date']){{$_GET['end_date']}}@endisset">
                </div>
            </div>
            <div class=" p-2">
                <button class="btn btn-primary pd-x-20" type="submit">{{ __('locale.Search') }}</button>
                <a href="{{url('order')}}?per_page=10" class="btn btn-default pd-x-20">Reset</a>
            </div>

            <input type="hidden" name="page" value="{{ Request::get('page') }}">
            <input type="hidden" name="per_page" value="{{ Request::get('per_page') }}">
            <input type="hidden" name="sort" value="{{ Request::get('sort') }}">
        </form>
        <br>
        <div class="row">
            <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>Transactions</h4></center>
            </div>
        </div>
        <br>

        <div class="d-flex justify-content-between">
            <div>
                <a href="{{url('wholesale')}}?status=2" class="btn btn-link @if (request('status') == 2) bg-white @endif ">Pending</a>
                <a href="{{url('wholesale')}}?status=3" class="btn btn-link @if (request('status') == 3) bg-white @endif ">Completed</a>
                <a href="{{url('wholesale')}}" class="btn btn-link @if (!request('status')) bg-white @endif ">All</a>
            </div>
            <div class="">
            </div>
        </div>
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
                        <div class="card-header pb-0 d-flex justify-content-between">
                            <h5 class="card-title mg-b-0"> Transactions </h5>
                            <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#record_payment">Record Payment</button>
                            <form method="GET" action="{{ url('transaction/export_reports/') }}" target="_BLANK" class="form-inline">
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
                                            <th><small><b>Balance</b></small></th>
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
                                                if ($transaction->has('children')) {
                                                    $remaining = $transaction->amount-$transaction->children->sum('amount');
                                                }else {
                                                    $remaining = $transaction->amount;
                                                }
                                            @endphp
                                            <tr @if ($transaction->status == 2) class="bg-warning" @elseif ($transaction->status == 3) class="bg-lightgreen" @endif>
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
                                                <td title="{{ $transaction->description }}" class="wd-250">{{ Str::limit($transaction->description, 27) }}
                                                    {{ $transaction->parent->reference_id ?? null }}</td>
                                                <td>€{{ amount_formatter($transaction->amount,2) }}</td>

                                                <td>
                                                    @if ($transaction->has('children'))
                                                        €{{ amount_formatter($remaining) ?? null }}
                                                    @endif
                                                </td>
                                                <td>{{ $transaction->creator->first_name }}</td>
                                                <td>{{ date('d-m-Y',strtotime($transaction->date)) }}</td>
                                                <td>

                                                    <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical  tx-18"></i></a>
                                                    <div class="dropdown-menu">
                                                        @if ($transaction->payment_method_id == null)

                                                            <a href="javascript:void(0);"  data-bs-toggle="modal" data-bs-target="#record_payment" class="dropdown-item" data-transaction_id="{{ $transaction->id }}" data-transaction_ref="{{ $transaction->reference_id }}" data-customer_id="{{ $transaction->customer->id }}" data-type="1" data-amount="{{ $remaining }}" data-description="{{ $transaction->description }}" data-date="{{ $transaction->date }}"  data-currency="{{ $transaction->currency }}" data-exchange_rate="{{ $transaction->exchange_rate }}">Record Payment</a>
                                                        @endif
                                                        <a href="javascript:void(0);" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#record_payment" data-transaction_id="{{ $transaction->id }}" data-transaction_ref="{{ $transaction->reference_id }}" data-amount="{{ $transaction->amount }}" data-description="{{ $transaction->description }}" data-date="{{ $transaction->date }}"  data-currency="{{ $transaction->currency }}" data-exchange_rate="{{ $transaction->exchange_rate }}" data-func="edit">Edit Transaction</a>
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

        <div class="modal fade" id="record_payment" tabindex="-1" role="dialog" aria-labelledby="record_payment" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <form method="POST" action="{{url('transaction/add_payment')}}" id="record_payment_form">
                        @csrf
                        <input type="hidden" name="customer_id" value="">
                        <input type="hidden" name="type" value="1">
                        <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel2">Record Payment for </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">&times;</button>
                        </div>
                        <div class="modal-body pd-20">
                        <input type="hidden" name="transaction_id" id="transaction_id">
                            <div class="row hide-on-edit">
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
                                            <option value="1">Bank Transfer</option>
                                            <option value="2">Cash</option>
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
                    var amount = button.data('amount');
                    var description = button.data('description');
                    var date = button.data('date');
                    var currency = button.data('currency');
                    var exchange_rate = button.data('exchange_rate');
                    var transaction_id = button.data('transaction_id');
                    var transaction_ref = button.data('transaction_ref');
                    var modal = $(this);
                    modal.find('.modal-body #transaction_id').val(transaction_id);
                    modal.find('.modal-body #amount').val(amount);
                    modal.find('.modal-body #description').val(description);
                    modal.find('.modal-body #date').val(date.split(' ')[0]);
                    modal.find('.modal-body #currency').val(currency).change();
                    modal.find('.modal-body #exchange_rate').val(exchange_rate);

                    if(button.data('func') == 'edit'){
                        modal.find('.modal-title').text('Edit Transaction ' + transaction_ref);
                        modal.find('.modal-body .hide-on-edit').hide();
                        modal.find('.modal-content #record_payment_form').attr('action', "{{url('transaction/update/')}}/"+transaction_id);
                    }else {

                        var type = button.data('type');
                        var customer_id = button.data('customer_id');
                        modal.find('.modal-title').text('Record Payment for ' + transaction_ref);
                        modal.find('.modal-body #type').val(type);
                        modal.find('.modal-body #customer_id').val(customer_id);
                    }

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
