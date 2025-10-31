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

        $orderIds = $transactions->pluck('order_id')->filter()->unique();
        $orders = Order_model::whereIn('id', $orderIds)->get(['id', 'price', 'currency', 'reference_id']);

        $salesTransactions = $transactions->filter(function ($transaction) {
            return Str::lower(trim((string) $transaction->description)) === 'sales';
        });

        $salesTransactionTotal = (float) $salesTransactions->sum('amount');
        $orderTotal = (float) $orders->sum(function ($order) {
            return (float) ($order->price ?? 0);
        });

        $currencyIds = $orders->pluck('currency')->merge($salesTransactions->pluck('currency'))->filter(function ($id) {
            return !is_null($id);
        })->unique();

        $currencyLabels = $currencyIds->isEmpty()
            ? collect()
            : Currency_model::whereIn('id', $currencyIds)->pluck('code', 'id');

        $salesPerCurrency = $salesTransactions->groupBy('currency')->map(function ($group) {
            return (float) $group->sum('amount');
        });

        $orderPerCurrency = $orders->groupBy('currency')->map(function ($group) {
            return (float) $group->sum(function ($order) {
                return (float) ($order->price ?? 0);
            });
        });

        $currencyBreakdown = $currencyIds->map(function ($currencyId) use ($currencyLabels, $salesPerCurrency, $orderPerCurrency) {
            $salesTotal = $salesPerCurrency[$currencyId] ?? 0.0;
            $orderTotalByCurrency = $orderPerCurrency[$currencyId] ?? 0.0;

            return [
                'currency'    => $currencyLabels[$currencyId] ?? (string) $currencyId,
                'sales_total' => $salesTotal,
                'order_total' => $orderTotalByCurrency,
                'difference'  => abs($salesTotal) - abs($orderTotalByCurrency),
            ];
        })->values();

        $data['salesVsOrders'] = [
            'transaction_total' => $salesTransactionTotal,
            'order_total'       => $orderTotal,
            'difference'        => abs($salesTransactionTotal) - abs($orderTotal),
            'breakdown'         => $currencyBreakdown,
        ];

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

