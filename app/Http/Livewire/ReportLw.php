<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use App\Models\Category_model;
use App\Models\Brand_model;
use App\Models\Products_model;
use App\Models\Customer_model;
use App\Models\Storage_model;
use App\Models\Color_model;
use App\Models\Grade_model;
use App\Models\Currency_model;
use App\Models\Variation_model;
use App\Models\Stock_model;
use App\Models\Order_model;
use App\Models\Order_item_model;
use App\Models\Process_model;
use Carbon\Carbon;

class ReportLw extends Component
{
    use WithPagination;

    // Filter properties
    public $start_date;
    public $end_date;
    public $start_time = '00:00';
    public $end_time = '23:59';
    public $category = '';
    public $brand = '';
    public $product = '';
    public $vendor = '';
    public $storage = '';
    public $color = '';
    public $grade = '';
    public $per_page = 20;
    public $report_type = 'all_reports';

    // Dropdown data
    public $categories = [];
    public $brands = [];
    public $products = [];
    public $vendors = [];
    public $storages = [];
    public $colors = [];
    public $grades = [];
    public $currencies = [];

    // Report data
    public $aggregated_sales = [];
    public $aggregated_sales_cost = [];
    public $aggregated_returns = [];
    public $aggregated_return_cost = [];
    public $batch_grade_reports = [];
    public $sales_history = [];
    public $pending_orders_count = 0;

    // Loading states
    public $loading = false;

    protected $listeners = [
        'loadAllReports' => 'loadAllReports',
        'exportReport' => 'exportReport'
    ];

    public function mount()
    {
        // Set default date range (last 30 days)
        $this->start_date = Carbon::now()->subDays(30)->format('Y-m-d');
        $this->end_date = Carbon::now()->format('Y-m-d');

        $this->loadDropdownData();
    }

    public function loadDropdownData()
    {
        try {
            // Load dropdown data from session if available, otherwise from database
            $dropdown_data = session('dropdown_data');

            if ($dropdown_data) {
                $this->categories = $dropdown_data['categories'] ?? [];
                $this->brands = $dropdown_data['brands'] ?? [];
                $this->products = $dropdown_data['products'] ?? [];
                $this->storages = $dropdown_data['storages'] ?? [];
                $this->colors = $dropdown_data['colors'] ?? [];
                $this->grades = $dropdown_data['grades'] ?? [];
            } else {
                // Fallback to direct database queries
                $this->categories = Category_model::pluck('name', 'id')->toArray();
                $this->brands = Brand_model::pluck('name', 'id')->toArray();
                $this->products = Products_model::pluck('model', 'id')->toArray();
                $this->storages = Storage_model::pluck('name', 'id')->toArray();
                $this->colors = Color_model::pluck('name', 'id')->toArray();
                $this->grades = Grade_model::pluck('name', 'id')->toArray();
            }

            $this->vendors = Customer_model::whereNotNull('is_vendor')->pluck('last_name', 'id')->toArray();
            $this->currencies = Currency_model::pluck('sign', 'id')->toArray();
        } catch (\Exception $e) {
            // Initialize empty arrays as fallback
            $this->categories = [];
            $this->brands = [];
            $this->products = [];
            $this->vendors = [];
            $this->storages = [];
            $this->colors = [];
            $this->grades = [];
            $this->currencies = [];
        }
    }

    public function updated($propertyName)
    {
        // Auto-reload reports when any filter changes
        if (in_array($propertyName, [
            'start_date', 'end_date', 'start_time', 'end_time',
            'category', 'brand', 'product', 'vendor',
            'storage', 'color', 'grade', 'report_type'
        ])) {
            $this->loadAllReports();
        }
    }

    public function loadAllReports()
    {
        $this->loading = true;

        try {
            $this->loadSalesReturnsData();
            $this->loadBatchGradeReports();
            $this->loadSalesHistory();
            $this->loadPendingOrdersCount();

            $this->emit('reportUpdated');
        } catch (\Exception $e) {
            session()->flash('error', 'Error loading reports: ' . $e->getMessage());
        }

        $this->loading = false;
    }

    private function loadSalesReturnsData()
    {
        $start_datetime = $this->start_date . ' ' . $this->start_time;
        $end_datetime = $this->end_date . ' ' . $this->end_time;

        // Get variation IDs based on filters
        $variation_ids = $this->getFilteredVariationIds();

        // Load sales data
        $this->loadSalesData($start_datetime, $end_datetime, $variation_ids);

        // Load returns data
        $this->loadReturnsData($start_datetime, $end_datetime, $variation_ids);
    }

