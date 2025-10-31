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
            .report-section {
                margin-bottom: 1.5rem;
            }
            .section-divider {
                border-top: 2px solid #e9ecef;
                margin: 2rem 0;
            }
            .summary-card {
                background: #f8f9fa;
                border-left: 4px solid #0162e8;
                padding: 1rem;
                margin-bottom: 1rem;
            }
            .variance-positive {
                color: #28a745;
                font-weight: 600;
            }
            .variance-negative {
                color: #dc3545;
                font-weight: 600;
            }
            .variance-neutral {
                color: #6c757d;
            }
            @media print {
                .no-print {
                    display: none !important;
                }
                .card {
                    break-inside: avoid;
                }
            }
        </style>
    @endsection
    @section('content')


        <!-- breadcrumb -->
            <div class="breadcrumb-header justify-content-between mt-0 no-print">
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

        <!-- Process Header -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-2 text-primary">BM Invoice: {{ $process->reference_id }}</h4>
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <small class="text-muted d-block">Batch Quantity</small>
                                <strong>{{ $process->quantity }}</strong>
                            </div>
                            <div class="col-md-3 mb-2">
                                <small class="text-muted d-block">Scanned Quantity</small>
                                <strong>{{ $process->process_stocks->count() }}</strong>
                            </div>
                            @if ($process->status > 1)
                            <div class="col-md-3 mb-2">
                                <small class="text-muted d-block">Verified Quantity</small>
                                <strong>{{ $process->process_stocks->where('status', 2)->count() }}</strong>
                            </div>
                            @endif
                            @if ($process->status == 3)
                            <div class="col-md-3 mb-2">
                                <small class="text-muted d-block">Listed Quantity</small>
                                <strong>{{ $process->listed_stocks_verification->sum('qty_change') }}</strong>
                            </div>
                            @endif
                        </div>
                    </div>

                    @if ($process->status == 1)
                    <div class="col-md-4">
                        <div class="border rounded p-3 bg-light">
                            <form class="form-inline" action="{{ url('delete_topup_imei') }}" method="POST" id="topup_item">
                                @csrf
                                <label for="imei" class="mb-2"><strong>Remove Item</strong></label>
                                <div class="input-group">
                                    <input type="text" class="form-control form-control-sm" name="imei" @if (request('remove') == 1) id="imei" @endif placeholder="Enter IMEI / Serial" autofocus required>
                                    <input type="hidden" name="process_id" value="{{$process->id}}">
                                    <input type="hidden" name="remove" value="1">
                                    <button class="btn btn-sm btn-danger" type="submit">Remove</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Report Actions -->
        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <h4 class="mb-0">Invoice Financial Report</h4>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-primary" onclick="PrintElem('invoice_report_content');">
                    <i class="fe fe-printer"></i> Print Report
                </button>
            </div>
        </div>
        <!-- Alert Messages -->
        @if (session('warning'))
            <div class="alert alert-warning alert-dismissible fade show no-print" role="alert">
                <span class="alert-inner--icon"><i class="fe fe-info"></i></span>
                <span class="alert-inner--text"><strong>{{session('warning')}}</strong></span>
                <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
            </div>
            @php session()->forget('warning'); @endphp
        @endif

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
                <span class="alert-inner--icon"><i class="fe fe-thumbs-up"></i></span>
                <span class="alert-inner--text"><strong>{{session('success')}}</strong></span>
                <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
            </div>
            @php session()->forget('success'); @endphp
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
                <span class="alert-inner--icon"><i class="fe fe-thumbs-down"></i></span>
                <span class="alert-inner--text"><strong>{{session('error')}}</strong></span>
                <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
            </div>
            <script>alert("{{session('error')}}");</script>
            @php session()->forget('error'); @endphp
        @endif

        <!-- Main Report Content -->
        <div id="invoice_report_content">

            <!-- Report Header (Print Only) -->
            <div class="d-none d-print-block mb-4">
                <h2>BM Invoice Financial Report</h2>
                <p><strong>Reference:</strong> {{ $process->reference_id }}</p>
                <p><strong>Generated:</strong> {{ now()->format('d M Y H:i') }}</p>
            </div>

                    <!-- Section 4: Refund Validation -->
            @if(isset($refundReport) && $refundReport instanceof \Illuminate\Support\Collection)
                @php
                    $refundSummary = $refundReport->get('summary', []);
                    $refundDetails = $refundReport->get('details', collect());
                    $totalsByCurrency = $refundSummary['totals_by_currency'] ?? collect();
                    $isSingleCurrency = $refundSummary['is_single_currency'] ?? true;
                    $refundMatchedCount = $refundSummary['matched_count'] ?? 0;
                    $refundMissingCount = $refundSummary['missing_order_count'] ?? 0;
                    $refundTotal = $refundSummary['total'] ?? 0;
                @endphp
                @if($refundDetails instanceof \Illuminate\Support\Collection && $refundDetails->isNotEmpty())
                    <div class="report-section">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="fe fe-rotate-ccw"></i> Refund Validation Report
                                    </h5>
                                    <span class="badge bg-dark">
                                        {{ $refundMatchedCount }}/{{ $refundTotal }} Matched
                                        @if($refundMissingCount > 0)
                                            | <span class="text-danger">{{ $refundMissingCount }} Missing</span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                @if($isSingleCurrency && $totalsByCurrency->isNotEmpty())
                                    @php
                                        $primaryRefund = $totalsByCurrency->first();
                                        $primaryCurr = $primaryRefund['currency'] ?? '';
                                        $refundVariance = $primaryRefund['difference'] ?? 0;
                                        $refundVarianceClass = abs($refundVariance) < 0.01 ? 'variance-neutral' : ($refundVariance < 0 ? 'variance-negative' : 'variance-positive');
                                    @endphp
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-4">
                                            <div class="summary-card">
                                                <small class="text-muted d-block mb-1">Ledger Refund Total</small>
                                                <h4 class="mb-0">{{ $primaryCurr }} {{ number_format($primaryRefund['transaction_total'] ?? 0, 2) }}</h4>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="summary-card">
                                                <small class="text-muted d-block mb-1">System Refund Orders</small>
                                                <h4 class="mb-0">{{ $primaryCurr }} {{ number_format($primaryRefund['order_total'] ?? 0, 2) }}</h4>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="summary-card">
                                                <small class="text-muted d-block mb-1">Variance</small>
                                                <h4 class="mb-0 {{ $refundVarianceClass }}">{{ $primaryCurr }} {{ number_format($refundVariance, 2) }}</h4>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="alert alert-info mb-3">
                                        <i class="fe fe-info"></i> Multiple refund currencies detected.
                                    </div>
                                    @if($totalsByCurrency->isNotEmpty())
                                        <div class="table-responsive mb-3">
                                            <table class="table table-bordered table-sm">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Currency</th>
                                                        <th class="text-end">Ledger Total</th>
                                                        <th class="text-end">System Total</th>
                                                        <th class="text-end">Variance</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($totalsByCurrency as $currTotal)
                                                        @php
                                                            $currVar = $currTotal['difference'] ?? 0;
                                                            $currVarClass = abs($currVar) < 0.01 ? 'variance-neutral' : ($currVar < 0 ? 'variance-negative' : 'variance-positive');
                                                        @endphp
                                                        <tr>
                                                            <td><strong>{{ $currTotal['currency'] ?? '—' }}</strong></td>
                                                            <td class="text-end">{{ number_format($currTotal['transaction_total'] ?? 0, 2) }}</td>
                                                            <td class="text-end">{{ number_format($currTotal['order_total'] ?? 0, 2) }}</td>
                                                            <td class="text-end {{ $currVarClass }}">{{ number_format($currVar, 2) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                @endif

                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Txn ID</th>
                                                <th>Txn Ref</th>
                                                <th class="text-center">Currency</th>
                                                <th class="text-end">Amount</th>
                                                <th class="text-center">Order Match</th>
                                                <th>Order Ref</th>
                                                <th class="text-end">Order Amount</th>
                                                <th class="text-end">Variance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($refundDetails as $refund)
                                                @php
                                                    $orderMatchLabel = $refund['order_found']
                                                        ? ($refund['match_source'] === 'reference' ? 'By Ref' : 'By ID')
                                                        : 'Missing';
                                                    $orderMatchClass = $refund['order_found'] ? 'text-success' : 'text-danger';
                                                    $variance = $refund['difference'] ?? 0;
                                                    $varianceClass = abs($variance) < 0.01 ? 'variance-neutral' : ($variance < 0 ? 'variance-negative' : 'variance-positive');
                                                @endphp
                                                <tr>
                                                    <td>{{ $refund['transaction_id'] }}</td>
                                                    <td>{{ $refund['transaction_reference'] ?? '—' }}</td>
                                                    <td class="text-center">{{ $refund['transaction_currency'] ?? '—' }}</td>
                                                    <td class="text-end">{{ number_format($refund['transaction_amount'] ?? 0, 2) }}</td>
                                                    <td class="text-center">
                                                        <span class="badge {{ $refund['order_found'] ? 'bg-success' : 'bg-danger' }}">{{ $orderMatchLabel }}</span>
                                                    </td>
                                                    <td>{{ $refund['order_reference'] ?? '—' }}</td>
                                                    <td class="text-end">
                                                        @if($refund['order_found'])
                                                            {{ number_format($refund['order_amount'] ?? 0, 2) }}
                                                        @else
                                                            <span class="text-muted">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-end {{ $varianceClass }}">{{ number_format($variance, 2) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endif

        </div><!-- End invoice_report_content -->
    </div>

    <br>

            <!-- Section 2: Transaction vs Charge Summary -->
            @if(isset($report) && $report instanceof \Illuminate\Support\Collection && $report->isNotEmpty())
                @php
                    $salesRow = $reportSalesRow ?? null;
                    $txnSum = $report->sum('transaction_total');
                    $chargeSum = $report->sum('charge_total');
                    $diffSum = $report->sum('difference');
                @endphp
                <div class="report-section">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0 text-white">
                                <i class="fe fe-file-text"></i> Transaction vs Charge Summary
                            </h5>
                        </div>
                        <div class="card-body">
                            @if($salesRow)
                                <div class="alert alert-info py-2 mb-3">
                                    <strong>Note:</strong> Sales transactions (Ledger {{ number_format($salesRow['transaction_total'], 2) }} vs Invoice {{ number_format(abs($salesRow['charge_total']), 2) }}) are detailed in the section above and excluded from the table below to prevent duplication.
                                </div>
                            @endif
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Description</th>
                                            <th class="text-end">Transactions</th>
                                            <th class="text-end">Charges</th>
                                            <th class="text-end">Difference</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($report as $row)
                                            <tr>
                                                <td><strong>{{ $row['description'] }}</strong></td>
                                                <td class="text-end">{{ number_format($row['transaction_total'], 2) }}</td>
                                                <td class="text-end">{{ number_format($row['charge_total'], 2) }}</td>
                                                <td class="text-end">{{ number_format($row['difference'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="table-secondary">
                                        <tr>
                                            <td><strong>Total</strong></td>
                                            <td class="text-end"><strong>{{ number_format($txnSum, 2) }}</strong></td>
                                            <td class="text-end"><strong>{{ number_format($chargeSum, 2) }}</strong></td>
                                            <td class="text-end"><strong>{{ number_format($diffSum, 2) }}</strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="section-divider"></div>
            @endif

            <!-- Section 1: Sales vs Invoice Summary -->
            @if(isset($salesVsOrders))
                @php
                    $primaryCurrency = $salesVsOrders['primary_currency'] ?? null;
                    $breakdown = $salesVsOrders['breakdown'] ?? collect();
                    if (! $breakdown instanceof \Illuminate\Support\Collection) {
                        $breakdown = collect($breakdown ?? []);
                    }
                @endphp
                <div class="report-section">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0 text-white">
                                <i class="fe fe-dollar-sign"></i> Sales vs BM Invoice Summary
                                @if($primaryCurrency)
                                    <small>({{ $primaryCurrency }})</small>
                                @else
                                    <small>(Multi-currency)</small>
                                @endif
                            </h5>
                        </div>
                        <div class="card-body">
                            @if($primaryCurrency)
                                @php
                                    $primaryVariance = $salesVsOrders['difference'] ?? 0;
                                    $primaryClass = abs($primaryVariance) < 0.01 ? 'variance-neutral' : ($primaryVariance < 0 ? 'variance-negative' : 'variance-positive');
                                @endphp
                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <div class="summary-card">
                                            <small class="text-muted d-block mb-1">Recorded Sales Total</small>
                                            <h4 class="mb-0">{{ $primaryCurrency }} {{ number_format($salesVsOrders['transaction_total'] ?? 0, 2) }}</h4>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="summary-card">
                                            <small class="text-muted d-block mb-1">BM Invoice Total</small>
                                            <h4 class="mb-0">{{ $primaryCurrency }} {{ number_format($salesVsOrders['order_total'] ?? 0, 2) }}</h4>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="summary-card">
                                            <small class="text-muted d-block mb-1">Variance (Sales - Invoice)</small>
                                            <h4 class="mb-0 {{ $primaryClass }}">{{ $primaryCurrency }} {{ number_format($primaryVariance, 2) }}</h4>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="alert alert-info mb-3">
                                    <i class="fe fe-info"></i> Multiple currencies detected. See breakdown below.
                                </div>
                            @endif

                            @if($breakdown->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Currency</th>
                                                <th class="text-end">Recorded Sales</th>
                                                <th class="text-end">BM Invoice</th>
                                                <th class="text-end">Variance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($breakdown as $row)
                                                @php
                                                    $varianceClass = abs($row['difference']) < 0.01 ? 'variance-neutral' : ($row['difference'] < 0 ? 'variance-negative' : 'variance-positive');
                                                @endphp
                                                <tr>
                                                    <td><strong>{{ $row['currency'] }}</strong></td>
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
                </div>
                <div class="section-divider"></div>
            @endif

            <!-- Section 3: Order-Level Variance -->
            @if(isset($orderComparisons) && $orderComparisons instanceof \Illuminate\Support\Collection && $orderComparisons->isNotEmpty())
                @php
                    $groupedOrderComparisons = $orderComparisons->groupBy(function ($row) {
                        return $row['order_currency'] ?? '—';
                    });
                    $hasMultipleOrderCurrencies = $groupedOrderComparisons->count() > 1;
                @endphp
                <div class="report-section">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0 text-white">
                                    <i class="fe fe-list"></i> Order-Level Variance Analysis
                                </h5>
                                @if($hasMultipleOrderCurrencies)
                                    <span class="badge bg-white text-success">{{ $groupedOrderComparisons->count() }} Currencies</span>
                                @endif
                            </div>
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
                                        <th class="text-center"><small><b>Type</b></small></th>
                                        <th class="text-center"><small><b>Currency</b></small></th>
                                        <th class="text-end"><small><b>BM Invoice Amount</b></small></th>
                                        <th class="text-center"><small><b>Recorded Amount Currency</b></small></th>
                                        <th class="text-end"><small><b>Recorded Amount</b></small></th>
                                        <th class="text-end"><small><b>Variance (Sales - Invoice)</b></small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="table-secondary">
                                        <td class="text-center">—</td>
                                        <td><b>Totals</b></td>
                                        <td class="text-center">—</td>
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
                                            $typeLabel = $order['primary_transaction_type'] ?? 'unknown';
                                            $typeLabel = $typeLabel === 'unknown' ? '—' : ucfirst($typeLabel);
                                        @endphp
                                        <tr>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}">
                                                    <i class="fa fa-search"></i>
                                                </button>
                                            </td>
                                            <td>{{ $order['order_reference'] ?? $order['order_id'] }}</td>
                                            <td class="text-center">{{ $typeLabel }}</td>
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
                                            <td colspan="8" class="bg-light">
                                                <div class="mb-2">
                                                    <strong>Variance Summary:</strong>
                                                    <ul class="mb-2 small">
                                                        <li>Order Type: {{ $typeLabel === '—' ? 'Unknown' : $typeLabel }}</li>
                                                        <li>Invoice Amount: {{ number_format($order['order_amount'] ?? 0, 2) }} {{ $order['order_currency'] ?? '—' }}</li>
                                                        <li>Recorded Amount Currency: {{ $order['sales_currency'] ?? '—' }}</li>
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
                                                                    <th class="text-center"><small><b>Type</b></small></th>
                                                                    <th><small><b>Date</b></small></th>
                                                                    <th class="text-center"><small><b>Currency</b></small></th>
                                                                    <th class="text-end"><small><b>Recorded Amount</b></small></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($order['transactions'] as $trx)
                                                                    <tr>
                                                                        <td>{{ $trx['id'] }}</td>
                                                                        <td>{{ $trx['reference_id'] }}</td>
                                                                        <td>{{ $trx['description'] }}</td>
                                                                        @php
                                                                            $trxType = $trx['type'] ?? 'other';
                                                                            $trxType = $trxType === 'other' ? 'Other' : ucfirst($trxType);
                                                                        @endphp
                                                                        <td class="text-center">{{ $trxType }}</td>
                                                                        <td>{{ $trx['date'] ?? '—' }}</td>
                                                                        <td class="text-center">{{ $trx['currency'] }}</td>
                                                                        <td class="text-end">{{ number_format($trx['amount'] ?? 0, 2) }}</td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @else
                                                    <p class="mb-0 text-muted">No related transactions.</p>
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
                                                <th class="text-center"><small><b>Type</b></small></th>
                                                <th class="text-center"><small><b>Currency</b></small></th>
                                                <th class="text-end"><small><b>BM Invoice Amount</b></small></th>
                                                <th class="text-center"><small><b>Recorded Amount Currency</b></small></th>
                                                <th class="text-end"><small><b>Recorded Amount</b></small></th>
                                                <th class="text-end"><small><b>Variance (Sales - Invoice)</b></small></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="table-secondary">
                                                <td class="text-center">—</td>
                                                <td><b>Totals</b></td>
                                                <td class="text-center">—</td>
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
                                                    $typeLabel = $order['primary_transaction_type'] ?? 'unknown';
                                                    $typeLabel = $typeLabel === 'unknown' ? '—' : ucfirst($typeLabel);
                                                @endphp
                                                <tr>
                                                    <td class="text-center">
                                                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}">
                                                            <i class="fa fa-search"></i>
                                                        </button>
                                                    </td>
                                                    <td>{{ $order['order_reference'] ?? $order['order_id'] }}</td>
                                                    <td class="text-center">{{ $typeLabel }}</td>
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
                                                    <td colspan="8" class="bg-light">
                                                        <div class="mb-2">
                                                            <strong>Variance Summary:</strong>
                                                            <ul class="mb-2 small">
                                                                <li>Order Type: {{ $typeLabel === '—' ? 'Unknown' : $typeLabel }}</li>
                                                                <li>Invoice Amount: {{ number_format($order['order_amount'] ?? 0, 2) }} {{ $order['order_currency'] ?? '—' }}</li>
                                                                <li>Recorded Amount Currency: {{ $order['sales_currency'] ?? '—' }}</li>
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
                                                                            <th class="text-center"><small><b>Type</b></small></th>
                                                                            <th><small><b>Date</b></small></th>
                                                                            <th class="text-center"><small><b>Currency</b></small></th>
                                                                            <th class="text-end"><small><b>Recorded Amount</b></small></th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @foreach ($order['transactions'] as $trx)
                                                                            <tr>
                                                                                <td>{{ $trx['id'] }}</td>
                                                                                <td>{{ $trx['reference_id'] }}</td>
                                                                                <td>{{ $trx['description'] }}</td>
                                                                                @php
                                                                                    $trxType = $trx['type'] ?? 'other';
                                                                                    $trxType = $trxType === 'other' ? 'Other' : ucfirst($trxType);
                                                                                @endphp
                                                                                <td class="text-center">{{ $trxType }}</td>
                                                                                <td>{{ $trx['date'] ?? '—' }}</td>
                                                                                <td class="text-center">{{ $trx['currency'] }}</td>
                                                                                <td class="text-end">{{ number_format($trx['amount'] ?? 0, 2) }}</td>
                                                                            </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        @else
                                                            <p class="mb-0 text-muted">No related transactions.</p>
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
                </div>
                <div class="section-divider"></div>
            @endif

            <!-- Section 4: Refund Validation -->


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
                mywindow.document.write('<title>BM Invoice Report - {{ $process->reference_id }}</title>');
                mywindow.document.write(
                    `<link rel="stylesheet" href="{{ asset('assets/plugins/bootstrap/css/bootstrap.min.css') }}" type="text/css" />`
                );
                mywindow.document.write(
                    `<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}" type="text/css" />`);
                mywindow.document.write('<style>');
                mywindow.document.write('.no-print { display: none !important; }');
                mywindow.document.write('.card { break-inside: avoid; margin-bottom: 1rem; }');
                mywindow.document.write('.variance-positive { color: #28a745; font-weight: 600; }');
                mywindow.document.write('.variance-negative { color: #dc3545; font-weight: 600; }');
                mywindow.document.write('.variance-neutral { color: #6c757d; }');
                mywindow.document.write('</style>');
                mywindow.document.write('</head><body>');
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
