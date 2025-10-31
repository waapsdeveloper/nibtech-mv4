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
            @php
                $baseCurrencyCode = strtoupper($salesVsOrders['base_currency'] ?? '');
                $basePrefix = $baseCurrencyCode !== '' ? $baseCurrencyCode . ' ' : '';
                $breakdown = $salesVsOrders['breakdown'] ?? collect();
                if (! $breakdown instanceof \Illuminate\Support\Collection) {
                    $breakdown = collect($breakdown ?? []);
                }
            @endphp
            <div class="card mt-3">
                <div class="card-header pb-0">
                    <h4 class="card-title mg-b-0">
                        Sales Transactions vs Order Amounts
                        @if($baseCurrencyCode !== '')
                            <small class="text-muted">(Base: {{ $baseCurrencyCode }})</small>
                        @endif
                    </h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-3 text-md-nowrap">
                            <tbody>
                                <tr>
                                    <td><b>Sales Transactions</b></td>
                                    <td class="text-end">{{ $basePrefix }}{{ number_format($salesVsOrders['transaction_total'] ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td><b>Order Amount</b></td>
                                    <td class="text-end">{{ $basePrefix }}{{ number_format($salesVsOrders['order_total'] ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td><b>Difference</b></td>
                                    <td class="text-end">{{ $basePrefix }}{{ number_format($salesVsOrders['difference'] ?? 0, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    @if($breakdown->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>Currency</b></small></th>
                                        <th class="text-end"><small><b>Sales (Currency)</b></small></th>
                                        <th class="text-end"><small><b>Orders (Currency)</b></small></th>
                                        <th class="text-end"><small><b>Difference (Currency)</b></small></th>
                                        @if($baseCurrencyCode !== '')
                                            <th class="text-end"><small><b>Sales ({{ $baseCurrencyCode }})</b></small></th>
                                            <th class="text-end"><small><b>Orders ({{ $baseCurrencyCode }})</b></small></th>
                                            <th class="text-end"><small><b>Difference ({{ $baseCurrencyCode }})</b></small></th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($breakdown as $row)
                                        <tr>
                                            <td>{{ $row['currency'] }}</td>
                                            <td class="text-end">{{ number_format($row['sales_total'], 2) }}</td>
                                            <td class="text-end">{{ number_format($row['order_total'], 2) }}</td>
                                            <td class="text-end">{{ number_format($row['difference'], 2) }}</td>
                                            @if($baseCurrencyCode !== '')
                                                <td class="text-end">{{ number_format($row['sales_total_base'], 2) }}</td>
                                                <td class="text-end">{{ number_format($row['order_total_base'], 2) }}</td>
                                                <td class="text-end">{{ number_format($row['difference_base'], 2) }}</td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if(isset($orderComparisons) && $orderComparisons instanceof \Illuminate\Support\Collection && $orderComparisons->isNotEmpty())
            @php
                $baseCurrencyCode = strtoupper($salesVsOrders['base_currency'] ?? '');
                $basePrefix = $baseCurrencyCode !== '' ? $baseCurrencyCode . ' ' : '';
                $orderSalesBaseSum = $orderComparisons->sum('sales_total_base');
                $orderAmountBaseSum = $orderComparisons->sum('order_amount_base');
                $orderDifferenceBaseSum = $orderComparisons->sum('difference_base');
            @endphp
            <div class="card mt-3">
                <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                    <h4 class="card-title mg-b-0">Order-Level Comparison</h4>
                    @if($baseCurrencyCode !== '')
                        <span class="text-muted small">Base Currency: {{ $baseCurrencyCode }}</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0 text-md-nowrap align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 48px;"><span class="visually-hidden">Toggle</span></th>
                                    <th><small><b>Order Ref</b></small></th>
                                    <th class="text-center"><small><b>Currency</b></small></th>
                                    <th class="text-end"><small><b>Order Amount</b></small></th>
                                    <th class="text-center"><small><b>Sales Currency</b></small></th>
                                    <th class="text-end"><small><b>Sales Amount</b></small></th>
                                    <th class="text-end"><small><b>Difference</b></small></th>
                                    @if($baseCurrencyCode !== '')
                                        <th class="text-end"><small><b>Order ({{ $baseCurrencyCode }})</b></small></th>
                                        <th class="text-end"><small><b>Sales ({{ $baseCurrencyCode }})</b></small></th>
                                        <th class="text-end"><small><b>Difference ({{ $baseCurrencyCode }})</b></small></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($orderComparisons as $order)
                                    @php
                                        $differenceBase = $order['difference_base'] ?? 0;
                                        $differenceCurrency = $order['difference_currency'];
                                        $diffClass = abs($differenceBase) < 0.01 ? 'text-success' : ($differenceBase > 0 ? 'text-warning' : 'text-danger');
                                        $collapseId = 'order-compare-' . $order['order_id'];
                                    @endphp
                                    <tr>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}">
                                                <i class="fa fa-search"></i>
                                            </button>
                                        </td>
                                        <td>{{ $order['order_reference'] ?? $order['order_id'] }}</td>
                                        <td class="text-center">{{ $order['order_currency'] }}</td>
                                        <td class="text-end">{{ number_format($order['order_amount'] ?? 0, 2) }}</td>
                                        <td class="text-center">{{ $order['sales_currency'] ?? '—' }}</td>
                                        <td class="text-end">
                                            @if(!is_null($order['sales_total_currency']))
                                                {{ number_format($order['sales_total_currency'], 2) }}
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-end {{ $diffClass }}">
                                            @if(!is_null($differenceCurrency))
                                                {{ number_format($differenceCurrency, 2) }}
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        @if($baseCurrencyCode !== '')
                                            <td class="text-end">{{ number_format($order['order_amount_base'] ?? 0, 2) }}</td>
                                            <td class="text-end">{{ number_format($order['sales_total_base'] ?? 0, 2) }}</td>
                                            <td class="text-end {{ $diffClass }}">{{ number_format($differenceBase ?? 0, 2) }}</td>
                                        @endif
                                    </tr>
                                    <tr class="collapse" id="{{ $collapseId }}">
                                        <td colspan="{{ $baseCurrencyCode !== '' ? 10 : 7 }}" class="bg-light">
                                            <div class="mb-2">
                                                <strong>Difference Summary:</strong>
                                                <ul class="mb-2 small">
                                                    @if(!is_null($differenceCurrency))
                                                        <li>Currency Difference ({{ $order['order_currency'] ?? '—' }}): <span class="{{ $diffClass }}">{{ number_format($differenceCurrency, 2) }}</span></li>
                                                    @endif
                                                    @if($baseCurrencyCode !== '')
                                                        <li>{{ $baseCurrencyCode }} Difference: <span class="{{ $diffClass }}">{{ number_format($differenceBase, 2) }}</span></li>
                                                    @endif
                                                    <li>Order Amount vs Sales Total: {{ number_format($order['order_amount_base'] ?? 0, 2) }} → {{ number_format($order['sales_total_base'] ?? 0, 2) }}</li>
                                                </ul>
                                            </div>

                        @if(!empty($order['transactions']))
                                                <div class="table-responsive">
                                                    <table class="table table-sm mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th><small><b>ID</b></small></th>
                                                                <th><small><b>Reference</b></small></th>
                                                                <th><small><b>Description</b></small></th>
                                                                <th><small><b>Date</b></small></th>
                                                                <th class="text-center"><small><b>Currency</b></small></th>
                                                                <th class="text-end"><small><b>Amount</b></small></th>
                                                                @if($baseCurrencyCode !== '')
                                                                    <th class="text-end"><small><b>Amount ({{ $baseCurrencyCode }})</b></small></th>
                                                                    <th class="text-end"><small><b>Balance vs Order ({{ $baseCurrencyCode }})</b></small></th>
                                                                @endif
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @php
                                                                $runningBaseTotal = 0.0;
                                                            @endphp
                                                            @foreach ($order['transactions'] as $trx)
                                                                @php
                                                                    $amountBase = $trx['amount_base'] ?? 0;
                                                                    $runningBaseTotal += $amountBase;
                                                                    $balanceBase = ($order['order_amount_base'] ?? 0) - $runningBaseTotal;
                                                                @endphp
                                                                <tr>
                                                                    <td>{{ $trx['id'] }}</td>
                                                                    <td>{{ $trx['reference_id'] }}</td>
                                                                    <td>{{ $trx['description'] }}</td>
                                                                    <td>{{ $trx['date'] ?? '—' }}</td>
                                                                    <td class="text-center">{{ $trx['currency'] }}</td>
                                                                    <td class="text-end">{{ number_format($trx['amount'] ?? 0, 2) }}</td>
                                                                    @if($baseCurrencyCode !== '')
                                                                        <td class="text-end">{{ number_format($amountBase, 2) }}</td>
                                                                        <td class="text-end {{ abs($balanceBase) < 0.01 ? 'text-success' : ($balanceBase > 0 ? 'text-warning' : 'text-danger') }}">{{ number_format($balanceBase, 2) }}</td>
                                                                    @endif
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @else
                                                <p class="mb-0 text-muted">No related sales transactions.</p>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    @if($baseCurrencyCode !== '')
                                        <td colspan="7" class="text-end"><b>Base Totals</b></td>
                                        <td class="text-end"><b>{{ number_format($orderAmountBaseSum, 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($orderSalesBaseSum, 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($orderDifferenceBaseSum, 2) }}</b></td>
                                    @else
                                        <td colspan="7" class="text-center text-muted"><b>No base currency totals available</b></td>
                                    @endif
                                </tr>
                            </tfoot>
                        </table>
                    </div>
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
