<?php

namespace App\Http\Livewire\Dashboard;

use Livewire\Component;
use App\Models\Order_item_model;
use App\Models\Variation_model;
use App\Models\Products_model;
use App\Models\Color_model;
use App\Models\Grade_model;
use App\Models\Storage_model;
use App\Models\Admin_model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TopSellingProducts extends Component
{
    public $perPage = 10;
    public $top_products = [];
    public $products;
    public $colors;
    public $grades;
    public $storages;
    public $start_date;
    public $end_date;

    public function mount()
    {
        $this->perPage = request('per_page', 10);
        $this->start_date = request('start_date', now()->startOfMonth()->format('Y-m-d'));
        $this->end_date = request('end_date', now()->endOfDay()->format('Y-m-d'));

        $this->products = session('dropdown_data')['products'];
        $this->colors = session('dropdown_data')['colors'];
        $this->grades = session('dropdown_data')['grades'];
        $this->storages = session('dropdown_data')['storages'];

        $this->loadTopProducts();
    }

    public function updatedPerPage()
    {
        $this->loadTopProducts();
    }

    public function loadTopProducts()
    {
        $variation_ids = Variation_model::withoutGlobalScope('Status_not_3_scope')
            ->when(request('product'), fn($q) => $q->where('product_id', request('product')))
            ->when(request('sku'), fn($q) => $q->where('sku', 'LIKE', '%' . request('sku') . '%'))
            ->when(request('storage'), fn($q) => $q->where('storage', 'LIKE', request('storage') . '%'))
            ->when(request('color'), fn($q) => $q->where('color', 'LIKE', request('color') . '%'))
            ->when(request('grade'), fn($q) => $q->where('grade', 'LIKE', request('grade') . '%'))
            ->when(request('category'), fn($q) => $q->whereHas('product', fn($qu) => $qu->where('category', request('category'))))
            ->when(request('brand'), fn($q) => $q->whereHas('product', fn($qu) => $qu->where('brand', request('brand'))))
            ->pluck('id');

        $this->top_products = Order_item_model::whereIn('variation_id', $variation_ids)
            ->whereHas('order', fn($q) =>
                $q->where(['order_type_id' => 3, 'currency' => 4])
                  ->whereBetween('created_at', [$this->start_date . ' 00:00:00', $this->end_date . ' 23:59:59'])
            )
            ->select('variation_id', DB::raw('SUM(quantity) as total_quantity_sold'), DB::raw('AVG(price) as average_price'))
            ->groupBy('variation_id')
            ->orderByDesc('total_quantity_sold')
            ->take($this->perPage)
            ->get();
    }

    public function render()
    {
        return view('livewire.dashboard.top-selling-products');
    }
}
