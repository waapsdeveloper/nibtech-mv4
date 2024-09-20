@extends('layouts.app')

    @section('styles')


    @endsection

    @section('content')

        <!-- breadcrumb -->
        <div class="breadcrumb-header justify-content-between">
            <div class="left-content">
                <span class="main-content-title mg-b-lg-1">Charge Detail</span>
            </div>
            <div class="justify-content-center mt-2">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item tx-15"><a href="{{url('charge')}}">Charges</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Charge Detail</li>
                </ol>
            </div>
        </div>
        <!-- /breadcrumb -->
        <hr style="border-bottom: 1px solid #000">
        <!-- row -->
        <div class="card">
            <div class="card-body">
                <form action="{{url('charge/update')}}/{{$charge->id}}" method="POST">
                    @csrf
                    <div class="row">

                        <div class="col-md row align-items-center">
                            <div class="col-md-3">
                                <label class="form-label">Charge Frequency</label>
                            </div>
                            <div class="col-md-9">
                                <select class="form-select" placeholder="Input Charge Frequency" name="charge[charge_frequency]" required>
                                    @foreach ($charge_frequencies as $id=>$name)
                                        <option value="{{ $id }}" @if ($charge->charge_frequency_id == $id)
                                            selected

                                        @endif>{{ $name }}</option>

                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md row align-items-center">
                            <div class="col-md-3">
                                <label class="form-label">Order Type</label>
                            </div>
                            <select class="form-select" placeholder="Input Order Type" name="charge[order_type]">
                                <option value="">None</option>
                                @foreach ($order_types as $id=>$name)
                                    <option value="{{ $id }}" @if ($charge->order_type_id == $id)
                                        selected

                                    @endif>{{ $name }}</option>

                                @endforeach
                            </select>
                        </div>
                        <div class="col-md row align-items-center">
                            <div class="col-md-3">
                                <label class="form-label">Payment Method</label>
                            </div>
                            <div class="col-md-9">
                                <select class="form-select" placeholder="Input Payment Method" name="charge[payment_method]">
                                    <option value="">None / Any</option>
                                    @foreach ($payment_methods as $id=>$name)
                                        <option value="{{ $id }}" @if ($charge->payment_method_id == $id)
                                            selected

                                        @endif>{{ $name }}</option>

                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md row align-items-center">
                            <div class="col-md-3">
                                <label class="form-label">Amount Type</label>
                            </div>
                            <div class="col-md-9">
                                <select class="form-select" placeholder="Input Amount Type" name="charge[amount_type]">
                                    <option value="1" @if ($charge->amount_type == 1)
                                        selected

                                    @endif>Unit</option>
                                    <option value="2" @if ($charge->amount_type == 2)
                                        selected

                                    @endif>Percent</option>
                                </select>
                            </div>
                        </div>

                    </div>
                    <br>
                    <div class="row">
                        <div class="col-md row align-items-center">
                            <div class="col-md-3">
                                <label class="form-label">Name</label>
                            </div>
                            <div class="col-md-9">
                                <input class="form-control" placeholder="Input Charge Name" name="charge[name]" value="{{$charge->name}}" required>
                            </div>
                        </div>
                        <div class="col-md-8 row align-items-center">
                            <div class="col-md-2">
                                <label class="form-label">Description</label>
                            </div>
                            <div class="col-md-10">
                                <textarea class="form-control" rows="1" placeholder="Input Charge Description" name="charge[description]">{{$charge->description}}</textarea>
                            </div>
                        </div>
                    </div>

                    <button class="btn btn-primary" >Update</button>
                    @if ($charge->order_charges->count() == 0)
                        <a href="{{url('charge/delete')}}/{{$charge->id}}">Delete</a>
                    @endif
                </form>
                <br>
                <form class="form-inline justify-content-between" action="{{url('order_charge')}}" method="POST">
                    @csrf
                    <input type="hidden" name="order_charge[charge_id]" value="{{$charge->id}}">
                    <h5>Change Charge Standard: </h5>
                    <div class="input-group">
                        <label class="form-label">Start Date: </label>
                        <input type="datetime-local" class="form-control" name="order_charge[start_date]" required>
                    </div>
                    <div class="input-group">
                        <label class="form-label">Value: </label>
                        <input type="number" step="0.01" class="form-control" name="order_charge[amount]" required>
                    </div>
                    <button class="btn btn-primary" >Add</button>

                </form>
                <div class="row">
                    <div class="col-md-12">
                        <br>
                        <h5>Order Charges</h5>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Order Type</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Amount Type</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($charge->order_charges as $order_charge)
                                    <tr>
                                        <td>{{$order_charge->order_type->name}}</td>
                                        <td>{{$order_charge->amount}}</td>
                                        <td>{{$order_charge->payment_method->name}}</td>
                                        <td>{{$order_charge->amount_type == 1 ? 'Unit' : 'Percent'}}</td>
                                        <td><a href="{{url('order_charge/delete')}}/{{$order_charge->id}}">Delete</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- /row -->


        @endsection
    @section('scripts')

		<!-- Form-layouts js -->
		<script src="{{asset('assets/js/form-layouts.js')}}"></script>

    @endsection
