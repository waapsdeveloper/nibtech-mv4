<?php

namespace App\Http\Livewire;

use App\Models\Account_transaction_model;
use App\Models\Currency_model;
use App\Models\ExchangeRate;
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

        $currencyContext = $this->buildCurrencyContext($orders, $salesTransactions);
        $chargeMap = $this->buildChargeMap($orders);

        return [
            'duplicateTransactions' => $duplicateTransactions,
            'salesVsOrders' => $this->summarizeSalesVsOrders($orders, $salesTransactions, $currencyContext),
            'orderComparisons' => $this->buildOrderComparisons($orders, $salesTransactions, $currencyContext),
            'report' => $this->buildDescriptionReport($processId, $chargeMap, $orders),
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

    private function buildCurrencyContext(Collection $orders, Collection $salesTransactions): array
    {
        $currencyIds = $orders->pluck('currency')
            ->merge($salesTransactions->pluck('currency'))
            ->filter(fn ($id) => ! is_null($id))
            ->unique()
            ->values();

        $currencyMeta = $currencyIds->isEmpty()
            ? collect()
            : Currency_model::whereIn('id', $currencyIds)->get(['id', 'code'])->keyBy('id');

        $exchangeRates = $currencyMeta->isEmpty()
            ? []
            : ExchangeRate::whereIn('target_currency', $currencyMeta->pluck('code'))
                ->pluck('rate', 'target_currency')
                ->toArray();

        $baseCurrencyCode = ExchangeRate::query()->value('base_currency');

        if (! $baseCurrencyCode && $currencyMeta->isNotEmpty()) {
            $baseCurrencyCode = optional($currencyMeta->first())->code;
        }

        $baseCurrencyCode = $baseCurrencyCode ? strtoupper($baseCurrencyCode) : 'GBP';

        $convertToBase = function ($amount, $currencyId) use ($currencyMeta, $exchangeRates, $baseCurrencyCode) {
            $amount = (float) $amount;

            if ($amount == 0.0 || is_null($currencyId)) {
                return $amount;
            }

            $currencyCode = optional($currencyMeta->get($currencyId))->code;

            if (! $currencyCode) {
                return $amount;
            }

            if ($baseCurrencyCode && strtoupper((string) $currencyCode) === $baseCurrencyCode) {
                return $amount;
            }

            $rate = $exchangeRates[$currencyCode] ?? null;

            if (! $rate || (float) $rate == 0.0) {
                return $amount;
            }

            return $amount / (float) $rate;
        };

        return [
            'currencyIds' => $currencyIds,
            'currencyMeta' => $currencyMeta,
            'baseCurrency' => $baseCurrencyCode,
            'convertToBase' => $convertToBase,
        ];
    }

    private function summarizeSalesVsOrders(Collection $orders, Collection $salesTransactions, array $currencyContext): array
    {
        $convertToBase = $currencyContext['convertToBase'];
        $currencyMeta = $currencyContext['currencyMeta'];
        $currencyIds = $currencyContext['currencyIds'];

        $salesTotalBase = $salesTransactions->sum(function ($transaction) use ($convertToBase) {
            return $convertToBase($transaction->amount, $transaction->currency);
        });

        $orderTotalBase = $orders->sum(function ($order) use ($convertToBase) {
            return $convertToBase($order->price, $order->currency);
        });

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

        $currencyBreakdown = $currencyIds->map(function ($currencyId) use ($currencyMeta, $salesPerCurrency, $ordersPerCurrency, $convertToBase) {
            $salesTotal = $salesPerCurrency->get($currencyId, 0.0);
            $orderTotal = $ordersPerCurrency->get($currencyId, 0.0);

            $salesTotalBase = $convertToBase($salesTotal, $currencyId);
            $orderTotalBase = $convertToBase($orderTotal, $currencyId);

            return [
                'currency_id' => $currencyId,
                'currency' => optional($currencyMeta->get($currencyId))->code ?? (string) $currencyId,
                'sales_total' => $salesTotal,
                'order_total' => $orderTotal,
                'difference' => $salesTotal - $orderTotal,
                'sales_total_base' => $salesTotalBase,
                'order_total_base' => $orderTotalBase,
                'difference_base' => $salesTotalBase - $orderTotalBase,
            ];
        })->values();

        return [
            'transaction_total' => $salesTotalBase,
            'order_total' => $orderTotalBase,
            'difference' => $salesTotalBase - $orderTotalBase,
            'base_currency' => $currencyContext['baseCurrency'],
            'breakdown' => $currencyBreakdown,
        ];
    }

    private function buildOrderComparisons(Collection $orders, Collection $salesTransactions, array $currencyContext): Collection
    {
        $convertToBase = $currencyContext['convertToBase'];
        $currencyMeta = $currencyContext['currencyMeta'];

        $salesByOrder = $salesTransactions->groupBy('order_id')->map(function ($group) use ($convertToBase) {
            return [
                'total' => (float) $group->sum('amount'),
                'total_base' => $group->sum(function ($transaction) use ($convertToBase) {
                    return $convertToBase($transaction->amount, $transaction->currency);
                }),
                'transactions' => $group,
            ];
        });

        return $orders->map(function ($order) use ($salesByOrder, $convertToBase, $currencyMeta) {
            $salesData = $salesByOrder->get($order->id, [
                'total' => 0.0,
                'total_base' => 0.0,
                'transactions' => collect(),
            ]);

            $orderAmount = (float) ($order->price ?? 0);
            $orderAmountBase = $convertToBase($orderAmount, $order->currency);

            $orderCurrencyCode = optional($currencyMeta->get($order->currency))->code ?? (string) $order->currency;

            $salesTotal = (float) ($salesData['total'] ?? 0.0);
            $salesTotalBase = (float) ($salesData['total_base'] ?? 0.0);

            $salesTransactionsCollection = $salesData['transactions'] instanceof Collection
                ? $salesData['transactions']
                : collect($salesData['transactions']);

            $salesCurrencyIds = $salesTransactionsCollection->pluck('currency')->filter()->unique();

            if ($salesCurrencyIds->isEmpty()) {
                $salesCurrencyCode = $orderCurrencyCode;
                $salesTotalCurrency = 0.0;
                $differenceCurrency = is_null($order->currency) ? null : (0.0 - $orderAmount);
            } elseif ($salesCurrencyIds->count() === 1) {
                $singleCurrencyId = $salesCurrencyIds->first();
                $salesCurrencyCode = optional($currencyMeta->get($singleCurrencyId))->code ?? (string) $singleCurrencyId;
                $salesTotalCurrency = $salesTotal;
                $differenceCurrency = ((string) $singleCurrencyId === (string) $order->currency)
                    ? ($salesTotal - $orderAmount)
                    : null;
            } else {
                $salesCurrencyCode = 'Mixed';
                $salesTotalCurrency = null;
                $differenceCurrency = null;
            }

            $transactions = $salesTransactionsCollection->map(function ($transaction) use ($convertToBase, $currencyMeta) {
                $currencyCode = optional($currencyMeta->get($transaction->currency))->code ?? (string) $transaction->currency;
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
                    'amount_base' => $convertToBase($amount, $transaction->currency),
                ];
            })->values()->all();

            return [
                'order_id' => $order->id,
                'order_reference' => $order->reference_id,
                'order_currency' => $orderCurrencyCode,
                'order_amount' => $orderAmount,
                'order_amount_base' => $orderAmountBase,
                'sales_currency' => $salesCurrencyCode,
                'sales_total_currency' => $salesTotalCurrency,
                'sales_total_base' => $salesTotalBase,
                'difference_currency' => $differenceCurrency,
                'difference_base' => $salesTotalBase - $orderAmountBase,
                'transactions' => $transactions,
            ];
        })->sortByDesc(function ($row) {
            return abs($row['difference_base']);
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

    private function buildDescriptionReport(int $processId, array $chargeMap, Collection $orders): Collection
    {
        $orderPriceTotal = (float) $orders->sum(function ($order) {
            return (float) ($order->price ?? 0.0);
        });

        return Account_transaction_model::query()
            ->selectRaw('description, SUM(amount) AS transaction_total')
            ->where('process_id', $processId)
            ->groupBy('description')
            ->get()
            ->map(function ($row) use ($chargeMap, $orderPriceTotal) {
                $description = trim((string) $row->description) ?: '—';
                $transactionTotal = (float) $row->transaction_total;
                $chargeTotal = $chargeMap[$description] ?? 0.0;

                if ($this->isSalesDescription($description)) {
                    $chargeTotal = $orderPriceTotal;
                }

                if ($chargeTotal > 0 && $description !== 'sales') {
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
    }

    private function normalizeDescription($description): string
    {
        return Str::lower(trim((string) $description));
    }

    private function isSalesDescription($description): bool
    {
        return $this->normalizeDescription($description) === 'sales';
    }
}

