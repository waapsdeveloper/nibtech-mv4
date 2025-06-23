<?php

namespace App\Http\Livewire\Dashboard;

use App\Models\Order_item_model;
use Livewire\Component;
use App\Models\Stock_model;
use App\Models\Variation_model;
use App\Models\Order_model;
use Illuminate\Support\Facades\DB;

class InventoryOverviewWidget extends Component
{
    public $gradedInventory = [];
    public $listedInventory = 0;
    public $pendingOrdersCount = [];
    public $purchaseStatus = [
        2 => '(Pending)',
        3 => '',
    ];

    public function mount()
    {
        $aftersale = Order_item_model::whereHas('order', function ($q) {
            $q->where('order_type_id', 4)->where('status', '<', 3);
        })->pluck('stock_id')->toArray();

        if (session('user')->hasPermission('dashboard_view_inventory')) {
            $this->gradedInventory = Stock_model::select(
                'grade.name as grade',
                'variation.grade as grade_id',
                'orders.status as status_id',
                DB::raw('COUNT(*) as quantity')
            )
            ->whereNotIn('stock.id', $aftersale)
            ->where('stock.status', 1)
            ->join('variation', 'stock.variation_id', '=', 'variation.id')
            ->join('grade', 'variation.grade', '=', 'grade.id')
            ->join('orders', 'stock.order_id', '=', 'orders.id')
            ->groupBy('variation.grade', 'grade.name', 'orders.status')
            ->orderBy('grade_id')
            ->get();
        }

        if (session('user')->hasPermission('dashboard_view_listing_total')) {
            $this->listedInventory = Variation_model::where('listed_stock', '>', 0)->sum('listed_stock');
        }

        if (session('user')->hasPermission('dashboard_view_pending_orders')) {
            $this->pendingOrdersCount = Order_model::where('status', 2)
                ->groupBy('order_type_id')
                ->select('order_type_id', DB::raw('COUNT(id) as count'), DB::raw('SUM(price) as price'))
                ->orderBy('order_type_id', 'asc')
                ->get();
        }
    }

    public function render()
    {
        return view('livewire.dashboard.inventory-overview-widget');
    }
}
