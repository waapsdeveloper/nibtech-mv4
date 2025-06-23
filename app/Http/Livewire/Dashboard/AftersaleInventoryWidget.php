<?php

namespace App\Http\Livewire\Dashboard;

use Livewire\Component;
use App\Models\Order_model;
use App\Models\Stock_model;
use App\Models\Order_item_model;
use Illuminate\Support\Facades\DB;

class AftersaleInventoryWidget extends Component
{
    public $aftersaleInventory = [];
    public $returnsInProgress = 0;
    public $rma = 0;
    public $awaitingReplacement = 0;

    public function mount()
    {
        if (!session('user')->hasPermission('dashboard_view_aftersale_inventory')) {
            return;
        }

        $aftersale = Order_item_model::whereHas('order', function ($q) {
            $q->where('order_type_id', 4)->where('status', '<', 3);
        })->pluck('stock_id')->toArray();

        $this->returnsInProgress = count($aftersale);

        $rmas = Order_model::whereIn('order_type_id', [2, 5])->pluck('id')->toArray();

        $this->rma = Stock_model::whereDoesntHave('order_items', function ($q) use ($rmas) {
                $q->whereIn('order_id', $rmas);
            })
            ->whereHas('variation', fn($q) => $q->where('grade', 10))
            ->where('status', 2)
            ->count();

        $this->aftersaleInventory = Stock_model::select(
                'grade.name as grade',
                'variation.grade as grade_id',
                'orders.status as status_id',
                'stock.status as stock_status',
                DB::raw('COUNT(*) as quantity')
            )
            ->where('stock.status', 2)
            ->whereDoesntHave('sale_order', fn($q) => $q->where('customer_id', 3955))
            ->whereHas('sale_order', function ($q) {
                $q->where('order_type_id', 3)
                  ->orWhere(['order_type_id' => 5, 'reference_id' => 999]);
            })
            ->join('variation', 'stock.variation_id', '=', 'variation.id')
            ->join('grade', 'variation.grade', '=', 'grade.id')
            ->whereIn('grade.id', [8, 12, 17])
            ->join('orders', 'stock.order_id', '=', 'orders.id')
            ->groupBy('variation.grade', 'grade.name', 'orders.status', 'stock.status')
            ->orderBy('grade_id')
            ->get();

        $replacements = Order_item_model::where(['order_id' => 8974])
            ->whereNotNull('reference_id')
            ->pluck('reference_id')
            ->toArray();

        $this->awaitingReplacement = Stock_model::where('status', 1)
            ->whereHas('order_items.order', function ($q) use ($replacements) {
                $q->where(['status' => 3, 'order_type_id' => 3])
                  ->whereNotIn('reference_id', $replacements);
            })->count();
    }

    public function render()
    {
        return view('livewire.dashboard.aftersale-inventory-widget');
    }
}
