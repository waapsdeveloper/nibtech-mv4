@extends('layouts.new')

@section('styles')
<style type="text/css" media="print">
    @page { size: landscape; }
  </style>


@endsection

    @section('content')
    <div class="card">
        <div class="card-body m-2 p-2 d-flex justify-content-between">

            <div>
                <img src="{{ asset('assets/img/brand/logo1.png') }}" alt="" height="60">
                <br>
                <br>
                <h4><strong>(NI) Britain Tech Ltd</strong></h4>
                {{-- <h4>Cromac Square,</h4>
                <h4>Forsyth House,</h4>
                <h4>Belfast, BT2 8LA</h4> --}}

            </div>

            <div>
                <h2 style=" ">Vendor Report</h2>

                <div class="text-center">
                    <h5 style="line-height: 10px"><strong>Vendor: </strong>{{ $vendor->company }}</h5>
                    <h5 style="line-height: 10px"><strong>From: </strong>{{ \Carbon\Carbon::parse(request('start_date'))->format('d-m-Y') }}</h5>
                    <h5 style="line-height: 10px"><strong>Till: </strong>{{ \Carbon\Carbon::parse(request('end_date'))->format('d-m-Y') }}</h5>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header m-0">
            <h4 class="card-title mb-0">Vendor Stats</h4>

        </div>
        <div class="card-body m-2 p-2 d-flex justify-content-between">

            <div class="text-center row">
                <div class="col-6">Total Items Purchased:</div><div class="col-6">{{ $vendor->company }}</div>
                <div class="col-6">Total Purchase Cost:</div><div class="col-6">{{ $vendor->company }}</div>
                <div class="col-6">Total RMA:</div><div class="col-6">{{ $vendor->company }}</div>
                <div class="col-6">Total RMA Cost:</div><div class="col-6">{{ $vendor->company }}</div>

            </div>

            <div class="text-center row">
                <div class="col-6">Total Items Sold:</div><div class="col-6">{{ $vendor->company }}</div>
                <div class="col-6">Total Sale Price:</div><div class="col-6">{{ $vendor->company }}</div>
                <div class="col-6">Total Item Remaining:</div><div class="col-6">{{ $vendor->company }}</div>
                <div class="col-6">Total Remaining Cost:</div><div class="col-6">{{ $vendor->company }}</div>

            </div>

            <div class="text-center row">
                <div class="col-6">Total Profit:</div><div class="col-6">{{ $vendor->company }}</div>
                <div class="col-6">Total Repaired:</div><div class="col-6">{{ $vendor->company }}</div>
                <div class="col-6">Total RMA:</div><div class="col-6">{{ $vendor->company }}</div>
                <div class="col-6">Total RMA Cost:</div><div class="col-6">{{ $vendor->company }}</div>

            </div>


        </div>
    </div>

    @endsection

    @section('scripts')

    @endsection
