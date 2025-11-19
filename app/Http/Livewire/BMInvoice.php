<?php

namespace App\Http\Livewire;

use App\Models\Account_transaction_model;
use App\Models\Order_charge_model;
use App\Models\Order_model;
use App\Models\Process_model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

class BMInvoice extends Component
{
    protected ?array $currencyLabelCache = null;

    public function mount()
    {
        if (! session('user_id')) {
            return redirect('index');
        }
    }

    public function render()
    {
        $title = 'BM Invoices Report';
        session()->put('page_title', $title);

        return view('livewire.bm_invoice', [
            'title_page' => $title,
            'batches' => $this->paginatedBatches((int) request('per_page', 20)),
        ]);
    }

    public function invoice_detail($processId)
    {
        $processId = (int) $processId;

        $this->applyRuntimeLimits();
        $this->rememberListPage();

        $process = Process_model::with(['admin', 'listed_stocks_verification', 'process_stocks'])->find($processId);

        if (! $process) {
            abort(404, 'Process not found.');
        }

        $detailData = $this->prepareInvoiceDetailData($processId);

        $data = array_merge($detailData, [
            'title_page' => 'BM Invoice Detail',
            'process' => $process,
        ]);

        session()->put('page_title', $data['title_page']);

        return view('livewire.bm_invoice_detail')->with($data);
    }

