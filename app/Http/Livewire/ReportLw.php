<?php
// filepath: c:\xampp\htdocs\nibritaintech\app\Http\Livewire\ReportLw.php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Order_model;
use App\Models\Order_item_model;
use App\Models\Products_model;
use App\Models\Customer_model;
use App\Models\Currency_model;
use App\Models\Grade_model;
use App\Models\Variation_model;
use App\Models\Stock_model;
use App\Models\Process_stock_model;
use App\Models\Process_model;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ReportLw extends Component
{
    use WithPagination;

    // Filters
    public $start_date;
    public $end_date;
    public $start_time = '00:00:00';
    public $end_time = '23:59:59';
    public $category = '';
    public $brand = '';
    public $product = '';
    public $storage = '';
    public $color = '';
    public $grade = '';
    public $vendor = '';
    public $batch = '';
    public $per_page = 20;

    // Report type
    public $report_type = 'sales_returns';

    // Data arrays
    public $aggregated_sales = [];
    public $aggregated_sales_cost = [];
    public $aggregated_returns = [];
    public $aggregated_return_cost = [];
    public $batch_grade_reports = [];

    // Computed properties cache
    public $variation_ids = [];
    public $is_filtered = false;

    protected $queryString = [
        'start_date',
        'end_date',
        'category',
        'brand',
        'product',
        'vendor',
        'report_type'
    ];

    public function mount()
    {
        if (!session()->has('rep') && session('user_id') != 1) {
            return redirect()->route('report.pass');
        }

        $this->start_date = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->end_date = Carbon::now()->format('Y-m-d');

        session()->put('page_title', 'Reports - Livewire');
    }

    public function updating($property)
    {
        // Reset pagination when filters change
        if (in_array($property, ['start_date', 'end_date', 'category', 'brand', 'product', 'vendor', 'batch'])) {
            $this->resetPage();
            $this->clearCache();
        }
    }

    public function updated($property)
    {
        if ($property === 'report_type') {
            $this->resetPage();
            $this->clearCache();
        }
    }

    private function clearCache()
    {
        $this->variation_ids = [];
        $this->is_filtered = false;
    }

    private function getVariationIds()
    {
        if (!empty($this->variation_ids)) {
            return $this->variation_ids;
        }

        $cacheKey = 'variation_ids_' . md5(serialize([
            $this->category,
            $this->brand,
            $this->product,
            $this->storage,
            $this->color,
            $this->grade,
            $this->vendor,
            $this->batch
        ]));

        $this->variation_ids = Cache::remember($cacheKey, 300, function () {
            $query = Variation_model::select('id');

            // Product filters
            $query->when($this->category || $this->brand, function ($q) {
                return $q->whereHas('product', function ($qu) {
                    $qu->when($this->category, fn($q) => $q->where('category', $this->category))
                       ->when($this->brand, fn($q) => $q->where('brand', $this->brand));
                });
            });

            // Vendor/batch filters
            $query->when($this->vendor || $this->batch, function ($q) {
                return $q->whereHas('stocks.order', function ($qu) {
                    $qu->when($this->vendor, fn($q) => $q->where('customer_id', $this->vendor))
                       ->when($this->batch, fn($q) => $q->where('reference_id', $this->batch));
                });
            });

            // Direct variation filters
            $query->when($this->product, fn($q) => $q->where('product_id', $this->product))
                  ->when($this->storage, fn($q) => $q->where('storage', $this->storage))
                  ->when($this->color, fn($q) => $q->where('color', $this->color))
                  ->when($this->grade, fn($q) => $q->where('grade', $this->grade));

            return $query->pluck('id')->toArray();
        });

        $this->is_filtered = !empty(array_filter([
            $this->category, $this->brand, $this->product,
            $this->storage, $this->color, $this->grade,
            $this->vendor, $this->batch
        ]));

        return $this->variation_ids;
    }

    public function getSalesData()
    {
        $start_datetime = $this->start_date . " " . $this->start_time;
        $end_datetime = $this->end_date . " " . $this->end_time;
        $variation_ids = $this->getVariationIds();

        // Optimized sales query using subquery approach
        $repair_process_ids = Process_model::where('process_type_id', 9)->pluck('id')->toArray();

        $subquery = DB::table('order_items')
            ->join('variation', 'variation.id', '=', 'order_items.variation_id')
            ->join('products', 'products.id', '=', 'variation.product_id')
            ->join('category', 'category.id', '=', 'products.category')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('stock', 'stock.id', '=', 'order_items.stock_id')
            ->leftJoin('orders as p_order', 'p_order.id', '=', 'stock.order_id')
            ->leftJoin('process_stock', function ($join) use ($repair_process_ids) {
                $join->on('process_stock.stock_id', '=', 'stock.id')
                     ->whereIn('process_stock.process_id', $repair_process_ids);
            })
            ->whereIn('orders.order_type_id', [2, 3, 5])
            ->whereNull('orders.deleted_at')
            ->whereNull('order_items.deleted_at')
            ->whereIn('orders.status', [3, 6])
            ->whereIn('order_items.status', [3, 6])
            ->when($this->vendor, fn($q) => $q->where('p_order.customer_id', $this->vendor))
            ->when($this->is_filtered, fn($q) => $q->whereIn('variation.id', $variation_ids))
            ->where(function ($query) use ($start_datetime, $end_datetime) {
                $query->where(function ($subQuery) use ($start_datetime, $end_datetime) {
                    $subQuery->where('orders.order_type_id', 3)
                            ->whereBetween('orders.processed_at', [$start_datetime, $end_datetime]);
                })->orWhere(function ($subQuery) use ($start_datetime, $end_datetime) {
                    $subQuery->where('orders.order_type_id', '!=', 3)
                            ->whereBetween('order_items.created_at', [$start_datetime, $end_datetime]);
                });
            })
            ->selectRaw('
                category.id as category_id,
                order_items.id as order_item_id,
                order_items.price as order_item_price,
                orders.price as order_price,
                orders.charges as order_charges,
                orders.currency,
                orders.order_type_id,
                stock.id as stock_id,
                process_stock.price as repair_price
            ');

        $this->aggregated_sales = DB::table(DB::raw("({$subquery->toSql()}) as sub"))
            ->mergeBindings($subquery)
            ->select(
                'sub.category_id',
                DB::raw('COUNT(DISTINCT sub.order_item_id) as orders_qty'),
                DB::raw('SUM(sub.order_charges) as charges'),
                DB::raw('SUM(CASE WHEN sub.currency = 4 OR sub.order_type_id = 5 THEN sub.order_item_price ELSE 0 END) as eur_items_sum'),
                DB::raw('SUM(CASE WHEN sub.currency = 5 AND sub.order_type_id = 3 THEN sub.order_price ELSE 0 END) as gbp_items_sum'),
                DB::raw('GROUP_CONCAT(DISTINCT sub.stock_id) as stock_ids'),
                DB::raw('SUM(repair_price) as items_repair_sum')
            )
            ->groupBy('sub.category_id')
            ->get()
            ->toArray();

        // Calculate costs in chunks for memory efficiency
        $this->aggregated_sales_cost = [];
        foreach ($this->aggregated_sales as $agg) {
            if (empty($agg->stock_ids)) {
                $this->aggregated_sales_cost[$agg->category_id] = 0;
                continue;
            }

            $stock_ids = explode(',', $agg->stock_ids);
            $total_cost = 0;

            foreach (array_chunk($stock_ids, 1000) as $chunk) {
                $total_cost += Order_item_model::whereIn('stock_id', $chunk)
                    ->whereHas('order', fn($q) => $q->where('order_type_id', 1))
                    ->sum('price');
            }

            $this->aggregated_sales_cost[$agg->category_id] = $total_cost;
        }
    }

    public function getReturnsData()
    {
        $start_datetime = $this->start_date . " " . $this->start_time;
        $end_datetime = $this->end_date . " " . $this->end_time;
        $variation_ids = $this->getVariationIds();

        $this->aggregated_returns = DB::table('category')
            ->join('products', 'category.id', '=', 'products.category')
            ->join('variation', 'products.id', '=', 'variation.product_id')
            ->join('order_items', 'variation.id', '=', 'order_items.variation_id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('stock', 'order_items.stock_id', '=', 'stock.id')
            ->leftJoin('orders as p_order', 'stock.order_id', '=', 'p_order.id')
            ->leftJoin('process_stock', 'stock.id', '=', 'process_stock.stock_id')
            ->leftJoin('process', 'process_stock.process_id', '=', 'process.id')
            ->select(
                'category.id as category_id',
                DB::raw('COUNT(DISTINCT order_items.id) as orders_qty'),
                DB::raw('SUM(CASE WHEN orders.status = 3 THEN 1 ELSE 0 END) as approved_orders_qty'),
                DB::raw('SUM(CASE WHEN order_items.currency is null OR order_items.currency = 4 THEN order_items.price ELSE 0 END) as eur_items_sum'),
                DB::raw('SUM(CASE WHEN order_items.currency = 5 THEN order_items.price ELSE 0 END) as gbp_items_sum'),
                DB::raw('GROUP_CONCAT(stock.id) as stock_ids'),
                DB::raw('SUM(CASE WHEN process.process_type_id = 9 THEN process_stock.price ELSE 0 END) as items_repair_sum')
            )
            ->whereBetween('order_items.created_at', [$start_datetime, $end_datetime])
            ->when($this->is_filtered, fn($q) => $q->whereIn('variation.id', $variation_ids))
            ->when($this->vendor, fn($q) => $q->where('p_order.customer_id', $this->vendor))
            ->whereIn('orders.order_type_id', [4, 6])
            ->whereNull('orders.deleted_at')
            ->whereNull('order_items.deleted_at')
            ->whereNull('stock.deleted_at')
            ->whereNull('process_stock.deleted_at')
            ->groupBy('category.id')
            ->get()
            ->toArray();

        // Calculate return costs
        $this->aggregated_return_cost = [];
        foreach ($this->aggregated_returns as $agg) {
            if (empty($agg->stock_ids)) {
                $this->aggregated_return_cost[$agg->category_id] = 0;
                continue;
            }

            $this->aggregated_return_cost[$agg->category_id] = Order_item_model::whereIn('stock_id', explode(',', $agg->stock_ids))
                ->whereHas('order', fn($q) => $q->where('order_type_id', 1))
                ->sum('price');
        }
    }

    public function getBatchGradeReports()
    {
        $variation_ids = $this->getVariationIds();

        $this->batch_grade_reports = Stock_model::select(
                'variation.grade as grade',
                'orders.id as order_id',
                'orders.reference_id as reference_id',
                'orders.reference as reference',
                'orders.customer_id',
                'customer.first_name as vendor',
                DB::raw('COUNT(*) as quantity'),
                DB::raw('AVG(order_items.price) as average_cost')
            )
            ->join('variation', 'stock.variation_id', '=', 'variation.id')
            ->join('orders', 'stock.order_id', '=', 'orders.id')
            ->join('customer', 'orders.customer_id', '=', 'customer.id')
            ->join('order_items', function ($join) {
                $join->on('stock.id', '=', 'order_items.stock_id')
                    ->whereNull('order_items.deleted_at')
                    ->whereRaw('order_items.order_id = stock.order_id')
                    ->limit(1);
            })
            ->when($this->vendor, fn($q) => $q->where('orders.customer_id', $this->vendor))
            ->when($this->is_filtered, fn($q) => $q->whereIn('variation.id', $variation_ids))
            ->groupBy('variation.grade', 'orders.id', 'orders.reference_id', 'orders.reference', 'customer.first_name')
            ->orderByDesc('order_id')
            ->paginate($this->per_page)
            ->toArray();
    }

    public function getSalesHistory()
    {
        $variation_ids = $this->getVariationIds();

        // Get B2C sales data for last 7 days
        $b2c_sales_data = [];
        for ($i = 0; $i <= 6; $i++) {
            $day_start = Carbon::now()->subDays($i)->startOfDay();
            $day_end = Carbon::now()->subDays($i)->endOfDay();

            $sales = Order_item_model::whereHas('order', function ($q) use ($day_start, $day_end) {
                    $q->whereIn('order_type_id', [3])
                        ->whereIn('status', [3, 6])
                        ->whereBetween('processed_at', [$day_start, $day_end]);
                })
                ->when($this->vendor, function ($q) {
                    return $q->whereHas('stock.order', fn($qu) => $qu->where('customer_id', $this->vendor));
                })
                ->when($this->is_filtered, fn($q) => $q->whereIn('variation_id', $variation_ids))
                ->groupBy(DB::raw('COALESCE(currency, 4)'))
                ->selectRaw('COALESCE(currency, 4) as currency, AVG(price) as average_price, SUM(price) as total_sales, COUNT(*) as quantity')
                ->get();

            $b2c_sales_data[$day_start->format('l')] = [];
            foreach ($sales as $sale) {
                $currency = $sale->currency ?? 4;
                $b2c_sales_data[$day_start->format('l')][$currency] = [
                    'average_price' => amount_formatter($sale->average_price ?? 0),
                    'total_sales' => amount_formatter($sale->total_sales ?? 0),
                    'quantity' => $sale->quantity ?? 0,
                ];
            }
        }

        return $b2c_sales_data;
    }

    public function exportReport($type)
    {
        switch ($type) {
            case 'excel':
                // Return Excel export
                break;
            case 'pdf':
                // Return PDF export
                break;
        }
    }

    public function render()
    {
        // Only load data for the current report type
        switch ($this->report_type) {
            case 'sales_returns':
                $this->getSalesData();
                $this->getReturnsData();
                break;
            case 'batch_grades':
                $this->getBatchGradeReports();
                break;
            case 'sales_history':
                $sales_history = $this->getSalesHistory();
                break;
        }

        return view('livewire.report-lw', [
            'categories' => session('dropdown_data')['categories'] ?? [],
            'brands' => session('dropdown_data')['brands'] ?? [],
            'products' => Products_model::orderBy('model')->pluck('model', 'id'),
            'colors' => session('dropdown_data')['colors'] ?? [],
            'storages' => session('dropdown_data')['storages'] ?? [],
            'grades' => session('dropdown_data')['grades'] ?? [],
            'vendors' => Customer_model::whereNotNull('is_vendor')->pluck('first_name', 'id'),
            'currencies' => Currency_model::pluck('sign', 'id'),
            'pending_orders_count' => Order_model::where('order_type_id', 3)->where('status', 2)->count(),
            'sales_history' => $sales_history ?? [],
        ])->extends('layouts.app')->section('content');
    }
}
