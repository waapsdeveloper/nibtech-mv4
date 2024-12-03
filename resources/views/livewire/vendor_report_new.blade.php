@extends('layouts.new')

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/DataTables/datatables.min.css') }}">
<style type="text/css" media="print">
    @page { size: landscape; }
  </style>


@endsection

    @section('content')
    <div class="card">
        <div class="card-body m-2 p-2 d-flex justify-content-between">

            <div>
                <img src="{{ asset('assets/img/brand').'/'.env('APP_LOGO') }}" alt="" height="60">
                <h4 class="mt-2"><strong>{{ env('APP_NAME') }}</strong></h4>
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
                <div class="col-6"><h6>Total Purchased:</h6></div><div class="col-6"><h6>{{ $vendor->purchase_qty }}</h6></div>
                <div class="col-6"><h6>Total Purchase Cost:</h6></div><div class="col-6"><h6>€ {{ amount_formatter($vendor->purchase_cost,2) }}</h6></div>
                <div class="col-6"><h6>Total RMA:</h6></div><div class="col-6"><h6>{{ $vendor->rma_qty }}</h6></div>
                <div class="col-6"><h6>Total RMA Cost:</h6></div><div class="col-6"><h6>€ {{ amount_formatter($vendor->rma_price,2) }}</h6></div>

            </div>

            <div class="text-center row">
                <div class="col-6"><h6>Total Items Sold:</h6></div><div class="col-6"><h6>{{ $sold_stock_count }}</h6></div>
                <div class="col-6"><h6>Total Sale Price:</h6></div><div class="col-6"><h6>€{{ amount_formatter($sold_stock_cost) }}</h6></div>
                <div class="col-6"><h6>Total Item Remaining:</h6></div><div class="col-6"><h6>{{ $available_stock_count }}</h6></div>
                <div class="col-6"><h6>Total Remaining Cost:</h6></div><div class="col-6"><h6>€{{ amount_formatter($available_stock_cost) }}</h6></div>

            </div>

            <div class="text-center row">
                <div class="col-6"><h6>Total Profit:</h6></div><div class="col-6"><h6>{{ $vendor->company }}</h6></div>
                <div class="col-6"><h6>Total Repaired:</h6></div><div class="col-6"><h6>{{ $vendor->company }}</h6></div>
                <div class="col-6"><h6>Total RMA:</h6></div><div class="col-6"><h6>{{ $vendor->company }}</h6></div>
                <div class="col-6"><h6>Total RMA Cost:</h6></div><div class="col-6"><h6>{{ $vendor->company }}</h6></div>

            </div>


        </div>
    </div>
    <div class="card">
        <div class="card-header pb-0">
            Purchse Report
        </div>
        <div class="card-body">
            <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0 text-md-nowrap" id="purchase_report">
                <thead>
                    <tr>
                        <th><small><b>No</b></small></th>
                        <th><small><b>Model</b></small></th>
                        <th><small><b>Sold + Available = Total</b></small></th>
                        <th><small><b>Cost</b></small></th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $i = 0;
                    @endphp
                    {{-- @foreach ($purchase_report as $summery)
                        <tr>
                            <td>{{ $i++ }}</td>
                            <td><a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#graded_count_modal">{{ $summery['model'] }}</a></td>
                            <td>{{ $summery['sold_stock_count'] ." + ". $summery['available_stock_count'] ." = ". $summery['sold_stock_count'] + $summery['available_stock_count'] }}</td>
                            <td>€ {{ amount_formatter($summery['stock_cost']) }}</td>
                        </tr>
                    @endforeach --}}
                </tbody>

            </table>
        </div>
    </div>

    <div class="row p-3">

        {{-- <div class="card col-6">
            <div class="card-header m-0">
                <h4 class="card-title mb-0">RMA Report</h4>

            </div>
            <div class="card-body p-2">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                        <thead>
                            <tr>
                                <th><small><b>No</b></small></th>
                                <th><small><b>Message</b></small></th>
                                <th colspan="2"><small><b>Count</b></small></th>
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

                                    $imeis = implode(" ", [implode(" ",$value->pluck('imei')->unique()->toArray()), implode(" ",$value->pluck('serial_number')->unique()->toArray())]);
                                @endphp
                                <tr class="">
                                    <td>{{ ++$i }}</td>
                                    <td>{{ $key }}</td>
                                    <td>{{ count($value) }}</td>
                                    <td>

                                        <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical  tx-18"></i></a>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" id="test{{$j}}" href="#">Open All</a>
                                            <a class="dropdown-item" id="change_entry_message_{{$j}}" href="#" onclick="var newMessage = prompt('Please enter new message'); if(newMessage != null){window.location.href = '{{ url('move_inventory/change_grade/1') }}?imei={{ $imeis }}&description='+newMessage}"
                                            >Change All</a>
                                        </div>
                                    </td>
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
            <div class="card-body p-2">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                        <thead>
                            <tr>
                                <th><small><b>No</b></small></th>
                                <th><small><b>Message</b></small></th>
                                <th colspan="2"><small><b>Count</b></small></th>
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
                                <tr class="">
                                    <td>{{ ++$i }}</td>
                                    <td>{{ $key }}</td>
                                    <td>{{ count($value) }}</td>
                                    <td>

                                        <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical  tx-18"></i></a>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" id="test{{$j}}" href="#">Open All</a>
                                        </div>
                                    </td>
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
        </div> --}}
    </div>

    @endsection

    @section('scripts')
    <script src="{{ asset('assets/DataTables/datatables.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            let start_date = "{{ request('start_date') ? request('start_date') : '' }}";
            let end_date = "{{ request('end_date') ? request('end_date') : '' }}";

            $('#purchase_report').DataTable({
                ajax: {
                    url: "{{ url('vendor_purchase_report').'/'.$vendor->id }}?start_date="+start_date+"&end_date="+end_date,
                    dataSrc: ''
                },
            });


        });
    </script>
    @endsection