    private function paginatedBatches(int $perPage)
    {
        $perPage = $perPage > 0 ? $perPage : 20;

        return Process_model::where('process_type_id', 19)
            ->with(['admin', 'transactions'])
            ->when(request('start_date'), fn ($query, $start) => $query->where('created_at', '>=', $start))
            ->when(request('end_date'), fn ($query, $end) => $query->where('created_at', '<=', $end . ' 23:59:59'))
            ->when(request('batch_id'), fn ($query, $batchId) => $query->where('reference_id', 'LIKE', $batchId . '%'))
            ->when(request('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByRaw('CAST(reference_id AS UNSIGNED) DESC')
            ->paginate($perPage)
            ->appends(request()->except('page'));
    }

    private function applyRuntimeLimits(): void
    {
        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', '300');
        ini_set('pdo_mysql.max_input_vars', '10000');
    }

    private function rememberListPage(): void
    {
        $previous = url()->previous();
        $listingUrl = url('bm_invoice');

        if (str_contains($previous, $listingUrl) && ! str_contains($previous, 'detail')) {
            session()->put('previous', $previous);
        }
    }

    private function prepareInvoiceDetailData(int $processId): array
    {
        [$transactions, $duplicateTransactions] = $this->loadTransactions($processId);

        $orders = $this->loadOrders($transactions);
        $salesTransactions = $transactions
            ->filter(fn ($transaction) => $this->isSalesDescription($transaction->description))
            ->values();

        $refundTransactions = $transactions
            ->filter(fn ($transaction) => $this->isRefundDescription($transaction->description))
            ->values();

        $chargeMap = $this->buildChargeMap($orders);

        $refundOrders = $this->loadRefundOrders($transactions);

        $systemIssuedRefundOrders = $this->loadSystemIssuedRefundOrders($refundOrders);

        $additionalRefundOrders = collect();

        if ($refundTransactions->isNotEmpty()) {
            $refundReferenceIds = $refundTransactions
                ->filter(fn ($transaction) => empty($transaction->order_id) && ! empty($transaction->reference_id))
                ->pluck('reference_id')
                ->filter()
                ->unique();

            if ($refundReferenceIds->isNotEmpty()) {
                $additionalRefundOrders = Order_model::whereIn('reference_id', $refundReferenceIds)
                    ->where('order_type_id', 4)
                    ->get(['id', 'price', 'currency', 'reference_id']);
            }
        }

        $allRefundOrders = $refundOrders->merge($additionalRefundOrders)->merge($systemIssuedRefundOrders)->unique('id');

        $descriptionSummary = $this->buildDescriptionReport($transactions, $chargeMap, $orders);
        $refundReport = $this->buildRefundReport($refundTransactions, $refundOrders, $allRefundOrders);
        $creditRequestReport = $this->buildOrderWiseReport($transactions, $orders, 'credit_request');
        $regularizationChargebackReport = $this->buildOrderWiseReport($transactions, $orders, 'regularization_chargeback');

        return [
            'duplicateTransactions' => $duplicateTransactions,
            'salesVsOrders' => $this->summarizeSalesVsOrders($orders, $salesTransactions),
            'orderComparisons' => $this->buildOrderComparisons($orders, $transactions),
            'report' => $descriptionSummary->get('report'),
            'reportSalesRow' => $descriptionSummary->get('sales'),
            'refundReport' => $refundReport,
            'creditRequestReport' => $creditRequestReport,
            'regularizationChargebackReport' => $regularizationChargebackReport,
        ];
    }

    private function loadTransactions(int $processId): array
    {
        $transactions = Account_transaction_model::where('process_id', $processId)->get();

        $seen = [];
        $duplicates = collect();

        $unique = $transactions->filter(function ($transaction) use (&$seen, $duplicates) {
            $normalizedDescription = $this->normalizeDescription($transaction->description);
                $normalizedAmount = number_format((float) $transaction->amount, 4, '.', '');

            $key = implode('|', [
                $transaction->order_id ?? 'null',
                $normalizedDescription,
                $normalizedAmount,
                $transaction->currency ?? 'null',
            ]);

            if (isset($seen[$key])) {
                $duplicates->push($transaction);
                return false;
            }

            $seen[$key] = true;

            return true;
        })->values();

        return [$unique, $duplicates->values()];
    }

    private function loadOrders(Collection $transactions): Collection
    {
        $orderIds = $transactions->where('description', 'sales')->pluck('order_id')->filter()->unique();

        if ($orderIds->isEmpty()) {
            return collect();
        }

        return Order_model::whereIn('id', $orderIds)->where('order_type_id', 3)
            ->get(['id', 'price', 'currency', 'reference_id']);
    }

    private function loadRefundOrders(Collection $transactions): Collection
    {
        $orderIds = $transactions->where('description', 'refunds')->pluck('order_id')->filter()->unique();

        if ($orderIds->isEmpty()) {
            return collect();
        }

        return Order_model::whereIn('id', $orderIds)->where('order_type_id', 3)
            ->get(['id', 'price', 'currency', 'reference_id']);
    }

    private function loadSystemIssuedRefundOrders(Collection $refundOrders): Collection
    {
        $refundOrderIds = $refundOrders->pluck('id')->filter()->unique();

        if ($refundOrderIds->isEmpty()) {
            return collect();
        }

        $refundOrderItemIds = DB::table('order_items')
            ->whereIn('order_id', $refundOrderIds)
            ->pluck('id');

        if ($refundOrderItemIds->isEmpty()) {
            return collect();
        }

        $linkedRefundOrderIds = DB::table('order_items')
            ->whereIn('linked_id', $refundOrderItemIds)
            ->distinct()
            ->pluck('order_id');

        if ($linkedRefundOrderIds->isEmpty()) {
            return collect();
        }

        return Order_model::whereIn('id', $linkedRefundOrderIds)
            ->where('order_type_id', 4)
            ->get(['id', 'price', 'currency', 'reference_id']);
    }

    private function summarizeSalesVsOrders(Collection $orders, Collection $salesTransactions): array
    {
        $salesPerCurrency = $salesTransactions
            ->groupBy('currency')
            ->map(fn ($group) => (float) $group->sum('amount'));

        $ordersPerCurrency = $orders
            ->groupBy('currency')
            ->map(function ($group) {
                return (float) $group->sum(function ($order) {
                    return (float) ($order->price ?? 0);
                });
            });

        $currencyBreakdown = $ordersPerCurrency
            ->keys()
            ->merge($salesPerCurrency->keys())
            ->unique()
            ->map(function ($currencyId) use ($salesPerCurrency, $ordersPerCurrency) {
                $salesTotal = $salesPerCurrency->get($currencyId, 0.0);
                $orderTotal = $ordersPerCurrency->get($currencyId, 0.0);

                return [
                    'currency_id' => (string) $currencyId,
                    'currency' => $this->formatCurrencyId($currencyId),
                    'sales_total' => $salesTotal,
                    'order_total' => $orderTotal,
                    'difference' => $salesTotal - $orderTotal,
                ];
            })
            ->filter(function ($row) {
                return abs($row['sales_total']) > 0.01 || abs($row['order_total']) > 0.01;
            })
            ->values();

        $isSingleCurrency = $currencyBreakdown->count() === 1;

        return [
            'transaction_total' => $isSingleCurrency ? ($currencyBreakdown[0]['sales_total'] ?? 0.0) : null,
            'order_total' => $isSingleCurrency ? ($currencyBreakdown[0]['order_total'] ?? 0.0) : null,
            'difference' => $isSingleCurrency ? ($currencyBreakdown[0]['difference'] ?? 0.0) : null,
            'primary_currency' => $isSingleCurrency ? ($currencyBreakdown[0]['currency'] ?? null) : null,
            'breakdown' => $currencyBreakdown,
        ];
    }

    private function buildOrderComparisons(Collection $orders, Collection $transactions): Collection
    {
        $transactionsByOrder = $transactions
            ->filter(fn ($transaction) => ! empty($transaction->order_id))
            ->groupBy('order_id');

        return $orders->map(function ($order) use ($transactionsByOrder) {
            $orderTransactions = $transactionsByOrder->get($order->id, collect());

            $salesTransactions = $orderTransactions->filter(fn ($transaction) => $this->isSalesDescription($transaction->description));
            $refundTransactions = $orderTransactions->filter(fn ($transaction) => $this->isRefundDescription($transaction->description));

            $originalOrderAmount = (float) ($order->price ?? 0.0);
            $isRefundOrder = $originalOrderAmount < 0;
            $orderCurrencyId = (string) ($order->currency ?? '');

            $primaryTransactions = $isRefundOrder ? $refundTransactions : $salesTransactions;
            $primaryType = $isRefundOrder ? 'refund' : 'sale';

            if ($primaryTransactions->isEmpty()) {
                if ($salesTransactions->isNotEmpty()) {
                    $primaryTransactions = $salesTransactions;
                    $primaryType = 'sale';
                } elseif ($refundTransactions->isNotEmpty()) {
                    $primaryTransactions = $refundTransactions;
                    $primaryType = 'refund';
                } else {
                    $primaryTransactions = collect();
                    $primaryType = 'unknown';
                }
            }

            $orderAmountForComparison = $originalOrderAmount;
            if ($primaryType === 'refund') {
                $orderAmountForComparison = $originalOrderAmount > 0
                    ? $originalOrderAmount * -1
                    : $originalOrderAmount;
            } elseif ($primaryType === 'sale') {
                $orderAmountForComparison = $originalOrderAmount < 0
                    ? abs($originalOrderAmount)
                    : $originalOrderAmount;
            }

            $currencyIds = $primaryTransactions->pluck('currency')->filter()->unique();

            if ($primaryTransactions->isEmpty()) {
                $recordedCurrencyCode = '—';
                $recordedTotal = null;
                $differenceCurrency = null;
            } elseif ($currencyIds->count() === 1) {
                $recordedCurrencyCode = (string) $currencyIds->first();
                $recordedTotal = (float) $primaryTransactions->sum('amount');
                $differenceCurrency = ((string) $recordedCurrencyCode === (string) $order->currency)
                    ? ($recordedTotal - $orderAmountForComparison)
                    : null;
            } else {
                $recordedCurrencyCode = 'Mixed';
                $recordedTotal = null;
                $differenceCurrency = null;
            }

            $transactionRows = $orderTransactions->map(function ($transaction) {
                $currencyCode = (string) $transaction->currency;
                $displayCurrency = $this->formatCurrencyId($currencyCode);
                $amount = (float) $transaction->amount;

                $date = $transaction->date;
                if (! $date && $transaction->created_at) {
                    $date = $transaction->created_at->toDateTimeString();
                }

                $type = $this->isSalesDescription($transaction->description)
                    ? 'sale'
                    : ($this->isRefundDescription($transaction->description) ? 'refund' : 'other');

                return [
                    'id' => $transaction->id,
                    'reference_id' => $transaction->reference_id,
                    'description' => $transaction->description,
                    'date' => $date,
                    'currency' => $displayCurrency,
                    'amount' => $amount,
                    'type' => $type,
                ];
            })->values()->all();

            $displayOrderCurrency = $this->formatCurrencyId($orderCurrencyId);
            $displayRecordedCurrency = in_array($recordedCurrencyCode, ['—', 'Mixed'], true)
                ? $recordedCurrencyCode
                : $this->formatCurrencyId($recordedCurrencyCode);

            return [
                'order_id' => $order->id,
                'order_reference' => $order->reference_id,
                'order_currency_id' => $orderCurrencyId,
                'order_currency' => $displayOrderCurrency,
                'order_amount' => $orderAmountForComparison,
                'order_amount_original' => $originalOrderAmount,
                'sales_currency_code' => $recordedCurrencyCode,
                'sales_currency' => $displayRecordedCurrency,
                'sales_total_currency' => $recordedTotal,
                'difference_currency' => $differenceCurrency,
                'transactions' => $transactionRows,
                'primary_transaction_type' => $primaryType,
            ];
        })->filter(function ($row) {
            $difference = $row['difference_currency'];
            $recordedTotal = $row['sales_total_currency'];
            $salesCurrency = $row['sales_currency'] ?? null;
            $transactions = $row['transactions'] ?? [];

            $hasMeaningfulDifference = is_numeric($difference) ? abs($difference) >= 0.01 : true;
            $recordedMissing = ! is_numeric($recordedTotal) || ($salesCurrency === 'Mixed');
            $hasTransactions = ! empty($transactions);

            return $hasMeaningfulDifference || $recordedMissing || ! $hasTransactions;
        })->sortByDesc(function ($row) {
            $difference = $row['difference_currency'] ?? null;
            return is_numeric($difference) ? abs($difference) : 0.0;
        })->values();
    }

    private function buildChargeMap(Collection $orders): array
    {
        $orderIds = $orders->pluck('id')->filter()->unique();

        if ($orderIds->isEmpty()) {
            return [];
        }

        $charges = Order_charge_model::query()
            ->selectRaw('charge_value_id, SUM(amount) AS charge_total')
            ->with('charge')
            ->whereIn('order_id', $orderIds)
            ->groupBy('charge_value_id')
            ->get();

        return $charges
            ->groupBy(function ($charge) {
                return trim(optional($charge->charge)->name ?? '');
            })
            ->filter(fn ($_, $key) => $key !== '')
            ->map(function ($group) {
                return (float) $group->sum('charge_total');
            })
            ->all();
    }

    private function buildDescriptionReport(Collection $transactions, array $chargeMap, Collection $orders): Collection
    {
        $orderPriceTotal = (float) $orders->sum(function ($order) {
            return (float) ($order->price ?? 0.0);
        });

        $rows = $transactions
            ->groupBy(function ($transaction) {
                return trim((string) $transaction->description) ?: '—';
            })
            ->map(function ($group, $description) use ($chargeMap, $orderPriceTotal) {
                $transactionTotal = (float) $group->sum('amount');
                $chargeTotal = $chargeMap[$description] ?? 0.0;

                if ($this->isSalesDescription($description)) {
                    $chargeTotal = $orderPriceTotal;
                }

                if ($chargeTotal > 0) {
                    $chargeTotal *= -1;
                }

                return [
                    'description' => $description,
                    'transaction_total' => $transactionTotal,
                    'charge_total' => $chargeTotal,
                    'difference' => $transactionTotal - $chargeTotal,
                ];
            })
            ->values();

        $salesRow = $rows->first(fn ($row) => $this->isSalesDescription($row['description']));

        $chargeRows = $rows->reject(fn ($row) => $this->isSalesDescription($row['description']))->values();

        return collect([
            'report' => $chargeRows,
            'sales' => $salesRow,
        ]);
    }

    private function buildRefundReport(Collection $refundTransactions, Collection $orders, Collection $referenceOrders): Collection
    {
        if ($refundTransactions->isEmpty()) {
            return collect([
                'summary' => [
                    'totals_by_currency' => collect(),
                    'is_single_currency' => true,
                    'primary_currency' => null,
                    'transaction_total' => null,
                    'order_total' => null,
                    'difference' => null,
                    'matched_count' => 0,
                    'missing_order_count' => 0,
                    'total' => 0,
                ],
                'details' => collect(),
            ]);
        }

        $ordersById = $orders->keyBy('id');
        $ordersByReference = $referenceOrders->keyBy('reference_id');

        $systemRefundsByOriginalOrderId = collect();

        if ($referenceOrders->isNotEmpty()) {
            $refundOrderIds = $referenceOrders->pluck('id')->filter()->unique();

            if ($refundOrderIds->isNotEmpty()) {
                $systemRefundsByOriginalOrderId = DB::table('order_items as refund_items')
                    ->join('order_items as sales_items', 'refund_items.linked_id', '=', 'sales_items.id')
                    ->whereIn('refund_items.order_id', $refundOrderIds)
                    ->select('sales_items.order_id as original_order_id', 'refund_items.order_id as refund_order_id')
                    ->get()
                    ->groupBy('original_order_id')
                    ->map(function ($group) use ($referenceOrders) {
                        $refundOrderIds = $group->pluck('refund_order_id')->unique();
                        return $referenceOrders->whereIn('id', $refundOrderIds);
                    });
            }
        }

        $details = $refundTransactions->map(function ($transaction) use ($ordersById, $ordersByReference, $systemRefundsByOriginalOrderId) {
            $transactionAmount = (float) $transaction->amount;
            $transactionCurrencyId = (string) ($transaction->currency ?? '');
            $transactionCurrency = $this->formatCurrencyId($transactionCurrencyId);

            $order = null;
            $matchSource = null;

            $orderCurrencyId = null;
            $orderCurrency = null;

            if (! empty($transaction->order_id) && $ordersById->has($transaction->order_id)) {
                $order = $ordersById->get($transaction->order_id);
                $matchSource = 'order_id';
            } elseif (! empty($transaction->reference_id) && $ordersByReference->has($transaction->reference_id)) {
                $order = $ordersByReference->get($transaction->reference_id);
                $matchSource = 'reference';
            }

            $orderId = null;
            $orderReference = null;
            $orderAmount = 0.0;
            $salesOrderReference = null;

            if ($order) {
                $orderId = $order->id;
                $orderReference = $order->reference_id;
                $salesOrderReference = $order->reference_id;
                $orderAmount = (float) ($order->price ?? 0.0);
                $orderCurrencyId = (string) ($order->currency ?? '');
                $orderCurrency = $this->formatCurrencyId($orderCurrencyId);
            } elseif (! empty($transaction->order_id) && $systemRefundsByOriginalOrderId->has($transaction->order_id)) {
                $linkedRefunds = $systemRefundsByOriginalOrderId->get($transaction->order_id);
                $orderAmount = (float) $linkedRefunds->sum('price');
                $matchSource = 'system_refund';

                if ($ordersById->has($transaction->order_id)) {
                    $originalOrder = $ordersById->get($transaction->order_id);
                    $orderId = $originalOrder->id;
                    $salesOrderReference = $originalOrder->reference_id;
                    $orderReference = $linkedRefunds->first()->reference_id ?? null;
                    $orderCurrencyId = (string) ($originalOrder->currency ?? '');
                    $orderCurrency = $this->formatCurrencyId($orderCurrencyId);
                }
            }

            return [
                'transaction_id' => $transaction->id,
                'transaction_reference' => $transaction->reference_id,
                'transaction_description' => (string) $transaction->description,
                'transaction_currency_id' => $transactionCurrencyId,
                'transaction_currency' => $transactionCurrency,
                'transaction_amount' => $transactionAmount,
                'order_found' => (bool) $order || $matchSource === 'system_refund',
                'match_source' => $matchSource,
                'order_id' => $orderId,
                'order_reference' => $orderReference,
                'sales_order_reference' => $salesOrderReference,
                'order_currency_id' => $orderCurrencyId,
                'order_currency' => $orderCurrency,
                'order_amount' => $orderAmount,
                'difference' => $transactionAmount + $orderAmount,
            ];
        })->values();

        $totalsByCurrency = $details
            ->groupBy(function ($row) {
                $currencyId = $row['transaction_currency_id'] ?? null;
                $currencyId = is_null($currencyId) || $currencyId === '' ? '—' : (string) $currencyId;

                return $currencyId;
            })
            ->map(function ($group, $currencyId) {
                $currencyLabel = $currencyId === '—'
                    ? '—'
                    : $this->formatCurrencyId($currencyId);

                return [
                    'currency_id' => $currencyId,
                    'currency' => $currencyLabel,
                    'transaction_total' => (float) $group->sum('transaction_amount'),
                    'order_total' => (float) $group->sum('order_amount'),
                    'difference' => (float) $group->sum(function ($row) {
                        return ($row['transaction_amount'] ?? 0) + ($row['order_amount'] ?? 0);
                    }),
                ];
            })
            ->values();

        $isSingleCurrency = $totalsByCurrency->count() === 1;
        $primaryRow = $isSingleCurrency ? $totalsByCurrency->first() : null;

        return collect([
            'summary' => [
                'totals_by_currency' => $totalsByCurrency,
                'is_single_currency' => $isSingleCurrency,
                'primary_currency' => $primaryRow['currency'] ?? null,
                'transaction_total' => $primaryRow['transaction_total'] ?? null,
                'order_total' => $primaryRow['order_total'] ?? null,
                'difference' => $primaryRow['difference'] ?? null,
                'matched_count' => $details->where('order_found', true)->count(),
                'missing_order_count' => $details->where('order_found', false)->count(),
                'total' => $details->count(),
            ],
            'details' => $details,
        ]);
    }

    private function currencyLabels(): array
    {
        if ($this->currencyLabelCache !== null) {
            return $this->currencyLabelCache;
        }

    $this->currencyLabelCache = \App\Models\Currency_model::query()
            ->get()
            ->mapWithKeys(function ($currency) {
                $symbol = trim((string) ($currency->symbol ?? ''));
                $code = trim((string) ($currency->code ?? ''));
                $label = $symbol !== '' ? $symbol : ($code !== '' ? $code : (string) $currency->id);

                return [
                    (string) $currency->id => $label,
                ];
            })
            ->all();

        return $this->currencyLabelCache;
    }

    private function formatCurrencyId($currencyId): string
    {
        if ($currencyId === null) {
            return '—';
        }

        $value = trim((string) $currencyId);

        if ($value === '' || $value === '—' || strcasecmp($value, 'mixed') === 0) {
            return $value === '' ? '—' : $value;
        }

        $labels = $this->currencyLabels();

        return $labels[$value] ?? $value;
    }

    private function normalizeDescription($description): string
    {
        return Str::lower(trim((string) $description));
    }

    private function isSalesDescription($description): bool
    {
        return $this->normalizeDescription($description) === 'sales';
    }

    private function isRefundDescription($description): bool
    {
        return in_array($this->normalizeDescription($description), ['refund', 'refunds'], true);
    }

    private function buildOrderWiseReport(Collection $transactions, Collection $orders, string $descriptionFilter): Collection
    {
        $normalizedFilter = $this->normalizeDescription($descriptionFilter);

        // Handle both singular and plural variations
        $filterVariations = [$normalizedFilter];
        if (substr($normalizedFilter, -1) === 'k') {
            // regularization_chargeback -> regularization_chargebacks
            $filterVariations[] = $normalizedFilter . 's';
        } elseif (substr($normalizedFilter, -1) === 't') {
            // credit_request -> credit_requests
            $filterVariations[] = $normalizedFilter . 's';
        } else {
            $filterVariations[] = $normalizedFilter . 's';
        }

        $filteredTransactions = $transactions->filter(function ($transaction) use ($filterVariations) {
            $normalized = $this->normalizeDescription($transaction->description);
            return in_array($normalized, $filterVariations, true);
        });

        if ($filteredTransactions->isEmpty()) {
            return collect([
                'summary' => [
                    'transaction_total' => 0,
                    'charge_total' => 0,
                    'difference_total' => 0,
                    'count' => 0,
                ],
                'details' => collect(),
            ]);
        }

        $transactionsWithOrders = $filteredTransactions
            ->filter(fn ($transaction) => !empty($transaction->order_id))
            ->load('order:id,reference_id,price,currency');

        $details = $transactionsWithOrders
            ->groupBy('order_id')
            ->map(function ($group) {
                $transaction = $group->first();
                $order = $transaction->order;
                $transactionTotal = (float) $group->sum('amount');
                $orderAmount = $order ? (float) ($order->price ?? 0) : 0;

                $currencies = $group->pluck('currency')->filter()->unique();
                $currency = $currencies->count() === 1 ? $this->formatCurrencyId($currencies->first()) : 'Mixed';

                return [
                    'order_id' => $transaction->order_id,
                    'order_reference' => $order ? $order->reference_id : '—',
                    'currency' => $currency,
                    'transaction_total' => $transactionTotal,
                    'charge_total' => $orderAmount,
                    'difference' => $transactionTotal - $orderAmount,
                    'transaction_count' => $group->count(),
                ];
            })
            ->values();

        return collect([
            'summary' => [
                'transaction_total' => (float) $details->sum('transaction_total'),
                'charge_total' => (float) $details->sum('charge_total'),
                'difference_total' => (float) $details->sum('difference'),
                'count' => $details->count(),
            ],
            'details' => $details,
        ]);
    }
}