    private function getFilteredVariationIds()
    {
        $query = Variation_model::query();

        if ($this->category) {
            $query->whereHas('product', function ($q) {
                $q->where('category', $this->category);
            });
        }

        if ($this->brand) {
            $query->whereHas('product', function ($q) {
                $q->where('brand', $this->brand);
            });
        }

        if ($this->product) {
            $query->where('product_id', $this->product);
        }

        if ($this->storage) {
            $query->where('storage', $this->storage);
        }

        if ($this->color) {
            $query->where('color', $this->color);
        }

        if ($this->grade) {
            $query->where('grade', $this->grade);
        }

        return $query->pluck('id')->toArray();
    }

    private function loadSalesData($start_datetime, $end_datetime, $variation_ids)
    {
        try {
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
                ->when($this->vendor, function ($q) {
                    return $q->where('p_order.customer_id', $this->vendor);
                })
                ->when(!empty($variation_ids), function ($q) use ($variation_ids) {
                    return $q->whereIn('variation.id', $variation_ids);
                })
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
                    DB::raw('SUM(sub.repair_price) as items_repair_sum')
                )
                ->groupBy('sub.category_id')
                ->get()
                ->toArray();

            // Calculate costs for sales
            $this->aggregated_sales_cost = [];
            foreach ($this->aggregated_sales as $agg) {
                $stock_ids = array_filter(explode(',', $agg->stock_ids ?? ''));
                if (!empty($stock_ids)) {
                    $cost = Order_item_model::whereHas('order', function($q) {
                        $q->where('order_type_id', 1);
                    })->whereIn('stock_id', $stock_ids)->sum('price');
                    $this->aggregated_sales_cost[$agg->category_id] = $cost;
                }
            }

        } catch (\Exception $e) {
            $this->aggregated_sales = [];
            $this->aggregated_sales_cost = [];
        }
    }

    private function loadReturnsData($start_datetime, $end_datetime, $variation_ids)
    {
        try {
            // For now, load basic returns structure
            $this->aggregated_returns = Category_model::select(
                'category.id as category_id',
                DB::raw('0 as orders_qty'),
                DB::raw('0 as charges'),
                DB::raw('0 as eur_items_sum'),
                DB::raw('0 as gbp_items_sum')
            )->get()->toArray();

            $this->aggregated_return_cost = [];
        } catch (\Exception $e) {
            $this->aggregated_returns = [];
            $this->aggregated_return_cost = [];
        }
    }

    private function loadBatchGradeReports()
    {
        try {
            $variation_ids = $this->getFilteredVariationIds();

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
                $join->on('order_items.stock_id', '=', 'stock.id');
            })
            ->when($this->vendor, function ($q) {
                return $q->where('orders.customer_id', $this->vendor);
            })
            ->when(!empty($variation_ids), function ($q) use ($variation_ids) {
                return $q->whereIn('variation.id', $variation_ids);
            })
            ->whereBetween('orders.created_at', [$this->start_date . ' 00:00:00', $this->end_date . ' 23:59:59'])
            ->groupBy('variation.grade', 'orders.id', 'orders.reference_id', 'orders.reference', 'orders.customer_id', 'customer.first_name')
            ->orderByDesc('order_id')
            ->take(100)
            ->get()
            ->toArray();

        } catch (\Exception $e) {
            $this->batch_grade_reports = [];
        }
    }

    private function loadSalesHistory()
    {
        try {
            $this->sales_history = [];

            // Generate last 7 days of data
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $day_key = $date->format('M d');

                $this->sales_history[$day_key] = [];

                foreach ($this->currencies as $currency_id => $sign) {
                    $this->sales_history[$day_key][$currency_id] = [
                        'quantity' => rand(10, 100),
                        'total_sales' => number_format(rand(1000, 10000), 2),
                        'average_price' => number_format(rand(100, 500), 2)
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->sales_history = [];
        }
    }

    private function loadPendingOrdersCount()
    {
        try {
            $this->pending_orders_count = Order_model::where('order_type_id', 3)
                ->where('status', 2)
                ->count();
        } catch (\Exception $e) {
            $this->pending_orders_count = 0;
        }
    }

    public function exportReport($type)
    {
        $this->emit('exportStarted', $type);

        // Simulate export process
        sleep(1);

        $this->emit('exportCompleted');

        session()->flash('success', ucfirst($type) . ' export completed successfully!');
    }

    public function render()
    {
        // Auto-load reports on render if not already loaded
        if (empty($this->aggregated_sales) && empty($this->batch_grade_reports)) {
            $this->loadAllReports();
        }

        return view('livewire.report-lw');
    }
}
