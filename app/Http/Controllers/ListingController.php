<?php

namespace App\Http\Controllers;

use App\Models\Admin_model;
use App\Models\Color_model;
use App\Models\Country_model;
use App\Models\Currency_model;
use App\Models\Customer_model;
use App\Models\ExchangeRate;
use App\Models\Grade_model;
use App\Models\Listed_stock_verification_model;
use App\Models\Listing_model;
use App\Models\Marketplace_model;
use App\Models\MarketplaceStockModel;
use App\Models\Order_item_model;
use App\Models\Order_model;
use App\Models\Process_model;
use App\Models\Process_stock_model;
use App\Models\Products_model;
use App\Models\Stock_model;
use App\Models\Storage_model;
use App\Models\Variation_model;
use App\Events\VariationStockUpdated;
use App\Services\Marketplace\StockDistributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ListingController extends Controller
{
    protected $stockDistributionService;

    public function __construct(?StockDistributionService $stockDistributionService = null)
    {
        $this->stockDistributionService = $stockDistributionService;
    }

    public function index()
    {

        $data['title_page'] = "Listings";
        session()->put('page_title', $data['title_page']);


        if(request('process_id') != null){
            $process = Process_model::where('id', request('process_id'))->where('process_type_id', 22)->first();
            if($process != null){
                $data['process_id'] = $process->id;
                $data['title_page'] = "Listings - Topup - ".$process->reference_id;
            }else{
                $data['process_id'] = null;
            }
        }else{
            $data['process_id'] = null;
        }
        session()->put('page_title', $data['title_page']);
        $data['bm'] = new BackMarketAPIController();
        $data['storages'] = session('dropdown_data')['storages'];
        $data['colors'] = session('dropdown_data')['colors'];
        $data['grades'] = Grade_model::where('id',"<",6)->pluck('name','id')->toArray();
        $data['eur_gbp'] = ExchangeRate::where('target_currency','GBP')->first()->rate;
        $data['exchange_rates'] = ExchangeRate::pluck('rate','target_currency');
        $data['currencies'] = Currency_model::pluck('code','id');
        $data['currency_sign'] = Currency_model::pluck('sign','id');
        $countries = Country_model::all();
        foreach($countries as $country){
            $data['countries'][$country->id] = $country;
        }
        $marketplaces = Marketplace_model::all();
        foreach($marketplaces as $marketplace){
            $data['marketplaces'][$marketplace->id] = $marketplace;
        }



        return view('listings')->with($data);
    }
    public function get_variations(Request $request)
    {
        $perPage = $request->input('per_page', 10);

        return $this->buildVariationQuery($request)
            ->paginate($perPage)
            ->appends($request->except('page'));
    }

    public function exportFilteredListings(Request $request)
    {
        $fileName = 'filtered-listings-' . now()->format('Ymd_His') . '.csv';
        $query = $this->buildVariationQuery($request);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $this->listingExportHeaders());

            $query->chunk(250, function ($variations) use ($handle) {
                $variations->each(function ($variation) use ($handle) {
                    $this->writeVariationCsvRows($handle, $variation);
                });
            });

            fclose($handle);
        }, 200, $headers);
    }

    private function buildVariationQuery(Request $request)
    {
    list($productSearch, $storageSearch) = $this->resolveProductAndStorageSearch($request->input('product_name'));

        $query = Variation_model::with([
            'listings',
            'listings.country_id',
            'listings.currency',
            'listings.marketplace',
            'product',
            'pending_bm_orders',
            'storage_id',
            'color_id',
            'grade_id',
        ])
        // Only eager load available_stocks and pending_orders if not filtering by available_stock
        // to avoid duplicate column error when withCount is used
        ->when(!$request->filled('available_stock'), function ($q) {
            return $q->with(['available_stocks', 'pending_orders']);
        });

        $query->when($request->filled('reference_id'), function ($q) use ($request) {
            return $q->where('reference_id', $request->input('reference_id'));
        })
        ->when($request->filled('variation_id'), function ($q) use ($request) {
            return $q->where('id', $request->input('variation_id'));
        })
        ->when($request->filled('category'), function ($q) use ($request) {
            return $q->whereHas('product', function ($productQuery) use ($request) {
                $productQuery->where('category', $request->input('category'));
            });
        })
        ->when($request->filled('brand'), function ($q) use ($request) {
            return $q->whereHas('product', function ($productQuery) use ($request) {
                $productQuery->where('brand', $request->input('brand'));
            });
        })
        ->when($request->filled('marketplace'), function ($q) use ($request) {
             return $q->whereHas('listings', function ($q) {
                 $q->where('marketplace_id', request('marketplace'));
             });
         })
        ->when($request->filled('product'), function ($q) use ($request) {
            return $q->where('product_id', $request->input('product'));
        })
        ->when($productSearch->count() > 0, function ($q) use ($productSearch) {
            return $q->whereIn('product_id', $productSearch);
        })
        ->when($storageSearch->count() > 0, function ($q) use ($storageSearch) {
            return $q->whereIn('storage', $storageSearch);
        })
        ->when($request->filled('sku'), function ($q) use ($request) {
            return $q->where('sku', $request->input('sku'));
        })
        ->when($request->filled('color'), function ($q) use ($request) {
            return $q->where('color', $request->input('color'));
        })
        ->when($request->filled('storage'), function ($q) use ($request) {
            return $q->where('storage', $request->input('storage'));
        })
        ->when($request->filled('grade'), function ($q) use ($request) {
            return $q->whereIn('grade', (array) $request->input('grade'));
        })
        ->when($request->filled('topup'), function ($q) use ($request) {
            return $q->whereHas('listed_stock_verifications', function ($verificationQuery) use ($request) {
                $verificationQuery->where('process_id', $request->input('topup'));
            });
        })
        ->when($request->filled('listed_stock') || $request->filled('listed_stock_custom'), function ($q) use ($request) {
            $listedStock = $request->filled('listed_stock')
                ? $request->input('listed_stock')
                : $request->input('listed_stock_custom');

            if ((int) $listedStock === 1) {
                return $q->where('listed_stock', '>', 0);
            }

            if ((int) $listedStock === 2) {
                return $q->where('listed_stock', '<=', 0);
            }

            // Handle custom values like >20, <30, >=10, <=50, or just 20
            if (!in_array($listedStock, ['1', '2', ''])) {
                $value = trim($listedStock);

                // Check for operators
                if (preg_match('/^(>=|<=|>|<)(\d+)$/', $value, $matches)) {
                    $operator = $matches[1];
                    $number = (int) $matches[2];
                    return $q->where('listed_stock', $operator, $number);
                }
                // Just a number
                elseif (is_numeric($value)) {
                    return $q->where('listed_stock', '=', (int) $value);
                }
            }
        })
        ->when($request->filled('available_stock') || $request->filled('available_stock_custom'), function ($q) use ($request) {
            $availableStock = $request->filled('available_stock')
                ? $request->input('available_stock')
                : $request->input('available_stock_custom');

            if ((int) $availableStock === 1) {
                return $q->whereHas('available_stocks')
                    ->withCount(['available_stocks', 'pending_orders'])
                    ->havingRaw('(available_stocks_count - pending_orders_count) > 0');
            }

            if ((int) $availableStock === 2) {
                return $q->whereDoesntHave('available_stocks');
            }

            // Handle custom values like >20, <30, >=10, <=50, or just 20
            if (!in_array($availableStock, ['1', '2', ''])) {
                $value = trim($availableStock);

                // Check for operators
                if (preg_match('/^(>=|<=|>|<)(\d+)$/', $value, $matches)) {
                    $operator = $matches[1];
                    $number = (int) $matches[2];
                    return $q->withCount(['available_stocks', 'pending_orders'])
                        ->havingRaw("(available_stocks_count - pending_orders_count) {$operator} {$number}");
                }
                // Just a number
                elseif (is_numeric($value)) {
                    return $q->withCount(['available_stocks', 'pending_orders'])
                        ->havingRaw("(available_stocks_count - pending_orders_count) = " . (int) $value);
                }
            }
        });

        $state = $request->input('state');
        if ($state === null || $state === '') {
            $query->whereIn('state', [2, 3]);
        } elseif ((int) $state !== 10) {
            $query->where('state', $state);
        }

        $query->when($request->filled('sale_40'), function ($q) {
            return $q->withCount('today_orders as today_orders_count')
                ->having('today_orders_count', '<', DB::raw('listed_stock * 0.05'));
        })
        ->when((int) $request->input('handler_status') === 2, function ($q) use ($request) {
            return $q->whereHas('listings', function ($listingQuery) use ($request) {
                $listingQuery->where('handler_status', $request->input('handler_status'))
                    ->whereIn('country', [73, 199]);
            });
        })
        ->when(in_array((int) $request->input('handler_status'), [1, 3], true), function ($q) use ($request) {
            return $q->whereHas('listings', function ($listingQuery) use ($request) {
                $listingQuery->where('handler_status', $request->input('handler_status'));
            });
        })
        ->when($request->filled('process_id') && $request->input('special') === 'show_only', function ($q) use ($request) {
            return $q->whereHas('process_stocks', function ($processStockQuery) use ($request) {
                $processStockQuery->where('process_id', $request->input('process_id'));
            });
        })
        ->whereNotNull('sku')
        ->when($request->input('sort') == 4, function ($q) {
            return $q->join('products', 'variation.product_id', '=', 'products.id')
                ->orderBy('products.model', 'asc')
                ->orderBy('variation.storage', 'asc')
                ->orderBy('variation.color', 'asc')
                ->orderBy('variation.grade', 'asc')
                ->select('variation.*');
        })
        ->when($request->input('sort') == 3, function ($q) {
            return $q->join('products', 'variation.product_id', '=', 'products.id')
                ->orderBy('products.model', 'desc')
                ->orderBy('variation.storage', 'asc')
                ->orderBy('variation.color', 'asc')
                ->orderBy('variation.grade', 'asc')
                ->select('variation.*');
        })
        ->when($request->input('sort') == 2, function ($q) {
            return $q->orderBy('listed_stock', 'asc')
                ->orderBy('variation.storage', 'asc')
                ->orderBy('variation.color', 'asc')
                ->orderBy('variation.grade', 'asc');
        })
        ->when($request->input('sort') == 1 || $request->input('sort') === null, function ($q) {
            return $q->orderBy('listed_stock', 'desc')
                ->orderBy('variation.storage', 'asc')
                ->orderBy('variation.color', 'asc')
                ->orderBy('variation.grade', 'asc');
        });

        return $query;
    }

    private function resolveProductAndStorageSearch(?string $productName): array
    {
        if (empty($productName)) {
            return [collect(), collect()];
        }

        $searchTerm = trim($productName);
        $parts = explode(' ', $searchTerm);
        $lastSegment = end($parts);

        $storageSearch = Storage_model::where('name', 'like', $lastSegment . '%')->pluck('id');

        if ($storageSearch->count() > 0) {
            array_pop($parts);
            $searchTerm = trim(implode(' ', $parts));
        } else {
            $storageSearch = collect();
        }

        $productSearch = Products_model::where('model', 'like', '%' . $searchTerm . '%')->pluck('id');

        return [$productSearch, $storageSearch];
    }

    private function listingExportHeaders(): array
    {
        return [
            'Variation ID',
            'Variation Reference',
            'Variation UUID',
            'SKU',
            'Product Model',
            'Brand',
            'Category',
            'Storage',
            'Grade',
            'Color',
            'State',
            'Listed Stock',
            'Available Stocks',
            'Pending Orders',
            'Listing ID',
            'Listing Country',
            'Handler Status',
            'Currency',
            'Min Price',
            'Price',
            'Buybox',
            'Buybox Price',
            'Target Price',
            'Target Percentage',
            'Min Price Limit',
            'Price Limit',
            'Last Updated',
        ];
    }

    private function writeVariationCsvRows($handle, $variation): void
    {
        if ($variation->listings->isEmpty()) {
            fputcsv($handle, $this->mapVariationListingRow($variation, null));
            return;
        }

        foreach ($variation->listings as $listing) {
            fputcsv($handle, $this->mapVariationListingRow($variation, $listing));
        }
    }

    private function mapVariationListingRow($variation, $listing): array
    {
        $availableCount = $variation->available_stocks ? $variation->available_stocks->count() : 0;
        $pendingCount = $variation->pending_orders ? $variation->pending_orders->sum('quantity') : 0;
        $pendingBMCount = $variation->pending_bm_orders ? $variation->pending_bm_orders->count() : 0;
        return [
            $variation->id,
            $variation->reference_id,
            $listing->reference_uuid,
            $variation->sku,
            optional($variation->product)->model,
            optional($variation->product->brand_id)->name,
            optional($variation->product->category_id)->name,
            optional($variation->storage_id)->name,
            optional($variation->grade_id)->name,
            optional($variation->color_id)->name,
            $this->formatVariationState($variation->state),
            $variation->listed_stock,
            $availableCount,
            $pendingCount,
            $pendingBMCount,
            optional($listing)->id,
            $listing ? (optional($listing->country_id)->title ?? $listing->country) : null,
            $listing ? $this->formatHandlerStatus($listing->handler_status) : null,
            $listing ? (optional($listing->currency)->code ?? optional($listing->currency)->symbol ?? null) : null,
            optional($listing)->min_price,
            optional($listing)->price,
            $listing ? ($listing->buybox ? 'Yes' : 'No') : null,
            optional($listing)->buybox_price,
            optional($listing)->target_price,
            optional($listing)->target_percentage,
            optional($listing)->min_price_limit,
            optional($listing)->price_limit,
            optional(optional($listing)->updated_at)->format('Y-m-d H:i:s'),
        ];
    }

    private function formatVariationState(?int $state): string
    {
        return [
            0 => 'Missing price/comment',
            1 => 'Pending validation',
            2 => 'Online',
            3 => 'Offline',
            4 => 'Deactivated',
        ][$state] ?? 'Unknown';
    }

    private function formatHandlerStatus(?int $status): string
    {
        return [
            0 => 'Unassigned',
            1 => 'Active',
            2 => 'Inactive',
            3 => 'Re-Activated',
        ][$status] ?? '';
    }

    public function get_target_variations(){
        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 10;
        }

        if(request('product_name') != null){
            $product_name = trim(request('product_name'));

            $arr = explode(" ", $product_name);
            $last = end($arr);


            $storage_search = Storage_model::where('name', 'like', $last.'%')->pluck('id');

            if($storage_search->count() > 0){
                // dd($storage_search);
                array_pop($arr);
                $product_name = implode(" ", $arr);
            }else{
                $storage_search = [];
            }
            $product_search = Products_model::where('model', 'like', '%'.$product_name.'%')->pluck('id');


        }else{
            $product_search = [];
            $storage_search = [];
        }
        // dd($product_search, $storage_search);

        return Variation_model::when(request('reference_id') != '', function ($q) {
            return $q->where('reference_id', request('reference_id'));
        })
        ->when(request('category') != '', function ($q) {
            return $q->whereHas('product', function ($q) {
                $q->where('category', request('category'));
            });
        })
        ->when(request('brand') != '', function ($q) {
            return $q->whereHas('product', function ($q) {
                $q->where('brand', request('brand'));
            });
        })
        ->when(request('product') != '', function ($q) {
            return $q->where('product_id', request('product'));
        })
        ->when(count($product_search) > 0, function ($q) use ($product_search) {
            return $q->whereIn('product_id', $product_search);
        })
        ->when(count($storage_search) > 0, function ($q) use ($storage_search) {
            return $q->whereIn('storage', $storage_search);
        })
        ->when(request('sku') != '', function ($q) {
            return $q->where('sku', request('sku'));
        })
        // ->when(request('color') != '', function ($q) {
        //     return $q->where('color', request('color'));
        // })
        ->when(request('storage') != '', function ($q) {
            return $q->where('storage', request('storage'));
        })
        ->when(request('grade') != [], function ($q) {
            return $q->whereIn('grade', request('grade'));
        })
        ->when(request('listed_stock') != '', function ($q) {
            if (request('listed_stock') == 1) {
                return $q->where('listed_stock', '>', 0);
            } elseif (request('listed_stock') == 2) {
                return $q->where('listed_stock', '<=', 0);
            }
        })
        ->when(request('available_stock') != '', function ($q) {
            if (request('available_stock') == 1) {
                return $q->whereHas('available_stocks');
            } elseif (request('available_stock') == 2) {
                return $q->whereDoesntHave('available_stocks');
            }
        })
        ->when(request('state') == '', function ($q) {
            return $q->whereIn('state', [2, 3]);
        })
        ->when(request('state') != '' && request('state') != 10, function ($q) {
            return $q->where('state', request('state'));

        })
        ->when(request('handler_status') != '', function ($q) {
            return $q->whereHas('listings', function ($q) {
                $q->where('handler_status', request('handler_status'));
            });
        })
        ->when(request('marketplace') != '', function ($q) {
            return $q->whereHas('listings', function ($q) {
                $q->where('marketplace_id', request('marketplace'));
            });
        })
        ->where('sku', '!=', null)
        ->whereNull('variation.deleted_at')
        ->whereNull('products.deleted_at')
        ->join('products', 'variation.product_id', '=', 'products.id') // Join the products table
        ->join('storage', 'variation.storage', '=', 'storage.id') // Join the storage table
        ->join('grade', 'variation.grade', '=', 'grade.id')  // Join the grade table
        ->join('listings', function($join){
            $join->on('variation.id', '=', 'listings.variation_id')
            ->where('listings.country', 73);
        })
        ->orderBy('products.model', 'asc') // Order by product model in ascending order
        ->orderBy('variation.storage', 'asc') // Secondary order by storage
        // ->orderBy('variation.color', 'asc') // Secondary order by color
        ->orderBy('variation.grade', 'asc') // Secondary order by grade
        // ->orderBy('listed_stock', 'desc') // Secondary order by listed stock
        // ->select('variation.*') // Select only the variation columns
        ->groupBy(['variation.product_id', 'variation.storage', 'variation.grade'])
        ->select('variation.product_id', 'products.model as product_name', 'variation.storage', 'storage.name as storage_name', 'variation.grade', 'grade.name as grade_name', DB::raw('GROUP_CONCAT(variation.id) as ids'), DB::raw('GROUP_CONCAT(listings.id) as listing_ids'), DB::raw('AVG(listings.target_price) as target_price'), DB::raw('AVG(listings.target_percentage) as target_percentage'))
        ->paginate(300)
        ->appends(request()->except('page'));
        // ->get();
    }
    public function get_variation_available_stocks($id){
        $variation = Variation_model::find($id);

        // Get pagination parameters
        $page = request('page', 1);
        $perPage = request('per_page', 50); // Default 50 items per page

        // Remove restrictive whereHas filter to show ALL available stocks
        // Previously: ->whereHas('latest_listing_or_topup') was limiting results
        $stocksQuery = Stock_model::where('variation_id', $id)->where('status', 1)->whereHas('latest_closed_listing_or_topup');

        // Order by ID descending (latest stocks first)
        $stocks = $stocksQuery->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);

        // Get stock IDs from paginated items (for current page)
        $stockIds = $stocks->pluck('id');

        // Get ALL stock IDs for this variation (for average cost calculation)
        $allStockIds = Stock_model::where('variation_id', $id)->where('status', 1)->pluck('id');

        // Get stock costs for current page stocks
        $stock_costs = Order_item_model::whereHas('order', function($q){
            $q->where('order_type_id',1);
        })->whereIn('stock_id', $stockIds)->pluck('price','stock_id');

        // Calculate average cost from ALL stocks (not just current page)
        $all_stock_costs = Order_item_model::whereHas('order', function($q){
            $q->where('order_type_id',1);
        })->whereIn('stock_id', $allStockIds)->pluck('price');

        $average_cost = $all_stock_costs->count() > 0 ? $all_stock_costs->average() : 0;

        $vendors = Customer_model::whereNotNull('is_vendor')->pluck('last_name','id');

        $po = Order_model::where('order_type_id',1)->pluck('customer_id','id');

        $reference = Order_model::where('order_type_id',1)->pluck('reference_id','id');

        $topup_reference = Process_model::whereIn('process_type_id',[21,22])->pluck('reference_id','id');

        $latest_topup_items = Process_stock_model::whereIn('process_id', $topup_reference->keys())->whereIn('stock_id', $stockIds)->pluck('process_id','stock_id');

        // Calculate breakeven price from average cost of ALL stocks
        if($average_cost > 0){
            $breakeven_price = ($average_cost + 20) / 0.88;

            if($breakeven_price != $variation->breakeven_price){
                $variation->breakeven_price = $breakeven_price;
                $variation->save();
            }
        }else{
            $breakeven_price = 0;
        }

        // Fetch updated quantity from BackMarket API to ensure consistency
        $updatedQuantity = null;
        if($variation && $variation->reference_id){
            try {
                $bm = new BackMarketAPIController();
                $updatedQuantity = $variation->update_qty($bm);
            } catch (\Exception $e) {
                // If API call fails, use the stored listed_stock value
                $updatedQuantity = $variation->listed_stock ?? 0;
                Log::warning("Failed to fetch updated quantity from API for variation ID $id: " . $e->getMessage());
            }
        } else {
            $updatedQuantity = $variation->listed_stock ?? 0;
        }

        return response()->json([
            'stocks'=>$stocks->items(), // Get items from paginated collection
            'stock_costs'=>$stock_costs,
            'vendors'=>$vendors,
            'po'=>$po,
            'reference'=>$reference,
            'breakeven_price'=>$breakeven_price,
            'latest_topup_items'=>$latest_topup_items,
            'topup_reference'=>$topup_reference,
            'updatedQuantity' => (int)$updatedQuantity,
            'average_cost' => $average_cost, // Server-calculated average cost from ALL stocks
            'pagination' => [
                'current_page' => $stocks->currentPage(),
                'last_page' => $stocks->lastPage(),
                'per_page' => $stocks->perPage(),
                'total' => $stocks->total(),
                'from' => $stocks->firstItem(),
                'to' => $stocks->lastItem()
            ]
        ]);

    }

    public function get_variation_history($id){
        $listed_stock_verifications = Listed_stock_verification_model::where('variation_id',$id)->orderByDesc('id')->limit(20)->get();

        $listed_stock_verifications->each(function($verification){
            $verification->process_ref = Process_model::find($verification->process_id)->reference_id ?? null;
            $verification->admin = Admin_model::find($verification->admin_id)->first_name ?? null;
        });

        return response()->json(['listed_stock_verifications'=>$listed_stock_verifications]);
    }

    public function get_stock_cost($id){
        $stock = Stock_model::find($id);
        return $stock->purchase_item->price;
    }
    public function get_stock_price($id){
        $stock = Stock_model::find($id);
        return $stock->last_item()->price;
    }
    public function get_today_average($id){
        $order_items = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->startOfDay(), now()])->where('order_type_id',3)
              ->where(function($query) {
                  $query->whereNull('marketplace_id')->orWhere('marketplace_id', 1);
              });
        })->with('order.currency_id')->get()->map(function($item) {
            if($item->order->currency != 4) {
            $rate = ExchangeRate::where('target_currency', $item->order->currency_id->code)->first()->rate ?? 1;
            return $item->price / $rate;
            }
            return $item->price;
        })->avg();
        $order_items_count = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->startOfDay(), now()])->where('order_type_id',3)
              ->where(function($query) {
                  $query->whereNull('marketplace_id')->orWhere('marketplace_id', 1);
              });
        })->count();

        return "Today: €".number_format($order_items,2)." (".$order_items_count.")";
    }
    public function get_yesterday_average($id){
        $order_items = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->yesterday()->startOfDay(), now()->yesterday()->endOfDay()])->where('order_type_id',3)
              ->where(function($query) {
                  $query->whereNull('marketplace_id')->orWhere('marketplace_id', 1);
              });
        })->with('order.currency_id')->get()->map(function($item) {
            if($item->order->currency != 4) {
            $rate = ExchangeRate::where('target_currency', $item->order->currency_id->code)->first()->rate ?? 1;
            return $item->price / $rate;
            }
            return $item->price;
        })->avg();
        $order_items_count = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->yesterday()->startOfDay(), now()->yesterday()->endOfDay()])->where('order_type_id',3)
              ->where(function($query) {
                  $query->whereNull('marketplace_id')->orWhere('marketplace_id', 1);
              });
        })->count();

        return "Yesterday: €".number_format($order_items,2)." (".$order_items_count.")";
    }
    public function get_last_week_average($id){
        $order_items = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(7)->startOfDay(), now()->yesterday()->endOfDay()])->where('order_type_id',3)
              ->where(function($query) {
                  $query->whereNull('marketplace_id')->orWhere('marketplace_id', 1);
              });
        })->with('order.currency_id')->get()->map(function($item) {
            if($item->order->currency != 4) {
            $rate = ExchangeRate::where('target_currency', $item->order->currency_id->code)->first()->rate ?? 1;
            return $item->price / $rate;
            }
            return $item->price;
        })->avg();
        $order_items_count = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(7)->startOfDay(), now()->yesterday()->endOfDay()])->where('order_type_id',3)
              ->where(function($query) {
                  $query->whereNull('marketplace_id')->orWhere('marketplace_id', 1);
              });
        })->count();

        return "7 days: €".number_format($order_items,2)." (".$order_items_count.")";
    }
    public function get_2_week_average($id){
        $order_items = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(14)->startOfDay(), now()->yesterday()->endOfDay()])->where('order_type_id',3)
              ->where(function($query) {
                  $query->whereNull('marketplace_id')->orWhere('marketplace_id', 1);
              });
        })->with('order.currency_id')->get()->map(function($item) {
            if($item->order->currency != 4) {
            $rate = ExchangeRate::where('target_currency', $item->order->currency_id->code)->first()->rate ?? 1;
            return $item->price / $rate;
            }
            return $item->price;
        })->avg();
        $order_items_count = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(14)->startOfDay(), now()->yesterday()->endOfDay()])->where('order_type_id',3)
              ->where(function($query) {
                  $query->whereNull('marketplace_id')->orWhere('marketplace_id', 1);
              });
        })->count();

        return "14 days: €".number_format($order_items,2)." (".$order_items_count.")";
    }
    public function get_30_days_average($id){
        $order_items = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(30)->startOfDay(), now()->yesterday()->endOfDay()])->where('order_type_id',3)
              ->where(function($query) {
                  $query->whereNull('marketplace_id')->orWhere('marketplace_id', 1);
              });
        })->with('order.currency_id')->get()->map(function($item) {
            if($item->order->currency != 4) {
            $rate = ExchangeRate::where('target_currency', $item->order->currency_id->code)->first()->rate ?? 1;
            return $item->price / $rate;
            }
            return $item->price;
        })->avg();
        $order_items_count = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(30)->startOfDay(), now()->yesterday()->endOfDay()])->where('order_type_id',3)
              ->where(function($query) {
                  $query->whereNull('marketplace_id')->orWhere('marketplace_id', 1);
              });
        })->count();

        return "30 days: €".number_format($order_items,2)." (".$order_items_count.")";
    }

    public function get_sales($id){
        $week = $this->get_today_average($id);
        $week .= " - ".$this->get_yesterday_average($id);
        $week .= "<br>".$this->get_last_week_average($id);
        $week .= " - ".$this->get_2_week_average($id);
        $week .= " - ".$this->get_30_days_average($id);

        return "Avg: ".$week;
    }
    public function getUpdatedQuantity($variationId)
    {
        $bm = new BackMarketAPIController();
        // Call update_qty on the variation instance
        $variation = Variation_model::findOrFail($variationId);
        $updatedQuantity = $variation->update_qty($bm);
        return response()->json(['updatedQuantity' => $updatedQuantity]);
    }
    public function getCompetitors($id, $no_check = 0){
        $error = "";
        $variation = Variation_model::find($id);
        $reference = $variation->listings->where('marketplace_id', 1)->whereNotNull('reference_uuid')->first()->reference_uuid;
        // if($no_check = 0){

            $bm = new BackMarketAPIController();
            $responses = $bm->getListingCompetitors($reference);
            if(is_string($responses) || is_int($responses) || is_null($responses)){
                $error = $responses;
                $error .= " - ".$variation->reference_uuid;
                return response()->json(['error'=>$error]);
            }
            foreach($responses as $list){
                if(is_string($list) || is_int($list)){
                    $error .= $list;
                    continue;
                }
                if(is_array($list)){
                    $error .= json_encode($list);
                    continue;
                }
                $country = Country_model::where('code',$list->market)->first();
                $listings = Listing_model::where('variation_id',$id)->where('country',$country->id)->where('marketplace_id', 1)->get();
                if($listings->count() > 1){
                    $listings->each(function($listing, $key) {
                        if ($key > 0) {
                            $listing->delete();
                        }
                    });
                }
                $listing = Listing_model::firstOrNew(['variation_id'=>$id, 'country'=>$country->id, 'marketplace_id' => 1]);
                // if(isset($list->product_id)){
                    $listing->reference_uuid_2 = $list->product_id;
                // }

                if($list->price != null){
                    $listing->price = $list->price->amount;
                    $currency = Currency_model::where('code',$list->price->currency)->first();
                }
                if($list->min_price != null){
                    $listing->min_price = $list->min_price->amount;
                    $currency = Currency_model::where('code',$list->min_price->currency)->first();
                }
                $listing->buybox = $list->is_winning;
                $listing->buybox_price = $list->price_to_win->amount;
                $listing->buybox_winner_price = $list->winner_price->amount;
                $listing->currency_id = $currency->id;
                $listing->save();
            }
            if($no_check == 1){
                return $responses;
            }
        // }
        $listings = Listing_model::with('marketplace')->where('variation_id',$id)->get();
        return response()->json(['listings'=>$listings, 'error'=>$error]);
    }

    public function getOrCreateListing($variationId, $marketplaceId) {
        $variation = Variation_model::find($variationId);
        if(!$variation) {
            return response()->json(['error' => 'Variation not found'], 404);
        }

        // Get EUR country (country id 73 based on the code)
        $eurCountry = Country_model::where('id', 73)->first();
        if(!$eurCountry) {
            return response()->json(['error' => 'EUR country not found'], 404);
        }

        // Get EUR currency (currency_id 4)
        $eurCurrency = Currency_model::where('id', 4)->first();
        if(!$eurCurrency) {
            return response()->json(['error' => 'EUR currency not found'], 404);
        }

        // Get or create listing for this variation, marketplace, and EUR country
        $listing = Listing_model::firstOrNew([
            'variation_id' => $variationId,
            'marketplace_id' => $marketplaceId,
            'country' => $eurCountry->id
        ]);

        // Set default values if it's a new listing
        if(!$listing->exists) {
            $listing->currency_id = $eurCurrency->id;
            $listing->min_price = 0;
            $listing->price = 0;
            $listing->min_price_limit = 0;
            $listing->price_limit = 0;
            $listing->handler_status = 0;
            $listing->buybox = 0;
        }

        $listing->save();
        $listing->load('marketplace', 'country_id', 'currency');

        return response()->json(['listing' => $listing]);
    }
    public function update_quantity($id){
        $variation = Variation_model::find($id);
        $bm = new BackMarketAPIController();
        $updatedQuantity = $variation->update_qty($bm);
        // V1 listing: Skip buffer (buffer only applies to V2 listing)
        $response = $bm->updateOneListing($variation->reference_id,json_encode(['quantity'=>request('stock')]), null, true);
        if($response->quantity != null){
            $variation->listed_stock = $response->quantity;
            $variation->save();
        }
        return $response->quantity;
    }
    public function add_quantity($id, $stock = 'no', $process_id = null, $listing = false){
        if($stock == 'no'){
            // Accept both 'stock' and 'quantity' fields for backward compatibility
            $stock = request('stock') ?? request('quantity');
        }

        // Check if this is an exact stock set request (from stock formula page)
        $setExactStock = request('set_exact_stock', false);
        $exactStockValue = request('exact_stock_value', null);

        if($process_id == null && request('process_id') != null){
            $process = Process_model::where('process_type_id',22)->where('id', request('process_id'))->first();
            if($process != null){
                $process_id = $process->id;
            }else{
                $process_id = null;
            }
        }
        $variation = Variation_model::find($id);
        $bm = new BackMarketAPIController();
        $previous_qty = $variation->update_qty($bm);

        $variation = Variation_model::find($id);

        if(!in_array($variation->state, [0,1,2,3])){
            return 'Ad State is not valid for Topup: '.$variation->state;
        }
        $pending_orders = $variation->pending_orders->sum('quantity');

        // Check for active verification (needed for both quantity calculation and verification record creation)
        $check_active_verification = Process_model::where('process_type_id',21)->where('status',1)->where('id', $process_id)->first();

        // If setting exact stock, use the exact value directly
        if($setExactStock && $exactStockValue !== null){
            $new_quantity = (int)$exactStockValue;
        } else {
            // Normal flow: calculate based on addition
            if($check_active_verification != null){
                $new_quantity = $stock - $pending_orders;
                // $new_quantity = $stock;
            }else{
                if($process_id != null && $previous_qty < 0 && $pending_orders == 0){
                    $new_quantity = $stock;
                }else{
                    $new_quantity = $stock + $previous_qty;
                }
            }
        }

        // V1 listing: Skip buffer (buffer only applies to V2 listing)
        $response = $bm->updateOneListing($variation->reference_id,json_encode(['quantity'=>$new_quantity]), null, true);
        if(is_string($response) || is_int($response) || is_null($response)){
            Log::error("Error updating quantity for variation ID $id: $response");
            return $response;
        }

        // Check if response is valid object and has quantity property
        $responseQuantity = null;
        if($response && is_object($response) && isset($response->quantity)){
            $responseQuantity = $response->quantity;
        } else {
            // If API response doesn't have quantity, fetch the actual quantity from API
            // This ensures we get the actual quantity after buffer is applied
            $variation->refresh();
            $actualQuantity = $variation->update_qty($bm);
            $responseQuantity = $actualQuantity;
            Log::warning("API response missing quantity property for variation ID $id, fetched actual quantity from API: $actualQuantity (sent: $new_quantity)");
        }

        if($responseQuantity != null){
            $oldStock = $variation->listed_stock;
            $variation->listed_stock = $responseQuantity;
            $variation->save();

            // Calculate stock change
            if($setExactStock && $exactStockValue !== null){
                // For exact stock set: calculate the difference
                $stockChange = $responseQuantity - $oldStock;
            } else {
                // For normal addition: use the stock parameter
                $stockChange = (int)$stock;
            }

            // Distribute stock to marketplaces based on formulas (synchronously)
            if($stockChange != 0 && $this->stockDistributionService !== null){
                // Call distribution service directly to ensure it completes before response
                // Pass flag to ignore remaining stock if it's an exact set
                $this->stockDistributionService->distributeStock(
                    $variation->id,
                    $stockChange,
                    $responseQuantity, // Pass total stock for formulas that use apply_to: total
                    $setExactStock // Pass flag to ignore remaining stock
                );

                // Note: Event listener is disabled to prevent double distribution
                // Distribution is done synchronously above to ensure it completes before response
                // If you need event logging, add it here without triggering distribution
            }

            // Get updated marketplace stocks after distribution
            $marketplaceStocks = MarketplaceStockModel::where('variation_id', $variation->id)
                ->get()
                ->mapWithKeys(function($stock) {
                    return [$stock->marketplace_id => $stock->listed_stock];
                });
        } else {
            $marketplaceStocks = collect();
        }

        // If active verification exists, use firstOrNew, otherwise create new
        if($check_active_verification != null){
            // Try to find existing record with process_id, variation_id, null qty_to, and not null qty_from
            $listed_stock_verification = Listed_stock_verification_model::where('process_id', $process_id)
                ->where('variation_id', $variation->id)
                ->whereNull('qty_to')
                ->whereNotNull('qty_from')
                ->first();

            // If not found, create a new one
            if (!$listed_stock_verification) {
                $listed_stock_verification = new Listed_stock_verification_model();
                $listed_stock_verification->process_id = $process_id;
                $listed_stock_verification->variation_id = $variation->id;
            }
        } else {
            $listed_stock_verification = new Listed_stock_verification_model();
            $listed_stock_verification->qty_from = $previous_qty;
        }

        $listed_stock_verification->process_id = $process_id;
        $listed_stock_verification->variation_id = $variation->id;
        $listed_stock_verification->pending_orders = $pending_orders;

        $listed_stock_verification->qty_change = $stock;
        $listed_stock_verification->qty_to = $responseQuantity ?? 0;
        $listed_stock_verification->admin_id = session('user_id');
        $listed_stock_verification->save();

        $variation->listed_stock = $responseQuantity ?? 0;
        $variation->save();

        // Return JSON response with total stock and marketplace stocks
        // Check if request is AJAX by checking headers
        if(request()->ajax() || request()->expectsJson() || request()->wantsJson() || request()->header('X-Requested-With') == 'XMLHttpRequest'){
            return response()->json([
                'quantity' => (int)($responseQuantity ?? 0),
                'total_stock' => (int)($responseQuantity ?? 0),
                'marketplace_stocks' => $marketplaceStocks->toArray()
            ]);
        }

        // For non-AJAX requests, return plain text (backward compatibility)
        return (string)($responseQuantity ?? 0);
    }

    /**
     * Add quantity for a specific marketplace
     * Updates marketplace_stock table for the specific marketplace
     */
    public function add_quantity_marketplace($variationId, $marketplaceId){
        $stockToAdd = request('stock');

        // Log incoming request for debugging
        Log::info("Marketplace stock add request", [
            'variation_id' => $variationId,
            'marketplace_id' => $marketplaceId,
            'marketplace_id_from_request' => request('marketplace_id'),
            'stock_to_add' => $stockToAdd,
            'all_request_data' => request()->all()
        ]);

        if($stockToAdd == null || $stockToAdd == ''){
            return response()->json(['error' => 'Stock value is required'], 400);
        }

        $process_id = null;
        if(request('process_id') != null){
            $process = Process_model::where('process_type_id',22)->where('id', request('process_id'))->first();
            if($process != null){
                $process_id = $process->id;
            }
        }

        $variation = Variation_model::find($variationId);
        if(!$variation){
            return response()->json(['error' => 'Variation not found'], 404);
        }

        if(!in_array($variation->state, [0,1,2,3])){
            return response()->json(['error' => 'Ad State is not valid for Topup: '.$variation->state], 400);
        }

        // Ensure marketplace_id is integer
        $marketplaceId = (int)$marketplaceId;

        // Get or create marketplace_stock record
        $marketplaceStock = MarketplaceStockModel::firstOrCreate(
            [
                'variation_id' => (int)$variationId,
                'marketplace_id' => $marketplaceId
            ],
            [
                'listed_stock' => 0,
                'admin_id' => session('user_id')
            ]
        );

        $previous_qty = $marketplaceStock->listed_stock ?? 0;
        $pending_orders = $variation->pending_orders->sum('quantity');

        // Calculate new quantity for this marketplace
        $new_quantity = (int)$previous_qty + (int)$stockToAdd;
        if($new_quantity < 0) {
            $new_quantity = 0;
        }

        // Update marketplace stock
        $marketplaceStock->listed_stock = $new_quantity;
        // available_stock will be automatically recalculated by model observer
        $marketplaceStock->admin_id = session('user_id');
        $marketplaceStock->save();

        // Calculate total stock across all marketplaces
        $totalStock = MarketplaceStockModel::where('variation_id', $variationId)
            ->sum('listed_stock');

        // Update variation.listed_stock to reflect total (for backward compatibility)
        $variation->listed_stock = $totalStock;
        $variation->save();

        // Update via API if variation has reference_id (BackMarket integration)
        if($variation->reference_id) {
            $bm = new BackMarketAPIController();
            // V1 listing: Skip buffer (buffer only applies to V2 listing)
            $apiResponse = $bm->updateOneListing($variation->reference_id, json_encode(['quantity' => $totalStock]), null, true);

            if(is_string($apiResponse) || is_int($apiResponse) || is_null($apiResponse)){
                Log::warning("API update warning for variation ID $variationId: $apiResponse");
                // Continue even if API update fails - we've updated the database
            } else if($apiResponse && isset($apiResponse->quantity)) {
                // If API returns different quantity, use that
                $variation->listed_stock = $apiResponse->quantity;
                $variation->save();
            }
        }

        // Create verification record
        $listed_stock_verification = new Listed_stock_verification_model();
        $listed_stock_verification->process_id = $process_id;
        $listed_stock_verification->variation_id = $variation->id;
        $listed_stock_verification->pending_orders = $pending_orders;
        $listed_stock_verification->qty_from = $previous_qty;
        $listed_stock_verification->qty_change = $stockToAdd;
        $listed_stock_verification->qty_to = $new_quantity;
        $listed_stock_verification->admin_id = session('user_id');
        $listed_stock_verification->save();

        Log::info("Marketplace stock update", [
            'variation_id' => $variationId,
            'marketplace_id' => $marketplaceId,
            'stock_change' => $stockToAdd,
            'previous_qty' => $previous_qty,
            'new_marketplace_qty' => $new_quantity,
            'total_stock' => $totalStock
        ]);

        // Return both marketplace stock and total stock
        return response()->json([
            'marketplace_stock' => $new_quantity,
            'total_stock' => $totalStock
        ]);
    }

    public function update_price($id){
        $listing = Listing_model::with(['variation', 'marketplace', 'country_id', 'currency'])->find($id);
        if($listing == null){
            return "Listing not found.";
        }

        // Update marketplace_id if provided
        if(request('marketplace_id')){
            $listing->marketplace_id = request('marketplace_id');
        }

        $variationId = $listing->variation_id;
        $marketplaceId = $listing->marketplace_id;
        $countryId = $listing->country;

        $changes = [];
        $updateData = [];

        // Track min_price change
        if(request('min_price')){
            $newMinPrice = request('min_price');
            $oldMinPrice = $listing->min_price;

            if ($oldMinPrice != $newMinPrice) {
                $updateData['min_price'] = $newMinPrice;
                $changes['min_price'] = [
                    'old' => $oldMinPrice,
                    'new' => $newMinPrice
                ];
            }
        }

        // Track price change
        if(request('price')){
            $newPrice = request('price');
            $oldPrice = $listing->price;

            if ($oldPrice != $newPrice) {
                $updateData['price'] = $newPrice;
                $changes['price'] = [
                    'old' => $oldPrice,
                    'new' => $newPrice
                ];
            }
        }

        // Update listing if there are changes
        if (!empty($updateData)) {
            // Capture snapshot BEFORE updating the listing
            $rowSnapshot = $this->captureListingSnapshot($listing);

            $listing->fill($updateData);
            $listing->save();

            $bm = new BackMarketAPIController();
            if(request('min_price')){
                $response = $bm->updateOneListing($listing->variation->reference_id,json_encode(['min_price'=>request('min_price'),'currency'=>$listing->currency->code]), $listing->country_id->market_code);
            }elseif(request('price')){
                $response = $bm->updateOneListing($listing->variation->reference_id,json_encode(['price'=>request('price'),'currency'=>$listing->currency->code]), $listing->country_id->market_code);
            }

            // Track changes in history with pre-captured snapshot
            $this->trackListingChanges($variationId, $marketplaceId, $listing->id, $countryId, $changes, 'listing', 'Price update via V1 form', $rowSnapshot);
        } else {
            // If no changes, still return response for backward compatibility
            $bm = new BackMarketAPIController();
            if(request('min_price')){
                $response = $bm->updateOneListing($listing->variation->reference_id,json_encode(['min_price'=>request('min_price'),'currency'=>$listing->currency->code]), $listing->country_id->market_code);
            }elseif(request('price')){
                $response = $bm->updateOneListing($listing->variation->reference_id,json_encode(['price'=>request('price'),'currency'=>$listing->currency->code]), $listing->country_id->market_code);
            }
        }
        if(request('min_price')){
            return $response;
        }elseif(request('price')){
            return $response;
        }

        return $response ?? null;
    }

    /**
     * Capture a snapshot of the listing row before changes
     * Used for history tracking
     */
    private function captureListingSnapshot($listing)
    {
        return [
            'id' => $listing->id,
            'variation_id' => $listing->variation_id,
            'marketplace_id' => $listing->marketplace_id,
            'country' => $listing->country,
            'currency_id' => $listing->currency_id,
            'currency' => [
                'id' => $listing->currency->id ?? null,
                'code' => $listing->currency->code ?? null,
                'sign' => $listing->currency->sign ?? null,
            ],
            'reference_uuid' => $listing->reference_uuid,
            'reference_uuid_2' => $listing->reference_uuid_2 ?? null,
            'name' => $listing->name ?? null,
            'min_price' => $listing->min_price,
            'max_price' => $listing->max_price ?? null,
            'price' => $listing->price,
            'buybox' => $listing->buybox,
            'buybox_price' => $listing->buybox_price,
            'buybox_winner_price' => $listing->buybox_winner_price ?? null,
            'min_price_limit' => $listing->min_price_limit,
            'price_limit' => $listing->price_limit,
            'handler_status' => $listing->handler_status,
            'target_price' => $listing->target_price ?? null,
            'target_percentage' => $listing->target_percentage ?? null,
            'admin_id' => $listing->admin_id ?? null,
            'status' => $listing->status ?? null,
            'is_enabled' => $listing->is_enabled ?? null,
            'created_at' => $listing->created_at ? $listing->created_at->toDateTimeString() : null,
            'updated_at' => $listing->updated_at ? $listing->updated_at->toDateTimeString() : null,
        ];
    }

    /**
     * Track listing changes in history
     * @param int $variationId
     * @param int $marketplaceId
     * @param int $listingId
     * @param int $countryId
     * @param array $changes
     * @param string $changeType
     * @param string|null $reason
     * @param array|null $rowSnapshot Optional pre-captured snapshot (if listing was already updated)
     */
    private function trackListingChanges($variationId, $marketplaceId, $listingId, $countryId, $changes, $changeType = 'listing', $reason = null, $rowSnapshot = null)
    {
        if (empty($changes)) {
            return;
        }

        // Get or create state record
        $state = \App\Models\ListingMarketplaceState::getOrCreateState($variationId, $marketplaceId, $listingId, $countryId);

        // Get the listing to retrieve actual values for first-time changes
        $listing = Listing_model::find($listingId);

        // If snapshot not provided, capture it now (before any updates)
        if ($rowSnapshot === null) {
            $rowSnapshot = $this->captureListingSnapshot($listing);
        }

        // Map field names from state fields to listing table columns
        $listingFieldMapping = [
            'min_handler' => 'min_price_limit',
            'price_handler' => 'price_limit',
            'buybox' => 'buybox',
            'buybox_price' => 'buybox_price',
            'min_price' => 'min_price',
            'price' => 'price',
        ];

        // For first-time changes, get actual values from listing table
        // This ensures old_value in history shows the actual database value, not null
        $needsSave = false;
        $explicitOldValues = [];

        foreach ($changes as $field => $values) {
            $stateField = $field;
            $listingField = $listingFieldMapping[$field] ?? null;

            // Determine the actual old value to use
            $actualOldValue = null;

            // If state field is null (first change), get the actual value from listing table
            if ($listing && $listingField && $state->$stateField === null) {
                // Prefer the 'old' value from changes array
                // Otherwise get from listing table
                if (isset($values['old'])) {
                    $actualOldValue = $values['old'];
                } else {
                    // Get from listing table - this is the true old value from database
                    $actualOldValue = $listing->$listingField;
                }
                // Set it in the state so we have the baseline for future changes
                $state->$stateField = $actualOldValue;
                $needsSave = true;
            } else {
                // Use current state value as old value
                $actualOldValue = $state->$stateField;
            }

            // Store explicit old value for this field
            $explicitOldValues[$stateField] = $actualOldValue;
        }

        // Save state if we updated any null values
        if ($needsSave) {
            $state->save();
        }

        // Prepare data for state update
        $stateData = [];
        foreach ($changes as $field => $values) {
            $stateData[$field] = $values['new'];
        }

        // Update state and track changes with explicit old values and row snapshot
        $state->updateState($stateData, $changeType, $reason, $explicitOldValues, $rowSnapshot);
    }

    /**
     * Get listing history for a specific listing (V1 version)
     * Same functionality as V2 for consistency
     */
    public function get_listing_history($listingId, Request $request)
    {
        $listing = Listing_model::with([
            'variation.product',
            'variation.storage_id',
            'variation.color_id',
            'marketplace',
            'country_id'
        ])->find($listingId);

        if (!$listing) {
            return response()->json([
                'error' => 'Listing not found'
            ], 404);
        }

        $variationId = $request->input('variation_id', $listing->variation_id);
        $marketplaceId = $request->input('marketplace_id', $listing->marketplace_id);
        $countryId = $request->input('country_id', $listing->country);

        // Get history for this listing
        $history = \App\Models\ListingMarketplaceHistory::where('listing_id', $listingId)
            ->with(['admin'])
            ->orderBy('changed_at', 'desc')
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'field_name' => $item->field_name,
                    'field_label' => $item->field_label,
                    'old_value' => $item->old_value,
                    'new_value' => $item->new_value,
                    'formatted_old_value' => $item->formatted_old_value,
                    'formatted_new_value' => $item->formatted_new_value,
                    'row_snapshot' => $item->row_snapshot,
                    'change_type' => $item->change_type,
                    'change_reason' => $item->change_reason,
                    'admin_id' => $item->admin_id,
                    'admin_name' => $item->admin ? ($item->admin->name ?? 'Admin #' . $item->admin_id) : 'System',
                    'changed_at' => $item->changed_at ? $item->changed_at->toDateTimeString() : null,
                ];
            });

        // Get descriptive information
        $variationName = 'N/A';
        if ($listing->variation && $listing->variation->product) {
            $product = $listing->variation->product;
            $variationName = $product->model ?? ($product->name ?? 'Variation #' . $listing->variation_id);
            // Add storage and color if available
            if ($listing->variation->storage_id) {
                $variationName .= ' ' . ($listing->variation->storage_id->name ?? '');
            }
            if ($listing->variation->color_id) {
                $variationName .= ' ' . ($listing->variation->color_id->name ?? '');
            }
        }

        return response()->json([
            'success' => true,
            'listing' => [
                'id' => $listing->id,
                'variation_id' => $listing->variation_id,
                'marketplace_id' => $listing->marketplace_id,
                'country_id' => $listing->country,
                'variation_name' => $variationName,
                'marketplace_name' => $listing->marketplace->name ?? 'Unknown',
                'country_code' => $listing->country_id->code ?? 'N/A',
            ],
            'history' => $history
        ]);
    }
    public function update_limit($id){
        $listing = Listing_model::find($id);
        $listing->min_price_limit = request('min_price_limit');
        $listing->price_limit = request('price_limit');

        // Update marketplace_id if provided
        if(request('marketplace_id')){
            $listing->marketplace_id = request('marketplace_id');
        }

        if($listing->min_price_limit == null && $listing->price_limit == null){
            $listing->handler_status = 0;
        }
        if($listing->min_price_limit != null || $listing->price_limit != null){
            $listing->handler_status = 1;
        }


        $listing->save();
        // print_r($response);
        // die;
        return $listing;
    }
    public function update_target($id){
        $listing = Listing_model::find($id);

        if (!$listing) {
            return response()->json(['error' => 'Listing not found'], 404);
        }

        // V1/V2 Pattern: Only update EUR listings (currency_id = 4, country = 73)
        // Safety check: Ensure we're only updating EUR listings (even though get_target_variations filters by country 73)
        if ($listing->currency_id != 4) {
            return response()->json([
                'error' => 'Target updates are only allowed for EUR listings (currency_id = 4)',
                'currency_id' => $listing->currency_id
            ], 400);
        }

        $listing->target_price = request('target');
        $listing->target_percentage = request('percent');

        $listing->save();
        // print_r($response);
        // die;
        return $listing;
    }

    /**
     * Toggle enable/disable status for a listing
     */
    public function toggle_enable($id){
        $listing = Listing_model::find($id);
        if($listing == null){
            return response()->json(['error' => 'Listing not found'], 404);
        }

        // Get the requested status (1 = enabled, 0 = disabled)
        $isEnabled = request('is_enabled');
        if($isEnabled === null){
            // If not provided, toggle the current value
            $listing->is_enabled = $listing->is_enabled == 1 ? 0 : 1;
        } else {
            $listing->is_enabled = (int)$isEnabled;
        }

        $listing->save();

        Log::info("Listing enable/disable toggled", [
            'listing_id' => $id,
            'is_enabled' => $listing->is_enabled
        ]);

        return response()->json([
            'success' => true,
            'is_enabled' => $listing->is_enabled,
            'message' => $listing->is_enabled == 1 ? 'Listing enabled' : 'Listing disabled'
        ]);
    }

    public function start_listing_verification(){

        $bm = new BackMarketAPIController();
        $variations = Variation_model::where('listed_stock','>',0)->whereNotNull('reference_id')->get();
        $check_active_verification = Process_model::where('process_type_id',21)->where('status',1)->first();
        if($check_active_verification != null){
            session()->flash('error', 'There is already an active listing verification process.');
            return redirect()->back();
        }
        $last_process = Process_model::where('process_type_id',21)->orderBy('reference_id','desc')->first();
        if($last_process != null){
            $last_process = $last_process->reference_id;
        }else{
            $last_process = 9000;
        }
        $listing_verification = new Process_model();
        $listing_verification->description = "Listing verification";
        $listing_verification->process_type_id = 21;
        $listing_verification->reference_id = $last_process + 1;
        $listing_verification->admin_id = session('user_id');
        $listing_verification->status = 1;
        $listing_verification->save();

        foreach($variations as $variation){
            $updatedQuantity = $variation->update_qty($bm);
            $listed_stock_verification = Listed_stock_verification_model::firstOrNew(['process_id'=>$listing_verification->id, 'variation_id'=>$variation->id]);
            $listed_stock_verification->qty_from = $updatedQuantity;
            $listed_stock_verification->admin_id = session('user_id');
            $listed_stock_verification->save();

            // V1 listing: Skip buffer (buffer only applies to V2 listing)
            $response = $bm->updateOneListing($variation->reference_id,json_encode(['quantity'=>0]), null, true);

            if($response->quantity != null){
                $variation->listed_stock = $response->quantity;
                $variation->save();
            }
        }


        return redirect()->to(url('listing?special=verify_listing&sort=4'))->with('success', 'Listing verification process started successfully.');
    }
}
