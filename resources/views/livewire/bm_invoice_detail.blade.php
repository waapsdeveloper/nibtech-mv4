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

        @if(isset($refundReport) && $refundReport instanceof \Illuminate\Support\Collection)
            @php
                $refundSummary = $refundReport->get('summary', []);
                $refundDetails = $refundReport->get('details', collect());
                $refundBaseCurrency = strtoupper($refundSummary['base_currency'] ?? '');
                $refundBasePrefix = $refundBaseCurrency !== '' ? $refundBaseCurrency . ' ' : '';
                $refundTransactionTotal = $refundSummary['transaction_total_base'] ?? 0;
                $refundOrderTotal = $refundSummary['order_total_base'] ?? 0;
                $refundDifference = $refundSummary['difference_base'] ?? 0;
                $refundMatchedCount = $refundSummary['matched_count'] ?? 0;
                $refundMissingCount = $refundSummary['missing_order_count'] ?? 0;
                $refundTotal = $refundSummary['total'] ?? $refundDetails->count();
            @endphp
            @if($refundDetails instanceof \Illuminate\Support\Collection && $refundDetails->isNotEmpty())
                <div class="card mt-3">
                    <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                        <h4 class="card-title mg-b-0">Refund Validation</h4>
                        <span class="small text-muted">
                            Matched {{ $refundMatchedCount }} of {{ $refundTotal }} refunds | Missing {{ $refundMissingCount }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <div class="border rounded p-2">
                                    <small class="text-muted d-block">Ledger Refund Total</small>
                                    <span class="fw-semibold">{{ $refundBasePrefix }}{{ number_format($refundTransactionTotal, 2) }}</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-2">
                                    <small class="text-muted d-block">System Refund Orders</small>
                                    <span class="fw-semibold">{{ $refundBasePrefix }}{{ number_format($refundOrderTotal, 2) }}</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-2">
                                    <small class="text-muted d-block">Variance (Ledger - System)</small>
                                    @php
                                        $refundVarianceClass = abs($refundDifference) < 0.01 ? 'text-success' : ($refundDifference < 0 ? 'text-warning' : 'text-danger');
                                    @endphp
                                    <span class="fw-semibold {{ $refundVarianceClass }}">{{ $refundBasePrefix }}{{ number_format($refundDifference, 2) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0 text-md-nowrap align-middle">
                                <thead>
                                    <tr>
                                        <th><small><b>Txn ID</b></small></th>
                                        <th><small><b>Txn Ref</b></small></th>
                                        <th class="text-center"><small><b>Currency</b></small></th>
                                        <th class="text-end"><small><b>Refund Amount</b></small></th>
                                        @if($refundBaseCurrency !== '')
                                            <th class="text-end"><small><b>Refund ({{ $refundBaseCurrency }})</b></small></th>
                                        @endif
                                        <th class="text-center"><small><b>Order Match</b></small></th>
                                        <th><small><b>Order Ref</b></small></th>
                                        <th class="text-end"><small><b>Order Amount</b></small></th>
                                        @if($refundBaseCurrency !== '')
                                            <th class="text-end"><small><b>Order ({{ $refundBaseCurrency }})</b></small></th>
                                            <th class="text-end"><small><b>Variance ({{ $refundBaseCurrency }})</b></small></th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($refundDetails as $refund)
                                        @php
                                            $orderMatchLabel = $refund['order_found']
                                                ? ($refund['match_source'] === 'reference' ? 'Reference' : 'Order ID')
                                                : 'Missing';
                                            $orderMatchClass = $refund['order_found'] ? 'text-success' : 'text-danger';
                                            $varianceBase = $refund['difference_base'] ?? 0;
                                            $varianceClass = abs($varianceBase) < 0.01 ? 'text-success' : ($varianceBase < 0 ? 'text-warning' : 'text-danger');
                                        @endphp
                                        <tr>
                                            <td>{{ $refund['transaction_id'] }}</td>
                                            <td>{{ $refund['transaction_reference'] ?? '—' }}</td>
                                            <td class="text-center">{{ $refund['transaction_currency'] ?? '—' }}</td>
                                            <td class="text-end">{{ number_format($refund['transaction_amount'] ?? 0, 2) }}</td>
                                            @if($refundBaseCurrency !== '')
                                                <td class="text-end">{{ number_format($refund['transaction_amount_base'] ?? 0, 2) }}</td>
                                            @endif
                                            <td class="text-center {{ $orderMatchClass }}">{{ $orderMatchLabel }}</td>
                                            <td>{{ $refund['order_reference'] ?? '—' }}</td>
                                            <td class="text-end">
                                                @if($refund['order_found'])
                                                    {{ number_format($refund['order_amount'] ?? 0, 2) }}
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            @if($refundBaseCurrency !== '')
                                                <td class="text-end">
                                                    @if($refund['order_found'])
                                                        {{ number_format($refund['order_amount_base'] ?? 0, 2) }}
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td class="text-end {{ $varianceClass }}">{{ number_format($varianceBase, 2) }}</td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
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
                $salesRow = $reportSalesRow ?? null;
                $txnSum = $report->sum('transaction_total');
                $chargeSum = $report->sum('charge_total');
                $diffSum = $report->sum('difference');
            @endphp
            <div class="card">
                <div class="card-header pb-0">
                    <h4 class="card-title mg-b-0">Transaction vs Charge Summary</h4>
                </div>
                <div class="card-body">
                    @if($salesRow)
                        <div class="alert alert-info py-2 small">
                            <strong>Sales (GBP)</strong>: Ledger {{ number_format($salesRow['transaction_total'], 2) }} vs BM Invoice {{ number_format(abs($salesRow['charge_total']), 2) }} — variance {{ number_format($salesRow['difference'], 2) }}. Detailed sales vs invoice totals are shown in the section below.
                        </div>
                    @endif
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
                    @if($salesRow)
                        <p class="text-muted small mb-0">Sales transactions are excluded from the table above to prevent duplicate totals; see the Sales vs Invoice section for that comparison.</p>
                    @endif
                </div>
            </div>
        @endif

        @if(isset($salesVsOrders))
            @php
                $primaryCurrency = $salesVsOrders['primary_currency'] ?? null;
                $breakdown = $salesVsOrders['breakdown'] ?? collect();
                if (! $breakdown instanceof \Illuminate\Support\Collection) {
                    $breakdown = collect($breakdown ?? []);
                }
            @endphp
            <div class="card mt-3">
                <div class="card-header pb-0">
                    <h4 class="card-title mg-b-0">
                        BM Invoice vs Recorded Sales
                        @if($primaryCurrency)
                            <small class="text-muted">(Single Currency: {{ $primaryCurrency }})</small>
                        @else
                            <small class="text-muted">(Multi-currency view)</small>
                        @endif
                    </h4>
                </div>
                <div class="card-body">
                    @if($primaryCurrency)
                        @php
                            $primaryVariance = $salesVsOrders['difference'] ?? 0;
                            $primaryClass = abs($primaryVariance) < 0.01 ? 'text-success' : ($primaryVariance < 0 ? 'text-warning' : 'text-danger');
                        @endphp
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-3 text-md-nowrap">
                                <tbody>
                                    <tr>
                                        <td><b>Recorded Sales Total ({{ $primaryCurrency }})</b></td>
                                        <td class="text-end">{{ number_format($salesVsOrders['transaction_total'] ?? 0, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td><b>BM Invoice Total ({{ $primaryCurrency }})</b></td>
                                        <td class="text-end">{{ number_format($salesVsOrders['order_total'] ?? 0, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td><b>Variance (Sales - Invoice)</b></td>
                                        <td class="text-end {{ $primaryClass }}">{{ number_format($primaryVariance, 2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="small text-muted">Multiple currencies detected. Totals are detailed per currency below.</p>
                    @endif

                    @if($breakdown->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>Currency</b></small></th>
                                        <th class="text-end"><small><b>Recorded Sales</b></small></th>
                                        <th class="text-end"><small><b>BM Invoice</b></small></th>
                                        <th class="text-end"><small><b>Variance</b></small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($breakdown as $row)
                                        @php
                                            $varianceClass = abs($row['difference']) < 0.01 ? 'text-success' : ($row['difference'] < 0 ? 'text-warning' : 'text-danger');
                                        @endphp
                                        <tr>
                                            <td>{{ $row['currency'] }}</td>
                                            <td class="text-end">{{ number_format($row['sales_total'], 2) }}</td>
                                            <td class="text-end">{{ number_format($row['order_total'], 2) }}</td>
                                            <td class="text-end {{ $varianceClass }}">{{ number_format($row['difference'], 2) }}</td>
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
                $groupedOrderComparisons = $orderComparisons->groupBy(function ($row) {
                    return $row['order_currency'] ?? '—';
                });
                $hasMultipleOrderCurrencies = $groupedOrderComparisons->count() > 1;
            @endphp
            <div class="card mt-3">
                <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                    <h4 class="card-title mg-b-0">Order-Level Variance (BM Invoice vs Recorded Sales)</h4>
                    @if($hasMultipleOrderCurrencies)
                        <span class="text-muted small">Showing {{ $groupedOrderComparisons->count() }} currencies</span>
                    @endif
                </div>
                <div class="card-body">
                    @if(!$hasMultipleOrderCurrencies)
                        @php
                            $singleCurrencyKey = $groupedOrderComparisons->keys()->first();
                            $singleCurrencyOrders = $groupedOrderComparisons->first();
                            $singleCurrencyOrderTotal = $singleCurrencyOrders->sum('order_amount');
                            $singleCurrencySalesTotal = $singleCurrencyOrders->sum(function ($row) {
                                return is_numeric($row['sales_total_currency']) ? $row['sales_total_currency'] : 0;
                            });
                            $singleCurrencyVarianceTotal = $singleCurrencyOrders->sum(function ($row) {
                                return is_numeric($row['difference_currency']) ? $row['difference_currency'] : 0;
                            });
                            $singleCurrencySalesCurrencies = $singleCurrencyOrders->pluck('sales_currency')->filter()->unique();
                            $singleCurrencySalesCurrencyLabel = $singleCurrencySalesCurrencies->count() === 1
                                ? $singleCurrencySalesCurrencies->first()
                                : ($singleCurrencySalesCurrencies->isEmpty() ? '—' : 'Mixed');
                            $singleCurrencyHasSalesTotals = $singleCurrencyOrders->contains(function ($row) {
                                return is_numeric($row['sales_total_currency']);
                            });
                            $singleCurrencyHasVarianceTotals = $singleCurrencyOrders->contains(function ($row) {
                                return is_numeric($row['difference_currency']);
                            });
                        @endphp
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0 text-md-nowrap align-middle">
                                <thead>
                                    <tr>
                                        <th style="width: 48px;"><span class="visually-hidden">Toggle</span></th>
                                        <th><small><b>Order Ref</b></small></th>
                                        <th class="text-center"><small><b>Currency</b></small></th>
                                        <th class="text-end"><small><b>BM Invoice Amount</b></small></th>
                                        <th class="text-center"><small><b>Recorded Sales Currency</b></small></th>
                                        <th class="text-end"><small><b>Recorded Sales Amount</b></small></th>
                                        <th class="text-end"><small><b>Variance (Sales - Invoice)</b></small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="table-secondary">
                                        <td class="text-center">—</td>
                                        <td><b>Totals</b></td>
                                        <td class="text-center">{{ $singleCurrencyKey }}</td>
                                        <td class="text-end"><b>{{ number_format($singleCurrencyOrderTotal, 2) }}</b></td>
                                        <td class="text-center">{{ $singleCurrencySalesCurrencyLabel }}</td>
                                        <td class="text-end">
                                            <b>
                                                @if($singleCurrencyHasSalesTotals)
                                                    {{ number_format($singleCurrencySalesTotal, 2) }}
                                                @else
                                                    —
                                                @endif
                                            </b>
                                        </td>
                                        <td class="text-end">
                                            <b>
                                                @if($singleCurrencyHasVarianceTotals)
                                                    {{ number_format($singleCurrencyVarianceTotal, 2) }}
                                                @else
                                                    —
                                                @endif
                                            </b>
                                        </td>
                                    </tr>
                                    @foreach ($singleCurrencyOrders as $order)
                                        @php
                                            $difference = $order['difference_currency'];
                                            $diffClass = is_numeric($difference)
                                                ? (abs($difference) < 0.01 ? 'text-success' : ($difference > 0 ? 'text-warning' : 'text-danger'))
                                                : 'text-muted';
                                            $collapseId = 'order-compare-' . $order['order_id'];
                                        @endphp
                                        <tr>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}">
                                                    <i class="fa fa-search"></i>
                                                </button>
                                            </td>
                                            <td>{{ $order['order_reference'] ?? $order['order_id'] }}</td>
                                            <td class="text-center">{{ $order['order_currency'] ?? '—' }}</td>
                                            <td class="text-end">{{ number_format($order['order_amount'] ?? 0, 2) }}</td>
                                            <td class="text-center">{{ $order['sales_currency'] ?? '—' }}</td>
                                            <td class="text-end">
                                                @if(is_numeric($order['sales_total_currency']))
                                                    {{ number_format($order['sales_total_currency'], 2) }}
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-end {{ $diffClass }}">
                                                @if(is_numeric($difference))
                                                    {{ number_format($difference, 2) }}
                                                @else
                                                    <span class="text-muted">Variance unavailable</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr class="collapse" id="{{ $collapseId }}">
                                            <td colspan="7" class="bg-light">
                                                <div class="mb-2">
                                                    <strong>Variance Summary:</strong>
                                                    <ul class="mb-2 small">
                                                        <li>Invoice Amount: {{ number_format($order['order_amount'] ?? 0, 2) }} {{ $order['order_currency'] ?? '—' }}</li>
                                                        <li>Recorded Sales Currency: {{ $order['sales_currency'] ?? '—' }}</li>
                                                        <li>
                                                            Recorded Sales Amount:
                                                            @if(is_numeric($order['sales_total_currency']))
                                                                {{ number_format($order['sales_total_currency'], 2) }}
                                                            @else
                                                                <span class="text-muted">Mixed or unavailable</span>
                                                            @endif
                                                        </li>
                                                        @if(is_numeric($difference))
                                                            <li>Variance (Sales - Invoice): <span class="{{ $diffClass }}">{{ number_format($difference, 2) }}</span></li>
                                                        @else
                                                            <li class="text-muted">Variance shown only when currencies align.</li>
                                                        @endif
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
                                                                    <th class="text-end"><small><b>Recorded Sales Amount</b></small></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($order['transactions'] as $trx)
                                                                    <tr>
                                                                        <td>{{ $trx['id'] }}</td>
                                                                        <td>{{ $trx['reference_id'] }}</td>
                                                                        <td>{{ $trx['description'] }}</td>
                                                                        <td>{{ $trx['date'] ?? '—' }}</td>
                                                                        <td class="text-center">{{ $trx['currency'] }}</td>
                                                                        <td class="text-end">{{ number_format($trx['amount'] ?? 0, 2) }}</td>
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
                            </table>
                        </div>
                    @else
                        @foreach ($groupedOrderComparisons as $currencyKey => $ordersInCurrency)
                            @php
                                $orderCurrencyLabel = $currencyKey;
                                $orderCurrencyTotal = $ordersInCurrency->sum('order_amount');
                                $salesCurrencyTotal = $ordersInCurrency->sum(function ($row) {
                                    return is_numeric($row['sales_total_currency']) ? $row['sales_total_currency'] : 0;
                                });
                                $varianceCurrencyTotal = $ordersInCurrency->sum(function ($row) {
                                    return is_numeric($row['difference_currency']) ? $row['difference_currency'] : 0;
                                });
                            @endphp
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="mb-0">Currency: {{ $orderCurrencyLabel }}</h5>
                                    <span class="text-muted small">Orders: {{ $ordersInCurrency->count() }}</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover mb-0 text-md-nowrap align-middle">
                                        <thead>
                                            <tr>
                                                <th style="width: 48px;"><span class="visually-hidden">Toggle</span></th>
                                                <th><small><b>Order Ref</b></small></th>
                                                <th class="text-center"><small><b>Currency</b></small></th>
                                                <th class="text-end"><small><b>BM Invoice Amount</b></small></th>
                                                <th class="text-center"><small><b>Recorded Sales Currency</b></small></th>
                                                <th class="text-end"><small><b>Recorded Sales Amount</b></small></th>
                                                <th class="text-end"><small><b>Variance (Sales - Invoice)</b></small></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="table-secondary">
                                                <td class="text-center">—</td>
                                                <td><b>Totals</b></td>
                                                <td class="text-center">{{ $orderCurrencyLabel }}</td>
                                                <td class="text-end"><b>{{ number_format($orderCurrencyTotal, 2) }}</b></td>
                                                @php
                                                    $currencySalesCodes = $ordersInCurrency->pluck('sales_currency')->filter()->unique();
                                                    $currencySalesLabel = $currencySalesCodes->count() === 1 ? $currencySalesCodes->first() : ($currencySalesCodes->isEmpty() ? '—' : 'Mixed');
                                                    $currencyHasSalesTotals = $ordersInCurrency->contains(function ($row) {
                                                        return is_numeric($row['sales_total_currency']);
                                                    });
                                                    $currencyHasVarianceTotals = $ordersInCurrency->contains(function ($row) {
                                                        return is_numeric($row['difference_currency']);
                                                    });
                                                @endphp
                                                <td class="text-center">{{ $currencySalesLabel }}</td>
                                                <td class="text-end">
                                                    <b>
                                                        @if($currencyHasSalesTotals)
                                                            {{ number_format($salesCurrencyTotal, 2) }}
                                                        @else
                                                            —
                                                        @endif
                                                    </b>
                                                </td>
                                                <td class="text-end">
                                                    <b>
                                                        @if($currencyHasVarianceTotals)
                                                            {{ number_format($varianceCurrencyTotal, 2) }}
                                                        @else
                                                            —
                                                        @endif
                                                    </b>
                                                </td>
                                            </tr>
                                            @foreach ($ordersInCurrency as $order)
                                                @php
                                                    $difference = $order['difference_currency'];
                                                    $diffClass = is_numeric($difference)
                                                        ? (abs($difference) < 0.01 ? 'text-success' : ($difference > 0 ? 'text-warning' : 'text-danger'))
                                                        : 'text-muted';
                                                    $collapseId = 'order-compare-' . $order['order_id'];
                                                @endphp
                                                <tr>
                                                    <td class="text-center">
                                                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}">
                                                            <i class="fa fa-search"></i>
                                                        </button>
                                                    </td>
                                                    <td>{{ $order['order_reference'] ?? $order['order_id'] }}</td>
                                                    <td class="text-center">{{ $order['order_currency'] ?? '—' }}</td>
                                                    <td class="text-end">{{ number_format($order['order_amount'] ?? 0, 2) }}</td>
                                                    <td class="text-center">{{ $order['sales_currency'] ?? '—' }}</td>
                                                    <td class="text-end">
                                                        @if(is_numeric($order['sales_total_currency']))
                                                            {{ number_format($order['sales_total_currency'], 2) }}
                                                        @else
                                                            <span class="text-muted">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-end {{ $diffClass }}">
                                                        @if(is_numeric($difference))
                                                            {{ number_format($difference, 2) }}
                                                        @else
                                                            <span class="text-muted">Variance unavailable</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                                <tr class="collapse" id="{{ $collapseId }}">
                                                    <td colspan="7" class="bg-light">
                                                        <div class="mb-2">
                                                            <strong>Variance Summary:</strong>
                                                            <ul class="mb-2 small">
                                                                <li>Invoice Amount: {{ number_format($order['order_amount'] ?? 0, 2) }} {{ $order['order_currency'] ?? '—' }}</li>
                                                                <li>Recorded Sales Currency: {{ $order['sales_currency'] ?? '—' }}</li>
                                                                <li>
                                                                    Recorded Sales Amount:
                                                                    @if(is_numeric($order['sales_total_currency']))
                                                                        {{ number_format($order['sales_total_currency'], 2) }}
                                                                    @else
                                                                        <span class="text-muted">Mixed or unavailable</span>
                                                                    @endif
                                                                </li>
                                                                @if(is_numeric($difference))
                                                                    <li>Variance (Sales - Invoice): <span class="{{ $diffClass }}">{{ number_format($difference, 2) }}</span></li>
                                                                @else
                                                                    <li class="text-muted">Variance shown only when currencies align.</li>
                                                                @endif
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
                                                                            <th class="text-end"><small><b>Recorded Sales Amount</b></small></th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @foreach ($order['transactions'] as $trx)
                                                                            <tr>
                                                                                <td>{{ $trx['id'] }}</td>
                                                                                <td>{{ $trx['reference_id'] }}</td>
                                                                                <td>{{ $trx['description'] }}</td>
                                                                                <td>{{ $trx['date'] ?? '—' }}</td>
                                                                                <td class="text-center">{{ $trx['currency'] }}</td>
                                                                                <td class="text-end">{{ number_format($trx['amount'] ?? 0, 2) }}</td>
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
                                    </table>
                                </div>
                            </div>
                        @endforeach
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
