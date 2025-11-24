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
use App\Models\Order_item_model;
use App\Models\Order_model;
use App\Models\Process_model;
use App\Models\Process_stock_model;
use App\Models\Products_model;
use App\Models\Stock_model;
use App\Models\Storage_model;
use App\Models\Variation_model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ListingController extends Controller
{
    //
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
            'available_stocks',
            'pending_orders',
            'storage_id',
            'color_id',
            'grade_id',
        ]);

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
        ->when($request->filled('listed_stock'), function ($q) use ($request) {
            if ((int) $request->input('listed_stock') === 1) {
                return $q->where('listed_stock', '>', 0);
            }

            if ((int) $request->input('listed_stock') === 2) {
                return $q->where('listed_stock', '<=', 0);
            }
        })
        ->when($request->filled('available_stock'), function ($q) use ($request) {
            if ((int) $request->input('available_stock') === 1) {
                return $q->whereHas('available_stocks')
                    ->withCount(['available_stocks', 'pending_orders'])
                    ->havingRaw('(available_stocks_count - pending_orders_count) > 0');
            }

            if ((int) $request->input('available_stock') === 2) {
                return $q->whereDoesntHave('available_stocks');
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
        $pendingCount = $variation->pending_orders ? $variation->pending_orders->count() : 0;

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
        // if ($variation->product->brand == 2) {
        //     $variation_ids = Variation_model::where('product_storage_sort_id', $variation->product_storage_sort_id)->whereIn('grade',[1,2,3,4,5,7,9])->pluck('id');
        //     $stocks = Stock_model::whereIn('variation_id', $variation_ids)->where('status', 1)->whereHas('active_order')->get();
        // } elseif (in_array($variation->product->category, [3, 6])) {
        //     $variation_ids = Variation_model::where('product_storage_sort_id', $variation->product_storage_sort_id)->whereIn('grade',[1,2,3,4,5,7,9])->pluck('id');
        //     $stocks = Stock_model::whereIn('variation_id', $variation_ids)->where('status', 1)->whereHas('active_order')->get();
        // } else {
            $stocks = Stock_model::where('variation_id', $id)->where('status', 1)
            ->whereHas('latest_listing_or_topup')
            ->get();
        // }

        $stock_costs = Order_item_model::whereHas('order', function($q){
            $q->where('order_type_id',1);
        })->whereIn('stock_id',$stocks->pluck('id'))->pluck('price','stock_id');

        $vendors = Customer_model::whereNotNull('is_vendor')->pluck('last_name','id');

        $po = Order_model::where('order_type_id',1)->pluck('customer_id','id');

        $reference = Order_model::where('order_type_id',1)->pluck('reference_id','id');

        $topup_reference = Process_model::whereIn('process_type_id',[21,22])->pluck('reference_id','id');

        $latest_topup_items = Process_stock_model::whereIn('process_id', $topup_reference->keys())->whereIn('stock_id',$stocks->pluck('id'))->pluck('process_id','stock_id');

        if($stock_costs->count() > 0){

            $breakeven_price = ($stock_costs->average()+20)/0.88;

            if($breakeven_price != $variation->breakeven_price){
                $variation->breakeven_price = $breakeven_price;
                $variation->save();
            }
        }else{
            $breakeven_price = 0;
        }

        return response()->json(['stocks'=>$stocks, 'stock_costs'=>$stock_costs, 'vendors'=>$vendors, 'po'=>$po, 'reference'=>$reference, 'breakeven_price'=>$breakeven_price, 'latest_topup_items'=>$latest_topup_items, 'topup_reference'=>$topup_reference]);

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
            $q->whereBetween('created_at', [now()->startOfDay(), now()])->where('order_type_id',3);
        })->avg('price');
        $order_items_count = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->startOfDay(), now()])->where('order_type_id',3);
        })->count();

        return "Today: €".number_format($order_items,2)." (".$order_items_count.")";
    }
    public function get_yesterday_average($id){
        $order_items = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->yesterday()->startOfDay(), now()->yesterday()->endOfDay()])->where('order_type_id',3);
        })->avg('price');
        $order_items_count = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->yesterday()->startOfDay(), now()->yesterday()->endOfDay()])->where('order_type_id',3);
        })->count();

        return "Yesterday: €".number_format($order_items,2)." (".$order_items_count.")";
    }
    public function get_last_week_average($id){
        $order_items = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(7), now()->yesterday()->endOfDay()])->where('order_type_id',3);
        })->avg('price');
        $order_items_count = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(7), now()->yesterday()->endOfDay()])->where('order_type_id',3);
        })->count();

        return "7 days: €".number_format($order_items,2)." (".$order_items_count.")";
    }
    public function get_2_week_average($id){
        $order_items = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(14), now()->yesterday()->endOfDay()])->where('order_type_id',3);
        })->avg('price');
        $order_items_count = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(14), now()->yesterday()->endOfDay()])->where('order_type_id',3);
        })->count();

        return "14 days: €".number_format($order_items,2)." (".$order_items_count.")";
    }
    public function get_30_days_average($id){
        $order_items = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(30), now()->yesterday()->endOfDay()])->where('order_type_id',3);
        })->avg('price');
        $order_items_count = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(30), now()->yesterday()->endOfDay()])->where('order_type_id',3);
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
                // Log::error($error);
                return response()->json(['error'=>$error]);
            }
            // Log::info("Responses for variation ID $id: " . json_encode($responses));
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
        $response = $bm->updateOneListing($variation->reference_id,json_encode(['quantity'=>request('stock')]));
        if($response->quantity != null){
            $variation->listed_stock = $response->quantity;
            $variation->save();
        }
        return $response->quantity;
    }
    public function add_quantity($id, $stock = 'no', $process_id = null){
        if($stock == 'no'){
            $stock = request('stock');
        }
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

        $check_active_verification = Process_model::where('process_type_id',21)->where('status',1)->where('id', $process_id)->first();
        if($check_active_verification != null){
            $new_quantity = $stock - $pending_orders;
            $new_quantity = $stock;
        }else{
            if($process_id != null && $previous_qty < 0 && $pending_orders == 0){
                $new_quantity = $stock;
            }else{
                $new_quantity = $stock + $previous_qty;
            }
        }
        $response = $bm->updateOneListing($variation->reference_id,json_encode(['quantity'=>$new_quantity]));
        if(is_string($response) || is_int($response) || is_null($response)){
            Log::error("Error updating quantity for variation ID $id: $response");
            return $response;
        }
        if($response->quantity != null){
            $variation->listed_stock = $response->quantity;
            $variation->save();
        }
        $listed_stock_verification = new Listed_stock_verification_model();
        $listed_stock_verification->process_id = $process_id;
        $listed_stock_verification->variation_id = $variation->id;
        $listed_stock_verification->pending_orders = $pending_orders;
        $listed_stock_verification->qty_from = $previous_qty;
        $listed_stock_verification->qty_change = $stock;
        $listed_stock_verification->qty_to = $response->quantity;
        $listed_stock_verification->admin_id = session('user_id');
        $listed_stock_verification->save();

        return $response->quantity;
    }
    public function update_price($id){
        $listing = Listing_model::find($id);
        if($listing == null){
            return "Listing not found.";
        }
        
        // Update marketplace_id if provided
        if(request('marketplace_id')){
            $listing->marketplace_id = request('marketplace_id');
        }
        
        $bm = new BackMarketAPIController();
        if(request('min_price')){
            $listing->min_price = request('min_price');
            $response = $bm->updateOneListing($listing->variation->reference_id,json_encode(['min_price'=>request('min_price'),'currency'=>$listing->currency->code]), $listing->country_id->market_code);
        }elseif(request('price')){
            $listing->price = request('price');
            $response = $bm->updateOneListing($listing->variation->reference_id,json_encode(['price'=>request('price'),'currency'=>$listing->currency->code]), $listing->country_id->market_code);
        }

        $listing->save();
        // print_r($response);
        // die;
        // if($listing->country_id->code == 'SE'){
        //     Log::info("Updated listing price for listing ID $id: " . json_encode($response));
        // }
        if(request('min_price')){
            return $response;
        }elseif(request('price')){
            return $response;
        }
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
        $listing->target_price = request('target');
        $listing->target_percentage = request('percent');

        $listing->save();
        // print_r($response);
        // die;
        return $listing;
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

            $response = $bm->updateOneListing($variation->reference_id,json_encode(['quantity'=>0]));

            if($response->quantity != null){
                $variation->listed_stock = $response->quantity;
                $variation->save();
            }
        }


        return redirect()->to(url('listing?special=verify_listing&sort=4'))->with('success', 'Listing verification process started successfully.');
    }
}
