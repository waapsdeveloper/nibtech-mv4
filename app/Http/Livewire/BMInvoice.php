<?php

namespace App\Http\Livewire;

use App\Models\Account_transaction_model;
use App\Models\Order_charge_model;
use App\Models\Order_model;
use App\Models\Process_model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;

class BMInvoice extends Component
{
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
            ->orderBy('reference_id', 'desc')
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

        $additionalRefundOrders = collect();

        if ($refundTransactions->isNotEmpty()) {
            $refundReferenceIds = $refundTransactions
                ->filter(fn ($transaction) => empty($transaction->order_id) && ! empty($transaction->reference_id))
                ->pluck('reference_id')
                ->filter()
                ->unique();

            if ($refundReferenceIds->isNotEmpty()) {
                $additionalRefundOrders = Order_model::whereIn('reference_id', $refundReferenceIds)->get(['id', 'price', 'currency', 'reference_id']);
            }
        }

        $descriptionSummary = $this->buildDescriptionReport($transactions, $chargeMap, $orders);
        $refundReport = $this->buildRefundReport($refundTransactions, $orders, $additionalRefundOrders);

        return [
            'duplicateTransactions' => $duplicateTransactions,
            'salesVsOrders' => $this->summarizeSalesVsOrders($orders, $salesTransactions),
            'orderComparisons' => $this->buildOrderComparisons($orders, $salesTransactions),
            'report' => $descriptionSummary->get('report'),
            'reportSalesRow' => $descriptionSummary->get('sales'),
            'refundReport' => $refundReport,
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
        $orderIds = $transactions->pluck('order_id')->filter()->unique();

        if ($orderIds->isEmpty()) {
            return collect();
        }

        return Order_model::whereIn('id', $orderIds)->get(['id', 'price', 'currency', 'reference_id']);
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
                    'currency_id' => $currencyId,
                    'currency' => (string) $currencyId,
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

    private function buildOrderComparisons(Collection $orders, Collection $salesTransactions): Collection
    {
        $salesByOrder = $salesTransactions->groupBy('order_id')->map(function ($group) {
            return [
                'total' => (float) $group->sum('amount'),
                'transactions' => $group,
            ];
        });

        return $orders->map(function ($order) use ($salesByOrder) {
            $salesData = $salesByOrder->get($order->id, [
                'total' => 0.0,
                'transactions' => collect(),
            ]);

            $orderAmount = (float) ($order->price ?? 0);

            $salesTotal = (float) ($salesData['total'] ?? 0.0);

            $salesTransactionsCollection = $salesData['transactions'] instanceof Collection
                ? $salesData['transactions']
                : collect($salesData['transactions']);

            $salesCurrencyIds = $salesTransactionsCollection->pluck('currency')->filter()->unique();

            if ($salesCurrencyIds->isEmpty()) {
                $salesCurrencyCode = $order->currency;
                $salesTotalCurrency = 0.0;
                $differenceCurrency = is_null($order->currency) ? null : (0.0 - $orderAmount);
            } elseif ($salesCurrencyIds->count() === 1) {
                $singleCurrencyId = $salesCurrencyIds->first();
                $salesCurrencyCode = (string) $singleCurrencyId;
                $salesTotalCurrency = $salesTotal;
                $differenceCurrency = ((string) $singleCurrencyId === (string) $order->currency)
                    ? ($salesTotal - $orderAmount)
                    : null;
            } else {
                $salesCurrencyCode = 'Mixed';
                $salesTotalCurrency = null;
                $differenceCurrency = null;
            }

            $transactions = $salesTransactionsCollection->map(function ($transaction) {
                $currencyCode = (string) $transaction->currency;
                $amount = (float) $transaction->amount;

                $date = $transaction->date;
                if (! $date && $transaction->created_at) {
                    $date = $transaction->created_at->toDateTimeString();
                }

                return [
                    'id' => $transaction->id,
                    'reference_id' => $transaction->reference_id,
                    'description' => $transaction->description,
                    'date' => $date,
                    'currency' => $currencyCode,
                    'amount' => $amount,
                ];
            })->values()->all();

            return [
                'order_id' => $order->id,
                'order_reference' => $order->reference_id,
                'order_currency' => $order->currency,
                'order_amount' => $orderAmount,
                'sales_currency' => $salesCurrencyCode,
                'sales_total_currency' => $salesTotalCurrency,
                'difference_currency' => $differenceCurrency,
                'transactions' => $transactions,
            ];
        })->sortByDesc(function ($row) {
            return abs($row['difference_currency'] ?? 0.0);
        })->values();
    }

    private function buildChargeMap(Collection $orders): array
    {
        $orderIds = $orders->pluck('id')->filter()->unique();

        if ($orderIds->isEmpty()) {
            return [];
        }

        return Order_charge_model::query()
            ->selectRaw('charge_value_id, SUM(amount) AS charge_total')
            ->with('charge')
            ->whereIn('order_id', $orderIds)
            ->groupBy('charge_value_id')
            ->get()
            ->mapWithKeys(fn ($charge) => [
                trim(optional($charge->charge)->name ?? '') => (float) $charge->charge_total,
            ])
            ->filter(fn ($_, $key) => $key !== '')
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
                    'difference' => abs($transactionTotal) - abs($chargeTotal),
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

        $details = $refundTransactions->map(function ($transaction) use ($ordersById, $ordersByReference) {
            $transactionAmount = (float) $transaction->amount;
            $transactionCurrency = (string) $transaction->currency;

            $order = null;
            $matchSource = null;

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
            $orderCurrency = null;

            if ($order) {
                $orderId = $order->id;
                $orderReference = $order->reference_id;
                $orderAmount = (float) ($order->price ?? 0.0);
                $orderCurrency = (string) $order->currency;
            }

            return [
                'transaction_id' => $transaction->id,
                'transaction_reference' => $transaction->reference_id,
                'transaction_currency' => $transactionCurrency,
                'transaction_amount' => $transactionAmount,
                'order_found' => (bool) $order,
                'match_source' => $matchSource,
                'order_id' => $orderId,
                'order_reference' => $orderReference,
                'order_currency' => $orderCurrency,
                'order_amount' => $orderAmount,
                'difference' => $transactionAmount - $orderAmount,
            ];
        })->values();

        $totalsByCurrency = $details
            ->groupBy('transaction_currency')
            ->map(function ($group, $currency) {
                return [
                    'currency' => (string) $currency,
                    'transaction_total' => (float) $group->sum('transaction_amount'),
                    'order_total' => (float) $group->sum('order_amount'),
                    'difference' => (float) $group->sum(function ($row) {
                        return ($row['transaction_amount'] ?? 0) - ($row['order_amount'] ?? 0);
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
}

