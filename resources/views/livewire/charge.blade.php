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
                    <a href="javascript:void(0);" class="btn btn-success float-right" data-bs-target="#modaldemo"
                    data-bs-toggle="modal"><i class="mdi mdi-plus"></i> Create Charge </a>
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboards') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">
                                Charge
                        </li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <hr style="border-bottom: 1px solid #000">
        <br>
        <div class="tx-right">

                <a href="{{url('add-charge')}}" class="btn btn-success float-right"><i class="mdi mdi-plus"></i> Add Charge</a>
        </div>
        <br>
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
        <br>
        @php
        session()->forget('error');
        @endphp
        @endif
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between">
                            <h4 class="card-title mg-b-0">Charges</h4>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>{{ __('locale.S.No') }}</b></small></th>
                                        <th><small><b>Name</b></small></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = 0;
                                    @endphp
                                    @foreach ($charges as $charge)
                                    @php
                                        $i++;
                                    @endphp
                                        <tr>
                                            <td title="{{$charge->id}}">{{$i}}</td>
                                            <td>{{$charge->name}}</td>
                                            <td><center><a href="edit-charge/{{$charge->id}}" class="text text-success w-100 vh-100">{{ __('locale.Edit') }}</a></center></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <br>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal" id="modaldemo">
            <div class="modal-dialog wd-xl-600" role="document">
                <div class="modal-content">
                    <div class="modal-body pd-sm-40">
                        <button aria-label="Close" class="close pos-absolute t-15 r-20 tx-26" data-bs-dismiss="modal"
                            type="button"><span aria-hidden="true">&times;</span></button>
                        <h5 class="modal-title mg-b-5">Create Charge</h5>
                        <hr>
                        <form action="{{ url('add_charge') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="form-group col-md-6">
                                <label for="">Charge Frequency</label>
                                <select class="form-select" placeholder="Input Charge Frequency" name="charge[charge_frequency]" required>
                                    @foreach ($charge_frequencies as $id=>$name)
                                        <option value="{{ $id }}">{{ $name }}</option>

                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="">Order Type</label>
                                <select class="form-select" placeholder="Input Order Type" name="charge[order_type]">
                                    <option>None</option>
                                    @foreach ($order_types as $id=>$name)
                                        <option value="{{ $id }}">{{ $name }}</option>

                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="">Payment Method</label>
                                <select class="form-select" placeholder="Input Payment Method" name="charge[payment_method]">
                                    <option>None / Any</option>
                                    @foreach ($payment_methods as $id=>$name)
                                        <option value="{{ $id }}">{{ $name }}</option>

                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="">Amount Type</label>
                                <select class="form-select" placeholder="Input Amount Type" name="charge[amount_type]">
                                    <option value="1">Unit</option>
                                    <option value="2">Percent</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="">Charge Name</label>
                                <input class="form-control" placeholder="input name" name="charge[name]" value="" type="text" required>
                            </div>
                            <div class="form-group">
                                <label for="">Charge Description</label>
                                <textarea class="form-control" placeholder="Input Description" name="charge[description]"></textarea>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="">Value</label>
                                <input class="form-control" placeholder="input value" name="charge[value]" value="" type="number" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="">Start Date</label>
                                <input class="form-control" placeholder="input Start Date" name="charge[started_at]" value="" type="datetime" required>
                            </div>

                            <button class="btn btn-primary btn-block">{{ __('locale.Submit') }}</button>
                        </form>
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
