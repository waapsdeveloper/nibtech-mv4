<?php

namespace App\Http\Livewire\Dashboard;

use App\Models\Order_item_model;
use Livewire\Component;
use App\Models\Process_stock_model;
use App\Models\Stock_model;
use App\Models\Variation_model;
use App\Models\Order_model;
use Illuminate\Support\Facades\DB;

class InventoryOverviewWidget extends Component
{
    public $gradedInventory;
    public $listedInventory = 0;
    public $shouldBeListed = 0;
    public $pendingOrdersCount;
    public $purchaseStatus = [
        2 => '(Pending)',
        3 => '',
    ];
    public $readyToLoad = false;
    public $canViewInventory = false;
    public $canViewListingTotal = false;
    public $canViewPendingOrders = false;

    public function mount()
    {
    $user = session()->get('user');

        if ($user) {
            $this->canViewInventory = $user->hasPermission('dashboard_view_inventory');
            $this->canViewListingTotal = $user->hasPermission('dashboard_view_listing_total');
            $this->canViewPendingOrders = $user->hasPermission('dashboard_view_pending_orders');
        }

        $this->gradedInventory = collect();
        $this->pendingOrdersCount = collect();
    }

    public function loadInventoryOverview()
    {
        if (! $this->userCanAccessWidget()) {
            return;
        }

        $this->readyToLoad = true;
        $this->hydrateInventoryOverview();
    }

    public function refreshInventoryOverview()
    {
        if ($this->readyToLoad && $this->userCanAccessWidget()) {
            $this->hydrateInventoryOverview();
        }
    }

    public function render()
    {
        return view('livewire.dashboard.inventory-overview-widget');
    }

    protected function userCanAccessWidget(): bool
    {
        return $this->canViewInventory || $this->canViewListingTotal || $this->canViewPendingOrders;
    }

    protected function hydrateInventoryOverview(): void
    {
        $aftersaleStockIds = [];

        if ($this->canViewInventory || $this->canViewListingTotal) {
            $aftersaleStockIds = Order_item_model::whereHas('order', function ($query) {
                    $query->where('order_type_id', 4)->where('status', '<', 3);
                })
                ->pluck('stock_id')
                ->toArray();
        }

        if ($this->canViewInventory || $this->canViewListingTotal) {
            $this->gradedInventory = Stock_model::select(
                    'grade.name as grade',
                    'variation.grade as grade_id',
                    'orders.status as status_id',
                    DB::raw('COUNT(*) as quantity')
                )
                ->when(! empty($aftersaleStockIds), function ($query) use ($aftersaleStockIds) {
                    $query->whereNotIn('stock.id', $aftersaleStockIds);
                })
                ->where('stock.status', 1)
                ->join('variation', 'stock.variation_id', '=', 'variation.id')
                ->join('grade', 'variation.grade', '=', 'grade.id')
                ->join('orders', 'stock.order_id', '=', 'orders.id')
                ->groupBy('variation.grade', 'grade.name', 'orders.status')
                ->orderBy('grade_id')
                ->get();
        } else {
            $this->gradedInventory = collect();
        }

        if ($this->canViewListingTotal) {
            $this->listedInventory = Variation_model::where('listed_stock', '>', 0)->sum('listed_stock');

            $this->shouldBeListed = max(
                0,
                $this->gradedInventory->where('grade_id', '<', 6)->sum('quantity')
                - Process_stock_model::whereHas('process', function ($query) {
                    $query->where('process_type_id', 22)->where('status', '<', 3);
                })->count()
                - Order_model::where('status', 2)->where('order_type_id', 3)->count()
            );
        } else {
            $this->listedInventory = 0;
            $this->shouldBeListed = 0;
        }

        if ($this->canViewPendingOrders) {
            $this->pendingOrdersCount = Order_model::where('status', 2)
                ->groupBy('order_type_id')
                ->select('order_type_id', DB::raw('COUNT(id) as count'), DB::raw('SUM(price) as price'))
                ->orderBy('order_type_id', 'asc')
                ->get();
        } else {
            $this->pendingOrdersCount = collect();
        }
    }
}
