<?php

namespace App\Http\Livewire\V2;

use Livewire\Component;
use App\Models\V2\MarketplaceStockLock;
use App\Models\V2\MarketplaceStockModel;
use App\Models\Order_model;
use App\Models\Variation_model;
use Illuminate\Support\Facades\DB;

class StockLocks extends Component
{
    public $orderId = null;
    public $variationId = null;
    public $marketplaceId = null;
    public $showAll = false;

    public function mount($orderId = null, $variationId = null, $marketplaceId = null, $showAll = false)
    {
        $this->orderId = $orderId;
        $this->variationId = $variationId;
        $this->marketplaceId = $marketplaceId;
        $this->showAll = $showAll;
    }

    /**
     * Index page for stock locks dashboard
     */
    public function index()
    {
        $this->showAll = true;
        
        $data['title_page'] = "Stock Locks Dashboard";
        session()->put('page_title', $data['title_page']);
        
        return view('livewire.v2.stock-locks-dashboard')->with($data);
    }

    public function render()
    {
        $query = MarketplaceStockLock::with([
            'marketplaceStock.marketplace',
            'marketplaceStock.variation.product',
            'order',
            'orderItem'
        ]);

        if ($this->orderId) {
            $query->where('order_id', $this->orderId);
        }

        if ($this->variationId) {
            $query->where('variation_id', $this->variationId);
        }

        if ($this->marketplaceId) {
            $query->where('marketplace_id', $this->marketplaceId);
        }

        if (!$this->showAll) {
            $query->where('lock_status', 'locked');
        }

        $locks = $query->orderBy('locked_at', 'desc')->get();

        // Get summary statistics
        $summary = [
            'total_locked' => $locks->where('lock_status', 'locked')->sum('quantity_locked'),
            'total_consumed' => $locks->where('lock_status', 'consumed')->sum('quantity_locked'),
            'total_released' => $locks->where('lock_status', 'released')->sum('quantity_locked'),
            'active_locks_count' => $locks->where('lock_status', 'locked')->count(),
        ];

        return view('livewire.v2.stock-locks', [
            'locks' => $locks,
            'summary' => $summary
        ]);
    }

    /**
     * Get stock locks for a specific order
     */
    public static function getLocksForOrder($orderId)
    {
        return MarketplaceStockLock::where('order_id', $orderId)
            ->with(['marketplaceStock.marketplace', 'orderItem.variation'])
            ->get();
    }

    /**
     * Get stock locks for a specific variation
     */
    public static function getLocksForVariation($variationId, $marketplaceId = null)
    {
        $query = MarketplaceStockLock::where('variation_id', $variationId)
            ->where('lock_status', 'locked')
            ->with(['marketplaceStock.marketplace', 'order']);

        if ($marketplaceId) {
            $query->where('marketplace_id', $marketplaceId);
        }

        return $query->get();
    }
}

