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

class WeeklyEcommerceSalesGraph extends Component
{
    public function mount()
    {

    }
    public function render(Request $request)
    {

        $order = [];
        $dates = [];
        $k = 0;
        $today = date('d');
        for ($i = 5; $i >= 0; $i--) {
            $j = $i+1;
            $k++;
            $start = date('Y-m-25 23:00:00', strtotime("-".$j." months"));
            $end = date('Y-m-5 22:59:59', strtotime("-".$i." months"));
            $orders = Order_model::where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->count();
            $euro = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->where('currency',4);
            })->whereIn('status',[3,6])->sum('price');
            $pound = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->where('currency',5);
            })->whereIn('status',[3,6])->sum('price');
            $order[$k] = $orders;
            $eur[$k] = $euro;
            $gbp[$k] = $pound;
            $dates[$k] = date('25 M', strtotime("-".$j." months")) . " - " . date('05 M', strtotime("-".$i." months"));
            if($i == 0 && $today < 6){
                continue;
            }
            $k++;
            $start = date('Y-m-5 23:00:00', strtotime("-".$i." months"));
            $end = date('Y-m-15 22:59:59', strtotime("-".$i." months"));
            $orders = Order_model::where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->count();
            $euro = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->where('currency',4);
            })->whereIn('status',[3,6])->sum('price');
            $pound = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->where('currency',5);
            })->whereIn('status',[3,6])->sum('price');
            $order[$k] = $orders;
            $eur[$k] = $euro;
            $gbp[$k] = $pound;
            $dates[$k] = date('05 M', strtotime("-".$i." months")) . " - " . date('15 M', strtotime("-".$i." months"));
            if($i == 0 && $today < 16){
                continue;
            }
            $k++;
            $start = date('Y-m-15 23:00:00', strtotime("-".$i." months"));
            $end = date('Y-m-25 22:59:59', strtotime("-".$i." months"));
            $orders = Order_model::where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->count();
            $euro = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->where('currency',4);
            })->whereIn('status',[3,6])->sum('price');
            $pound = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->where('currency',5);
            })->whereIn('status',[3,6])->sum('price');
            $order[$k] = $orders;
            $eur[$k] = $euro;
            $gbp[$k] = $pound;
            $dates[$k] = date('15 M', strtotime("-".$i." months")) . " - " . date('25 M', strtotime("-".$i." months"));

        }
        echo '<script> sessionStorage.setItem("total2", "' . implode(',', $order) . '");</script>';
        echo '<script> sessionStorage.setItem("approved2", "' . implode(',', $eur) . '");</script>';
        echo '<script> sessionStorage.setItem("failed2", "' . implode(',', $gbp) . '");</script>';
        echo '<script> sessionStorage.setItem("dates2", "' . implode(',', $dates) . '");</script>';

        return view('livewire.reports.weekly-ecommerce-sales-graph');
    }
}
