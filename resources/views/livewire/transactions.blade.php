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
                <span class="main-content-title mg-b-0 mg-b-lg-1">{{ __('locale.Transactions') }}</span>
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboards') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('locale.Transactions') }}</li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <div class="row">
            <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>{{ __('locale.Search Transaction') }}</h4></center>
            </div>
        </div>
        <br>
        <form action="" method="GET">
            <div class="row">
                <div class="col-lg-2 col-xl-2 col-md-4 col-sm-6">
                    <div class="card-header">
                        <h4 class="card-title mb-1">{{ __('locale.Trnx9-Ref') }}</h4>
                    </div>
                    <input type="text" class="form-control" name="id" placeholder="{{ __('locale.Enter ID') }}" value="@isset($_GET['id']){{$_GET['id']}}@endisset">
                </div>
                <div class="col-lg-2 col-xl-2 col-md-4 col-sm-6">
                    <div class="card-header">
                        <h4 class="card-title mb-1">{{ __('locale.Merchant-Ref') }}</h4>
                    </div>
                    <input type="text" class="form-control" name="m_ref" placeholder="{{ __('locale.Enter Reference') }}" value="@isset($_GET['m_ref']){{$_GET['m_ref']}}@endisset">
                </div>
                <div class="col-lg-2 col-xl-2 col-md-4 col-sm-6">
                    <div class="card-header">
                        <h4 class="card-title mb-1">{{ __('locale.Type') }}</h4>
                    </div>
                    <select name="transaction_type" class="form-control form-select" data-bs-placeholder="Select Type">
                        <option value="">{{ __('locale.Select') }}</option>
                        @foreach ($transaction_types as $type)
                            <option value="{{$type->id}}" @if(isset($_GET['transaction_type']) && $type->id == $_GET['transaction_type']) {{'selected'}}@endif>{{$type->name}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-xl-2 col-md-4 col-sm-6">
                    <div class="card-header">
                        <h4 class="card-title mb-1">{{ __('locale.Status') }}</h4>
                    </div>
                    {{-- <select name="country" class="form-control form-select select2" data-bs-placeholder="Select Country">
                        <option value="br">Brazil</option>
                        <option value="cz">Czech Republic</option>
                        <option value="de">Germany</option>
                        <option value="pl" selected>Poland</option>
                    </select> --}}
                    <select name="status" class="form-control form-select select2" data-bs-placeholder="Select Status">
                        <option value="">{{ __('locale.Select') }}</option>
                        @foreach ($transaction_statuses as $status)
                            <option value="{{$status->id}}" @if(isset($_GET['status']) && $status->id == $_GET['status']) {{'selected'}}@endif>{{$status->name}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-xl-2 col-md-4 col-sm-6">
                    <div class="card-header">
                        <h4 class="card-title mb-1">{{ __('locale.Start Date') }}</h4>
                    </div>
                    <input class="form-control" name="start_date" id="datetimepicker" type="date" value="@isset($_GET['start_date']){{$_GET['start_date']}}@endisset">
                </div>
                <div class="col-lg-2 col-xl-2 col-md-4 col-sm-6">
                    <div class="card-header">
                        <h4 class="card-title mb-1">{{ __('locale.End Date') }}</h4>
                    </div>
                    <input class="form-control" name="end_date" id="datetimepicker" type="date" value="@isset($_GET['end_date']){{$_GET['end_date']}}@endisset">
                </div>
            </div>
            <div class=" p-2">
                <button class="btn btn-primary pd-x-20" type="submit">{{ __('locale.Search') }}</button>
            </div>
        </form>
        <br>
        <div class="row">
            <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>{{ __('locale.Transactions') }}</h4></center>
            </div>
        </div>
        <br>
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between">
                            <h4 class="card-title mg-b-0">{{ __('locale.All Transactions of') }} {{$our_id}}</h4>
                            <h5 class="card-title mg-b-0">{{ __('locale.From') }} {{$transactions->firstItem()}} {{ __('locale.To') }} {{$transactions->lastItem()}} {{ __('locale.Out Of') }} {{$transactions->total()}}</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>{{ __('locale.S.No') }}</b></small></th>
                                        <th><small><b>{{ __('locale.Datetime') }}</b></small></th>
                                        <th><small><b>{{ __('locale.Trnx9-Ref') }}</b></small></th>
                                        <th><small><b>{{ __('locale.Merchant-Ref') }}</b></small></th>
                                        <th><small><b>{{ __('locale.Type') }}</b></small></th>
                                        <th><small><b>{{ __('locale.Amount') }}</b></small></th>
                                        <th><small><b>{{ __('locale.Charges') }}</b></small></th>
                                        <th><small><b>{{ __('locale.Status') }}</b></small></th>
                                        <th><center><small><b>{{ __('locale.Action') }}</b></small></center></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = $transactions->firstItem()-1;
                                    @endphp
                                    @foreach ($transactions as $tr)
                                        @php
                                            $i++;
                                        @endphp
                                    <tr>
                                        <td>{{$i}}</td>
                                        <td>{{$tr->datetime}}</td>
                                        <td>{{$tr->id}}</td>
                                        <td>{{$tr->m_ref}}</td>
                                        <td>{{$tr->tr_type->name}}</td>
                                        <td>{{number_format($tr->amount,2)}}</td>
                                        <td>{{number_format($tr->charges,2)}}</td>
                                        <td>{{$tr->tr_status->name}}</td>
                                        <td><center><a class="text text-success w-100 vh-100" data-bs-target="#modaldemo{{$i}}" data-bs-toggle="modal" id="btn{{$i}}">Detail</a></center></td>

                                        <div class="modal" id="modaldemo{{$i}}">
                                            <div class="modal-dialog" style="max-width: 1000px;margin-top:10%" role="document">
                                                <div class="modal-content">
                                                    <div class="modal-body pd-sm-40">
                                                        <button aria-label="Close" class="close pos-absolute t-15 r-20 tx-26" data-bs-dismiss="modal" type="button"><span aria-hidden="true">&times;</span></button>
                                                        <h5 class="modal-title mg-b-5">{{ __('locale.Transaction Details') }}</h5>
                                                        <hr>
                                                        @php
                                                            $transaction = App\Models\Transactions_model::where('id',$tr->id)->first();
                                                        @endphp
                                                        <div class="row rows">
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Merchant ID') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs" >
                                                                {{session('our_id')}}
                                                            </div>
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Trnx9-Ref') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs" >
                                                                {{$transaction->id}}
                                                            </div>
                                                        </div>
                                                        <div class="row rows">
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Account Title') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs" >
                                                                {{$transaction->account_name}}
                                                            </div>
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Merchant-Ref') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs" >
                                                                {{$transaction->m_ref}}
                                                            </div>
                                                        </div>
                                                        <div class="row rows">
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Account Number') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs" >
                                                                {{$transaction->account_no}}
                                                            </div>
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Status') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs" >
                                                                {{$tr->tr_status->name}}
                                                            </div>
                                                        </div>
                                                        <div class="row rows">
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Bank') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs" >
                                                                {{$transaction->user_bank}}
                                                            </div>
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Full Name') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs" >
                                                                {{$transaction->first_name}} {{$transaction->last_name}}
                                                            </div>
                                                        </div>
                                                        <div class="row rows">
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Amount') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs" >
                                                                {{number_format($transaction->amount,2)}}
                                                            </div>
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Email') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs" >
                                                                {{$transaction->email}}
                                                            </div>
                                                        </div>
                                                        <div class="row rows">
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Charges') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs" >
                                                                {{number_format($transaction->charges,2)}}
                                                            </div>
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Phone') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs" >
                                                                {{$transaction->phone}}
                                                            </div>
                                                        </div>
                                                        <div class="row rows">

                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Fees') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs" >
                                                                {{-- {{number_format($transaction->final_amount,2)}} --}}
                                                            </div>
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Postback Url') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs" >
                                                                {{$transaction->postback_url_merchant}}
                                                            </div>
                                                        </div>
                                                        <div class="row rows">

                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Final Amount') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs" >
                                                                {{number_format($transaction->final_amount,2)}}
                                                            </div>
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Success Url') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs" >
                                                                {{$transaction->success_url_merchant}}
                                                            </div>
                                                        </div>
                                                        <div class="row rows">
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Currency') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs" >
                                                                {{$transaction->currency_id->code}}
                                                            </div>
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Note') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs" >
                                                                {{$transaction->note}}
                                                            </div>
                                                        </div>
                                                        <div class="row rows">
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Requested At') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs" >
                                                                {{$transaction->datetime}}
                                                            </div>
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Updated At') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs" >
                                                                {{$transaction->notify_date}}
                                                            </div>

                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </tr>

                                    @endforeach
                                </tbody>
                            </table>
                            <br>
                            {{ $transactions->onEachSide(1)->links() }} {{ __('locale.From') }} {{$transactions->firstItem()}} {{ __('locale.To') }} {{$transactions->lastItem()}} {{ __('locale.Out Of') }} {{$transactions->total()}}
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
