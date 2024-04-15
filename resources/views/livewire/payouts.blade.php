@extends('layouts.app')

@section('styles')
    <style>
        .rows {
            border: 1px solid #016a5949;
        }

        .columns {
            background-color: #016a5949;
            padding-top: 5px
        }

        .childs {
            padding-top: 5px
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
                <li class="breadcrumb-item active" aria-current="page">{{ __('locale.'.$Page) }}</li>
            </ol>
        </div>
    </div>
    <!-- /breadcrumb -->
    <div class="row">
        <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
            <h4 class="text-center">{{ __('locale.'.$Page) }}</h4>
        </div>
    </div>
    <br>
    @if (null !== session('member_id') && $type == 2)
    @else
        <div class="row">
            <div class="col-md-12" style="text-align: right">
                <a href="javascript:void(0);" class="btn btn-success float-right" data-bs-target="#modaldemo"
                    data-bs-toggle="modal"><i class="mdi mdi-plus"></i> {{ __('locale.Create Request') }}</a>
            </div>
        </div>
        <br>
    @endif
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <span class="alert-inner--icon"><i class="fe fe-thumbs-up"></i></span>&nbsp;
            <span class="alert-inner--text"><strong>{{ session('success') }}</strong></span>
            <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span
                    aria-hidden="true">&times;</span></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <span class="alert-inner--icon"><i class="fe fe-thumbs-down"></i></span>&nbsp;
            <span class="alert-inner--text"><strong>{{ session('error') }}</strong></span>
            <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span
                    aria-hidden="true">&times;</span></button>
        </div>
    @endif
    <br>
    <div class="row">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-header pb-0">
                    <div class="d-flex justify-content-between">
                        <h4 class="card-title mg-b-0">{{ __('locale.All') }} {{ __('locale.'.$Page) }} {{ __('locale.Of') }} {{ session('our_id') }}</h4>
                        <h5 class="card-title mg-b-0">{{ __('locale.From') }} {{ $transactions->firstItem() }} To
                            {{ $transactions->lastItem() }} {{ __('locale.Out Of') }} {{ $transactions->total() }}</h5>
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
                                    <th><small><b>{{ __('locale.Amount') }}</b></small></th>
                                    <th><small><b>{{ __('locale.Charges') }}</b></small></th>
                                    <th><small><b>{{ __('locale.Status') }}</b></small></th>

                                    <th colspan="3">
                                        <center><small><b>{{ __('locale.Action') }}</b></small></center>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $i = $transactions->firstItem() - 1;
                                @endphp
                                @foreach ($transactions as $tr)
                                    @php
                                        $i++;
                                    @endphp
                                    <tr>
                                        <td>{{ $i }}</td>
                                        <td>{{ $tr->datetime }}</td>
                                        <td>{{ $tr->id }}</td>
                                        <td>{{ $tr->m_ref }}</td>
                                        <td>{{ number_format($tr->amount, 2) }}</td>
                                        <td>{{ number_format($tr->charges, 2) }}</td>
                                        <td>{{ $tr->tr_status->name }}</td>
                                        <td><a class="text text-success w-100 vh-100"
                                                data-bs-target="#modaldemo{{ $i }}" data-bs-toggle="modal"
                                                id="btn{{ $i }}">Detail</a></td>
                                        @if ($tr->status > 2)
                                            @if ($tr->authorized_by_id != 0)
                                                <td><a class="text w-100 vh-100">Authorized</a></td>
                                            @else
                                                <form method="POST"
                                                    action="{{ url('authorize') }}/{{ $tr->id }}/{{ $page }}">
                                                    @csrf
                                                    <td class="input-group">
                                                        <input type="password" class="form-control" name="code"
                                                            placeholder="Code"><input type="submit"
                                                            class="form-control btn btn-success" value="Authorize">
                                                        {{-- <a href="{{url('authorize')}}/{{$tr->id}}/{{$page}}" class="text text-warning w-100 vh-100" >Authorize</a> --}}
                                                    </td>
                                                </form>
                                            @endif
                                            <td><a href="{{ url('decline') }}/{{ $tr->id }}/{{ $page }}"
                                                    class="text text-danger w-100 vh-100">Decline</a></td>
                                        @endif
                                        <div class="modal" id="modaldemo{{ $i }}">
                                            <div class="modal-dialog" style="max-width: 1000px;margin-top:10%"
                                                role="document">
                                                <div class="modal-content">
                                                    <div class="modal-body pd-sm-40">
                                                        <button aria-label="Close"
                                                            class="close pos-absolute t-15 r-20 tx-26"
                                                            data-bs-dismiss="modal" type="button"><span
                                                                aria-hidden="true">&times;</span></button>
                                                        <h5 class="modal-title mg-b-5">Transaction Details{{ __('locale.Transaction Details') }}</h5>
                                                        <hr>
                                                        @php
                                                            if ($type == 2) {
                                                                $transaction = App\Models\Transactions_model::where('id', $tr->id)->first();
                                                            } else {
                                                                $transaction = App\Models\Settlement_model::where('id', $tr->id)->first();
                                                            }
                                                        @endphp
                                                        <div class="row rows">
                                                            <div class="col-md-2 columns">
                                                                <b>Merchant ID{{ __('locale.Merchant ID') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs">
                                                                {{ session('our_id') }}
                                                            </div>
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Trnx9-Ref') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs">
                                                                {{ $transaction->id }}
                                                            </div>
                                                        </div>
                                                        <div class="row rows">
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Account Title') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs">
                                                                {{ $transaction->account_name }}
                                                            </div>
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Merchant-Ref') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs">
                                                                {{ $transaction->m_ref }}
                                                            </div>
                                                        </div>
                                                        <div class="row rows">
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Account Number') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs">
                                                                {{ $transaction->account_no }}
                                                            </div>
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Status') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs">
                                                                {{ $tr->tr_status->name }}
                                                            </div>
                                                        </div>
                                                        <div class="row rows">
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Bank') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs">
                                                                {{ $transaction->user_bank }}
                                                            </div>
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Full Name') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs">
                                                                {{ $transaction->first_name }}
                                                                {{ $transaction->last_name }}
                                                            </div>
                                                        </div>
                                                        <div class="row rows">
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Amount') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs">
                                                                {{ number_format($transaction->amount, 2) }}
                                                            </div>
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Email') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs">
                                                                {{ $transaction->email }}
                                                            </div>
                                                        </div>
                                                        <div class="row rows">
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Charges') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs">
                                                                {{ number_format($transaction->charges, 2) }}
                                                            </div>
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Phone') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs">
                                                                {{ $transaction->phone }}
                                                            </div>
                                                        </div>
                                                        <div class="row rows">

                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Fees') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs">
                                                                {{-- {{number_format($transaction->final_amount,2)}} --}}
                                                            </div>
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Postback Url') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs">
                                                                {{ $transaction->postback_url_merchant }}
                                                            </div>
                                                        </div>
                                                        <div class="row rows">

                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Final Amount') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs">
                                                                {{ number_format($transaction->final_amount, 2) }}
                                                            </div>
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Success Url') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs">
                                                                {{ $transaction->success_url_merchant }}
                                                            </div>
                                                        </div>
                                                        <div class="row rows">
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Currency') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs">
                                                                {{ $transaction->currency_id->code }}
                                                            </div>
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Note') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs">
                                                                {{ $transaction->note }}
                                                            </div>
                                                        </div>
                                                        <div class="row rows">
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Requested At') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs">
                                                                {{ $transaction->datetime }}
                                                            </div>
                                                            <div class="col-md-2 columns">
                                                                <b>{{ __('locale.Updated At') }}</b>
                                                            </div>
                                                            <div class="col-md-4 childs">
                                                                {{ $transaction->notify_date }}
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
                        {{ $transactions->onEachSide(1)->links() }} {{ __('locale.From') }} {{ $transactions->firstItem() }} To
                        {{ $transactions->lastItem() }} {{ __('locale.Out Of') }} {{ $transactions->total() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal" id="modaldemo">
        <div class="modal-dialog wd-xl-400" role="document">
            <div class="modal-content">
                <div class="modal-body pd-sm-40">
                    <button aria-label="Close" class="close pos-absolute t-15 r-20 tx-26" data-bs-dismiss="modal"
                        type="button"><span aria-hidden="true">&times;</span></button>
                    <h5 class="modal-title mg-b-5">{{ __('locale.Create') }} {{ __('locale.'.$Page) }} {{ __('locale.Request') }}</h5>
                    <h6>{{ __('locale.Current Balance') }} : {{ $currency_code . ' ' . number_format($balance, 2) }}</h6>
                    <hr>
                    <form action="{{ url('submit') }}" method="POST">
                        @csrf
                        <input type="hidden" name="type" id="" value="{{ $type }}">
                        @php
                            $payment_type = App\Models\Payment_method_model::where('id', $_GET['method_id'])->first()->code;
                        @endphp
                        <input type="hidden" name="payment_type" id=""
                            value="{{ strtolower($payment_type) }}">
                        @foreach ($api_required_fields as $required_field)
                            @if ($required_field->field == 'network')
                                <div class="form-group">
                                    <label for="">{{ __('locale.'.$required_field->name) }}</label>
                                    <select class="form-control" placeholder="Input {{ $required_field->name }}"
                                        name="{{ $required_field->field }}" required>
                                        <option>{{ __('locale.Select Network') }}</option>
                                        <option value="2">TRC20 | Charges : USDT 2</option>
                                        <option value="1">ERC20 | Charges : USDT 100</option>
                                    </select>
                                </div>
                            @else
                                <div class="form-group">
                                    <label for="">{{ __('locale.'.$required_field->name) }}</label>
                                    <input class="form-control" placeholder="{{ __('locale.Input') }} {{ __('locale.'.$required_field->name) }}"
                                        name="{{ $required_field->field }}" type="text" required>
                                </div>
                            @endif
                        @endforeach
                        <div class="form-group">
                            <label for="">{{ __('locale.Amount') }}</label>
                            <input class="form-control" placeholder="{{ __('locale.Input') }} {{ __('locale.Amount') }}" name="amount" type="number"
                                step="0.01" required>
                        </div>
                        @if ($type > 2)
                            <div class="form-group">
                                <label for="">{{ __('locale.Settlement Password') }}</label>
                                <input class="form-control" placeholder="{{ __('locale.Input') }} {{ __('locale.Settlement Password') }}" name="code"
                                    type="password" required>
                            </div>
                        @endif
                        <button class="btn btn-primary btn-block">{{ __('locale.Submit') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @php
        session()->forget('success');
        session()->forget('error');
    @endphp
@endsection

@section('scripts')
    <!--Internal Sparkline js -->
    <script src="{{ asset('assets/plugins/jquery-sparkline/jquery.sparkline.min.js') }}"></script>

    <!-- Internal Piety js -->
    <script src="{{ asset('assets/plugins/peity/jquery.peity.min.js') }}"></script>

    <!-- Internal Chart js -->
    <script src="{{ asset('assets/plugins/chartjs/Chart.bundle.min.js') }}"></script>
@endsection
