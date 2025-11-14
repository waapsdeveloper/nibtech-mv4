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

        @php
            $salesSummary = null;
            $chargeSummary = null;
            $deferredSummary = null;
            $refundSummary = null;
            $partialRefundSummary = null;
            $fullRefundSummary = null;

            $formatRefundAmount = function ($value) {
                $value = (float) $value;

                if (abs($value) < 0.0005) {
                    return 0.0;
                }

                return $value > 0 ? -abs($value) : $value;
            };

            if (isset($salesVsOrders)) {
                $salesBreakdown = $salesVsOrders['breakdown'] ?? collect();
                if (! $salesBreakdown instanceof \Illuminate\Support\Collection) {
                    $salesBreakdown = collect($salesBreakdown ?? []);
                }

                $salesSummary = [
                    'is_multi_currency' => empty($salesVsOrders['primary_currency']),
                    'primary_currency' => $salesVsOrders['primary_currency'] ?? null,
                    'recorded_total' => $salesVsOrders['transaction_total'] ?? null,
                    'invoice_total' => $salesVsOrders['order_total'] ?? null,
                    'variance' => $salesVsOrders['difference'] ?? null,
                    'breakdown' => $salesBreakdown,
                ];
            }

            if (isset($report) && $report instanceof \Illuminate\Support\Collection && $report->isNotEmpty()) {
                $deferredKeys = collect(['deferred_payout_released', 'deferred_payout_retained']);

                $normalizedReport = $report->map(function ($row) {
                    $description = (string) ($row['description'] ?? '');
                    $normalized = (string) \Illuminate\Support\Str::of($description)
                        ->lower()
                        ->replace(' ', '_')
                        ->replace('-', '_');

                    return array_merge($row, [
                        'normalized_description' => $normalized,
                    ]);
                });

                $deferredRows = $normalizedReport
                    ->filter(function ($row) use ($deferredKeys) {
                        $key = $row['normalized_description'] ?? '';
                        return $deferredKeys->contains($key);
                    })
                    ->values();

                $otherChargeRows = $normalizedReport
                    ->reject(function ($row) use ($deferredKeys) {
                        $key = $row['normalized_description'] ?? '';
                        return $deferredKeys->contains($key) || \Illuminate\Support\Str::contains($key, 'refund');
                    })
                    ->map(function ($row) {
                        unset($row['normalized_description']);
                        return $row;
                    })
                    ->values();

                if ($otherChargeRows->isNotEmpty()) {
                    $chargeSummary = [
                        'rows' => $otherChargeRows,
                        'transaction_total' => (float) $otherChargeRows->sum('transaction_total'),
                        'charge_total' => (float) $otherChargeRows->sum('charge_total'),
                        'difference_total' => (float) $otherChargeRows->sum('difference'),
                    ];
                }

                if ($deferredRows->isNotEmpty()) {
                    $deferredSummary = [
                        'rows' => $deferredRows,
                        'transaction_total' => (float) $deferredRows->sum('transaction_total'),
                        'charge_total' => (float) $deferredRows->sum('charge_total'),
                        'difference_total' => (float) $deferredRows->sum('difference'),
                    ];
                }
            }

            if (isset($refundReport) && $refundReport instanceof \Illuminate\Support\Collection) {
                $summary = $refundReport->get('summary');
                if (is_array($summary)) {
                    $totals = $summary['totals_by_currency'] ?? collect();
                    if (! $totals instanceof \Illuminate\Support\Collection) {
                        $totals = collect($totals ?? []);
                    }

                    $refundSummary = [
                        'is_single_currency' => (bool) ($summary['is_single_currency'] ?? false),
                        'primary_currency' => $summary['primary_currency'] ?? null,
                        'transaction_total' => $summary['transaction_total'] ?? null,
                        'order_total' => $summary['order_total'] ?? null,
                        'difference' => $summary['difference'] ?? null,
                        'totals_by_currency' => $totals,
                        'matched_count' => $summary['matched_count'] ?? 0,
                        'missing_count' => $summary['missing_order_count'] ?? 0,
                        'total_count' => $summary['total'] ?? 0,
                    ];
                }

                $refundDetails = $refundReport->get('details', collect());
                if (! $refundDetails instanceof \Illuminate\Support\Collection) {
                    $refundDetails = collect($refundDetails ?? []);
                }

                $classifiedRefundDetails = $refundDetails->map(function ($refund) {
                    $transactionAmount = (float) ($refund['transaction_amount'] ?? 0);
                    $orderAmount = (float) ($refund['order_amount'] ?? 0);
                    $orderFound = (bool) ($refund['order_found'] ?? false);

                    $hasOrderTotal = $orderFound && abs($orderAmount) > 0.01;
                    $isFullRefund = $hasOrderTotal && abs($transactionAmount - $orderAmount) < 0.01;
                    $isPartialRefund = $hasOrderTotal && ! $isFullRefund;

                    return array_merge($refund, [
                        'classification' => $isFullRefund ? 'full' : ($isPartialRefund ? 'partial' : 'other'),
                    ]);
                })->values();

                $buildRefundRows = function ($items) use ($formatRefundAmount) {
                    return collect($items)
                        // Only include refunds that have matched orders
                        ->filter(function ($row) {
                            return (bool) ($row['order_found'] ?? false);
                        })
                        ->groupBy(function ($row) {
                            $reference = $row['order_reference'] ?? $row['transaction_reference'] ?? ('txn-' . $row['transaction_id']);
                            $currency = $row['transaction_currency'] ?? '—';
                            return $reference . '|' . $currency;
                        })
                        ->map(function ($group, $key) use ($formatRefundAmount) {
                            [$reference, $currency] = array_pad(explode('|', $key, 2), 2, '—');
                            // Normalize both to negative values (refunds should be negative)
                            $transactionTotal = ($formatRefundAmount)((float) $group->sum('transaction_amount'));
                            $orderTotal = ($formatRefundAmount)((float) $group->sum('order_amount'));

                            return [
                                'description' => $reference,
                                'currency' => $currency,
                                'transaction_total' => $transactionTotal,
                                'charge_total' => $orderTotal,
                                'difference' => $transactionTotal - $orderTotal,
                            ];
                        })
                        ->values();
                };

                $partialRefundRows = $buildRefundRows($classifiedRefundDetails->where('classification', 'partial')->values())
                    ->filter(function ($row) {
                        return abs((float) ($row['difference'] ?? 0)) >= 0.01;
                    })
                    ->values();

                $fullRefundRows = $buildRefundRows($classifiedRefundDetails->where('classification', 'full')->values())->values();

                if ($partialRefundRows->isNotEmpty()) {
                    $partialRefundSummary = [
                        'rows' => $partialRefundRows,
                        'transaction_total' => (float) $partialRefundRows->sum('transaction_total'),
                        'charge_total' => (float) $partialRefundRows->sum('charge_total'),
                        'difference_total' => (float) $partialRefundRows->sum('difference'),
                    ];
                }

                if ($fullRefundRows->isNotEmpty()) {
                    $fullRefundSummary = [
                        'rows' => $fullRefundRows,
                        'transaction_total' => (float) $fullRefundRows->sum('transaction_total'),
                        'charge_total' => (float) $fullRefundRows->sum('charge_total'),
                        'difference_total' => (float) $fullRefundRows->sum('difference'),
                    ];
                }

                $refundDetails = $classifiedRefundDetails;
            }
        @endphp

    @if($salesSummary || $chargeSummary || $refundSummary || $deferredSummary || $partialRefundSummary || $fullRefundSummary)
            <div class="card mt-3">
                <div class="card-header pb-0">
                    <h4 class="card-title mg-b-0">Financial Summary</h4>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @if($salesSummary)
                            <div class="col-lg-3 col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <small class="text-uppercase">Sales vs Invoice</small>
                                    <div class="mt-2">
                                        @if(! $salesSummary['is_multi_currency'] && $salesSummary['primary_currency'])
                                            @php
                                                $salesCurrency = $salesSummary['primary_currency'];
                                                $salesRecorded = (float) ($salesSummary['recorded_total'] ?? 0);
                                                $salesInvoice = (float) ($salesSummary['invoice_total'] ?? 0);
                                                $salesVariance = (float) ($salesSummary['variance'] ?? 0);
                                                $salesVarianceClass = abs($salesVariance) < 0.01
                                                    ? 'text-success'
                                                    : ($salesVariance < 0 ? 'text-warning' : 'text-danger');
                                            @endphp
                                            <div class="d-flex justify-content-between">
                                                <span>Recorded ({{ $salesCurrency }})</span>
                                                <span class="fw-semibold">{{ number_format($salesRecorded, 2) }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span>Invoice ({{ $salesCurrency }})</span>
                                                <span class="fw-semibold">{{ number_format($salesInvoice, 2) }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span>Variance</span>
                                                <span class="fw-semibold {{ $salesVarianceClass }}">{{ number_format($salesVariance, 2) }}</span>
                                            </div>
                                        @else
                                            <p class="mb-2">Multiple currencies detected. See breakdown below.</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($chargeSummary)
                            <div class="col-lg-3 col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <small class="text-uppercase">Non-Sales Charges</small>
                                    @php
                                        $chargeLedger = (float) ($chargeSummary['transaction_total'] ?? 0);
                                        $chargeInvoice = (float) ($chargeSummary['charge_total'] ?? 0);
                                        $chargeVariance = (float) ($chargeSummary['difference_total'] ?? 0);
                                        $chargeVarianceClass = abs($chargeVariance) < 0.01
                                            ? 'text-success'
                                            : ($chargeVariance < 0 ? 'text-warning' : 'text-danger');
                                    @endphp
                                    <div class="mt-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Actual Ledger</span>
                                            <span class="fw-semibold">{{ number_format($chargeLedger, 2) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Calculated Charges</span>
                                            <span class="fw-semibold">{{ number_format($chargeInvoice, 2) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Variance</span>
                                            <span class="fw-semibold {{ $chargeVarianceClass }}">{{ number_format($chargeVariance, 2) }}</span>
                                        </div>
                                    </div>
                                    <p class="text-muted mb-0 mt-2">Variance = Actual - Calculated. Negative values = charges.</p>
                                </div>
                            </div>
                        @endif

                        @if($refundSummary)
                            <div class="col-lg-3 col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <small class="text-uppercase">Refund Variance</small>
                                    @php
                                        $refundVariance = (float) ($refundSummary['difference'] ?? 0);
                                        $refundVarianceClass = abs($refundVariance) < 0.01
                                            ? 'text-success'
                                            : ($refundVariance < 0 ? 'text-warning' : 'text-danger');
                                    @endphp
                                    <div class="mt-2">
                                        @if($refundSummary['is_single_currency'] && $refundSummary['primary_currency'])
                                            <div class="d-flex justify-content-between">
                                                <span>Ledger Refunds ({{ $refundSummary['primary_currency'] }})</span>
                                                <span class="fw-semibold">{{ number_format(($formatRefundAmount)($refundSummary['transaction_total'] ?? 0), 2) }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span>Order Refunds ({{ $refundSummary['primary_currency'] }})</span>
                                                <span class="fw-semibold">{{ number_format(($formatRefundAmount)($refundSummary['order_total'] ?? 0), 2) }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span>Variance</span>
                                                <span class="fw-semibold {{ $refundVarianceClass }}">{{ number_format($refundVariance, 2) }}</span>
                                            </div>
                                        @else
                                            <p class="mb-2">Multiple currencies detected. See breakdown below.</p>
                                        @endif
                                        <p class="text-muted mb-0">Matched {{ $refundSummary['matched_count'] ?? 0 }} of {{ $refundSummary['total_count'] ?? 0 }} refunds.</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($partialRefundSummary)
                            <div class="col-lg-3 col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <small class="text-uppercase">Partial Refunds</small>
                                    @php
                                        $partialVariance = (float) ($partialRefundSummary['difference_total'] ?? 0);
                                        $partialVarianceClass = abs($partialVariance) < 0.01
                                            ? 'text-success'
                                            : ($partialVariance < 0 ? 'text-warning' : 'text-danger');
                                    @endphp
                                    <div class="mt-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Actual Ledger</span>
                                            <span class="fw-semibold">{{ number_format($partialRefundSummary['transaction_total'] ?? 0, 2) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Calculated Refunds</span>
                                            <span class="fw-semibold">{{ number_format($partialRefundSummary['charge_total'] ?? 0, 2) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Variance</span>
                                            <span class="fw-semibold {{ $partialVarianceClass }}">{{ number_format($partialVariance, 2) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($fullRefundSummary)
                            <div class="col-lg-3 col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <small class="text-uppercase">Full Refunds</small>
                                    @php
                                        $fullVariance = (float) ($fullRefundSummary['difference_total'] ?? 0);
                                        $fullVarianceClass = abs($fullVariance) < 0.01
                                            ? 'text-success'
                                            : ($fullVariance < 0 ? 'text-warning' : 'text-danger');
                                    @endphp
                                    <div class="mt-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Actual Ledger</span>
                                            <span class="fw-semibold">{{ number_format($fullRefundSummary['transaction_total'] ?? 0, 2) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Calculated Refunds</span>
                                            <span class="fw-semibold">{{ number_format($fullRefundSummary['charge_total'] ?? 0, 2) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Variance</span>
                                            <span class="fw-semibold {{ $fullVarianceClass }}">{{ number_format($fullVariance, 2) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($deferredSummary)
                            <div class="col-lg-3 col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <small class="text-uppercase">Deferred Payouts</small>
                                    @php
                                        $deferredLedger = (float) ($deferredSummary['transaction_total'] ?? 0);
                                        $deferredInvoice = (float) ($deferredSummary['charge_total'] ?? 0);
                                        $deferredVariance = (float) ($deferredSummary['difference_total'] ?? 0);
                                        $deferredVarianceClass = abs($deferredVariance) < 0.01
                                            ? 'text-success'
                                            : ($deferredVariance < 0 ? 'text-warning' : 'text-danger');
                                    @endphp
                                    <div class="mt-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Ledger Total</span>
                                            <span class="fw-semibold">{{ number_format($deferredLedger, 2) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Invoice Total</span>
                                            <span class="fw-semibold">{{ number_format($deferredInvoice, 2) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Variance</span>
                                            <span class="fw-semibold {{ $deferredVarianceClass }}">{{ number_format($deferredVariance, 2) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    @if($salesSummary && $salesSummary['breakdown']->isNotEmpty())
                        <hr class="my-4">
                        <h6 class="mb-2">Sales Totals by Currency</h6>
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
                                    @foreach ($salesSummary['breakdown'] as $row)
                                        @php
                                            $varianceClass = abs($row['difference']) < 0.01
                                                ? 'text-success'
                                                : ($row['difference'] < 0 ? 'text-warning' : 'text-danger');
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

                    @if($chargeSummary && $chargeSummary['rows']->isNotEmpty())
                        <hr class="my-4">
                        <h6 class="mb-2">Non-Sales Charge Breakdown</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>Description</b></small></th>
                                        <th class="text-end"><small><b>Actual Ledger</b></small></th>
                                        <th class="text-end"><small><b>Calculated Charges</b></small></th>
                                        <th class="text-end"><small><b>Variance</b></small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($chargeSummary['rows'] as $row)
                                        @php
                                            $rowVarianceClass = abs($row['difference']) < 0.01
                                                ? 'text-success'
                                                : ($row['difference'] < 0 ? 'text-warning' : 'text-danger');
                                        @endphp
                                        <tr>
                                            <td>{{ $row['description'] }}</td>
                                            <td class="text-end">{{ number_format($row['transaction_total'], 2) }}</td>
                                            <td class="text-end">{{ number_format($row['charge_total'], 2) }}</td>
                                            <td class="text-end {{ $rowVarianceClass }}">{{ number_format($row['difference'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td><b>Total</b></td>
                                        <td class="text-end"><b>{{ number_format($chargeSummary['transaction_total'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($chargeSummary['charge_total'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($chargeSummary['difference_total'], 2) }}</b></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <p class="text-muted mb-0 mt-2">Sales transactions are excluded from this table; they are summarised above. Partial and full refunds, along with deferred payout adjustments, each appear in their dedicated sections below.</p>
                    @endif

                    @if($partialRefundSummary && $partialRefundSummary['rows']->isNotEmpty())
                        <hr class="my-4">
                        <h6 class="mb-2">Partial Refund Breakdown</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>Reference</b></small></th>
                                        <th class="text-center"><small><b>Currency</b></small></th>
                                        <th class="text-end"><small><b>Ledger Transactions</b></small></th>
                                        <th class="text-end"><small><b>BM Invoice Charges</b></small></th>
                                        <th class="text-end"><small><b>Variance</b></small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($partialRefundSummary['rows'] as $row)
                                        @php
                                            $partialRowVariance = (float) ($row['difference'] ?? 0);
                                            $partialRowClass = abs($partialRowVariance) < 0.01
                                                ? 'text-success'
                                                : ($partialRowVariance < 0 ? 'text-warning' : 'text-danger');
                                        @endphp
                                        <tr>
                                            <td>{{ $row['description'] }}</td>
                                            <td class="text-center">{{ $row['currency'] ?? '—' }}</td>
                                            <td class="text-end">{{ number_format(($formatRefundAmount)($row['transaction_total'] ?? 0), 2) }}</td>
                                            <td class="text-end">{{ number_format(($formatRefundAmount)($row['charge_total'] ?? 0), 2) }}</td>
                                            <td class="text-end {{ $partialRowClass }}">{{ number_format($partialRowVariance, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td><b>Total</b></td>
                                        <td class="text-center">—</td>
                                        <td class="text-end"><b>{{ number_format(($formatRefundAmount)($partialRefundSummary['transaction_total'] ?? 0), 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format(($formatRefundAmount)($partialRefundSummary['charge_total'] ?? 0), 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($partialRefundSummary['difference_total'], 2) }}</b></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif

                    @if($fullRefundSummary && $fullRefundSummary['rows']->isNotEmpty())
                        <hr class="my-4">
                        <h6 class="mb-2">Full Refund Breakdown</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>Reference</b></small></th>
                                        <th class="text-center"><small><b>Currency</b></small></th>
                                        <th class="text-end"><small><b>Ledger Transactions</b></small></th>
                                        <th class="text-end"><small><b>BM Invoice Charges</b></small></th>
                                        <th class="text-end"><small><b>Variance</b></small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($fullRefundSummary['rows'] as $row)
                                        @php
                                            $fullRowVariance = (float) ($row['difference'] ?? 0);
                                            $fullRowClass = abs($fullRowVariance) < 0.01
                                                ? 'text-success'
                                                : ($fullRowVariance < 0 ? 'text-warning' : 'text-danger');
                                        @endphp
                                        <tr>
                                            <td>{{ $row['description'] }}</td>
                                            <td class="text-center">{{ $row['currency'] ?? '—' }}</td>
                                            <td class="text-end">{{ number_format(($formatRefundAmount)($row['transaction_total'] ?? 0), 2) }}</td>
                                            <td class="text-end">{{ number_format(($formatRefundAmount)($row['charge_total'] ?? 0), 2) }}</td>
                                            <td class="text-end {{ $fullRowClass }}">{{ number_format($fullRowVariance, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td><b>Total</b></td>
                                        <td class="text-center">—</td>
                                        <td class="text-end"><b>{{ number_format(($formatRefundAmount)($fullRefundSummary['transaction_total'] ?? 0), 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format(($formatRefundAmount)($fullRefundSummary['charge_total'] ?? 0), 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($fullRefundSummary['difference_total'], 2) }}</b></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif

                    @if($deferredSummary && $deferredSummary['rows']->isNotEmpty())
                        <hr class="my-4">
                        <h6 class="mb-2">Deferred Payout Breakdown</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>Description</b></small></th>
                                        <th class="text-end"><small><b>Ledger Transactions</b></small></th>
                                        <th class="text-end"><small><b>BM Invoice Charges</b></small></th>
                                        <th class="text-end"><small><b>Variance</b></small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($deferredSummary['rows'] as $row)
                                        @php
                                            $deferredRowVariance = (float) ($row['difference'] ?? 0);
                                            $deferredRowClass = abs($deferredRowVariance) < 0.01
                                                ? 'text-success'
                                                : ($deferredRowVariance < 0 ? 'text-warning' : 'text-danger');
                                        @endphp
                                        <tr>
                                            <td>{{ $row['description'] }}</td>
                                            <td class="text-end">{{ number_format($row['transaction_total'], 2) }}</td>
                                            <td class="text-end">{{ number_format($row['charge_total'], 2) }}</td>
                                            <td class="text-end {{ $deferredRowClass }}">{{ number_format($deferredRowVariance, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td><b>Total</b></td>
                                        <td class="text-end"><b>{{ number_format($deferredSummary['transaction_total'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($deferredSummary['charge_total'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($deferredSummary['difference_total'], 2) }}</b></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif

                    @if($refundSummary && $refundSummary['totals_by_currency']->isNotEmpty())
                        <hr class="my-4">
                        <h6 class="mb-2">Refund Totals by Currency</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>Currency</b></small></th>
                                        <th class="text-end"><small><b>Ledger Refunds</b></small></th>
                                        <th class="text-end"><small><b>Order Refunds</b></small></th>
                                        <th class="text-end"><small><b>Variance</b></small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($refundSummary['totals_by_currency'] as $row)
                                        @php
                                            $refundRowVariance = $row['difference'] ?? 0;
                                            $refundRowClass = abs($refundRowVariance) < 0.01
                                                ? 'text-success'
                                                : ($refundRowVariance < 0 ? 'text-warning' : 'text-danger');
                                        @endphp
                                        <tr>
                                            <td>{{ $row['currency'] }}</td>
                                            <td class="text-end">{{ number_format(($formatRefundAmount)($row['transaction_total'] ?? 0), 2) }}</td>
                                            <td class="text-end">{{ number_format(($formatRefundAmount)($row['order_total'] ?? 0), 2) }}</td>
                                            <td class="text-end {{ $refundRowClass }}">{{ number_format($refundRowVariance, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- @endif --}}

        @if(isset($orderComparisons) && $orderComparisons instanceof \Illuminate\Support\Collection && $orderComparisons->isNotEmpty())
            @php
                $groupedOrderComparisons = $orderComparisons->groupBy(function ($row) {
                    $currencyId = $row['order_currency_id'] ?? null;

                    return $currencyId === null || $currencyId === '' ? '—' : (string) $currencyId;
                });
                $hasMultipleOrderCurrencies = $groupedOrderComparisons->count() > 1;
            @endphp
            <div class="card mt-3">
                <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                    <h4 class="card-title mg-b-0">Order-Level Variance (Invoice vs Recorded Amounts)</h4>
                    @if($hasMultipleOrderCurrencies)
                        <span class="text-muted">Showing {{ $groupedOrderComparisons->count() }} currencies</span>
                    @endif
                </div>
                <div class="card-body">
                    @if(!$hasMultipleOrderCurrencies)
                        @php
                            $singleCurrencyOrders = $groupedOrderComparisons->first();
                            $singleCurrencyLabel = optional($singleCurrencyOrders->first())['order_currency'] ?? '—';
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
                                        <th class="text-end"><small><b>Invoice Amount (Comparison)</b></small></th>
                                        <th class="text-center"><small><b>Recorded Amount Currency</b></small></th>
                                        <th class="text-end"><small><b>Recorded Amount</b></small></th>
                                        <th class="text-end"><small><b>Variance (Recorded - Invoice)</b></small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="table-secondary">
                                        <td class="text-center">—</td>
                                        <td><b>Totals</b></td>
                                        <td class="text-center">—</td>
                                        <td class="text-center">{{ $singleCurrencyLabel }}</td>
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
                                            $originalAmount = $order['order_amount_original'] ?? null;
                                            $comparisonAmount = $order['order_amount'] ?? 0;
                                            $showOriginalAmount = ! is_null($originalAmount) && abs($originalAmount - $comparisonAmount) > 0.01;
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
                                            <td class="text-end">
                                                {{ number_format($comparisonAmount, 2) }}
                                                @if($showOriginalAmount)
                                                    <div class="text-muted">Original: {{ number_format($originalAmount, 2) }}</div>
                                                @endif
                                            </td>
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
                                                    <ul class="mb-2">
                                                        <li>Order Type: {{ $typeLabel === '—' ? 'Unknown' : $typeLabel }}</li>
                                                        <li>Invoice Amount (comparison): {{ number_format($comparisonAmount, 2) }} {{ $order['order_currency'] ?? '—' }}</li>
                                                        @if($showOriginalAmount)
                                                            <li>Invoice Amount (original): {{ number_format($originalAmount, 2) }} {{ $order['order_currency'] ?? '—' }}</li>
                                                        @endif
                                                        <li>Recorded Amount Currency: {{ $order['sales_currency'] ?? '—' }}</li>
                                                        <li>
                                                            Recorded Amount:
                                                            @if(is_numeric($order['sales_total_currency']))
                                                                {{ number_format($order['sales_total_currency'], 2) }}
                                                            @else
                                                                <span class="text-muted">Mixed or unavailable</span>
                                                            @endif
                                                        </li>
                                                        @if(is_numeric($difference))
                                                            <li>Variance (Recorded - Invoice): <span class="{{ $diffClass }}">{{ number_format($difference, 2) }}</span></li>
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
                                                    <p class="mb-0">No related transactions.</p>
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
                                $orderCurrencyLabel = optional($ordersInCurrency->first())['order_currency'] ?? '—';
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
                                    <span class="text-muted">Orders: {{ $ordersInCurrency->count() }}</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover mb-0 text-md-nowrap align-middle">
                                        <thead>
                                            <tr>
                                                <th style="width: 48px;"><span class="visually-hidden">Toggle</span></th>
                                                <th><small><b>Order Ref</b></small></th>
                                                <th class="text-center"><small><b>Type</b></small></th>
                                                <th class="text-center"><small><b>Currency</b></small></th>
                                                <th class="text-end"><small><b>Invoice Amount (Comparison)</b></small></th>
                                                <th class="text-center"><small><b>Recorded Amount Currency</b></small></th>
                                                <th class="text-end"><small><b>Recorded Amount</b></small></th>
                                                <th class="text-end"><small><b>Variance (Recorded - Invoice)</b></small></th>
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
                                                    $originalAmount = $order['order_amount_original'] ?? null;
                                                    $comparisonAmount = $order['order_amount'] ?? 0;
                                                    $showOriginalAmount = ! is_null($originalAmount) && abs($originalAmount - $comparisonAmount) > 0.01;
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
                                                    <td class="text-end">
                                                        {{ number_format($comparisonAmount, 2) }}
                                                        @if($showOriginalAmount)
                                                            <div class="text-muted">Original: {{ number_format($originalAmount, 2) }}</div>
                                                        @endif
                                                    </td>
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
                                                            <ul class="mb-2">
                                                                <li>Order Type: {{ $typeLabel === '—' ? 'Unknown' : $typeLabel }}</li>
                                                                <li>Invoice Amount (comparison): {{ number_format($comparisonAmount, 2) }} {{ $order['order_currency'] ?? '—' }}</li>
                                                                @if($showOriginalAmount)
                                                                    <li>Invoice Amount (original): {{ number_format($originalAmount, 2) }} {{ $order['order_currency'] ?? '—' }}</li>
                                                                @endif
                                                                <li>Recorded Amount Currency: {{ $order['sales_currency'] ?? '—' }}</li>
                                                                <li>
                                                                    Recorded Amount:
                                                                    @if(is_numeric($order['sales_total_currency']))
                                                                        {{ number_format($order['sales_total_currency'], 2) }}
                                                                    @else
                                                                        <span class="text-muted">Mixed or unavailable</span>
                                                                    @endif
                                                                </li>
                                                                @if(is_numeric($difference))
                                                                    <li>Variance (Recorded - Invoice): <span class="{{ $diffClass }}">{{ number_format($difference, 2) }}</span></li>
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
                                                            <p class="mb-0">No related transactions.</p>
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

        @if(isset($refundReport) && $refundReport instanceof \Illuminate\Support\Collection)
            @php
                $refundDetails = $refundReport->get('details', collect());
                if (! $refundDetails instanceof \Illuminate\Support\Collection) {
                    $refundDetails = collect($refundDetails ?? []);
                }

                $visibleRefundDetails = $refundDetails->filter(function ($refund) {
                    $difference = is_numeric($refund['difference'] ?? null) ? (float) $refund['difference'] : 0;
                    $isMatched = (bool) ($refund['order_found'] ?? false);

                    // Hide fully matched refunds with no meaningful variance from the detailed table.
                    return ! ($isMatched && abs($difference) < 0.01);
                })->values();
            @endphp
            @if($visibleRefundDetails->isNotEmpty())
                <div class="card mt-3">
                    <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                        <h4 class="card-title mg-b-0">Refund Validation Details</h4>
                        @if($refundSummary)
                            <span class="text-muted">
                                Matched {{ $refundSummary['matched_count'] ?? 0 }} of {{ $refundSummary['total_count'] ?? $refundDetails->count() }}
                                | Missing {{ $refundSummary['missing_count'] ?? 0 }}
                            </span>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0 text-md-nowrap align-middle">
                                <thead>
                                    <tr>
                                        <th><small><b>Txn ID</b></small></th>
                                        <th><small><b>Txn Ref</b></small></th>
                                        <th class="text-center"><small><b>Currency</b></small></th>
                                        <th class="text-end"><small><b>Ledger Amount</b></small></th>
                                        <th class="text-center"><small><b>Order Match</b></small></th>
                                        <th><small><b>Order Ref</b></small></th>
                                        <th class="text-center"><small><b>Order Currency</b></small></th>
                                        <th class="text-end"><small><b>Order Amount</b></small></th>
                                        <th class="text-end"><small><b>Variance</b></small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($visibleRefundDetails as $refund)
                                        @php
                                            $matchSource = $refund['match_source'] ?? null;
                                            $matchLabel = $refund['order_found']
                                                ? ('Matched' . ($matchSource ? ' (' . ucfirst($matchSource) . ')' : ''))
                                                : 'Missing';
                                            $matchClass = $refund['order_found'] ? 'text-success' : 'text-danger';
                                            $varianceValue = $refund['difference'] ?? 0;
                                            $varianceClass = abs($varianceValue) < 0.01
                                                ? 'text-success'
                                                : ($varianceValue < 0 ? 'text-warning' : 'text-danger');
                                        @endphp
                                        <tr>
                                            <td>{{ $refund['transaction_id'] }}</td>
                                            <td>{{ $refund['transaction_reference'] ?? '—' }}</td>
                                            <td class="text-center">{{ $refund['transaction_currency'] ?? '—' }}</td>
                                            <td class="text-end">{{ number_format(($formatRefundAmount)($refund['transaction_amount'] ?? 0), 2) }}</td>
                                            <td class="text-center {{ $matchClass }}">{{ $matchLabel }}</td>
                                            <td>{{ $refund['order_reference'] ?? '—' }}</td>
                                            <td class="text-center">{{ $refund['order_currency'] ?? '—' }}</td>
                                            <td class="text-end">
                                                @if($refund['order_found'])
                                                    {{ number_format(($formatRefundAmount)($refund['order_amount'] ?? 0), 2) }}
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-end {{ $varianceClass }}">{{ number_format($varianceValue, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @elseif($refundDetails->isNotEmpty())
                <div class="alert alert-secondary mt-3" role="alert">
                    All refund records on this invoice are fully matched with no variance to review.
                </div>
            @endif
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
