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
                <h4 class="mt-2"><strong>(NI) Britain Tech Ltd</strong></h4>
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
                <div class="col-6"><h6>Total Items Purchased:</h6></div><div class="col-6"><h6>{{ $vendor->company }}</h6></div>
                <div class="col-6"><h6>Total Purchase Cost:</h6></div><div class="col-6"><h6>{{ $vendor->company }}</h6></div>
                <div class="col-6"><h6>Total RMA:</h6></div><div class="col-6"><h6>{{ $vendor->company }}</h6></div>
                <div class="col-6"><h6>Total RMA Cost:</h6></div><div class="col-6"><h6>{{ $vendor->company }}</h6></div>

            </div>

            <div class="text-center row">
                <div class="col-6"><h6>Total Items Sold:</h6></div><div class="col-6"><h6>{{ $vendor->company }}</h6></div>
                <div class="col-6"><h6>Total Sale Price:</h6></div><div class="col-6"><h6>{{ $vendor->company }}</h6></div>
                <div class="col-6"><h6>Total Item Remaining:</h6></div><div class="col-6"><h6>{{ $vendor->company }}</h6></div>
                <div class="col-6"><h6>Total Remaining Cost:</h6></div><div class="col-6"><h6>{{ $vendor->company }}</h6></div>

            </div>

            <div class="text-center row">
                <div class="col-6"><h6>Total Profit:</h6></div><div class="col-6"><h6>{{ $vendor->company }}</h6></div>
                <div class="col-6"><h6>Total Repaired:</h6></div><div class="col-6"><h6>{{ $vendor->company }}</h6></div>
                <div class="col-6"><h6>Total RMA:</h6></div><div class="col-6"><h6>{{ $vendor->company }}</h6></div>
                <div class="col-6"><h6>Total RMA Cost:</h6></div><div class="col-6"><h6>{{ $vendor->company }}</h6></div>

            </div>


        </div>
    </div>
    <div class="row p-3">

        <div class="card col-6">
            <div class="card-header m-0">
                <h4 class="card-title mb-0">RMA Report</h4>

            </div>
            <div class="card-body m-2 p-2">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                        <thead>
                            <tr>
                                <th><small><b>No</b></small></th>
                                <th><small><b>Message</b></small></th>
                                <th><small><b>Count</b></small></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $i = 0;
                                $j = 0;
                            @endphp
                            @foreach ($rma_report as $key => $value)
                                @php
                                    $j++;
                                @endphp
                                <tr class="">
                                    <td>{{ ++$i }}</td>
                                    <td>{{ $key }}</td>
                                    <td><a id="test{{$j}}" href="#">{{ count($value) }}</a></td>
                                    <td></td>
                                    <script type="text/javascript">


                                        document.getElementById("test{{$j}}").onclick = function(){
                                            @php
                                                foreach ($value as $val) {
                                                    echo "window.open('".url("imei")."?imei=".$val->imei.$val->serial_number."','_blank');
                                                    ";
                                                }

                                            @endphp
                                        }
                                    </script>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <br>
                </div>



            </div>
        </div>
        <div class="card col-6">
            <div class="card-header m-0">
                <h4 class="card-title mb-0">Repair Report</h4>

            </div>
            <div class="card-body m-2 p-2">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                        <thead>
                            <tr>
                                <th><small><b>No</b></small></th>
                                <th><small><b>Message</b></small></th>
                                <th><small><b>Count</b></small></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $i = 0;
                                $k = 10000;
                            @endphp
                            @foreach ($repair_report as $key => $value)
                                @php
                                    $k++;
                                @endphp
                                <tr class="bg-light tx-center">
                                    <td>{{ ++$i }}</td>
                                    <td>{{ $key }}</td>
                                    <td><a id="test{{$k}}" href="#">{{ count($value) }}</a></td>
                                    <td></td>
                                    <script type="text/javascript">


                                        document.getElementById("test{{$k}}").onclick = function(){
                                            @php
                                                foreach ($value as $val) {
                                                    echo "window.open('".url("imei")."?imei=".$val->imei.$val->serial_number."','_blank');
                                                    ";
                                                }

                                            @endphp
                                        }
                                    </script>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <br>
                </div>


            </div>
        </div>
    </div>

    @endsection

    @section('scripts')

    @endsection
