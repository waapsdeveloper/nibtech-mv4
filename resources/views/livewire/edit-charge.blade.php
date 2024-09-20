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
                <form action="{{url('update-charge')}}/{{$charge->id}}" method="POST">
                    @csrf
                    <div class="row">

                        <div class="col-md row">
                            <div class="col-md-3">
                                <label class="form-label">Charge Frequency</label>
                            </div>
                            <div class="col-md-9">
                                <select class="form-select" placeholder="Input Charge Frequency" name="charge[charge_frequency]" required>
                                    @foreach ($charge_frequencies as $id=>$name)
                                        <option value="{{ $id }}">{{ $name }}</option>

                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md row">
                            <div class="col-md-3">
                                <label class="form-label">Order Type</label>
                            </div>
                            <select class="form-select" placeholder="Input Order Type" name="charge[order_type]">
                                <option value="">None</option>
                                @foreach ($order_types as $id=>$name)
                                    <option value="{{ $id }}">{{ $name }}</option>

                                @endforeach
                            </select>
                        </div>
                        <div class="col-md row">
                            <div class="col-md-3">
                                <label class="form-label">Payment Method</label>
                            </div>
                            <div class="col-md-9">
                                <select class="form-select" placeholder="Input Payment Method" name="charge[payment_method]">
                                    <option value="">None / Any</option>
                                    @foreach ($payment_methods as $id=>$name)
                                        <option value="{{ $id }}">{{ $name }}</option>

                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md row">
                            <div class="col-md-3">
                                <label class="form-label">Amount Type</label>
                            </div>
                            <div class="col-md-9">
                                <select class="form-select" placeholder="Input Amount Type" name="charge[amount_type]">
                                    <option value="1">Unit</option>
                                    <option value="2">Percent</option>
                                </select>
                            </div>
                        </div>

                    </div>

                    <button class="btn btn-primary pd-x-30 mg-r-5 mg-t-5" >Update</button>
                    @if ($charge->order_charges->count() == 0)
                        <a href="{{url('charge/delete')}}/{{$charge->id}}">Delete</a>
                    @endif
                </form>
            </div>
        </div>
        <!-- /row -->


        @endsection
    @section('scripts')

		<!-- Form-layouts js -->
		<script src="{{asset('assets/js/form-layouts.js')}}"></script>

    @endsection
