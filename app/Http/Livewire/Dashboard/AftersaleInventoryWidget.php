<?php

namespace App\Http\Livewire\Dashboard;

use Livewire\Component;
use App\Models\Order_model;
use App\Models\RepairPartUsage;
use App\Models\Stock_model;
use App\Models\Order_item_model;
use Illuminate\Support\Facades\DB;

class AftersaleInventoryWidget extends Component
{
    public $aftersaleInventory;
    public $returnsInProgress = 0;
    public $rma = 0;
    public $awaitingReplacement = 0;
    /** @var int Parts used in repair jobs (process_type_id = 9), links to Parts Inventory usage */
    public $partsUsedInRepairs = 0;
    public $readyToLoad = false;

    public function mount()
    {
        $this->aftersaleInventory = collect();
    }

    public function loadAftersaleMetrics()
    {
        if (! $this->userCanViewAftersale()) {
            return;
        }

        $this->readyToLoad = true;
        $this->hydrateMetrics();
    }

    public function refreshAftersaleMetrics()
    {
        if ($this->readyToLoad && $this->userCanViewAftersale()) {
            $this->hydrateMetrics();
        }
    }

    public function render()
    {
        return view('livewire.dashboard.aftersale-inventory-widget');
    }

    protected function userCanViewAftersale(): bool
    {
        $user = session('user');

        return $user ? $user->hasPermission('dashboard_view_aftersale_inventory') : false;
    }

    protected function hydrateMetrics(): void
    {
        $aftersaleStockIds = Order_item_model::whereHas('order', function ($query) {
                $query->where('order_type_id', 4)
                      ->where('status', '<', 3);
            })
            ->pluck('stock_id')
            ->toArray();

        $this->returnsInProgress = count($aftersaleStockIds);

        $rmaOrderIds = Order_model::whereIn('order_type_id', [2, 5])
            ->pluck('id')
            ->toArray();

        $this->rma = Stock_model::where('status', 2)
            ->whereDoesntHave('order_items', function ($query) use ($rmaOrderIds) {
                $query->whereIn('order_id', $rmaOrderIds);
            })
            ->whereHas('variation', function ($query) {
                $query->where('grade', 10);
            })
            ->count();

        $this->aftersaleInventory = Stock_model::select(
                'grade.name as grade',
                'variation.grade as grade_id',
                'orders.status as status_id',
                'stock.status as stock_status',
                DB::raw('COUNT(*) as quantity')
            )
            ->where('stock.status', 2)
            ->whereDoesntHave('sale_order', function ($query) {
                $query->where('customer_id', 3955);
            })
            ->whereHas('sale_order', function ($query) {
                $query->where('order_type_id', 3)
                      ->orWhere('reference_id', 999);
            })
            ->join('variation', 'stock.variation_id', '=', 'variation.id')
            ->whereIn('variation.grade', [8, 12, 17])
            ->join('grade', 'variation.grade', '=', 'grade.id')
            ->join('orders', 'stock.order_id', '=', 'orders.id')
            ->groupBy('variation.grade', 'grade.name', 'orders.status', 'stock.status')
            ->orderBy('grade_id')
            ->get();

        $replacementReferenceIds = Order_item_model::where('order_id', 8974)
            ->whereNotNull('reference_id')
            ->pluck('reference_id')
            ->toArray();

        $this->awaitingReplacement = Stock_model::where('status', 1)
            ->whereHas('order_items.order', function ($query) use ($replacementReferenceIds) {
                $query->where('status', 3)
                      ->where('order_type_id', 3)
                      ->whereNotIn('reference_id', $replacementReferenceIds);
            })
            ->count();

        // Parts inventory: usages linked to repair processes (process_type_id = 9)
        $this->partsUsedInRepairs = RepairPartUsage::whereHas('process', function ($query) {
            $query->where('process_type_id', 9);
        })->count();
    }
}
