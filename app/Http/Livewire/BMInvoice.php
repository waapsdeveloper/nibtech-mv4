<?php

namespace App\Http\Livewire;

use App\Exports\TopupsheetExport;
use Livewire\Component;
use App\Models\Variation_model;
use App\Models\Products_model;
use App\Models\Stock_model;
use App\Models\Order_item_model;
use App\Models\Customer_model;
use App\Models\Storage_model;
use App\Http\Controllers\ListingController;
use App\Models\Account_transaction_model;
use App\Models\Brand_model;
use App\Models\Category_model;
use App\Models\Color_model;
use App\Models\ExchangeRate;
use App\Models\Grade_model;
use App\Models\Listed_stock_verification_model;
use App\Models\Order_charge_model;
use App\Models\Process_model;
use App\Models\Process_stock_model;
use App\Models\Product_storage_sort_model;
use App\Models\Stock_operations_model;
use App\Models\Order_model;
use App\Models\Currency_model;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class BMInvoice extends Component
{

    public function mount()
    {
        $user_id = session('user_id');
        if($user_id == NULL){
            return redirect('index');
        }
    }
    public function render()
    {

        $data['title_page'] = "BM Invoices Report";
        session()->put('page_title', $data['title_page']);

        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 20;
        }

        $data['batches'] = Process_model::where('process_type_id', 19)->with(['admin', 'transactions'])
        ->when(request('start_date'), function ($q) {
            return $q->where('created_at', '>=', request('start_date'));
        })
        ->when(request('end_date'), function ($q) {
            return $q->where('created_at', '<=', request('end_date') . " 23:59:59");
        })
        ->when(request('batch_id'), function ($q) {
            return $q->where('reference_id', 'LIKE', request('batch_id') . '%');
        })
        ->when(request('status'), function ($q) {
            return $q->where('status', request('status'));
        })
        ->orderBy('reference_id', 'desc') // Secondary order by reference_id
        ->paginate($per_page)
        ->appends(request()->except('page'));


        // dd($data['orders']);
        return view('livewire.bm_invoice')->with($data);
    }



    public function invoice_detail($process_id){

        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', 300);
        ini_set('pdo_mysql.max_input_vars', '10000');

        if(str_contains(url()->previous(),url('bm_invoice')) && !str_contains(url()->previous(),'detail')){
            session()->put('previous', url()->previous());
        }
        $data['title_page'] = "BM Invoice Detail";
        session()->put('page_title', $data['title_page']);

        $process = Process_model::with('admin')->find($process_id);
        $data['process'] = $process;

        $transactions = Account_transaction_model::where('process_id', $process_id)->get();

        $seenKeys = [];
        $duplicateTransactions = collect();

        $transactions = $transactions->filter(function ($transaction) use (&$seenKeys, &$duplicateTransactions) {
            $normalizedDescription = Str::lower(trim((string) $transaction->description));
            $normalizedAmount = number_format((float) $transaction->amount, 4, '.', '');
            $key = implode('|', [
                $transaction->order_id ?? 'null',
                $normalizedDescription,
                $normalizedAmount,
                $transaction->currency ?? 'null',
            ]);

            if (isset($seenKeys[$key])) {
                $duplicateTransactions->push($transaction);
                return false;
            }

            $seenKeys[$key] = true;
            return true;
        })->values();

        $data['duplicateTransactions'] = $duplicateTransactions;

        $orderIds = $transactions->pluck('order_id')->filter()->unique();
        $orders = Order_model::whereIn('id', $orderIds)->get(['id', 'price', 'currency', 'reference_id']);

        $salesTransactions = $transactions->filter(function ($transaction) {
            return Str::lower(trim((string) $transaction->description)) === 'sales';
        });

        $currencyIds = $orders->pluck('currency')
            ->merge($salesTransactions->pluck('currency'))
            ->filter(function ($id) {
                return !is_null($id);
            })
            ->unique();

        $currencyMeta = $currencyIds->isEmpty()
            ? collect()
            : Currency_model::whereIn('id', $currencyIds)->get(['id', 'code'])->keyBy('id');

        $exchangeRates = $currencyMeta->isEmpty()
            ? []
            : ExchangeRate::whereIn('target_currency', $currencyMeta->pluck('code'))
                ->pluck('rate', 'target_currency')
                ->toArray();

        $baseCurrencyCode = ExchangeRate::query()->value('base_currency');

        if (!$baseCurrencyCode && $currencyMeta->isNotEmpty()) {
            $baseCurrencyCode = optional($currencyMeta->first())->code;
        }

        $baseCurrencyCode = $baseCurrencyCode ? strtoupper($baseCurrencyCode) : 'GBP';

        $convertToBase = function ($amount, $currencyId) use ($currencyMeta, $exchangeRates, $baseCurrencyCode) {
            $amount = (float) $amount;

            if ($amount == 0.0 || is_null($currencyId)) {
                return $amount;
            }

            $code = optional($currencyMeta->get($currencyId))->code;

            if (!$code) {
                return $amount;
            }

            if ($baseCurrencyCode && strtoupper((string) $code) === $baseCurrencyCode) {
                return $amount;
            }

            $rate = $exchangeRates[$code] ?? null;

            if (!$rate || (float) $rate == 0.0) {
                return $amount;
            }

            return $amount / (float) $rate;
        };

        $salesPerCurrency = $salesTransactions->groupBy('currency')->map(function ($group) {
            return (float) $group->sum('amount');
        });

        $orderPerCurrency = $orders->groupBy('currency')->map(function ($group) {
            return (float) $group->sum(function ($order) {
                return (float) ($order->price ?? 0);
            });
        });

        $salesTransactionTotalBase = $salesTransactions->sum(function ($transaction) use ($convertToBase) {
            return $convertToBase($transaction->amount, $transaction->currency);
        });

        $orderTotalBase = $orders->sum(function ($order) use ($convertToBase) {
            return $convertToBase($order->price, $order->currency);
        });

        $salesByOrder = $salesTransactions->groupBy('order_id')->map(function ($group) use ($convertToBase) {
            return [
                'total'       => (float) $group->sum('amount'),
                'total_base'  => $group->sum(function ($transaction) use ($convertToBase) {
                    return $convertToBase($transaction->amount, $transaction->currency);
                }),
                'transactions' => $group,
            ];
        });

        $currencyBreakdown = $currencyIds->map(function ($currencyId) use ($currencyMeta, $salesPerCurrency, $orderPerCurrency, $convertToBase) {
            $salesTotal = $salesPerCurrency[$currencyId] ?? 0.0;
            $orderTotalByCurrency = $orderPerCurrency[$currencyId] ?? 0.0;

            $salesTotalBase = $convertToBase($salesTotal, $currencyId);
            $orderTotalBase = $convertToBase($orderTotalByCurrency, $currencyId);

            return [
                'currency_id'        => $currencyId,
                'currency'           => optional($currencyMeta->get($currencyId))->code ?? (string) $currencyId,
                'sales_total'        => $salesTotal,
                'order_total'        => $orderTotalByCurrency,
                'difference'         => $salesTotal - $orderTotalByCurrency,
                'sales_total_base'   => $salesTotalBase,
                'order_total_base'   => $orderTotalBase,
                'difference_base'    => $salesTotalBase - $orderTotalBase,
            ];
        })->values();

        $data['salesVsOrders'] = [
            'transaction_total' => $salesTransactionTotalBase,
            'order_total'       => $orderTotalBase,
            'difference'        => $salesTransactionTotalBase - $orderTotalBase,
            'base_currency'     => $baseCurrencyCode,
            'breakdown'         => $currencyBreakdown,
        ];

        $orderComparisons = $orders->map(function ($order) use ($salesByOrder, $convertToBase, $currencyMeta) {
            $salesData = $salesByOrder->get($order->id);

            $orderAmount = (float) ($order->price ?? 0);
            $orderAmountBase = $convertToBase($orderAmount, $order->currency);
            $orderCurrencyCode = optional($currencyMeta->get($order->currency))->code ?? (string) $order->currency;

            $salesTotal = $salesData['total'] ?? 0.0;
            $salesTotalBase = $salesData['total_base'] ?? 0.0;

            $salesTransactionsCollection = $salesData['transactions'] ?? collect();
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
                if (!$date && $transaction->created_at) {
                    $date = $transaction->created_at->toDateTimeString();
                }

                return [
                    'id'             => $transaction->id,
                    'reference_id'   => $transaction->reference_id,
                    'description'    => $transaction->description,
                    'date'           => $date,
                    'currency'       => $currencyCode,
                    'amount'         => $amount,
                    'amount_base'    => $convertToBase($amount, $transaction->currency),
                ];
            })->values()->all();

            return [
                'order_id'            => $order->id,
                'order_reference'     => $order->reference_id,
                'order_currency'      => $orderCurrencyCode,
                'order_amount'        => $orderAmount,
                'order_amount_base'   => $orderAmountBase,
                'sales_currency'      => $salesCurrencyCode,
                'sales_total_currency'=> $salesTotalCurrency,
                'sales_total_base'    => $salesTotalBase,
                'difference_currency' => $differenceCurrency,
                'difference_base'     => $salesTotalBase - $orderAmountBase,
                'transactions'        => $transactions,
            ];
        })->sortByDesc(function ($row) {
            return abs($row['difference_base']);
        })->values();

        $data['orderComparisons'] = $orderComparisons;

        $chargeMap = Order_charge_model::query()
            ->selectRaw('charge_value_id, SUM(amount) AS charge_total')
            ->with('charge')
            ->whereIn('order_id', $orderIds)
            ->groupBy('charge_value_id')
            ->get()
            ->mapWithKeys(fn ($charge) => [
                trim(optional($charge->charge)->name ?? '') => (float) $charge->charge_total,
            ])
            ->filter(fn ($_, $key) => $key !== '');

        $data['report'] = Account_transaction_model::query()
            ->selectRaw('description, SUM(amount) AS transaction_total')
            ->where('process_id', $process_id)
            ->groupBy('description')
            ->get()
            ->map(function ($row) use ($chargeMap) {
                $description   = trim((string) $row->description) ?: '—';
                $txnTotal      = (float) $row->transaction_total;
                $chargeTotal   = $chargeMap[$description] ?? 0.0;

                return [
                    'description'        => $description,
                    'transaction_total'  => $txnTotal,
                    'charge_total'       => $chargeTotal,
                    'difference'         => abs($txnTotal) - abs($chargeTotal),
                ];
            })
            ->values();

        return view('livewire.bm_invoice_detail')->with($data);

    }


}

