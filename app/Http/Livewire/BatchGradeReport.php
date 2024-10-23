<?php

namespace App\Http\Livewire;

use App\Exports\B2COrderReportExport;
use App\Exports\BatchInitialReportExport;
use App\Exports\BatchReportExport;
use App\Exports\OrderReportExport;
use App\Models\Brand_model;
use App\Models\Category_model;
use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Models\Order_model;
use App\Models\Order_item_model;
use App\Models\Products_model;
use App\Models\Color_model;
use App\Models\Currency_model;
use App\Models\Customer_model;
use App\Models\Storage_model;
use App\Models\Grade_model;
use App\Models\Multi_type_model;
use App\Models\Variation_model;
use App\Models\Stock_model;
use Symfony\Component\HttpFoundation\Request;
use Maatwebsite\Excel\Facades\Excel;
use TCPDF;

class BatchGradeReport extends Component
{
    public function mount()
    {

    }
    public function render(Request $request)
    {
        $data['grades'] = Grade_model::pluck('name', 'id');

        $data['batch_grade_reports'] = Stock_model::select('variation.grade as grade', 'orders.id as order_id', 'orders.reference_id as reference_id', 'orders.reference as reference', 'customer.first_name as vendor', DB::raw('COUNT(*) as quantity'))
        ->join('variation', 'stock.variation_id', '=', 'variation.id')
        ->join('orders', 'stock.order_id', '=', 'orders.id')
        ->join('customer', 'orders.customer_id', '=', 'customer.id')
        ->groupBy('variation.grade', 'orders.id', 'orders.reference_id', 'orders.reference', 'customer.first_name')
        ->orderByDesc('order_id')
        ->get();

        return view('livewire.reports.batch-grade-report')->with($data);
    }
}
