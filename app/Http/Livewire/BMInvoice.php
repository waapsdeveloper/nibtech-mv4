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
        ->onEachSide(5)
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


        $transactions = Account_transaction_model::where('process_id', $process_id)->get();

        $chargeMap = Order_charge_model::query()
            ->selectRaw('charge_value_id, SUM(amount) AS charge_total')
            ->with('charge')
            ->whereIn('order_id', $transactions->pluck('order_id')->filter()->unique())
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

