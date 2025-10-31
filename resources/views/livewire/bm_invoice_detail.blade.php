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
            <div class="breadcrumb-header justify-content-between mt-0">

                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                        <li class="breadcrumb-item tx-15"><a href="{{ session('previous')}}">BM Invoice</a></li>
                        <li class="breadcrumb-item active" aria-current="page">BM Invoice Detail</li>
                    </ol>
                    @if ($process->status > 1 && $process->listed_stocks_verification->sum('qty_change') > $process->process_stocks->count())
                        <a href="{{ url('topup/recheck_closed_topup').'/'.$process->id }}" class="btn btn-link">Recheck BM Invoice</a>

                    @endif
                </div>
            </div>
        <!-- /breadcrumb -->
        <div class="d-flex justify-content-between" style="border-bottom: 1px solid rgb(216, 212, 212);">
            <h5>Reference: {{ $process->reference_id }} | Batch Quantity: {{ $process->quantity }} | Scanned Quantity: {{ $process->process_stocks->count() }}
                @if ($process->status > 1)
                    | Verified Quantity: {{ $process->process_stocks->where('status', 2)->count() }}
                @endif
                @if ($process->status == 3)
                    | Listed Quantity: {{ $process->listed_stocks_verification->sum('qty_change') }}
                @endif
            </h5>

            @if ($process->status == 1)
            <div class="p-1">
                <form class="form-inline" action="{{ url('delete_topup_imei') }}" method="POST" id="topup_item"
                 {{-- onSubmit="return confirm('Are you sure you want to remove this item?');" --}}
                 >
                    @csrf
                    <label for="imei" class="">IMEI | Serial Number: &nbsp;</label>
                    <input type="text" class="form-control form-control-sm" name="imei" @if (request('remove') == 1) id="imei" @endif placeholder="Enter IMEI" onloadeddata="$(this).focus()" autofocus required>
                    <input type="hidden" name="process_id" value="{{$process->id}}">
                    <input type="hidden" name="remove" value="1">
                    <button class="btn-sm btn-secondary pd-x-20" type="submit">Remove</button>

                </form>
            </div>
            @endif
        </div>

        <br>

        <div class="d-flex justify-content-between" style="border-bottom: 1px solid rgb(216, 212, 212);">

            <h4>BM Invoice Details</h4>


            <div class="btn-group p-1" role="group">
                {{-- JS Print to Print topup Variations DIv --}}
                <button type="button" class="btn btn-primary" onclick="PrintElem('topup_variations');">Print</button>
            </div>
        </div>
        <div class="d-flex justify-content-between">

        </div>
        <br>
        @if (session('warning'))
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <span class="alert-inner--icon"><i class="fe fe-info"></i></span>
                <span class="alert-inner--text"><strong>{{session('warning')}}</strong></span>
                <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
            </div>
            <br>
            @php
            session()->forget('warning');
            @endphp
        @endif
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

        @if(isset($report) && $report instanceof \Illuminate\Support\Collection && $report->isNotEmpty())
            @php
                $txnSum = $report->sum('transaction_total');
                $chargeSum = $report->sum('charge_total');
                $diffSum = $report->sum('difference');
            @endphp
            <div class="card">
                <div class="card-header pb-0">
                    <h4 class="card-title mg-b-0">Transaction vs Charge Summary</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                            <thead>
                                <tr>
                                    <th><small><b>Description</b></small></th>
                                    <th class="text-end"><small><b>Transactions</b></small></th>
                                    <th class="text-end"><small><b>Charges</b></small></th>
                                    <th class="text-end"><small><b>Difference</b></small></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($report as $row)
                                    <tr>
                                        <td>{{ $row['description'] }}</td>
                                        <td class="text-end">{{ number_format($row['transaction_total'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['charge_total'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['difference'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td><b>Total</b></td>
                                    <td class="text-end"><b>{{ number_format($txnSum, 2) }}</b></td>
                                    <td class="text-end"><b>{{ number_format($chargeSum, 2) }}</b></td>
                                    <td class="text-end"><b>{{ number_format($diffSum, 2) }}</b></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if(isset($salesVsOrders))
            <div class="card mt-3">
                <div class="card-header pb-0">
                    <h4 class="card-title mg-b-0">Sales Transactions vs Order Amounts</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-3 text-md-nowrap">
                            <tbody>
                                <tr>
                                    <td><b>Sales Transactions</b></td>
                                    <td class="text-end">{{ number_format($salesVsOrders['transaction_total'] ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td><b>Order Amount</b></td>
                                    <td class="text-end">{{ number_format($salesVsOrders['order_total'] ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td><b>Difference</b></td>
                                    <td class="text-end">{{ number_format($salesVsOrders['difference'] ?? 0, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    @if(isset($salesVsOrders['breakdown']) && $salesVsOrders['breakdown'] instanceof \Illuminate\Support\Collection && $salesVsOrders['breakdown']->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>Currency</b></small></th>
                                        <th class="text-end"><small><b>Sales Transactions</b></small></th>
                                        <th class="text-end"><small><b>Order Amount</b></small></th>
                                        <th class="text-end"><small><b>Difference</b></small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($salesVsOrders['breakdown'] as $row)
                                        <tr>
                                            <td>{{ $row['currency'] }}</td>
                                            <td class="text-end">{{ number_format($row['sales_total'], 2) }}</td>
                                            <td class="text-end">{{ number_format($row['order_total'], 2) }}</td>
                                            <td class="text-end">{{ number_format($row['difference'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @endif


    </div>
        <br>


    @endsection

    @section('scripts')

        <script>

            $(document).ready(function() {

                $('#sb_toggle').click();

            });
            function PrintElem(elem)
            {
                var content = document.getElementById(elem).innerHTML;
                if (!content) {
                    alert('Nothing to print!');
                    return false;
                }

                var mywindow = window.open('', 'PRINT', 'height=600,width=900');
                mywindow.document.write('<html><head>');
                mywindow.document.write(
                    `<link rel="stylesheet" href="{{ asset('assets/plugins/bootstrap/css/bootstrap.min.css') }}" type="text/css" />`
                );
                mywindow.document.write(
                    `<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}" type="text/css" />`);
                mywindow.document.write('<title>' + document.title + '</title></head><body>');
                mywindow.document.write(content);
                mywindow.document.write('</body></html>');

                mywindow.document.close();
                mywindow.focus();

                setTimeout(function() {
                    mywindow.print();
                    mywindow.close();
                }, 500);

                return true;
            }
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
