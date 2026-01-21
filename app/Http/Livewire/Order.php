<?php

namespace App\Http\Livewire;
    use App\Http\Controllers\BackMarketAPIController;
    use App\Http\Controllers\RefurbedAPIController;
    use Livewire\Component;
    use App\Models\Admin_model;
    use App\Models\Variation_model;
    use App\Models\Products_model;
    use App\Models\Stock_model;
    use App\Models\Order_model;
    use App\Models\Order_item_model;
    use App\Models\Order_status_model;
    use App\Models\Customer_model;
    use App\Models\Currency_model;
    use App\Models\Country_model;
    use App\Models\Storage_model;
    use Carbon\Carbon;
    use App\Exports\OrdersExport;
    use App\Exports\PickListExport;
    use App\Exports\LabelsExport;
    use App\Exports\DeliveryNotesExport;
    use App\Exports\OrdersheetExport;
    use App\Exports\PurchasesheetExport;
    use App\Services\RefurbedCommercialInvoiceService;
    use App\Services\RefurbedOrderLineStateService;
    use App\Services\RefurbedShippingService;
use Illuminate\Support\Facades\DB;
    use Maatwebsite\Excel\Facades\Excel;
    use TCPDF;
    use App\Mail\InvoiceMail;
use App\Models\Account_transaction_model;
use App\Models\Api_request_model;
use App\Models\Brand_model;
use App\Models\Category_model;
use App\Models\Color_model;
use App\Models\ExchangeRate;
use App\Models\Grade_model;
use App\Models\Listed_stock_verification_model;
use App\Models\Marketplace_model;
use App\Models\Order_issue_model;
use App\Models\Process_model;
use App\Models\Process_stock_model;
use App\Models\Product_color_merge_model;
use App\Models\Product_storage_sort_model;
use App\Models\Stock_operations_model;
use App\Models\Stock_movement_model;
use App\Models\Vendor_grade_model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;


class Order extends Component
{
    protected const REFURBED_DEFAULT_CARRIER = 'DHL_EXPRESS';
    protected const REFURBED_MARKETPLACE_ID = 4;

    public function mount()
    {
        $user_id = session('user_id');
        if($user_id == NULL){
            return redirect('index');
        }
    }
    public function render()
    {
        // ini_set('memory_limit', '2560M');
        $data['title_page'] = "Sales";
        session()->put('page_title', $data['title_page']);
        $data['storages'] = session('dropdown_data')['storages'];
        $data['colors'] = session('dropdown_data')['colors'];
        $data['grades'] = session('dropdown_data')['grades'];
        $data['topups'] = Process_model::where('process_type_id', 22)
        ->where('status', '>=', 2)->orderByDesc('id')
        ->pluck('reference_id', 'id');

        $data['currencies'] = Currency_model::pluck('sign', 'id');
        $data['last_hour'] = Carbon::now()->subHour();
        $data['admins'] = Admin_model::pluck('first_name','id');
        $data['testers'] = Admin_model::where('role_id',7)->pluck('last_name');
        $user_id = session('user_id');
        $data['user_id'] = $user_id;
        $data['pending_orders_count'] = Order_model::where('order_type_id',3)->where('status',2)->count();
        $data['missing_charge_count'] = Order_model::where('order_type_id',3)->whereNot('status',2)->whereNull('charges')->where('processed_at','<=',now()->subHours(12))->count();
        $data['missing_processed_at_count'] = Order_model::where('order_type_id',3)->whereIn('status',[3,6])->where('processed_at',null)->count();
        $data['order_statuses'] = Order_status_model::pluck('name','id');
        $data['marketplaces'] = Marketplace_model::pluck('name','id');
        $refurbedShippingDefaults = $this->buildRefurbedShippingDefaults();
        $data['refurbedShippingDefaults'] = $refurbedShippingDefaults;
        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 10;
        }
        // if(request('care')){
        //     foreach(Order_model::where('status',2)->pluck('reference_id') as $pend){
        //         $this->recheck($pend);
        //     }
        // }

        switch (request('sort')){
            case 2: $sort = "orders.reference_id"; $by = "ASC"; break;
            case 3: $sort = "products.model"; $by = "DESC"; break;
            case 4: $sort = "products.model"; $by = "ASC"; break;
            default: $sort = "orders.reference_id"; $by = "DESC";
        }
        if(request('start_date') != '' && request('start_time') != ''){
            $start_date = request('start_date').' '.request('start_time');
        }elseif(request('start_date') != ''){
            $start_date = request('start_date');
        }else{
            $start_date = 0;
        }

        if(request('end_date') != '' && request('end_time') != ''){
            $end_date = request('end_date').' '.request('end_time');
        }elseif(request('end_date') != ''){
            $end_date = request('end_date')." 23:59:59";
        }else{
            $end_date = now();
        }


        $difference_variations = [];
        if(request('exclude_topup') != [] && request('exclude_topup') != null){
            $listed_stock_verification = Listed_stock_verification_model::whereIn('process_id', request('exclude_topup'))->get();

            $variations = Variation_model::whereIn('id', $listed_stock_verification->pluck('variation_id'))->get()->keyBy('id');

            foreach($variations as $variation){
                $difference = $listed_stock_verification->where('variation_id', $variation->id)->sum('qty_change') - $variation->listed_stock;
                if($difference > 0){
                    $difference_variations[$variation->id] = $difference;
                }
            }
        }

        // Removed 'customer.orders' from eager loading - it was loading ALL orders for each customer causing massive performance issues
        // Instead, we'll load customer orders separately with constraints (only recent orders, max 50 per customer)
        $orders = Order_model::with([
            'customer' => function($query) {
                // Load customer with constrained orders (only recent orders, max 50 to prevent performance issues)
                $query->with(['orders' => function($q) {
                    $q->where('order_type_id', 3)
                      ->orderBy('created_at', 'desc')
                      ->limit(50); // Limit to 50 most recent orders per customer
                }]);
            },
            'order_items',
            'order_items.variation',
            'order_items.variation.product',
            'order_items.variation.grade_id',
            'order_items.stock',
            'order_items.replacement',
            'transactions',
            'order_charges'
        ])
        // ->where('orders.order_type_id',3)
        ->when(request('marketplace') != null && request('marketplace') != 0, function ($q) {
            return $q->where('marketplace_id', request('marketplace'));
        })
        ->when(request('marketplace') == null, function ($q) {
            return $q->where('marketplace_id', 1);
        })
        ->when(request('type') == '', function ($q) {
            return $q->where('orders.order_type_id',3);
        })
        ->when(request('items') == 1, function ($q) {
            return $q->whereHas('order_items', operator: '>', count: 1);
        })

        ->when(request('start_date') != '', function ($q) use ($start_date) {
            if(request('adm') > 0){
                return $q->where('orders.processed_at', '>=', $start_date);
            }else{
                return $q->where('orders.created_at', '>=', $start_date);

            }
        })
        ->when(request('end_date') != '', function ($q) use ($end_date) {
            if(request('adm') > 0){
                return $q->where('orders.processed_at', '<=',$end_date)->orderBy('orders.processed_at','desc');
            }else{
                return $q->where('orders.created_at', '<=',$end_date);
            }
        })
        ->when(request('status') != '', function ($q) {
            return $q->where('orders.status', request('status'));
        })
        ->when(request('adm') != '', function ($q) {
            if(request('adm') == 0){
                return $q->where('orders.processed_by', null);
            }
            return $q->where('orders.processed_by', request('adm'));
        })
        ->when(request('care') != '', function ($q) {
            return $q->whereHas('order_items', function ($query) {
                $query->where('care_id', '!=', null);
            });
        })
        ->when(request('missing') == 'reimburse', function ($q) {
            return $q->whereHas('order_items.linked_child', function ($qu) {
                $qu->whereHas('order', function ($q) {
                    $q->where('orders.status', '!=', 1);
                });
            })->where('status', 3)->orderBy('orders.updated_at','desc');
        })
        ->when(request('missing') == 'refund', function ($q) {
            return $q->whereDoesntHave('order_items.linked_child')->wherehas('order_items.stock', function ($q) {
                $q->where('status', '!=', null);
            })->where('status', 6)->orderBy('orders.updated_at','desc');
        })
        ->when(request('missing') == 'charge', function ($q) {
            return $q->whereNot('status', 2)->whereNull('charges')->where('processed_at', '<=', now()->subHours(12));
        })
        ->when(request('missing') == 'scan', function ($q) {
            return $q->whereIn('status', [3,6])->whereNull('scanned')->where('processed_at', '<=', now()->subHours(24));
        })
        ->when(request('missing') == 'purchase', function ($q) {
            return $q->whereHas('order_items.stock', function ($q) {
                $q->whereNull('status');
            });
        })
        ->when(request('missing') == 'processed_at', function ($q) {
            return $q->whereIn('status', [3,6])->whereNull('processed_at');
        })
        ->when(request('transaction') == 1, function ($q) {
            return $q->whereHas('transactions', function ($q) {
                $q->where('status', null);
            });
        })
        ->when(request('order_id') != '', function ($q) {
            if(str_contains(request('order_id'),'<')){
                $order_ref = str_replace('<','',request('order_id'));
                return $q->where('orders.reference_id', '<', $order_ref);
            }elseif(str_contains(request('order_id'),'>')){
                $order_ref = str_replace('>','',request('order_id'));
                return $q->where('orders.reference_id', '>', $order_ref);
            }elseif(str_contains(request('order_id'),'<=')){
                $order_ref = str_replace('<=','',request('order_id'));
                return $q->where('orders.reference_id', '<=', $order_ref);
            }elseif(str_contains(request('order_id'),'>=')){
                $order_ref = str_replace('>=','',request('order_id'));
                return $q->where('orders.reference_id', '>=', $order_ref);
            }elseif(str_contains(request('order_id'),'-')){
                $order_ref = explode('-',request('order_id'));
                return $q->whereBetween('orders.reference_id', $order_ref);
            }elseif(str_contains(request('order_id'),',')){
                $order_ref = explode(',',request('order_id'));
                return $q->whereIn('orders.reference_id', $order_ref);
            }elseif(str_contains(request('order_id'),' ')){
                $order_ref = explode(' ',request('order_id'));
                return $q->whereIn('orders.reference_id', $order_ref);
            }else{
                return $q->where('orders.reference_id', 'LIKE', request('order_id') . '%');
            }
        })
        ->when(request('sku') != '', function ($q) {
            return $q->whereHas('order_items.variation', function ($q) {
                $q->where('sku', 'LIKE', '%' . request('sku') . '%');
            });
        })
        ->when(request('imei') != '', function ($q) {
            if(str_contains(request('imei'),' ')){
                $imei = explode(' ',request('imei'));
                return $q->whereHas('order_items.stock', function ($q) use ($imei) {
                    $q->whereIn('imei', $imei);
                });
            }else{

                return $q->whereHas('order_items.stock', function ($q) {
                    $q->where('imei', 'LIKE', '%' . request('imei') . '%');
                });
            }
        })
        ->when(request('currency') != '', function ($q) {
            return $q->where('currency', request('currency'));
        })
        ->when(request('tracking_number') != '', function ($q) {
            if(strlen(request('tracking_number')) == 21){
                $tracking = substr(request('tracking_number'),1);
            }else{
                $tracking = request('tracking_number');
            }
            return $q->where('tracking_number', 'LIKE', '%' . $tracking . '%');
        })
        ->when(request('with_stock') == 2, function ($q) {
            return $q->whereHas('order_items', function ($q) {
                $q->where('stock_id', 0);
            });
        })
        ->when(request('with_stock') == 1, function ($q) {
            return $q->whereHas('order_items', function ($q) {
                $q->where('stock_id','>', 0);
            });
        })

        ->when(request('sort') == 4, function ($q) {
            return $q->join('order_items', 'order_items.order_id', '=', 'orders.id')
                ->join('variation', 'order_items.variation_id', '=', 'variation.id')
                ->join('products', 'variation.product_id', '=', 'products.id')
                ->where(['orders.deleted_at' => null, 'order_items.deleted_at' => null, 'variation.deleted_at' => null, 'products.deleted_at' => null])
                ->orderBy('products.model', 'ASC')
                ->orderBy('variation.storage', 'ASC')
                ->orderBy('variation.color', 'ASC')
                ->orderBy('variation.grade', 'ASC')
                ->orderBy('variation.sku', 'ASC')
                ->select('orders.id','orders.reference_id','orders.customer_id','orders.delivery_note_url','orders.label_url','orders.tracking_number','orders.status','orders.processed_by','orders.created_at','orders.processed_at');
        })
        // })
        ->when(request('adm') > 0, function ($q) {
            return $q->orderBy('orders.processed_at', 'desc');
        })
        ->orderBy('orders.reference_id', 'desc'); // Secondary order by reference_id


        if($difference_variations != [] && request('exclude_topup') != [] && request('exclude_topup') != null){
            $orders_clone = $orders->clone();
            $orders_clone = $orders_clone->whereHas('order_items', function ($q) use ($difference_variations) {
                $q->whereIn('variation_id', array_keys($difference_variations));
            })->get();
            $ids = [];
            foreach($orders_clone as $ref => $order){
                // echo $order->reference_id . '<br>';
                foreach($order->order_items as $item){
                    // echo $item->variation_id . ' - ' . $item->variation->sku . '<br>';
                    if(isset($difference_variations[$item->variation_id]) && $difference_variations[$item->variation_id] > 0){
                        if(!in_array($item->order_id, $ids)){
                            $ids[] = $item->order_id;
                        }
                        // echo $item->variation_id . ' - ' . $difference_variations[$item->variation_id] . '<br>';
                        $difference_variations[$item->variation_id] -= 1;
                    }
                }
            }
            // dd($ids, $difference_variations, $orders_clone);
            $orders = $orders->whereNotIn('orders.id', $ids);
            // dd($difference_variations, array_sum($difference_variations));
        }

        if(request('bulk_invoice') && request('bulk_invoice') == 1){

            $data['orders2'] = $orders->get();
            foreach($data['orders2'] as $order){

                $data2 = [
                    'order' => $order,
                    'customer' => $order->customer,
                    'orderItems' => $order->order_items,
                ];

                // TEMPORARILY DISABLED: InvoiceMail sending disabled to prevent queue/system hogging
                // TODO: Re-enable when queue is stable
                // Mail::to($order->customer->email)->send(new InvoiceMail($data2));
                // sleep(2);

            }
            // return redirect()->back();

        }

        $data['orders'] = $this->buildOrdersPaginator($orders, $per_page);

        if ($this->tryFetchMissingRefurbedOrders()) {
            $data['orders'] = $this->buildOrdersPaginator($orders, $per_page);
        }


        if(request('missing') == 'processed_at'){
            $reference_ids = $data['orders']->pluck('reference_id');
            foreach($reference_ids as $ref){
                $this->recheck($ref);
            }
        }
        if ((int) request('marketplace', 0) !== self::REFURBED_MARKETPLACE_ID) {
            $ors = explode(' ', request('order_id'));
            if (count($data['orders']) != count($ors) && request('order_id')) {
                foreach ($ors as $or) {
                    $this->recheck($or);
                }
            }
        }
        // dd($data['orders']);
        return view('livewire.order')->with($data);
    }

    public function get_b2c_orders_by_customer_json($customer_id, $exclude_order)
    {
        $orders = Order_model::where('customer_id', $customer_id)
        ->where('order_type_id', 3)
        ->whereNot('id', $exclude_order)
        ->get();

        $items = Order_item_model::whereIn('order_id', $orders->pluck('id'))->get();
        $orderDetails = [];

        foreach ($orders as $ord) {
            $orderDetails[$ord->id] = [
                'customer' => $ord->customer->first_name . " " . $ord->customer->last_name . " " . $ord->customer->phone,
                'reference_id' => $ord->reference_id,
                'status' => $ord->status,
                'charges' => $ord->charges,
                'currency' => $ord->currency_id->sign,
                'price' => $ord->price,
                'order_status' => $ord->order_status->name,
                'created_at' => $ord->created_at,
                'updated_at' => $ord->updated_at,
            ];
            foreach ($ord->order_items as $itm) {
            $orderDetails[$ord->id]['items'][$itm->id] = [
                'sku' => $itm->variation->sku ?? '',
                'product_model' => $itm->variation->product->model ?? 'Model not defined',
                'storage' => $itm->variation->storage_id->name ?? '',
                'color' => $itm->variation->color_id->name ?? '',
                'grade' => $itm->variation->grade_id->name ?? '',
                'care_id' => $itm->care_id,
                'quantity' => $itm->quantity,
                'imei' => $itm->stock->imei ?? '',
                'serial_number' => $itm->stock->serial_number ?? '',
            ];
            }
        }

        return response()->json([
            'orders' => $orders,
            'items' => $items,
            'orderDetails' => $orderDetails,
        ]);



    }

    public function get_orders_by_customer_json($customer_id, $order_type_id, $exclude_orders = [])
    {
        $orders = Order_model::where('customer_id', $customer_id)
        ->where('order_type_id', $order_type_id)
        ->whereNotIn('id', $exclude_orders)
        ->get();

        $items = Order_item_model::whereIn('order_id', $orders->pluck('id'))->get();

        return response()->json([
            'orders' => $orders,
            'items' => $items,
        ]);
    }

    public function mark_scanned($id)
    {
        $order = Order_model::find($id);
        if($order->scanned == null && ($order->status == 3 || $order->status == 6) && ($order->label_url == null || $order->reference != null)){
            $order->scanned = 1;
            $order->save();
        }elseif($order->scanned == null && request('force') == 1){
            $order->scanned = 2;
            $order->save();
        }

        session()->flash('message', 'Order marked as scanned');
        session()->put('success', 'Order marked as scanned');
        return redirect()->back();
    }

    public function sales_allowed()
    {
        $data['title_page'] = "Sales (Admin)";
        session()->put('page_title', $data['title_page']);

        $data['grades'] = Grade_model::all();
        $data['last_hour'] = Carbon::now()->subHour();
        $data['admins'] = Admin_model::where('id','!=',1)->get();
        $data['testers'] = Admin_model::where('role_id',7)->pluck('last_name');
        $user_id = session('user_id');
        $data['user_id'] = $user_id;
        $data['pending_orders_count'] = Order_model::where('order_type_id',3)->where('status',2)->count();
        $data['order_statuses'] = Order_status_model::get();
        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 10;
        }

        switch (request('sort')){
            case 2: $sort = "orders.reference_id"; $by = "ASC"; break;
            case 3: $sort = "products.model"; $by = "DESC"; break;
            case 4: $sort = "products.model"; $by = "ASC"; break;
            default: $sort = "orders.reference_id"; $by = "DESC";
        }

        $orders = Order_model::with(['order_items','order_items.variation', 'order_items.variation.grade_id', 'order_items.stock'])
        ->where('order_type_id',3)
        ->when(request('start_date') != '', function ($q) {
            if(request('adm') > 0){
                return $q->where('processed_at', '>=', request('start_date', 0));
            }else{
                return $q->where('created_at', '>=', request('start_date', 0));

            }
        })
        ->when(request('end_date') != '', function ($q) {
            if(request('adm') > 0){
                return $q->where('processed_at', '<=', request('end_date', 0) . " 23:59:59")->orderBy('processed_at','desc');
            }else{
                return $q->where('created_at', '<=', request('end_date', 0) . " 23:59:59");
            }
        })
        ->when(request('status') != '', function ($q) {
            return $q->where('status', request('status'));
        })
        ->when(request('adm') != '', function ($q) {
            if(request('adm') == 0){
                return $q->where('processed_by', null);
            }
            return $q->where('processed_by', request('adm'));
        })
        ->when(request('care') != '', function ($q) {
            return $q->whereHas('order_items', function ($query) {
                $query->where('care_id', '!=', null);
            });
        })
        ->when(request('order_id') != '', function ($q) {
            return $q->where('reference_id', 'LIKE', request('order_id') . '%');
        })
        ->when(request('sku') != '', function ($q) {
            return $q->whereHas('order_items.variation', function ($q) {
                $q->where('sku', 'LIKE', '%' . request('sku') . '%');
            });
        })
        ->when(request('imei') != '', function ($q) {
            return $q->whereHas('order_items.stock', function ($q) {
                $q->where('imei', 'LIKE', '%' . request('imei') . '%');
            });
        })
        ->when(request('tracking_number') != '', function ($q) {
            if(strlen(request('tracking_number')) == 21){
                $tracking = substr(request('tracking_number'),1);
            }else{
                $tracking = request('tracking_number');
            }
            return $q->where('tracking_number', 'LIKE', '%' . $tracking . '%');
        })
        ->orderBy($sort, $by) // Order by variation name
        ->when(request('sort') == 4, function ($q) {
            return $q->whereHas('order_items.variation.product', function ($q) {
                $q->orderBy('model', 'ASC');
            })->whereHas('order_items.variation', function ($q) {
                $q->orderBy('variation.storage', 'ASC');
            })->whereHas('order_items.variation', function ($q) {
                $q->orderBy('variation.color', 'ASC');
            })->whereHas('order_items.variation', function ($q) {
                $q->orderBy('variation.grade', 'ASC');
            });

        })
        ->orderBy('reference_id', 'desc'); // Secondary order by reference_id
        if(request('bulk_invoice') && request('bulk_invoice') == 1){

            $data['orders2'] = $orders
            ->get();
            foreach($data['orders2'] as $order){

                $data2 = [
                    'order' => $order,
                    'customer' => $order->customer,
                    'orderItems' => $order->order_items,
                ];
                // TEMPORARILY DISABLED: InvoiceMail sending disabled to prevent queue/system hogging
                // TODO: Re-enable when queue is stable
                // Mail::to($order->customer->email)->send(new InvoiceMail($data2));

            }
            // return redirect()->back();

        }
        $data['orders'] = $orders
        ->paginate($per_page)
        ->onEachSide(5)
        ->appends(request()->except('page'));

        if(count($data['orders']) == 0 && request('order_id')){
            $this->recheck(request('order_id'));
        }
        // dd($data['orders']);
        return view('livewire.sales_allowed')->with($data);
    }
    public function purchase()
    {

        $data['title_page'] = "Purchases";
        session()->put('page_title', $data['title_page']);
        $data['latest_reference'] = Order_model::where('order_type_id',1)->orderBy('reference_id','DESC')->first()->reference_id ?? 9998;
        $data['vendors'] = Customer_model::whereNotNull('is_vendor')->pluck('first_name','id');
        $data['order_statuses'] = Order_status_model::get();
        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 10;
        }

        $data['orders'] = Order_model::with('order_items', 'order_issues')->withCount('order_items_available as available_stock')
        ->where('orders.order_type_id', 1)
        ->when(request('start_date'), function ($q) {
            return $q->where('orders.created_at', '>=', request('start_date'));
        })
        ->when(request('end_date'), function ($q) {
            return $q->where('orders.created_at', '<=', request('end_date') . " 23:59:59");
        })
        ->when(request('order_id'), function ($q) {
            return $q->where('orders.reference_id', 'LIKE', request('order_id') . '%');
        })
        ->when(request('customer_id'), function ($q) {
            return $q->where('orders.customer_id', request('customer_id'));
        })
        ->when(request('status'), function ($q) {
            return $q->where('orders.status', request('status'));
        })
        ->when(request('status') == 3 && request('stock') == 0, function ($query) {
            return $query->having('available_stock', '=', 0);
        })
        ->when(request('status') == 3 && request('stock') == 1, function ($query) {
            return $query->having('available_stock', '>', 0);
        })
        ->when(request('deleted') == 1, function ($q) {
            return $q->onlyTrashed();
        })
        ->orderBy('orders.reference_id', 'desc') // Secondary order by reference_id
        ->paginate($per_page)
        ->onEachSide(5)
        ->appends(request()->except('page'));


        // dd($data['orders']);
        return view('livewire.purchase')->with($data);
    }
    public function purchase_approve($order_id){
        $order = Order_model::find($order_id);
        $order->reference = request('reference');
        $order->reference_id = request('reference_id');
        $order->tracking_number = request('tracking_number');
        if(request('customer_id') != null){
            $order->customer_id = request('customer_id');
        }
        if(request('approve') == 1){
            $order->status += 1;
            $order->processed_at = now()->format('Y-m-d H:i:s');
        }
        $order->save();

        $transaction = Account_transaction_model::firstOrNew(['order_id'=>$order_id]);
        if($transaction->id == null && $order->status == 3){
            $transaction->amount = $order->order_items->sum('price');
            $transaction->currency = $order->currency;
            $transaction->exchange_rate = $order->exchange_rate;
            $transaction->customer_id = $order->customer_id;
            $transaction->transaction_type_id = 1;
            $transaction->status = 1;
            $transaction->description = $order->reference;
            $transaction->reference_id = $order->reference_id;
            $transaction->created_by = session('user_id');
            $transaction->date = now()->format('Y-m-d H:i:s');
            // $transaction->created_at = $order->created_at;

            $transaction->save();
            $transaction->reference_id = $transaction->id + 300000;
            $transaction->save();
        }elseif($transaction->id != null && $order->status == 3){
            $transaction->status = 2;
            $transaction->save();
        }

        if(request('approve') == 1){
            return redirect()->back();
        }else{
            return "Updated";
        }
    }
    public function purchase_revert_status($order_id){
        $order = Order_model::find($order_id);
        $order->status -= 1;
        $order->save();
        return redirect()->back();
    }
    public function delete_order($order_id){

        $stock = Stock_model::where(['order_id'=>$order_id,'status'=>2])->first();
        if($stock != null){
            session()->put('error', "Order cannot be deleted");
            return redirect()->back();
        }
        $items = Order_item_model::where('order_id',$order_id)->get();
        foreach($items as $orderItem){
            if($orderItem->stock){
                // Access the variation through orderItem->stock->variation
                $variation = $orderItem->stock->variation;

                // If a variation record exists and either product_id or sku is not null
                if ($variation->stock == 1 && $variation->product_id == null && $variation->sku == null) {
                    // Decrement the stock by 1

                    // Save the variation record
                    $variation->delete();
                } else {
                    $variation->stock -= 1;
                    // No variation record found or product_id and sku are both null, delete the order item
                }
                $stock = Stock_model::find($orderItem->stock_id);
                if($stock->status == 1){
                    $stock->delete();
                }else{
                    $stock->order_id = null;
                    $stock->status = null;
                    $stock->save();
                }
            }
            $orderItem->delete();
        }
        Order_model::where('id',$order_id)->delete();
        Order_issue_model::where('order_id',$order_id)->delete();
        return redirect(url('purchase'));
    }
    public function delete_sale_order($order_id){

        $items = Order_item_model::where('order_id',$order_id)->get();
        foreach($items as $orderItem){
            if($orderItem->stock){
                // Access the variation through orderItem->stock->variation
                $variation = $orderItem->stock->variation;

                // If a variation record exists and either product_id or sku is not null
                if ($variation->stock == 1 && $variation->product_id == null && $variation->sku == null) {
                    // Decrement the stock by 1

                    // Save the variation record
                    $variation->delete();
                } else {
                    $variation->stock += 1;
                    // No variation record found or product_id and sku are both null, delete the order item
                }
                // $stock = Stock_model::find($orderItem->stock_id);
                // if($stock->status == 1){
                //     $stock->delete();
                // }else{
                //     $stock->order_id = null;
                //     $stock->status = null;
                //     $stock->save();
                // }
            }
            $orderItem->delete();
        }
        Order_model::where('id',$order_id)->delete();
        Order_issue_model::where('order_id',$order_id)->delete();
        return redirect(url('purchase'));
    }
    public function delete_order_item($item_id){

        $orderItem = Order_item_model::find($item_id);

        if($orderItem == null){
            session()->put('error', "Order Item not found");
            return redirect()->back();
        }
        if($orderItem->stock->status == 2){
            session()->put('error', "Order Item cannot be deleted");
            return redirect()->back();
        }
        // Access the variation through orderItem->stock->variation
        $variation = $orderItem->stock->variation;

        $variation->stock -= 1;
        $variation->save();

        // No variation record found or product_id and sku are both null, delete the order item

        // $orderItem->stock->delete();
        $stock = Stock_model::find($orderItem->stock_id);
        $lp_item = Order_item_model::where('stock_id',$orderItem->stock_id)->where('order_id','!=',$orderItem->order_id)
        ->whereHas('order', function ($query) {
            $query->where('order_type_id', 1);
        })->orderBy('id','desc')->first();

        if($lp_item != null){
            $stock->order_id = $lp_item->order_id;
            $stock->save();
        }else{
            if($stock->status == 1){
                $stock->delete();
            }else{
                $stock->order_id = null;
                $stock->status = null;
                $stock->save();
            }
        }
        $orderItem->delete();

        return redirect()->back();
    }
    public function purchase_detail($order_id, $deleted = null){
        // if previous url contains url('purchase') then set session previous to url()->previous()
        if(str_contains(url()->previous(),url('purchase')) && !str_contains(url()->previous(),'detail')){
            session()->put('previous', url()->previous());
        }


        DB::statement("SET SESSION group_concat_max_len = 1000000;");
        $data['title_page'] = "Purchase Detail";
        session()->put('page_title', $data['title_page']);
        $data['vendors'] = Customer_model::whereNotNull('is_vendor')->pluck('company','id');
        $data['products'] = Products_model::pluck('model','id');
        $data['storages'] = session('dropdown_data')['storages'];
        $data['colors'] = session('dropdown_data')['colors'];
        $data['grades'] = session('dropdown_data')['grades'];

        $data['order'] = Order_model::when($deleted == 1, function ($q) {
            return $q->withTrashed();
        })->where('id',$order_id)->first();

        if(request('summery') == 1){
            $sold_total = [
                'total_cost' => 0,
                'total_repair' => 0,
                'total_price' => 0,
                'total_charge' => 0,
                'total_profit' => 0,
                'total_quantity' => 0,
            ];
            $available_total = [
                'total_cost' => 0,
                'total_quantity' => 0,
            ];
            $repair_total = [
                'total_cost' => 0,
                'total_quantity' => 0,
            ];

            $repair_ids = Process_model::where('process_type_id',9)->pluck('id');
            $repair_stock_ids = Process_stock_model::whereIn('process_id',$repair_ids)->where('status',1)->pluck('stock_id');


            // Retrieve variations with related stocks
            $sold_stocks = Variation_model::whereHas('stocks', function ($query) use ($order_id, $repair_stock_ids) {
                    $query->where('order_id', $order_id)->where('status', 2)->whereNotIn('id',$repair_stock_ids);
                })
                ->withCount([
                    'stocks as quantity' => function ($query) use ($order_id, $repair_stock_ids) {
                        $query->where('order_id', $order_id)->where('status', 2)->whereNotIn('id',$repair_stock_ids);
                    }
                ])
                ->with([
                    'stocks' => function ($query) use ($order_id, $repair_stock_ids) {
                        $query->where('order_id', $order_id)->where('status', 2)->whereNotIn('id',$repair_stock_ids);
                    }
                ])
                ->get(['product_id', 'storage']);

            // Process the retrieved data to get stock IDs
            $result = $sold_stocks->map(function ($variation) use ($repair_stock_ids) {
                $stocks = $variation->stocks->whereNotIn('id',$repair_stock_ids);

                // Collect all stock IDs
                $stockIds = $stocks->pluck('id');

                return [
                    'pss_id' => $variation->product_storage_sort_id,
                    'product_id' => $variation->product_id,
                    'storage' => $variation->storage,
                    'quantity' => $variation->quantity, // Use quantity from withCount
                    'stock_ids' => $stockIds->toArray() // Convert collection to array
                ];
            });

            // Group the results by product_id and storage
            $groupedResult = $result->groupBy(function ($item) {
                    return $item['product_id'] . '.' . $item['storage'];
                })->map(function ($items, $key) {
                    list($product_id, $storage) = explode('.', $key);

                    // Merge all stock IDs for the group
                    $stockIds = $items->flatMap(function ($item) {
                        return $item['stock_ids'];
                    })->unique()->values()->toArray(); // Convert to array

                    // Sum the quantity
                    $quantity = $items->sum('quantity'); // Sum the quantities

                    return [
                        'pss_id' => $items[0]['pss_id'],
                        'product_id' => $product_id,
                        'storage' => $storage,
                        'quantity' => $quantity,
                        'stock_ids' => $stockIds // Already an array
                    ];
                })->values();

            $s_orders = [];
            // Sort the results by quantity in descending order
            $sold_stocks_2 = $groupedResult->sortByDesc('quantity')->toArray();
            foreach($sold_stocks_2 as $key => $sold_stock){
                $average_cost = Order_item_model::whereIn('stock_id', $sold_stock['stock_ids'])->where('order_id',$order_id)->avg('price');
                $total_cost = Order_item_model::whereIn('stock_id', $sold_stock['stock_ids'])->where('order_id',$order_id)->sum('price');
                // $total_cost = 0;
                $total_repair = 0;
                $total_price = 0;
                $total_charge = 0;
                $total_quantity = 0;

                foreach($sold_stock['stock_ids'] as $stock_id){
                    $stock = Stock_model::find($stock_id);
                    // $total_cost += $stock->purchase_item->price;
                    $last_item = $stock->last_item();
                    $last_order = $last_item->order;
                    if(in_array($last_order->order_type_id,[2,3,5])){
                        if($last_order->order_type_id == 3 && $last_item->currency != 4 && $last_item->currency != null){
                            $currency = Currency_model::find($last_item->currency);
                            $exchange_rate = ExchangeRate::where('target_currency',$currency->code)->first();

                            $total_price += $last_item->price / $exchange_rate->rate;
                            // $total_price += $last_item->price;
                            if(!in_array($last_item->order_id,$s_orders)){
                                $s_orders[] = $last_item->order_id;
                                $total_charge += $last_item->order->charges / $exchange_rate->rate;
                            }
                        }else{
                            $total_price += $last_item->price;
                            if(!in_array($last_item->order_id,$s_orders)){
                                $s_orders[] = $last_item->order_id;
                                $total_charge += $last_item->order->charges;
                            }
                        }

                        $total_quantity++;
                    }

                    $process_stocks = $stock->process_stocks;
                    if($process_stocks != null){
                        foreach($process_stocks as $process_stock){
                            if($process_stock->process->process_type_id == 9 && $process_stock->status == 2){
                                $total_repair += $process_stock->price;
                            }
                        }
                    }

                }
                // $average_cost = $total_cost/$total_quantity;
                if($total_quantity == 0){
                    $average_price = "Issue";
                    $average_charge = "Issue";
                    $average_profit = "Issue";
                }else{
                    $average_price = $total_price/$total_quantity;
                    $average_charge = $total_charge/$total_quantity;
                    $average_profit = ($total_price - $total_cost - $total_charge - $total_repair)/$total_quantity;
                }
                $sold_stocks_2[$key]['average_cost'] = $average_cost;
                $sold_stocks_2[$key]['total_cost'] = $total_cost;
                $sold_stocks_2[$key]['total_repair'] = $total_repair;
                $sold_stocks_2[$key]['average_price'] = $average_price;
                $sold_stocks_2[$key]['total_price'] = $total_price;
                $sold_stocks_2[$key]['average_charge'] = $average_charge;
                $sold_stocks_2[$key]['total_charge'] = $total_charge;
                $sold_stocks_2[$key]['sold_quantity'] = $total_quantity;
                $sold_stocks_2[$key]['profit'] = $total_price - $total_cost - $total_charge - $total_repair;
                $sold_stocks_2[$key]['average_profit'] = $average_profit;

                $sold_total['total_cost'] += $total_cost;
                $sold_total['total_repair'] += $total_repair;
                $sold_total['total_price'] += $total_price;
                $sold_total['total_charge'] += $total_charge;
                $sold_total['total_profit'] += $total_price - $total_cost - $total_charge - $total_repair;
                $sold_total['total_quantity'] += $total_quantity;
            }

            // dd($sold_stocks_2);
            $data['sold_stock_summery'] = $sold_stocks_2;
            $data['sold_total'] = $sold_total;



            // Retrieve variations with related stocks
            $repair_stocks = Variation_model::whereHas('stocks', function ($query) use ($order_id, $repair_stock_ids) {
                    $query->where('order_id', $order_id)->where('status', 2)->whereIn('id',$repair_stock_ids);
                })
                ->withCount([
                    'stocks as quantity' => function ($query) use ($order_id, $repair_stock_ids) {
                        $query->where('order_id', $order_id)->where('status', 2)->whereIn('id',$repair_stock_ids);
                    }
                ])
                ->with([
                    'stocks' => function ($query) use ($order_id, $repair_stock_ids) {
                        $query->where('order_id', $order_id)->where('status', 2)->whereIn('id',$repair_stock_ids);
                    }
                ])
                ->get(['product_id', 'storage', 'product_storage_sort_id']);

            // Process the retrieved data to get stock IDs
            $result = $repair_stocks->map(function ($variation) use ($repair_stock_ids) {
                $stocks = $variation->stocks->whereIn('id',$repair_stock_ids);

                // Collect all stock IDs
                $stockIds = $stocks->pluck('id');

                return [
                    'pss_id' => $variation->product_storage_sort_id,
                    'product_id' => $variation->product_id,
                    'storage' => $variation->storage,
                    'quantity' => $variation->quantity, // Use quantity from withCount
                    'stock_ids' => $stockIds->toArray() // Convert collection to array
                ];
            });

            // Group the results by product_id and storage
            $groupedResult = $result->groupBy(function ($item) {
                    return $item['product_id'] . '.' . $item['storage'];
                })->map(function ($items, $key) {
                    list($product_id, $storage) = explode('.', $key);

                    // Merge all stock IDs for the group
                    $stockIds = $items->flatMap(function ($item) {
                            return $item['stock_ids'];
                        })->unique()->values()->toArray(); // Convert to array

                        // Sum the quantity
                    $quantity = $items->sum('quantity'); // Sum the quantities

                    return [
                        'pss_id' => $items[0]['pss_id'],
                        'product_id' => $product_id,
                        'storage' => $storage,
                        'quantity' => $quantity,
                        'stock_ids' => $stockIds // Already an array
                    ];
                })->values();

            // Sort the results by quantity in descending order
            $repair_stocks_2 = $groupedResult->sortByDesc('quantity')->toArray();

            foreach($repair_stocks_2 as $key => $repair_stock){
                $average_cost = Order_item_model::whereIn('stock_id', $repair_stock['stock_ids'])->where('order_id',$order_id)->avg('price');
                $total_cost = Order_item_model::whereIn('stock_id', $repair_stock['stock_ids'])->where('order_id',$order_id)->sum('price');
                $repair_stocks_2[$key]['average_cost'] = $average_cost;
                $repair_stocks_2[$key]['total_cost'] = $total_cost;

                $repair_total['total_cost'] += $total_cost;
                $repair_total['total_quantity'] += $repair_stocks_2[$key]['quantity'];
            }

            // dd($repair_stocks_2);
            $data['repair_sent_stock_summery'] = $repair_stocks_2;
            $data['repair_sent_total'] = $repair_total;


            // Retrieve variations with related stocks
            $available_stocks = Variation_model::whereHas('stocks', function ($query) use ($order_id) {
                    $query->where('order_id', $order_id)->where('status', 1);
                })
                ->withCount([
                    'stocks as quantity' => function ($query) use ($order_id) {
                        $query->where('order_id', $order_id)->where('status', 1);
                    }
                ])
                ->with([
                    'stocks' => function ($query) use ($order_id) {
                        $query->where('order_id', $order_id)->where('status', 1);
                    }
                ])
                ->get(['product_id', 'storage', 'product_storage_sort_id']);

            // Process the retrieved data to get stock IDs
            $result = $available_stocks->map(function ($variation) {
                $stocks = $variation->stocks;

                // Collect all stock IDs
                $stockIds = $stocks->pluck('id');

                return [
                    'pss_id' => $variation->product_storage_sort_id,
                    'product_id' => $variation->product_id,
                    'storage' => $variation->storage,
                    'quantity' => $variation->quantity, // Use quantity from withCount
                    'stock_ids' => $stockIds->toArray() // Convert collection to array
                ];
            });

            // Group the results by product_id and storage
            $groupedResult = $result->groupBy(function ($item) {
                    return $item['product_id'] . '.' . $item['storage'];
                })->map(function ($items, $key) {
                    list($product_id, $storage) = explode('.', $key);

                    // Merge all stock IDs for the group
                    $stockIds = $items->flatMap(function ($item) {
                            return $item['stock_ids'];
                        })->unique()->values()->toArray(); // Convert to array

                        // Sum the quantity
                    $quantity = $items->sum('quantity'); // Sum the quantities

                    return [
                        'pss_id' => $items[0]['pss_id'],
                        'product_id' => $product_id,
                        'storage' => $storage,
                        'quantity' => $quantity,
                        'stock_ids' => $stockIds // Already an array
                    ];
                })->values();

            // Sort the results by quantity in descending order
            $available_stocks_2 = $groupedResult->sortByDesc('quantity')->toArray();

            foreach($available_stocks_2 as $key => $available_stock){
                $average_cost = Order_item_model::whereIn('stock_id', $available_stock['stock_ids'])->where('order_id',$order_id)->avg('price');
                $total_cost = Order_item_model::whereIn('stock_id', $available_stock['stock_ids'])->where('order_id',$order_id)->sum('price');
                $available_stocks_2[$key]['average_cost'] = $average_cost;
                $available_stocks_2[$key]['total_cost'] = $total_cost;

                $available_total['total_cost'] += $total_cost;
                $available_total['total_quantity'] += $available_stocks_2[$key]['quantity'];
            }

            // dd($available_stocks_2);
            $data['available_stock_summery'] = $available_stocks_2;
            $data['available_total'] = $available_total;
        }elseif(request('summery') == 2){


            ini_set('memory_limit', '2048M');

            $repair_ids = Process_model::where('process_type_id',9)->pluck('id');
            $repair_stock_ids = Process_stock_model::whereIn('process_id',$repair_ids)->where('status',1)->pluck('stock_id');

            $product_storage_sort = Product_storage_sort_model::whereHas('stocks', function($q) use ($order_id){
                $q->where('stock.order_id', $order_id);
            })->orderBy('sort')
            ->with('stocks')
            ->get();

            $variations = Variation_model::whereIn('product_storage_sort_id',$product_storage_sort->pluck('id'))->get();
            $wip_variations = $variations->whereIn('grade', [9])->pluck('id');
            $rtg_variations = $variations->whereIn('grade', [1,2,3,4,5,7])->pluck('id');
            $twox_variations = $variations->whereIn('grade', [6])->pluck('id');
            $repair_variations = $variations->whereIn('grade', [8])->pluck('id');
            $rma_variations = $variations->whereIn('grade', [10])->pluck('id');
            $ws_variations = $variations->whereIn('grade', [11])->pluck('id');
            $bt_variations = $variations->whereIn('grade', [21])->pluck('id');
            $other_variations = $variations->whereNotIn('id',$wip_variations)->whereNotIn('id',$rtg_variations)->whereNotIn('id',$twox_variations)->whereNotIn('id',$repair_variations)->whereNotIn('id',$rma_variations)->whereNotIn('id',$ws_variations)->whereNotIn('id',$bt_variations)->pluck('id');

            $result = [];
            foreach($product_storage_sort->sortBy('sort') as $pss){
                $product = $pss->product;
                $storage = $pss->storage_id->name ?? null;



                $datas = [];
                $datas['pss_id'] = $pss->id;
                $datas['sort'] = $pss->sort;
                $datas['model'] = $product->model.' '.$storage;
                $datas['available_stock_count'] = $pss->stocks->where('order_id',$order_id)->where('status',1)->count();
                $datas['wip_stock_count'] = $pss->stocks->where('order_id',$order_id)->where('status',1)->whereIn('variation_id',$wip_variations)->count();
                $datas['rtg_stock_count'] = $pss->stocks->where('order_id',$order_id)->where('status',1)->whereIn('variation_id',$rtg_variations)->count();
                $datas['twox_stock_count'] = $pss->stocks->where('order_id',$order_id)->where('status',1)->whereIn('variation_id',$twox_variations)->count();
                $datas['rep_stock_count'] = $pss->stocks->where('order_id',$order_id)->where('status',1)->whereIn('variation_id',$repair_variations)->count();
                $datas['rma_stock_count'] = $pss->stocks->where('order_id',$order_id)->where('status',1)->whereIn('variation_id',$rma_variations)->count();
                $datas['ws_stock_count'] = $pss->stocks->where('order_id',$order_id)->where('status',1)->whereIn('variation_id',$ws_variations)->count();
                $datas['bt_stock_count'] = $pss->stocks->where('order_id',$order_id)->where('status',1)->whereIn('variation_id',$bt_variations)->count();
                $datas['other_stock_count'] = $pss->stocks->where('order_id',$order_id)->where('status',1)->whereIn('variation_id',$other_variations)->count();

                $datas['sold_stock_count'] = $pss->stocks->where('order_id',$order_id)->where('status',2)->whereNotIn('id',$repair_stock_ids)->count();
                $datas['repair_stock_count'] = $pss->stocks->where('order_id',$order_id)->where('status',2)->whereIn('id',$repair_stock_ids)->count();


                $result[] = $datas;
            }

            $data['stock_summery'] = $result;

            // dd($result);

        }else{
            if (!request('status') || request('status') == 1){
                $data['variations'] = Variation_model::with(['stocks' => function ($query) use ($order_id) {
                    $query->where(['order_id'=> $order_id, 'status'=>1]);
                },
                'stocks.stock_operations'
                ])
                ->whereHas('stocks', function ($query) use ($order_id, $deleted) {
                    $query->where(['order_id'=> $order_id, 'status'=>1])
                    ->when($deleted == 1, function ($q) {
                        return $q->onlyTrashed();
                    });
                })
                ->orderBy('grade', 'asc')
                ->get();

            }

            if (!request('status') || request('status') == 2){

                $data['sold_stocks'] = Stock_model::with('order_items')
                ->where(['order_id'=> $order_id, 'status'=>2])
                ->orderBy('variation_id', 'asc')
                ->when($deleted == 1, function ($q) {
                    return $q->onlyTrashed();
                })
                ->get();

            }

            $data['graded_count'] = Stock_model::select('grade.name as grade', 'variation.grade as grade_id', DB::raw('COUNT(*) as quantity'))
            ->when(request('status'), function ($q) {
                return $q->where('stock.status', request('status'));
            })
            ->where('stock.order_id', $order_id)
            ->join('variation', 'stock.variation_id', '=', 'variation.id')
            ->join('grade', 'variation.grade', '=', 'grade.id')
            ->groupBy('variation.grade', 'grade.name')
            ->orderBy('grade_id')
            ->get();

            $data['region_count'] = Stock_model::select('region.name as region', 'stock.region_id', DB::raw('COUNT(*) as quantity'))
            ->when(request('status'), function ($q) {
                return $q->where('stock.status', request('status'));
            })
            ->where('stock.order_id', $order_id)
            ->join('region', 'stock.region_id', '=', 'region.id')
            ->groupBy('stock.region_id', 'region.name')
            ->orderBy('region')
            ->get();

            $data['missing_stock'] = Order_item_model::where('order_id',$order_id)->whereHas('stock',function ($q) {
                $q->where(['imei'=>null,'serial_number'=>null]);
            })->get();
            // $order_issues = Order_issue_model::where('order_id',$order_id)->orderBy('message','ASC')->get();
            $order_issues = Order_issue_model::where('order_id',$order_id)->select(
                DB::raw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.name")) AS name'),
                'message',
                DB::raw('COUNT(*) as count'),
                DB::raw('GROUP_CONCAT(JSON_OBJECT("id", id, "order_id", order_id, "data", data, "message", message, "created_at", created_at, "updated_at", updated_at)) AS all_rows')
            )
            ->groupBy('name', 'message')
            ->get();
            // dd($order_issues);

            $data['order_issues'] = $order_issues;
            // dd($data['missing_stock']);

            if($data['order']->created_at >= now()->subDays(7) && $data['order']->created_at <= now()->subMinutes(15)){
                $buildLookup = static function ($options) {
                    return collect($options)
                        ->mapWithKeys(function ($name, $id) {
                            return [strtolower($name) => $id];
                        })
                        ->all();
                };

                $productLookup = $buildLookup($data['products']);
                $storageLookup = $buildLookup($data['storages']);
                $colorLookup = $buildLookup($data['colors']);

                $variationCache = [];

                $requests = Api_request_model::whereNull('status')
                    ->where('request->BatchID', 'LIKE', '%'.$data['order']->reference_id.'%')
                    ->orderByDesc('id')
                    ->get();

                $testingsCollection = $requests->map(function ($item) use ($productLookup, $storageLookup, $colorLookup, &$variationCache) {
                    $request = json_decode($item->request);
                    $product = $request->ModelName ?? null;
                    $storage = $request->Memory ?? null;
                    $color = $request->Color ?? null;

                    $productId = $product ? ($productLookup[strtolower($product)] ?? null) : null;
                    $storageId = $storage ? ($storageLookup[strtolower($storage)] ?? null) : null;
                    $colorId = $color ? ($colorLookup[strtolower($color)] ?? null) : null;

                    $variationId = null;
                    if ($productId !== null && $storageId !== null) {
                        $cacheKey = implode(':', [
                            $productId,
                            $storageId,
                            $colorId ?? 'null',
                            9,
                        ]);

                        if (! isset($variationCache[$cacheKey])) {
                            $variationCache[$cacheKey] = Variation_model::firstOrCreate([
                                'product_id' => $productId,
                                'storage' => $storageId,
                                'color' => $colorId,
                                'grade' => 9,
                            ])->id;
                        }

                        $variationId = $variationCache[$cacheKey];
                    }

                    return [
                        'imei' => $request->Imei ?? null,
                        'serial_number' => $request->Serial ?? null,
                        'variation_id' => $variationId,
                        'product' => $product,
                        'storage' => $storage,
                        'color' => $color,
                    ];
                })->filter(function ($row) {
                    return $row['variation_id'] !== null && ($row['imei'] || $row['serial_number']);
                });

                $imeiCandidates = $testingsCollection->pluck('imei')->filter()->unique()->values()->all();
                $serialCandidates = $testingsCollection->pluck('serial_number')->filter()->unique()->values()->all();

                $existingImeis = [];
                $existingSerials = [];

                if ($testingsCollection->isNotEmpty() && (! empty($imeiCandidates) || ! empty($serialCandidates))) {
                    Stock_model::query()
                        ->select('imei', 'serial_number')
                        ->where(function ($query) use ($imeiCandidates, $serialCandidates) {
                            if (! empty($imeiCandidates)) {
                                $query->whereIn('imei', $imeiCandidates);
                            }
                            if (! empty($serialCandidates)) {
                                $method = empty($imeiCandidates) ? 'whereIn' : 'orWhereIn';
                                $query->{$method}('serial_number', $serialCandidates);
                            }
                        })
                        ->get()
                        ->each(function ($stock) use (&$existingImeis, &$existingSerials) {
                            if ($stock->imei) {
                                $existingImeis[$stock->imei] = true;
                            }
                            if ($stock->serial_number) {
                                $existingSerials[$stock->serial_number] = true;
                            }
                        });
                }

                $testings = $testingsCollection
                    ->groupBy('variation_id')
                    ->map(function ($group) use ($existingImeis, $existingSerials) {
                        $unique = [];
                        $existingPushTracker = [];

                        return $group->filter(function ($item) use (&$unique, &$existingPushTracker, $existingImeis, $existingSerials) {
                            $key = ($item['imei'] ?? '') . '|' . ($item['serial_number'] ?? '');
                            if (! $key || isset($unique[$key])) {
                                return false;
                            }

                            $hasStock = ($item['imei'] && isset($existingImeis[$item['imei']])) || ($item['serial_number'] && isset($existingSerials[$item['serial_number']]));
                            if ($hasStock && isset($existingPushTracker[$key])) {
                                return false;
                            }

                            if ($hasStock) {
                                $existingPushTracker[$key] = true;
                            }

                            $unique[$key] = true;
                            return true;
                        })->values();
                    });

                // if($testings->count() > 0){
                //     dd($testings);
                // }
                $data['testing_list'] = $testings;

                // if($testings->count() > 0){
                //     $testing_list = [];
                //     $imei_list = [];
                //     $lower_products = array_map('strtolower', $data['products']->toArray());
                //     $lower_storages = array_map('strtolower', $data['storages']->toArray());
                //     $lower_colors = array_map('strtolower', $data['colors']->toArray());
                //     $lower_grades = array_map('strtolower', $data['grades']->toArray());
                //     // dd($testings);
                //     foreach($testings as $testing){
                //         $request = json_decode($testing->request);
                //         if(!str_contains($request->BatchID, $data['order']->reference_id)){
                //             continue;
                //         }
                //         if(in_array(strtolower($request->ModelName), $lower_products)){
                //             $product_id = array_search(strtolower($request->ModelName), $lower_products);
                //         }else{
                //             continue;
                //         }
                //         if(in_array(strtolower($request->Memory), $lower_storages)){
                //             $storage_id = array_search(strtolower($request->Memory), $lower_storages);
                //         }else{
                //             continue;
                //         }
                //         if(in_array(strtolower($request->Color), $lower_colors)){
                //             $color_id = array_search(strtolower($request->Color), $lower_colors);
                //         }else{
                //             $color_id = null;
                //         }
                //         // if(in_array(strtolower($request->Grade), $lower_grades)){
                //         //     $grade_id = array_search(strtolower($request->Grade), $lower_grades);
                //         // }else{
                //             $grade_id = 9;
                //         // }

                //         $variation = Variation_model::firstOrNew([
                //             'product_id'=>$product_id,
                //             'storage'=>$storage_id,
                //             'color'=>$color_id,
                //             'grade'=>$grade_id,
                //         ]);
                //         if($variation->id == null){
                //             $variation->save();
                //         }

                //         if($request->Imei != null || $request->Serial != null){
                //             if(Stock_model::where('imei',$request->Imei)->orWhere('imei',$request->Imei2)->orWhere('serial_number',$request->Serial)->exists()){
                //                 continue;
                //             }
                //             if($request->Imei != null){
                //                 $imei = $request->Imei;
                //             }else{
                //                 $imei = $request->Serial;
                //             }
                //             if(in_array($imei, $imei_list)){
                //                 continue;
                //             }else{
                //                 $imei_list[] = $imei;
                //             }
                //             $testing_list[$variation->id][] = [
                //                 'imei' => $request->Imei,
                //                 'serial_number' => $request->Serial,
                //                 'variation_id' => $variation->id,
                //                 'product' => $request->ModelName,
                //                 'storage' => $request->Memory,
                //                 'color' => $request->Color,
                //                 // 'grade' => $request->Grade,
                //                 'status' => 1,
                //             ];
                //         }
                //     }
                    // if(count($testing_list) > 0){
                    //     // dd($testing_list);
                    //     $data['testing_list'] = $testing_list;
                    // }
                // }
            }
        }
        $data['all_variations'] = Variation_model::where('grade',9)->get();
        $data['order_id'] = $order_id;
        $data['currency'] = $data['order']->currency_id->sign;


        // echo "<pre>";
        // // print_r($items->stocks);
        // print_r($items);

        // echo "</pre>";
        // dd($data['variations']);
        return view('livewire.purchase_detail')->with($data);

    }

    public function purchase_recovery($order_id)
    {
        $order = Order_model::withTrashed()->find($order_id);

        if (! $order) {
            session()->put('error', 'Purchase order not found');
            return redirect(url('purchase'));
        }

        $data['title_page'] = 'Purchase Recovery';
        session()->put('page_title', $data['title_page']);

        $data['order'] = $order;
        $data['order_id'] = $order_id;
        $data['current_count'] = Order_item_model::where('order_id', $order_id)->count();
        $data['import_result'] = session('purchase_recovery_result');
        session()->forget('purchase_recovery_result');

        // Manual grouping: derive first variation per stock using earliest stock_operation
        $stockIds = Stock_model::where('order_id', $order_id)->pluck('id');

        $firstOpIds = $stockIds->isEmpty()
            ? collect()
            : DB::table('stock_operations')
                ->select(DB::raw('MIN(id) as id'), 'stock_id')
                ->whereIn('stock_id', $stockIds)
                ->groupBy('stock_id')
                ->pluck('id', 'stock_id');

        $firstVariationByStock = $firstOpIds->isEmpty()
            ? collect()
            : DB::table('stock_operations')
                ->whereIn('id', $firstOpIds->values())
                ->pluck(DB::raw('COALESCE(new_variation_id, old_variation_id)'), 'stock_id');

        $stockVariations = DB::table('stock')
            ->whereIn('id', $stockIds)
            ->pluck('variation_id', 'id');

        $resolved = [];
        foreach ($stockIds as $sid) {
            $resolved[$sid] = $firstVariationByStock[$sid] ?? $stockVariations[$sid] ?? null;
        }

        $groups = collect($resolved)
            ->filter()
            ->groupBy(function ($variationId) {
                return $variationId;
            })
            ->map(function ($stockList, $variationId) {
                return [
                    'variation_id' => (int) $variationId,
                    'stock_ids'    => $stockList->keys()->values()->all(),
                    'count'        => $stockList->count(),
                ];
            })
            ->values();

        $variationMeta = Variation_model::with(['product:id,model', 'storage_id:id,name', 'color_id:id,name'])
            ->whereIn('id', $groups->pluck('variation_id'))
            ->get()
            ->keyBy('id');

        $data['manual_groups'] = $groups->map(function ($g) use ($variationMeta) {
            $v = $variationMeta[$g['variation_id']] ?? null;
            $label = $v
                ? trim(($v->product->model ?? '').' '.($v->storage_id->name ?? '').' '.($v->color_id->name ?? ''))
                : 'Variation '.$g['variation_id'];
            return array_merge($g, ['label' => $label]);
        });

        return view('livewire.purchase_recovery')->with($data);
    }

    public function purchase_recovery_import($order_id)
    {
        $order = Order_model::withTrashed()->find($order_id);

        if (! $order) {
            session()->put('error', 'Purchase order not found');
            return redirect(url('purchase'));
        }

        request()->validate([
            'recovery_file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $priceOnly = request()->boolean('price_only');

        $sheet = Excel::toArray([], request()->file('recovery_file'))[0] ?? [];

        if (count($sheet) === 0) {
            session()->put('error', 'The uploaded file is empty.');
            return redirect()->back();
        }

        $header = array_map(static function ($value) {
            return strtolower(trim($value));
        }, $sheet[0]);

        $index = function ($name) use ($header) {
            $pos = array_search($name, $header);
            return $pos === false ? null : $pos;
        };

        // Match add_purchase sheet headers
        $required = ['name', 'imei', 'cost'];
        foreach ($required as $req) {
            if ($index($req) === null) {
                session()->put('error', "Missing required column: {$req}");
                return redirect()->back();
            }
        }

        $imeiIndexes = [];
        foreach ($header as $idx => $value) {
            if ($value === 'imei') {
                $imeiIndexes[] = $idx;
            }
        }

        $map = [
            'id'           => $index('id'),
            'linked_id'    => $index('linked_id'),
            'reference_id' => $index('reference_id'),
            'care_id'      => $index('care_id'),
            'reference'    => $index('reference'),
            'variation_id' => $index('variation_id'),
            'stock_id'     => $index('stock_id'),
            'quantity'     => $index('quantity'),
            'currency'     => $index('currency'),
            'price'        => $index('cost'),
            'discount'     => $index('discount'),
            'status'       => $index('status'),
            'admin_id'     => $index('admin_id'),
            'created_at'   => $index('created_at'),
            'updated_at'   => $index('updated_at'),
            'deleted_at'   => $index('deleted_at'),
            'color'        => $index('color'),
            'grade'        => $index('grade'),
            'notes'        => $index('notes'),
        ];

        unset($sheet[0]);

        $orderCurrency = $order->currency ?? 4;

        $rows = collect($sheet)
            ->filter(function ($row) use ($map, $imeiIndexes) {
                $hasImei = false;
                foreach ($imeiIndexes as $i) {
                    if (isset($row[$i]) && trim((string) $row[$i]) !== '') {
                        $hasImei = true;
                        break;
                    }
                }
                $hasCost = isset($row[$map['price']]) && trim((string) $row[$map['price']]) !== '';
                return $hasImei && $hasCost;
            })
            ->values();

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        $unmapped = 0;

        // Build a quick lookup of linked_id by stock_id from sold items
        $linkedByStock = DB::table('order_items')
            ->select('stock_id', DB::raw('MAX(linked_id) as linked_id'))
            ->whereNotNull('linked_id')
            ->groupBy('stock_id')
            ->pluck('linked_id', 'stock_id');

        $reservedIds = [];

        // Build first variation per stock from earliest stock_operation
        $firstOpIds = DB::table('stock_operations')
            ->select(DB::raw('MIN(id) as id'), 'stock_id')
            ->groupBy('stock_id')
            ->pluck('id', 'stock_id');

        $firstVariationByStock = $firstOpIds->isEmpty()
            ? collect()
            : DB::table('stock_operations')
                ->whereIn('id', $firstOpIds->values())
                ->pluck(DB::raw('COALESCE(new_variation_id, old_variation_id)'), 'stock_id');

        $rows->chunk(300)->each(function ($chunk) use (&$inserted, &$updated, &$skipped, &$errors, &$unmapped, $map, $order_id, $orderCurrency, $linkedByStock, &$reservedIds, $imeiIndexes, $priceOnly, $firstVariationByStock) {
            foreach ($chunk as $row) {
                $id = $map['id'] !== null ? ($row[$map['id']] ?? null) : null;

                $imeiValue = null;
                foreach ($imeiIndexes as $i) {
                    if (isset($row[$i]) && trim((string) $row[$i]) !== '') {
                        $imeiValue = trim((string) $row[$i]);
                        break;
                    }
                }

                $stockId = $map['stock_id'] !== null ? ($row[$map['stock_id']] ?? null) : null;
                if (! $stockId && $imeiValue) {
                    $stock = Stock_model::withTrashed()
                        ->where('imei', $imeiValue)
                        ->orWhere('serial_number', $imeiValue)
                        ->first();
                    if ($stock) {
                        $stockId = $stock->id;
                    }
                }

                $price = $row[$map['price']] ?? null;

                $price = is_numeric($price) ? $price : null;

                if ($stockId === null || $price === null || $price === '') {
                    $errors++;
                    continue;
                }

                // Derive target id: explicit id -> sheet linked_id -> linked sale item by stock
                // Require a resolved id; never create a new auto-increment id
                if ($id === null || $id === '') {
                    if ($map['linked_id'] !== null && isset($row[$map['linked_id']]) && $row[$map['linked_id']] !== '') {
                        $id = $row[$map['linked_id']];
                    } elseif (isset($linkedByStock[$stockId])) {
                        $id = $linkedByStock[$stockId];
                    }
                }

                if ($id === null || $id === '') {
                    $unmapped++;
                    continue;
                }

                if (isset($reservedIds[$id])) {
                    $skipped++;
                    continue;
                }

                $existing = DB::table('order_items')->where('id', $id)->first();
                if ($existing) {
                    if ($priceOnly) {
                        DB::table('order_items')->where('id', $id)->update([
                            'price' => $price,
                            'updated_at' => now(),
                        ]);
                        $updated++;
                        continue;
                    }

                    $skipped++;
                    continue;
                }

                $variationId = $map['variation_id'] !== null ? ($row[$map['variation_id']] ?? null) : null;
                if (! $variationId && $stockId) {
                    if (isset($firstVariationByStock[$stockId])) {
                        $variationId = $firstVariationByStock[$stockId];
                    }
                    if (! $variationId) {
                        $stock = Stock_model::withTrashed()->find($stockId);
                        if ($stock) {
                            $variationId = $stock->variation_id;
                        }
                    }
                }

                $record = [
                    'id'           => $id,
                    'order_id'     => $order_id,
                    'reference_id' => $map['reference_id'] !== null ? ($row[$map['reference_id']] ?? null) : null,
                    'care_id'      => $map['care_id'] !== null ? ($row[$map['care_id']] ?? null) : null,
                    'reference'    => $map['reference'] !== null ? ($row[$map['reference']] ?? null) : ($map['notes'] !== null ? ($row[$map['notes']] ?? null) : null),
                    'variation_id' => $variationId,
                    'stock_id'     => $stockId,
                    'quantity'     => $map['quantity'] !== null ? ($row[$map['quantity']] ?? 1) : 1,
                    'currency'     => $map['currency'] !== null ? ($row[$map['currency']] ?? $orderCurrency) : $orderCurrency,
                    'price'        => $price,
                    'discount'     => $map['discount'] !== null ? ($row[$map['discount']] ?? null) : null,
                    'status'       => $map['status'] !== null ? ($row[$map['status']] ?? 3) : 3,
                    'linked_id'    => $map['linked_id'] !== null ? ($row[$map['linked_id']] ?? null) : null,
                    'admin_id'     => $map['admin_id'] !== null ? ($row[$map['admin_id']] ?? session('user_id')) : session('user_id'),
                    'created_at'   => $map['created_at'] !== null ? ($row[$map['created_at']] ?? now()) : now(),
                    'updated_at'   => $map['updated_at'] !== null ? ($row[$map['updated_at']] ?? now()) : now(),
                    'deleted_at'   => $map['deleted_at'] !== null ? ($row[$map['deleted_at']] ?? null) : null,
                ];

                try {
                    DB::table('order_items')->insert($record);
                    $reservedIds[$id] = true;
                    $inserted++;
                } catch (\Throwable $e) {
                    $errors++;
                }
            }
        });

        session()->put('purchase_recovery_result', [
            'inserted' => $inserted,
            'updated'  => $updated,
            'skipped'  => $skipped,
            'errors'   => $errors,
            'unmapped' => $unmapped,
        ]);

        return redirect()->back();
    }

    public function export_purchase_sheet($order_id, $invoice = null)
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '300');

        $order = Order_model::find($order_id);

        // Additional content from your view
        if(request('packlist') == 1){
        }elseif(request('sheet') == 2){

            return Excel::download(new PurchasesheetExport, 'PO'.$order->reference_id.' '.$order->customer->company.' '.$order->order_items->sum('quantity').' pcs.xlsx');

        }

    }
    public function purchase_model_color_graded_sale($order_id, $pss_id){
        $repair_ids = Process_model::where('process_type_id', 9)->pluck('id');
        $pss = Product_storage_sort_model::find($pss_id);
        $variations = $pss->variations;
        $stocks = $pss->stocks->where('order_id', $order_id)->where('status', 2);
        $sent_repair = Process_stock_model::whereIn('stock_id', $stocks->pluck('id'))->where('status', 1)->whereIn('process_id', $repair_ids)->pluck('stock_id')->toArray();
        $stocks = $stocks->whereNotIn('id', $sent_repair);
        $grades = Grade_model::pluck('name', 'id');
        $colors = Color_model::whereIn('id', $variations->pluck('color'))->pluck('name', 'id');
        $graded_count = [];
        $s_orders = [];
        $sold_stocks_2 = [];

        $total_graded_count = [
            'quantity' => 0,
            'total_cost' => 0,
            'average_cost' => 0,
            'total_repair' => 0,
            'average_repair' => 0,
            'total_price' => 0,
            'total_charge' => 0,
            'profit' => 0,
            'average_price' => 0,
            'average_charge' => 0,
            'average_profit' => 0,
        ];

        foreach ($colors as $color_id => $color) {
            foreach ($grades as $grade_id => $grade) {
                $graded_variations = $variations->where('grade', $grade_id)->where('color', $color_id);
                $graded_stock_ids = $stocks->whereIn('variation_id', $graded_variations->pluck('id'))->pluck('id')->toArray();
                $total_cost = Order_item_model::whereIn('stock_id', $graded_stock_ids)->where('order_id', $order_id)->sum('price');
                $average_cost = $graded_stock_ids ? $total_cost / count($graded_stock_ids) : 0;
                $total_repair = Process_stock_model::whereIn('stock_id', $graded_stock_ids)->where('status', 2)->whereIn('process_id', $repair_ids)->sum('price');
                $average_repair = $graded_stock_ids ? $total_repair / count($graded_stock_ids) : 0;

                if (count($graded_stock_ids) == 0) {
                    continue;
                }
                $graded_count[$color_id . '.' . $grade_id] = [
                    'quantity' => count($graded_stock_ids),
                    'grade' => $grade,
                    'grade_id' => $grade_id,
                    'color' => $color,
                    'color_id' => $color_id,
                    'stock_ids' => $graded_stock_ids,
                    'total_cost' => $total_cost,
                    'average_cost' => $average_cost,
                    'total_repair' => $total_repair,
                    'average_repair' => $average_repair,
                ];
                $total_graded_count['quantity'] += count($graded_stock_ids);
                $total_graded_count['total_cost'] += $total_cost;
                $total_graded_count['average_cost'] += $average_cost;
                $total_graded_count['total_repair'] += $total_repair;
                $total_graded_count['average_repair'] += $average_repair;
            }
        }

        foreach ($graded_count as $key => $sold_stock) {
            $total_cost = $sold_stock['total_cost'];
            $total_repair = $sold_stock['total_repair'];
            $total_price = 0;
            $total_charge = 0;
            $total_quantity = 0;

            foreach ($sold_stock['stock_ids'] as $stock_id) {
                $stock = Stock_model::find($stock_id);
                $last_item = $stock->last_item();
                $last_order = $last_item->order;

                if (in_array($last_item->order->order_type_id, [2, 3, 5])) {

                    if($last_order->order_type_id == 3 && $last_item->currency != 4 && $last_item->currency != null){
                        $currency = Currency_model::find($last_item->currency);
                        $exchange_rate = ExchangeRate::where('target_currency',$currency->code)->first();

                        $total_price += $last_item->price / $exchange_rate->rate;
                        if (!in_array($last_item->order_id, $s_orders)) {
                            $s_orders[] = $last_item->order_id;
                            $total_charge += $last_item->order->charges / $exchange_rate->rate ?? 0;
                        }
                    }else{
                        $total_price += $last_item->price;
                        if (!in_array($last_item->order_id, $s_orders)) {
                            $s_orders[] = $last_item->order_id;
                            $total_charge += $last_item->order->charges ?? 0;
                        }
                    }
                    // $total_price += $last_item->price;
                    $total_quantity++;
                }

            }

            $average_price = $total_quantity ? $total_price / $total_quantity : "Issue";
            $average_charge = $total_quantity ? $total_charge / $total_quantity : "Issue";
            $average_profit = $total_quantity ? ($total_price - $total_cost - $total_charge - $total_repair) / $total_quantity : "Issue";
            $graded_count[$key]['average_price'] = is_numeric($average_price) ? amount_formatter($average_price) : $average_price;
            $graded_count[$key]['total_price'] = is_numeric($total_price) ? amount_formatter($total_price) : $total_price;
            $graded_count[$key]['average_charge'] = is_numeric($average_charge) ? amount_formatter($average_charge) : $average_charge;
            $graded_count[$key]['total_charge'] = is_numeric($total_charge) ? amount_formatter($total_charge) : $total_charge;
            $graded_count[$key]['profit'] = is_numeric($total_price - $total_cost - $total_charge - $total_repair) ? amount_formatter($total_price - $total_cost - $total_charge - $total_repair) : $total_price - $total_cost - $total_charge - $total_repair;
            $graded_count[$key]['average_profit'] = is_numeric($average_profit) ? amount_formatter($average_profit) : $average_profit;

            $total_graded_count['total_price'] += $total_price;
            $total_graded_count['total_charge'] += $total_charge;
            $total_graded_count['profit'] += $total_price - $total_cost - $total_charge - $total_repair;
            $total_graded_count['average_price'] += $average_price;
            $total_graded_count['average_charge'] += $average_charge;
            $total_graded_count['average_profit'] += $average_profit;
        }

        $data['graded_count'] = $graded_count;

        $data['total_graded_count'] = [
            'quantity' => $total_graded_count['quantity'],
            'total_cost' => amount_formatter($total_graded_count['total_cost']),
            'average_cost' => amount_formatter($total_graded_count['average_cost']),
            'total_repair' => amount_formatter($total_graded_count['total_repair']),
            'average_repair' => amount_formatter($total_graded_count['average_repair']),
            'total_price' => amount_formatter($total_graded_count['total_price']),
            'total_charge' => amount_formatter($total_graded_count['total_charge']),
            'profit' => amount_formatter($total_graded_count['profit']),
            'average_price' => amount_formatter($total_graded_count['average_price']),
            'average_charge' => amount_formatter($total_graded_count['average_charge']),
            'average_profit' => amount_formatter($total_graded_count['average_profit']),
        ];

        return response()->json($data);
    }

    public function purchase_model_color_graded_repair($order_id, $pss_id){
        $repair_ids = Process_model::where('process_type_id', 9)->pluck('id');
        $pss = Product_storage_sort_model::find($pss_id);
        $variations = $pss->variations;
        $stocks = $pss->stocks->where('order_id', $order_id)->where('status', 2);
        $sent_repair = Process_stock_model::whereIn('stock_id', $stocks->pluck('id'))->where('status', 1)->whereIn('process_id', $repair_ids)->pluck('stock_id')->toArray();
        $stocks = $stocks->whereIn('id', $sent_repair);
        $grades = Grade_model::pluck('name', 'id');
        $colors = Color_model::whereIn('id', $variations->pluck('color'))->pluck('name', 'id');
        $graded_count = [];
        $s_orders = [];
        $sold_stocks_2 = [];

        $total_graded_count = [
            'quantity' => 0,
            'total_cost' => 0,
            'average_cost' => 0,
            'total_repair' => 0,
            'average_repair' => 0,
            'total_price' => 0,
            'total_charge' => 0,
            'profit' => 0,
            'average_price' => 0,
            'average_charge' => 0,
            'average_profit' => 0,
        ];

        foreach ($colors as $color_id => $color) {
            foreach ($grades as $grade_id => $grade) {
                $graded_variations = $variations->where('grade', $grade_id)->where('color', $color_id);
                $graded_stock_ids = $stocks->whereIn('variation_id', $graded_variations->pluck('id'))->pluck('id')->toArray();
                $total_cost = Order_item_model::whereIn('stock_id', $graded_stock_ids)->where('order_id', $order_id)->sum('price');
                $average_cost = $graded_stock_ids ? $total_cost / count($graded_stock_ids) : 0;
                $total_repair = Process_stock_model::whereIn('stock_id', $graded_stock_ids)->where('status', 2)->whereIn('process_id', $repair_ids)->sum('price');
                $average_repair = $graded_stock_ids ? $total_repair / count($graded_stock_ids) : 0;

                if (count($graded_stock_ids) == 0) {
                    continue;
                }
                $graded_count[$color_id . '.' . $grade_id] = [
                    'quantity' => count($graded_stock_ids),
                    'grade' => $grade,
                    'grade_id' => $grade_id,
                    'color' => $color,
                    'color_id' => $color_id,
                    'stock_ids' => $graded_stock_ids,
                    'total_cost' => $total_cost,
                    'average_cost' => $average_cost,
                    'total_repair' => $total_repair,
                    'average_repair' => $average_repair,
                ];
                $total_graded_count['quantity'] += count($graded_stock_ids);
                $total_graded_count['total_cost'] += $total_cost;
                $total_graded_count['average_cost'] += $average_cost;
                $total_graded_count['total_repair'] += $total_repair;
                $total_graded_count['average_repair'] += $average_repair;
            }
        }

        foreach ($graded_count as $key => $sold_stock) {
            $total_cost = $sold_stock['total_cost'];
            $total_repair = $sold_stock['total_repair'];
            $total_price = 0;
            $total_charge = 0;
            $total_quantity = 0;

        }

        $data['graded_count'] = $graded_count;

        $data['total_graded_count'] = [
            'quantity' => $total_graded_count['quantity'],
            'total_cost' => amount_formatter($total_graded_count['total_cost']),
            'average_cost' => amount_formatter($total_graded_count['average_cost']),
            'total_repair' => amount_formatter($total_graded_count['total_repair']),
            'average_repair' => amount_formatter($total_graded_count['average_repair']),
            'total_price' => amount_formatter($total_graded_count['total_price']),
            'total_charge' => amount_formatter($total_graded_count['total_charge']),
            'profit' => amount_formatter($total_graded_count['profit']),
            'average_price' => amount_formatter($total_graded_count['average_price']),
            'average_charge' => amount_formatter($total_graded_count['average_charge']),
            'average_profit' => amount_formatter($total_graded_count['average_profit']),
        ];

        return response()->json($data);
    }
    public function purchase_model_color_graded_available($order_id, $pss_id){
        $repair_ids = Process_model::where('process_type_id', 9)->pluck('id');
        $pss = Product_storage_sort_model::find($pss_id);
        $variations = $pss->variations;
        $stocks = $pss->stocks->where('order_id', $order_id)->where('status', 1);
        $sent_repair = Process_stock_model::whereIn('stock_id', $stocks->pluck('id'))->where('status', 1)->whereIn('process_id', $repair_ids)->pluck('stock_id')->toArray();
        $stocks = $stocks->whereNotIn('id', $sent_repair);
        $grades = Grade_model::pluck('name', 'id');
        $colors = Color_model::whereIn('id', $variations->pluck('color'))->pluck('name', 'id');
        $graded_count = [];
        $s_orders = [];
        $sold_stocks_2 = [];

        $total_graded_count = [
            'quantity' => 0,
            'total_cost' => 0,
            'average_cost' => 0,
            'total_repair' => 0,
            'average_repair' => 0,
        ];

        foreach ($colors as $color_id => $color) {
            foreach ($grades as $grade_id => $grade) {
                $graded_variations = $variations->where('grade', $grade_id)->where('color', $color_id);
                $graded_stock_ids = $stocks->whereIn('variation_id', $graded_variations->pluck('id'))->pluck('id')->toArray();
                $total_cost = Order_item_model::whereIn('stock_id', $graded_stock_ids)->where('order_id', $order_id)->sum('price');
                $average_cost = $graded_stock_ids ? $total_cost / count($graded_stock_ids) : 0;
                $total_repair = Process_stock_model::whereIn('stock_id', $graded_stock_ids)->where('status', 2)->whereIn('process_id', $repair_ids)->sum('price');
                $average_repair = $graded_stock_ids ? $total_repair / count($graded_stock_ids) : 0;

                if (count($graded_stock_ids) == 0) {
                    continue;
                }
                $graded_count[$color_id . '.' . $grade_id] = [
                    'quantity' => count($graded_stock_ids),
                    'grade' => $grade,
                    'grade_id' => $grade_id,
                    'color' => $color,
                    'color_id' => $color_id,
                    'stock_ids' => $graded_stock_ids,
                    'total_cost' => $total_cost,
                    'average_cost' => $average_cost,
                    'total_repair' => $total_repair,
                    'average_repair' => $average_repair,
                ];
                $total_graded_count['quantity'] += count($graded_stock_ids);
                $total_graded_count['total_cost'] += $total_cost;
                $total_graded_count['average_cost'] += $average_cost;
                $total_graded_count['total_repair'] += $total_repair;
                $total_graded_count['average_repair'] += $average_repair;
            }
        }

        foreach ($graded_count as $key => $sold_stock) {
            $total_cost = $sold_stock['total_cost'];
            $total_repair = $sold_stock['total_repair'];

        }

        $data['graded_count'] = $graded_count;

        $data['total_graded_count'] = [
            'quantity' => $total_graded_count['quantity'],
            'total_cost' => amount_formatter($total_graded_count['total_cost']),
            'average_cost' => amount_formatter($total_graded_count['average_cost']),
            'total_repair' => amount_formatter($total_graded_count['total_repair']),
            'average_repair' => amount_formatter($total_graded_count['average_repair']),
        ];

        return response()->json($data);
    }
    public function purchase_model_graded_count($order_id, $pss_id, $s_type = null){
        $pss = Product_storage_sort_model::find($pss_id);
        $stocks = $pss->stocks->where('order_id',$order_id);
        $grades = session('dropdown_data')['grades'];
        if($s_type == 'rtg'){
            $grades = Grade_model::whereIn('id',[1,2,3,4,5,7])->pluck('name','id');
        }
        if($s_type == 'sold' || $s_type == 'repair'){
            $processes = Process_model::where('process_type_id', 9)->where('status', '<', 3)->pluck('id');
            $process_stocks = Process_stock_model::whereIn('process_id', $processes)->where('status', 1)->whereIn('stock_id', $stocks->pluck('id'))->pluck('stock_id')->toArray();
        }

        foreach($grades as $grade_id => $grade){
            $graded_variations = $pss->variations->where('grade',$grade_id);

            if($s_type == 'sold'){
                $data['graded_count'][$grade_id]['quantity'] = $stocks->whereIn('variation_id',$graded_variations->pluck('id'))->where('status',2)->whereNotIn('id',$process_stocks)->count();
            }elseif($s_type == 'repair'){
                $data['graded_count'][$grade_id]['quantity'] = $stocks->whereIn('variation_id',$graded_variations->pluck('id'))->where('status',2)->whereIn('id',$process_stocks)->count();
            }elseif($s_type == 'available'){
                $data['graded_count'][$grade_id]['quantity'] = $stocks->whereIn('variation_id',$graded_variations->pluck('id'))->where('status',1)->count();
            }else{
                $data['graded_count'][$grade_id]['quantity'] = $stocks->whereIn('variation_id',$graded_variations->pluck('id'))->count();
            }
            $data['graded_count'][$grade_id]['grade'] = $grade;
            $data['graded_count'][$grade_id]['grade_id'] = $grade_id;
        }

        // $data['graded_count'] = $stocks->select('grade.name as grade', 'variation.grade as grade_id', DB::raw('COUNT(*) as quantity'))
        // ->join('variation', 'stock.variation_id', '=', 'variation.id')
        // ->join('grade', 'variation.grade', '=', 'grade.id')
        // ->groupBy('variation.grade', 'grade.name')
        // ->orderBy('grade_id')
        // ->get();

        return response()->json($data['graded_count']);
    }

    public function add_purchase(){

        // dd(request('purchase'));
        $purchase = (object) request('purchase');
        $error = "";
        $issue = [];

        if(request('purchase.sheet') == null){


            $order = Order_model::firstOrNew(['reference_id' => $purchase->reference_id, 'order_type_id' => $purchase->type ]);

            if($order->id != null){
                if(session('user')->hasPermission('append_purchase_sheet')){}else{
                    session()->put('error', "Append Purchase Sheet not Allowed");
                    return redirect()->back();
                }
            }

            $order->customer_id = $purchase->vendor;
            $order->status = 2;
            $order->currency = 4;
            $order->order_type_id = $purchase->type;
            $order->processed_by = session('user_id');
            $order->save();

            return redirect(url('purchase/detail').'/'.$order->id);
        }

        // Validate the uploaded file
        request()->validate([
            'purchase.sheet' => 'required|file|mimes:xlsx,xls',
        ]);

        // Store the uploaded file in a temporary location
        $filePath = request()->file('purchase.sheet')->store('temp');

        // // Perform operations on the Excel file
        // $spreadsheet = IOFactory::load(storage_path('app/'.$filePath));
        // // Perform your operations here...

        // Replace 'your-excel-file.xlsx' with the actual path to your Excel file
        $excelFilePath = storage_path('app/'.$filePath);

        $data = Excel::toArray([], $excelFilePath)[0];
        $dh = $data[0];
        // print_r($dh);
        unset($data[0]);
        $arrayLower = array_map('strtolower', $dh);
        $arrayLower = array_map('trim', $arrayLower);
        // Search for the lowercase version of the search value in the lowercase array
        $name = array_search('name', $arrayLower);
        // echo $name;
        $imei = array_search('imei', $arrayLower);
        // if multiple imei columns exist, create an array of all their indexes
        $imeiIndexes = [];
        foreach ($arrayLower as $index => $value) {
            if ($value === 'imei') {
                $imeiIndexes[] = $index;
            }
        }
        // echo $imei;
        $cost = array_search('cost', $arrayLower);
        if(!in_array('name', $arrayLower)){
            session()->put('error', "Heading not Found(name)");
            return redirect()->back();
        }
        if(!in_array('imei', $arrayLower)){
            session()->put('error', "Heading not Found(imei)");
            return redirect()->back();
        }
        if(!in_array('cost', $arrayLower)){
            session()->put('error', "Heading not Found(cost)");
            return redirect()->back();
        }

        if(!in_array('name', $arrayLower) || !in_array('imei', $arrayLower) || !in_array('cost', $arrayLower)){
            print_r($dh);
            session()->put('error', "Heading not Found(name, imei, cost)");
            return redirect()->back();
        }
        if(isset($data[1]) && !is_numeric($data[1][$cost])){
            session()->put('error', "Formula in Cost is not Allowed");
            return redirect()->back();

        }
        $color = array_search('color', $arrayLower);
        $v_grade = array_search('grade', $arrayLower);
        $note = array_search('notes', $arrayLower);

        // if($note){
        //     dd($data[1][$note]);
        // }
        // echo $cost;
        $grade = 9;


        $order = Order_model::firstOrNew(['reference_id' => $purchase->reference_id, 'order_type_id' => $purchase->type ]);

        if($order->id != null){
            if(session('user')->hasPermission('append_purchase_sheet')){}else{
                session()->put('error', "Append Purchase Sheet not Allowed");
                return redirect()->back();
            }
        }

        $order->customer_id = $purchase->vendor;
        $order->status = 2;
        $order->currency = 4;
        $order->order_type_id = $purchase->type;
        $order->processed_by = session('user_id');
        $order->save();

        $storages = Storage_model::pluck('name','id')->toArray();
        $colors = Color_model::pluck('name','id')->toArray();
        $grades = Vendor_grade_model::pluck('name','id')->toArray();
        // $grades = ['mix','a','a-','b+','b','c','asis','asis+','cpo','new'];

        $products = Products_model::pluck('model','id')->toArray();

        // $variations = Variation_model::where('grade',$grade)->get();

        foreach($data as $dr => $d){
            foreach ($imeiIndexes as $imeiIndex) {
                $imei = $imeiIndex;
                // $name = ;
                // echo $dr." ";
                // print_r($d);
                $n = trim($d[$name]);
                $n = str_replace('  ',' ',$n);
                $n = str_replace('  ',' ',$n);
                $c = $d[$cost];
                $im = trim($d[$imei]);
                if(ctype_digit(trim($im))){
                    $i = trim($im);
                    $s = null;
                }else{
                    $i = null;
                    $s = trim($im);
                }
                $names = explode(" ",$n);
                $last = end($names);
                if(in_array($last, $storages)){
                    $gb = array_search($last,$storages);
                    array_pop($names);
                    $n = implode(" ", $names);
                }else{
                    $gb = 0;
                }

                if(trim($d[$imei]) == ''){
                    if(trim($n) != '' || trim($c) != ''){
                        if(isset($storages[$gb])){$st = $storages[$gb];}else{$st = null;}
                        $issue[$dr]['data']['row'] = $dr;
                        $issue[$dr]['data']['name'] = $n;
                        $issue[$dr]['data']['storage'] = $st;
                        if($color){
                            $issue[$dr]['data']['color'] = $d[$color];
                        }
                        if($v_grade){
                            $issue[$dr]['data']['v_grade'] = $d[$v_grade];
                        }
                        if($note){
                            $issue[$dr]['data']['note'] = $d[$note];
                        }
                        $issue[$dr]['data']['imei'] = $i.$s;
                        $issue[$dr]['data']['cost'] = $c;
                        $issue[$dr]['message'] = 'IMEI not Provided';
                    }
                    continue;
                }
                if(trim($n) == ''){
                    if(trim($n) != '' || trim($c) != ''){
                        if(isset($storages[$gb])){$st = $storages[$gb];}else{$st = null;}
                        $issue[$dr]['data']['row'] = $dr;
                        $issue[$dr]['data']['name'] = $n;
                        $issue[$dr]['data']['storage'] = $st;
                        if($color){
                            $issue[$dr]['data']['color'] = $d[$color];
                        }
                        if($v_grade){
                            $issue[$dr]['data']['v_grade'] = $d[$v_grade];
                        }
                        if($note){
                            $issue[$dr]['data']['note'] = $d[$note];
                        }
                        $issue[$dr]['data']['imei'] = $i.$s;
                        $issue[$dr]['data']['cost'] = $c;
                        $issue[$dr]['message'] = 'Name not Provided';
                    }
                    continue;
                }
                if(trim($c) == ''){
                    if(trim($n) != '' || trim($c) != ''){
                    if(isset($storages[$gb])){$st = $storages[$gb];}else{$st = null;}
                    $issue[$dr]['data']['row'] = $dr;
                    $issue[$dr]['data']['name'] = $n;
                    $issue[$dr]['data']['storage'] = $st;
                    if($color){
                        $issue[$dr]['data']['color'] = $d[$color];
                    }
                    if($v_grade){
                        $issue[$dr]['data']['v_grade'] = $d[$v_grade];
                    }
                    if($note){
                        $issue[$dr]['data']['note'] = $d[$note];
                    }
                    $issue[$dr]['data']['imei'] = $i.$s;
                    $issue[$dr]['data']['cost'] = $c;
                    $issue[$dr]['message'] = 'Cost not Provided';
                    continue;
                    }
                }
                if(in_array(strtolower($n), array_map('strtolower',$products)) && ($i != null || $s != null)){
                    $product = array_search(strtolower($n), array_map('strtolower',$products));
                    $storage = $gb;
                    if ($color) {
                        // Convert each color name to lowercase
                        $lowercaseColors = array_map('strtolower', $colors);

                        $colorName = strtolower($d[$color]); // Convert color name to lowercase

                        if (in_array($colorName, $lowercaseColors)) {
                            // If the color exists in the predefined colors array,
                            // retrieve its index
                            $clr = array_search($colorName, $lowercaseColors);
                        } else {
                            // If the color doesn't exist in the predefined colors array,
                            // create a new color record in the database
                            $newColor = Color_model::create([
                                'name' => ucwords($colorName)
                            ]);
                            $colors = Color_model::pluck('name','id')->toArray();
                            $lowercaseColors = array_map('strtolower', $colors);
                            // Retrieve the ID of the newly created color
                            $clr = $newColor->id;
                        }
                        $check_merge_color = Product_color_merge_model::where(['product_id' => $product, 'color_from' => $clr])->first();
                        if($check_merge_color != null){
                            $clr = $check_merge_color->color_to;
                        }
                        $variation = Variation_model::firstOrNew(['product_id' => $product, 'grade' => $grade, 'storage' => $storage, 'color' => $clr]);

                    }else{

                    $variation = Variation_model::firstOrNew(['product_id' => $product, 'grade' => $grade, 'storage' => $storage]);
                    }
                    $grd = null;
                    if ($v_grade) {
                        // Convert each v_grade name to lowercase
                        $lowercaseGrades = array_map('strtolower', $grades);

                        $v_gradeName = strtolower($d[$v_grade]); // Convert v_grade name to lowercase

                        $v_grd = Vendor_grade_model::firstOrNew(['name' => strtoupper($v_gradeName)]);
                        $v_grd->save();

                        $grd = $v_grd->id;
                    }

                    // echo $product." ".$grade." ".$storage." | ";

                    $stock = Stock_model::firstOrNew(['imei' => $i, 'serial_number' => $s]);

                    if($stock->id && $stock->status != null && $stock->order_id != null){
                        if(isset($storages[$gb])){$st = $storages[$gb];}else{$st = null;}
                        $issue[$dr]['data']['row'] = $dr;
                        $issue[$dr]['data']['name'] = $n;
                        $issue[$dr]['data']['storage'] = $st;
                        if($variation){
                            $issue[$dr]['data']['variation'] = $variation->id;
                        }
                        if($color){
                            $issue[$dr]['data']['color'] = $d[$color];
                        }
                        if($v_grade){
                            $issue[$dr]['data']['v_grade'] = $d[$v_grade];
                        }
                        if($note){
                            $issue[$dr]['data']['note'] = $d[$note];
                        }
                        $issue[$dr]['data']['imei'] = $i.$s;
                        $issue[$dr]['data']['cost'] = $c;
                        if($stock->order_id == $order->id && $stock->status == 1){
                            $issue[$dr]['message'] = 'Item already added in this order';
                        }else{
                                $reference_id = $stock->order->reference_id ?? "Missing";
                            if($stock->status != 2){
                                $issue[$dr]['message'] = 'Item already available in inventory under order reference '.$reference_id;
                            }else{
                                $issue[$dr]['message'] = 'Item previously purchased in order reference '.$reference_id;
                            }

                        }

                    }else{
                        $stock2 = Stock_model::withTrashed()->where(['imei' => $i, 'serial_number' => $s])->first();
                        if($stock2 != null){
                            $stock2->restore();
                            $stock2->order_id = $order->id;
                            $stock2->status = 1;
                            $stock2->save();
                            $order_item = Order_item_model::firstOrNew(['order_id' => $order->id, 'variation_id' => $variation->id, 'stock_id' => $stock2->id]);
                            $order_item->reference_id = $grd;
                            if($note){
                                $order_item->reference = $d[$note];
                            }
                            $order_item->quantity = 1;
                            $order_item->price = $c;
                            $order_item->status = 3;
                            $order_item->save();

                            $stock = $stock2;
                        }else{
                            $variation->stock += 1;
                            $variation->status = 1;
                            $variation->save();

                            $stock->product_id = $product;
                            $stock->variation_id = $variation->id;
                            $stock->added_by = session('user_id');
                            $stock->order_id = $order->id;
                            $stock->status = 1;
                            $stock->save();

                            $order_item = Order_item_model::firstOrNew(['order_id' => $order->id, 'variation_id' => $variation->id, 'stock_id' => $stock->id]);
                            $order_item->reference_id = $grd;
                            if($note){
                                $order_item->reference = $d[$note];
                            }
                            $order_item->quantity = 1;
                            $order_item->price = $c;
                            $order_item->status = 3;
                            $order_item->save();

                        }

                    }

                }else{
                    if(isset($storages[$gb])){$st = $storages[$gb];}else{$st = null;}
                    if($n != null){
                        $error .= $n . " " . $st . " " . $i.$s . " || ";
                        $issue[$dr]['data']['row'] = $dr;
                        $issue[$dr]['data']['name'] = $n;
                        $issue[$dr]['data']['storage'] = $st;
                        if($color){
                            $issue[$dr]['data']['color'] = $d[$color];
                        }
                        if($v_grade){
                            $issue[$dr]['data']['v_grade'] = $d[$v_grade];
                        }
                        if($note){
                            $issue[$dr]['data']['note'] = $d[$note];
                        }
                        $issue[$dr]['data']['imei'] = $i.$s;
                        $issue[$dr]['data']['cost'] = $c;
                        if($i == null && $s == null){
                            $issue[$dr]['message'] = 'IMEI/Serial Not Found';
                        }else{
                            if($st == null){
                                $issue[$dr]['message'] = 'Product Variation Not Found';
                            }else{
                                $issue[$dr]['message'] = 'Product Name Not Found';
                            }
                        }

                    }
                }
            }
        }

        // Delete the temporary file
        // Storage::delete($filePath);
        if($error != ""){

            session()->put('error', $error);
            session()->put('missing', $issue);
        }
        if($issue != []){
            foreach($issue as $row => $datas){
                Order_issue_model::create([
                    'order_id' => $order->id,
                    'data' => json_encode($datas['data']),
                    'message' => $datas['message'],
                ]);
            }
        }

            $data['dropdown_data'] = [];
            $data['dropdown_data']['products'] = Products_model::pluck('model', 'id');
            $data['dropdown_data']['categories'] = Category_model::pluck('name', 'id');
            $data['dropdown_data']['brands'] = Brand_model::pluck('name', 'id');
            $data['dropdown_data']['colors'] = Color_model::pluck('name', 'id');
            $data['dropdown_data']['storages'] = Storage_model::pluck('name', 'id');
            $data['dropdown_data']['grades'] = Grade_model::pluck('name', 'id');
            session(['dropdown_data' => $data['dropdown_data']]);
        return redirect(url('purchase/detail').'/'.$order->id);
    }
    public function add_purchase_item($order_id, $imei = null, $variation_id = null, $price = null, $return = null, $v_grade = null){
        $issue = [];
        if(request('imei')){
            $imei = trim(request('imei'));
        }
        if(request('order')){
            $order_id = request('order');
        }
        if(request('variation') && $return == null){
            $variation_id = request('variation');
        }
        $variation_id = trim($variation_id);
        if(!ctype_digit($variation_id)){
            $products = Products_model::pluck('model','id')->toArray();
            $storages = Storage_model::pluck('name','id')->toArray();
            $names = explode(" ",trim($variation_id));
            $last = end($names);
            if(in_array($last, $storages)){
                $gb = array_search($last,$storages);
                array_pop($names);
                $n = implode(" ", $names);
            }else{
                $gb = 0;
            }
            if(in_array(strtolower($n), array_map('strtolower',$products))){
                $product = array_search(strtolower($n), array_map('strtolower',$products));
                $storage = $gb;

                if($product == null){
                    session()->put('error', "Product Not Found");
                    if($return == null){
                        return redirect()->back();
                    }else{
                        return $issue;
                    }
                }
                $variation = Variation_model::firstOrNew(['product_id' => $product, 'grade' => 9, 'storage' => $storage]);
                $variation->stock += 1;
                $variation->status = 1;
                $variation->save();
            }
        }else{
            $variation = Variation_model::find($variation_id);
        }


        if(request('price')){
            $price = request('price');
            if(!is_numeric($price)){
                session()->put('error', "Formula in Cost is not Allowed");
                return redirect()->back();
            }
        }
        if(request('v_grade')){
            $v_grade = request('v_grade');
        }
        $imei = trim($imei);

        if(ctype_digit($imei)){
            $i = $imei;
            $s = null;
        }else{
            $i = null;
            $s = $imei;
        }

        if($variation == null){
            session()->put('error', 'Variation Not Found '. $variation_id);
            if($return == null){
                return redirect()->back();
            }else{
                return $issue;
            }
        }

        $stock = Stock_model::firstOrNew(['imei' => $i, 'serial_number' => $s]);
        if($stock->id && $stock->status != null && $stock->order_id != null && $stock->status != 2){
            $issue['data']['variation'] = $variation_id;
            $issue['data']['imei'] = $i.$s;
            $issue['data']['cost'] = $price;
            $issue['data']['stock_id'] = $stock->id;
            $issue['data']['v_grade'] = $v_grade;
            if($stock->order_id == $order_id && $stock->status == 1){
                $issue['message'] = 'Duplicate IMEI';
            }else{
                if($stock->status != 2){
                    $issue['message'] = 'IMEI Available In Inventory';
                }else{
                    $issue['message'] = 'IMEI Repurchase';
                }
            }
            // $stock->status = 2;
        }else{
            $stock2 = Stock_model::withTrashed()->where(['imei' => $i, 'serial_number' => $s])->orderByDesc('id')->first();
            if($stock2 != null){
                $stock2->restore();
                $stock2->order_id = $order_id;
                $stock2->status = 1;
                $stock2->save();
                $order_item = Order_item_model::firstOrNew(['order_id' => $order_id, 'variation_id' => $variation->id, 'stock_id' => $stock2->id]);
                $order_item->reference_id = $v_grade;
                $order_item->quantity = 1;
                $order_item->price = $price;
                $order_item->status = 3;
                $order_item->save();
                $stock = $stock2;

            }else{


                $variation->stock += 1;
                $variation->status = 1;
                $variation->save();


                $stock->added_by = session('user_id');
                $stock->order_id = $order_id;

                $stock->product_id = $variation->product_id;
                $stock->variation_id = $variation->id;
                $stock->status = 1;
                $stock->save();

                $order_item = new Order_item_model();
                $order_item->order_id = $order_id;
                $order_item->reference_id = $v_grade;
                $order_item->variation_id = $variation->id;
                $order_item->stock_id = $stock->id;
                $order_item->quantity = 1;
                $order_item->price = $price;
                $order_item->status = 3;
                $order_item->save();
            }

            $order = Order_model::find($order_id);
            if($order->status == 3 && !in_array($order_id,[8441,1,5,8,9,12,13,14,185,263,4739])){

                $issue['data']['variation'] = $variation_id;
                $issue['data']['imei'] = $i.$s;
                $issue['data']['cost'] = $price;
                $issue['data']['stock_id'] = $stock->id;
                $issue['data']['v_grade'] = $v_grade;
                $issue['message'] = 'Additional Item';
            }

        }

        if($issue != []){
            Order_issue_model::create([
                'order_id' => $order_id,
                'data' => json_encode($issue['data']),
                'message' => $issue['message'],
            ]);
        }else{
            $issue = 1;
        }
        // Delete the temporary file
        // Storage::delete($filePath);
        if($return == null){
            return redirect()->back();
        }else{
            return $issue;
        }

    }
    public function add_testing_list($order_id){
        $order = Order_model::find($order_id);
        $variation = Variation_model::find(request('variation_id'));
        $imeis = request('imeis');
        $price = request('price');
        if($order != null && $variation != null && count($imeis) > 0 && $price != null){
            foreach($imeis as $imei){
                $imei = trim($imei);
                $this->add_purchase_item($order_id, $imei, $variation->id, $price, 1);
            }
        }
        return redirect()->back()->with('success', 'IMEI Added to Batch');

    }
    public function remove_issues(){
        // dd(request()->all());
        $ids = request('ids');
        $id = request('id');
        if(request('ids')){
            $issues = Order_issue_model::whereIn('id',$ids)->get();
        }
        if(request('id')){
            $issue = Order_issue_model::find($id);
        }

        if(request('remove_entries') == 1){
            foreach ($issues as $issue) {
                $issue->delete();
            }
        }
        if(request('remove_entry') == 1){
            // foreach ($issues as $issue) {
                $issue->delete();
            // }
        }
        if(request('insert_variation') == 1){
            $varia = request('variation');

            if(!ctype_digit($varia)){
                $storages = Storage_model::pluck('name','id')->toArray();
                $names = explode(" ",trim($varia));
                $last = end($names);
                if(in_array($last, $storages)){
                    $gb = array_search($last,$storages);
                    array_pop($names);
                    $n = implode(" ", $names);
                }else{
                    $gb = null;
                    $n = implode(" ", $names);
                }
                $product = Products_model::where('model',$n)->first();
                if($product == null){
                    session()->put('error', 'Product Not Found');
                    return redirect()->back();
                }
                $color = null;

                $var = Variation_model::firstOrNew(['product_id' => $product->id, 'grade' => 9, 'storage' => $gb, 'color' => null]);
                $var->save();

                $variation = $var->id;
                // dd($variation);
            }else{
                $variation = $varia;

            }
            if(ctype_digit($variation)){

                foreach($issues as $issue){
                    $data = json_decode($issue->data);
                    // echo $variation." ".$data->imei." ".$data->cost;


                    echo $variation;
                    echo $data->cost;


                    $var = Variation_model::find($variation);
                    if($var != null && isset($data->color) && $data->color != null){
                        $clr = Color_model::firstOrNew(['name' => $data->color]);
                        $clr->save();

                        $var2 = Variation_model::firstOrNew(['product_id' => $var->product_id, 'grade' => $var->grade, 'storage' => $var->storage, 'color' => $clr->id]);
                        $var2->save();
                        $variation = $var2->id;
                    }

                    if($this->add_purchase_item($issue->order_id,
                    $data->imei,
                    $variation,
                    $data->cost, 1) == 1){
                        $issue->delete();
                    }

                }
            }
        }
        if(request('insert_product') == 1){
            $product = request('product');

            // if(!ctype_digit($varia)){

            //     $storages = Storage_model::pluck('name','id')->toArray();
            //     // $names = explode(" ",trim($varia));
            //     // $last = end($names);
            //     if(in_array($last, $storages)){
            //         $gb = array_search($last,$storages);
            //         array_pop($names);
            //         $n = implode(" ", $names);
            //     }else{
            //         $gb = null;
            //     }
            //     $product = Products_model::where('model',$n)->first();
            //     if($product == null){
            //         session()->put('error', 'Product Not Found');
            //         return redirect()->back();
            //     }
            //     $var = Variation_model::firstOrNew(['product_id' => $product->id, 'grade' => 9, 'storage' => $gb, 'color' => null]);
            //     $var->save();

            //     $variation = $var->id;
            //     // dd($variation);
            // }else{
            //     $variation = $varia;
            // }
            if(ctype_digit($product)){
                $storages = Storage_model::pluck('name','id')->toArray();
                $colors = Color_model::pluck('name','id')->toArray();

                foreach($issues as $issue){
                    $data = json_decode($issue->data);
                    // echo $variation." ".$data->imei." ".$data->cost;
                    $gb = array_search($data->storage,$storages) ?? 0;
                    if(isset($data->color)){
                        $clr = array_search($data->color,$colors) ?? null;
                    }else{
                        $clr = null;
                    }
                    $var = Variation_model::firstOrNew(['product_id' => $product, 'grade' => 9, 'storage' => $gb, 'color' => $clr]);
                    $var->save();
                    $variation = $var->id;

                    echo $product;
                    echo $data->cost;

                    if($this->add_purchase_item($issue->order_id,
                    $data->imei,
                    $variation,
                    $data->cost, 1) == 1){
                        $issue->delete();
                    }

                }
            }
        }
        if(request('add_imei') == 1){
            $data = json_decode($issue->data);
            $imei = request('imei');
            $variation = request('variation');
            if($variation == null){
                $product_id = Products_model::where('model', $data->name)->first()->id;
                if($product_id == null){
                    session()->put('error', 'Product Not Found');
                    return redirect()->back();
                }
                $storage = Storage_model::where('name', $data->storage)->first()->id ?? 0;
                $color = Color_model::where('name', $data->color)->first()->id ?? null;
                $variation = Variation_model::firstOrNew(['product_id' => $product_id, 'grade' => 9, 'storage' => $storage, 'color' => $color]);
                $variation->save();
                $variation = $variation->id;

            }

            // echo $variation." ".$data->imei." ".$data->cost;
            if(isset($data->v_grade) && $data->v_grade){
                $v_grade = Vendor_grade_model::where('name',$data->v_grade)->first()->id ?? null;
            }else{
                $v_grade = null;
            }

            if($this->add_purchase_item($issue->order_id, $imei, $variation, $data->cost, 1, $v_grade) == 1){
                if($data->imei){

                    $stock = Stock_model::where('imei',$imei)->orWhere('serial_number', $imei)->where('status','!=',null)->first();
                    $stock_operation = new Stock_operations_model();
                    $stock_operation->new_operation($stock->id, null, null, null, $stock->variation_id, $stock->variation_id, "IMEI Changed from ".$data->imei);
                }
                $issue->delete();
            }

        }
        if(request('change_imei') == 1){
            $imei = request('imei');
            $serial_number = null;
            $imei = trim($imei);
            if(!ctype_digit($imei)){
                $serial_number = $imei;
                $imei = null;
            }
            $old_stock = Stock_model::where(['imei'=>$imei,'serial_number'=>$serial_number])->where('status','!=',null)->first();
            if(!$old_stock){

                session()->put('error', "IMEI not Found");
                return redirect()->back();
            }
            $data = json_decode($issue->data);
            $new_stock = Stock_model::find($data->stock_id);
            if(!$new_stock){

                session()->put('error', "Additional Item not added Properly");
                return redirect()->back();
            }
            $new_item = Order_item_model::find($new_stock->purchase_item->id);
            $new_item->order_id = $old_stock->order_id;
            $new_item->price = $old_stock->purchase_item->price;

            $new_stock->order_id = $old_stock->order_id;

            $stock_operation = new Stock_operations_model();
            $stock_operation->new_operation($new_stock->id, $new_item->id, null, null, $old_stock->variation_id, $new_stock->variation_id, "IMEI Changed from ".$old_stock->imei.$old_stock->serial_number);

            Order_item_model::where('stock_id',$old_stock->id)->update(['stock_id' => $new_stock->id]);
            Process_stock_model::where('stock_id',$old_stock->id)->update(['stock_id' => $new_stock->id]);
            Stock_operations_model::where('stock_id',$old_stock->id)->update(['stock_id' => $new_stock->id]);

            // $stock_operation = Stock_operations_model::create([
            //     'stock_id' => $new_stock->id,
            //     'old_variation_id' => $old_stock->variation_id,
            //     'new_variation_id' => $new_stock->variation_id,
            //     'description' => "IMEI Changed from ".$old_stock->imei.$old_stock->serial_number,
            //     'admin_id' => session('user_id'),
            // ]);

            $old_stock->purchase_item->delete();
            $old_stock->delete();

            $new_item->save();
            $new_stock->save();

            $issue->delete();

        }
        if(request('repurchase') == 1){
            $data = json_decode($issue->data);
            if($this->add_purchase_item($issue->order_id, $data->imei, $data->variation, $data->cost, 1) != null){
                if($data->imei){
                    $stock = Stock_model::where('imei',$data->imei)->orWhere('serial_number', $data->imei)->where('status','!=',null)->first();
                    $stock_operation = new Stock_operations_model();
                    $stock_operation->new_operation($stock->id, null, null, null, $stock->variation_id, $stock->variation_id, "IMEI Repurchased");
                }
                $issue->delete();
            }

        }


            $datas['dropdown_data'] = [];
            $datas['dropdown_data']['products'] = Products_model::pluck('model', 'id');
            $datas['dropdown_data']['categories'] = Category_model::pluck('name', 'id');
            $datas['dropdown_data']['brands'] = Brand_model::pluck('name', 'id');
            $datas['dropdown_data']['colors'] = Color_model::pluck('name', 'id');
            $datas['dropdown_data']['storages'] = Storage_model::pluck('name', 'id');
            $datas['dropdown_data']['grades'] = Grade_model::pluck('name', 'id');
            session(['dropdown_data' => $datas['dropdown_data']]);

        return redirect()->back();

    }
    public function export_invoice_new($orderId)
    {

        // Find the order
        $order = Order_model::with('customer', 'order_items')->find($orderId);
        $order_items = Order_item_model::where('order_id', $orderId);
        if($order_items->count() > 1){
            $order_items = $order_items->whereHas('stock', function($q) {
                $q->where('status', 2)->orWhere('status',null);
            })->get();
        }else{
            $order_items = $order_items->get();
        }

        $item_price = $order_items->sum('price');
        if($order->price != $item_price){
            $variation_items = $order_items->groupBy('variation_id');
            foreach($variation_items as $variation_id => $items){
                if($items->count() > 1 && $order->price < $items->sum('price')){
                    $total_price = $items->sum('price');
                    foreach($items as $item){
                        $proportional_price = $order->price / $items->count();
                        $item->price = round($proportional_price, 2);
                        $item->save();
                    }
                }
            }
        }
        // Generate PDF for the invoice content
        $data = [
            'order' => $order,
            'customer' => $order->customer,
            'orderItems' => $order_items,
        ];

        // Create a new TCPDF instance
        $pdf = new TCPDF();

        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        // $pdf->SetTitle('Invoice');
        // $pdf->SetHeaderData('', 0, 'Invoice', '');

        // Add a page
        $pdf->AddPage();

        // Set font
        // $fontname = TCPDF_FONTS::addTTFfont(asset('assets/font/OpenSans_Condensed-Regular.ttf'), 'TrueTypeUnicode', '', 96);

        $pdf->SetFont('dejavusans', '', 12);


        // Additional content from your view
        $html = view('export.invoice', $data)->render();
        $pdf->writeHTML($html, true, false, true, false, '');

        // dd($pdfContent);
        // Send the invoice via email
        // TEMPORARILY DISABLED: InvoiceMail queue calls disabled to prevent queue hogging
        // TODO: Re-enable when queue is stable
        /*
        try {
            Mail::to($order->customer->email)->queue(new InvoiceMail($data));
        } catch (\Exception $e) {
            // Try sending with a different mailer if the default fails
            try {
            Mail::mailer('smtp_secondary')->to($order->customer->email)->queue(new InvoiceMail($data));
            } catch (\Exception $e2) {
            session()->put('error', 'Failed to send invoice email: ' . $e->getMessage() . ' | Retry failed: ' . $e2->getMessage());
            }
        }
        */

        $pdfContent = $pdf->Output('', 'S');
        // Return a response or redirect

        // Pass the PDF content to the view
        return view('livewire.show_pdf')->with(['pdfContent'=> $pdfContent, 'delivery_note'=>$order->delivery_note_url]);
    }
    public function export_invoice($orderId, $packing = null)
    {
        $data['title_page'] = "Invoice";
        session()->put('page_title', $data['title_page']);


        // Find the order
        $order = Order_model::with('customer', 'order_items')->find($orderId);
        $order_items = Order_item_model::where('order_id', $orderId);
        if($order_items->count() > 1){
            $order_items = $order_items->whereHas('stock', function($q) {
                $q->where('status', 2)->orWhere('status',null);
            })->get();
        }else{
            $order_items = $order_items->get();
        }

        $item_price = $order_items->sum('price');
        if($order->price != $item_price){
            $variation_items = $order_items->groupBy('variation_id');
            foreach($variation_items as $variation_id => $items){
                if($items->count() > 1 && $order->price < $items->sum('price')){
                    $total_price = $items->sum('price');
                    foreach($items as $item){
                        $proportional_price = $order->price / $items->count();
                        $item->price = round($proportional_price, 2);
                        $item->save();
                    }
                }
            }
        }
        $packingMode = (string) request('packing') === '1' || (string) $packing === '1';

        // Generate PDF for the invoice content
        $data = [
            'order' => $order,
            'customer' => $order->customer,
            'orderItems' => $order_items,
            'packingMode' => $packingMode,
            'deliveryNoteUrl' => $order->delivery_note_url,
            'labelUrl' => $order->label_url,
            'sessionA4Printer' => session('a4_printer'),
            'sessionLabelPrinter' => session('label_printer'),
        ];

        // Create a new TCPDF instance

        if($order->customer->email == null){
            session()->put('error', 'Customer Email Not Found');
            return view('livewire.invoice_new')->with($data);
        }
        // TEMPORARILY DISABLED: InvoiceMail queue calls disabled to prevent queue hogging
        // TODO: Re-enable when queue is stable
        // Mail::to($order->customer->email)->queue(new InvoiceMail($data));
        /*
        try {
            Mail::to($order->customer->email)->queue(new InvoiceMail($data));
        } catch (\Exception $e) {
            // Try sending with a different mailer if the default fails
            try {
            Mail::mailer('smtp_secondary')->to($order->customer->email)->queue(new InvoiceMail($data));
            } catch (\Exception $e2) {
            session()->put('error', 'Failed to send invoice email: ' . $e->getMessage() . ' | Retry failed: ' . $e2->getMessage());
            }
        }
        */

        $view = $packingMode ? 'livewire.invoice_2_new' : 'livewire.invoice_new';

        return view($view)->with($data);
    }

    public function storePrinterPreferences(Request $request)
    {
        $validated = $request->validate([
            'a4_printer' => ['nullable', 'string', 'max:255'],
            'label_printer' => ['nullable', 'string', 'max:255'],
        ]);

        if (array_key_exists('a4_printer', $validated)) {
            $value = $validated['a4_printer'];
            if ($value === null || $value === '') {
                session()->forget('a4_printer');
            } else {
                session()->put('a4_printer', $value);
            }
        }

        if (array_key_exists('label_printer', $validated)) {
            $value = $validated['label_printer'];
            if ($value === null || $value === '') {
                session()->forget('label_printer');
            } else {
                session()->put('label_printer', $value);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    public function packingDeliveryPrint($orderId)
    {
        $order = Order_model::find($orderId);

        if (!$order || !$order->delivery_note_url) {
            abort(404, 'Delivery note not available for printing');
        }

        $pdfProxyUrl = url('order/proxy_server') . '?url=' . urlencode($order->delivery_note_url);

        return view('exports.packing-delivery-print', [
            'pdfProxyUrl' => $pdfProxyUrl,
            'sessionA4Printer' => session('a4_printer'),
        ]);
    }
    public function proxy_server(){

        $url = $_GET['url']; // The URL of the PDF you want to fetch

        // Validate URL
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            // Set the headers
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="document.pdf"');

            // Fetch and output the file
            readfile($url);
        } else {
            echo "Invalid URL.";
        }
    }
    public function export_refund_invoice($orderId)
    {

        // Find the order
        $order = Order_model::with('customer', 'order_items')->find($orderId);
        $order_items = Order_item_model::where('order_id', $orderId);
        if($order_items->count() > 1){
            $order_items = $order_items->whereHas('stock', function($q) {
                $q->where('status', 2)->orWhere('status',null);
            })->get();
        }else{
            $order_items = $order_items->get();
        }

        // Generate PDF for the invoice content
        $data = [
            'order' => $order,
            'customer' => $order->customer,
            'orderItems' => $order_items,
        ];

        // Create a new TCPDF instance
        $pdf = new TCPDF();

        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        // $pdf->SetTitle('Invoice');
        // $pdf->SetHeaderData('', 0, 'Invoice', '');

        // Add a page
        $pdf->AddPage();

        // Set font
        // $fontname = TCPDF_FONTS::addTTFfont(asset('assets/font/OpenSans_Condensed-Regular.ttf'), 'TrueTypeUnicode', '', 96);

        $pdf->SetFont('dejavusans', '', 12);


        // Additional content from your view
        $html = view('export.refund_invoice', $data)->render();
        $pdf->writeHTML($html, true, false, true, false, '');


        $pdfContent = $pdf->Output('', 'S');
        // Return a response or redirect

        // Pass the PDF content to the view
        return view('livewire.show_pdf')->with(['pdfContent'=> $pdfContent]);
    }
    public function dispatch($id)
    {
        $order = Order_model::find($id);

        if($order == null){
            session()->put('error', "Order Not Found");
            return redirect()->back();
        }

        $isRefurbed = (int) $order->marketplace_id === 4;
        $bm = null;
        $refurbedApi = null;
        $refurbedDocumentLinks = [];

        if($isRefurbed){
            try {
                $refurbedApi = new RefurbedAPIController();
            } catch (\Throwable $e) {
                Log::error('Refurbed: Failed to initialize API client for dispatch', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
                session()->put('error', 'Unable to connect to Refurbed API: ' . $e->getMessage());
                return redirect()->back();
            }

            $orderObj = (object) [
                'state' => 3,
                'tracking_number' => $order->tracking_number,
            ];
        }else{
            $bm = new BackMarketAPIController();
            // $orderObj = $bm->getOneOrder($order->reference_id);
            $orderObj = $this->updateBMOrder($order->reference_id, false, null, true);
        }

        if($orderObj == null){

            session()->put('error', "Order Not Found");
            return redirect()->back();
        }
        if($order->status != 2){

            session()->put('error', "Order Status error");
            return redirect()->back();
        }
        $tester = request('tester');
        if (!is_array($tester)) {
            $tester = $tester !== null ? (array) $tester : [];
        }
        $sku = (array) request('sku', []);
        $imeis = (array) request('imei', []);

        // Initialize an empty result array
        $skus = [];
        if(count($sku) > 1 && count($imeis) > 1){

            // Loop through the numbers array
            foreach ($sku as $index => $number) {
                // If the value doesn't exist as a key in the skus array, create it
                if (!isset($skus[$number])) {
                    $skus[$number] = [];
                }
                // Add the current number to the skus array along with its index in the original array
                $skus[$number][$index] = $number;
            }
    }
    // print_r(request('imei'));
    $externalState = $isRefurbed ? 3 : $this->resolveExternalOrderState($orderObj, $order);

    if (! $isRefurbed && $externalState === null) {
        Log::warning('Dispatch blocked: missing Back Market state data', [
            'order_id' => $order->id,
            'reference_id' => $order->reference_id,
        ]);
        session()->put('error', 'Unable to verify Back Market order state. Please refresh the order before dispatch.');
        return redirect()->back();
    }

    $canDispatch = $isRefurbed ? true : ((int) $externalState === 3);
    if($canDispatch){
            foreach($imeis as $i => $imei){

                $stocksForFreshBlock = collect();

                $variant = Variation_model::where('sku',$sku[$i])->first();
                if($variant->storage != null){
                    $storage2 = $variant->storage_id->name . " - ";
                }else{
                    $storage2 = null;
                }
                if($variant->color != null){
                    $color2 = $variant->color_id->name . " - ";
                }else{
                    $color2 = null;
                }

                $serial_number = null;
                $imei = trim($imei);
                if(!ctype_digit($imei)){
                    $serial_number = $imei;
                    $imei = null;

                }else{

                    if(strlen($imei) != 15){

                        session()->put('error', "IMEI invalid");
                        return redirect()->back();
                    }
                }

                $stock[$i] = Stock_model::where(['imei'=>$imei, 'serial_number'=>$serial_number])->first();

                if(!$stock[$i] || $stock[$i]->status == null){
                    session()->put('error', "Stock not Found");
                    return redirect()->back();
                }
                if($stock[$i]->created_at->diffInDays() < 20 && !session('user')->hasPermission('allow_sell_new_stock')){
                    $stocksForFreshBlock = Stock_model::where('variation_id', $variant->id)
                        ->where('status', 1)
                        ->where('order_id', '<', $stock[$i]->order_id)
                        ->where('updated_at', '<', now()->subDays(5))
                        ->whereDoesntHave('latest_topup', function ($q) {
                            $q->where('status', '<', 3);
                        })
                        ->whereHas('latest_listing_or_topup')
                        ->get();
                    if($stocksForFreshBlock->count() > 3 && !$stock[$i]->stock_repairs()->exists()){
                        session()->put('error', "Sell Old Stock First | ".$stocksForFreshBlock->count() . "pcs Available");
                        return redirect()->back();
                    }
                }
                if(session('user_id') == 1 && $stocksForFreshBlock->isNotEmpty()){
                    dd($stocksForFreshBlock);
                }
                // if($stock[$i]->status != 1){

                    $last_item = $stock[$i]->last_item();
                    if($last_item == null){
                        $imei = new IMEI();
                        $imei->rearrange($stock[$i]->id);
                        $last_item = $stock[$i]->last_item();
                    }

                    // if(session('user_id') == 1){
                    //     dd($last_item);
                    // }
                    if($stock[$i]->stock_repairs()->where('status',1)->exists()){
                        session()->put('error', "Stock Under Repair");
                        return redirect()->back();
                    }
                    if(in_array($last_item->order->order_type_id,[1,4,6])){

                        if($stock[$i]->status == 2){
                            $stock[$i]->status = 1;
                            $stock[$i]->save();
                        }
                    }else{
                        if($stock[$i]->status == 1){
                            $stock[$i]->status = 2;
                            $stock[$i]->save();
                        }
                        session()->put('error', "Stock Already Sold");
                        return redirect()->back();
                    }
                // }
                if($stock[$i]->order->status < 3){
                    session()->put('error', "Stock List Awaiting Approval");
                    return redirect()->back();
                }
                $stock_variation = $stock[$i]->variation;
                if($stock_variation->grade == 17){
                    session()->put('error', "IMEI Flagged | Contact Admin");
                    return redirect()->back();
                }
                $stock_movement = Stock_movement_model::where(['stock_id'=>$stock[$i]->id, 'received_at'=>null])->first();
                // , 'admin_id' => session('user_id')

                if($stock_movement == null && !session('user')->hasPermission('skip_stock_exit')){
                    session()->put('error', "Missing Exit Entry");
                    return redirect()->back();
                }
                if($stock_movement == null && session('user')->hasPermission('skip_stock_exit')){
                    $stock_movement = Stock_movement_model::create([
                        'stock_id' => $stock[$i]->id,
                        'admin_id' => session('user_id'),
                        'exit_at' => now(),
                        'description' => 'Auto Exit by ' . session('user')->first_name . ' ' . session('user')->last_name,
                    ]);
                }
                if($stock_variation->storage != null){
                    $storage = $stock_variation->storage_id->name . " - ";
                }else{
                    $storage = null;
                }
                if($stock_variation->color != null){
                    $color = $stock_variation->color_id->name . " - ";
                }else{
                    $color = null;
                }
                if($stock_variation->product_id != $variant->product_id) {
                    session()->put('error', "Product Model not matched");
                    return redirect()->back();
                }
                if($stock_variation->storage != $variant->storage) {
                    session()->put('error', "Product Storage not matched");
                    return redirect()->back();
                }
                if($stock_variation->grade != $variant->grade) {
                    session()->put('error', "Product Grade not matched");
                    return redirect()->back();

                }
                if($stock_variation->color != $variant->color && !session('user')->hasPermission('allow_change_color_dispatch')){
                    session()->put('error', "Product Color not matched");
                    return redirect()->back();
                }
                $testerValue = $tester[$i] ?? null;

                if ($testerValue === null && isset($stock[$i]->latest_testing)) {
                    $testerValue = $stock[$i]->latest_testing->admin->last_name;
                    $tester[$i] = $testerValue;
                }
                if (
                    isset($stock[$i]->latest_testing) &&
                    $testerValue !== null &&
                    strtoupper($stock[$i]->latest_testing->admin->last_name) != strtoupper($testerValue)
                ) {
                    Log::info('Tester Mismatch for Stock IMEI ' . $stock[$i]->imei.$stock[$i]->serial_number . ': Expected ' . strtoupper($tester[$i]) . ', Found ' . strtoupper($stock[$i]->latest_testing->admin->last_name));
                }
                $testerValue = $tester[$i] ?? null;
                if($stock[$i]->variation_id != $variant->id){
                    echo "<script>
                    if (confirm('System Model: " . $stock_variation->product->model . " - " . $storage . $color . $stock_variation->grade_id->name . "\\nRequired Model: " . $variant->product->model . " - " . $storage2 . $color2 . $variant->grade_id->name . "')) {
                        // User clicked OK, do nothing or perform any other action
                    } else {
                        // User clicked Cancel, redirect to the previous page
                        window.history.back();
                    }
                    </script>";

                    $stock_operation = Stock_operations_model::create([
                        'stock_id' => $stock[$i]->id,
                        'old_variation_id' => $stock[$i]->variation_id,
                        'new_variation_id' => $variant->id,
                        'description' => "Grade changed for Sell",
                        'admin_id' => session('user_id'),
                    ]);
                }
                $stock[$i]->variation_id = $variant->id;
                $stock[$i]->tester = $tester[$i] ?? null;
                $stock[$i]->sale_order_id = $id;
                $stock[$i]->status = 2;
                $stock[$i]->save();

                $stock[$i]->all_listings_or_topups()->update([
                    'status' => 3,
                ]);

            }
            $items = $order->order_items;
            $detail = null;
            if($isRefurbed){
                $detail = $this->handleRefurbedShipping($order, $refurbedApi);
            }else{
                if(count($items) > 1 || $items[0]->quantity > 1){
                    $indexes = 0;
                    foreach($skus as $each_sku){
                        if($indexes == 0 && count($each_sku) == 1){
                            $detail = $bm->shippingOrderlines($order->reference_id,$sku[0],trim($imeis[0]),$orderObj->tracking_number,$serial_number);
                        }elseif($indexes == 0 && count($each_sku) > 1){
                            // dd("Hello");
                            $detail = $bm->shippingOrderlines($order->reference_id,$sku[0],false,$orderObj->tracking_number,$serial_number);
                            if(count($each_sku) == 1){
                                $order_item = Order_item_model::where('order_id',$order->id)->whereHas('variation', function($q) use ($each_sku){
                                    $q->whereIn('sku',$each_sku);
                                })->first();
                                $detail = $bm->orderlineIMEI($order_item->reference_id,trim($imeis[0]),$serial_number);
                            }
                        }elseif($indexes > 0 && count($each_sku) == 1){
                            $order_item = Order_item_model::where('order_id',$order->id)->whereHas('variation', function($q) use ($each_sku){
                                $q->whereIn('sku',$each_sku);
                            })->first();
                            $detail = $bm->orderlineIMEI($order_item->reference_id,trim($imeis[$indexes]),$serial_number);
                        }else{

                        }
                        $indexes++;
                    }
                }else{
                    $detail = $bm->shippingOrderlines($order->reference_id,$sku[0],trim($imeis[0]),$orderObj->tracking_number,$serial_number);
                }
            }
            // print_r($detail);

            if(is_string($detail)){
                session()->put('error', $detail);
                return redirect()->back();
            }

            if($isRefurbed){
                $this->ensureRefurbedLabelArtifacts($order, $refurbedApi, $detail);
                $refurbedDocumentLinks = $this->captureRefurbedDocumentLinks($order, $refurbedApi);
                $carrierContext = data_get($detail, 'carrier')
                    ?? request('refurbed_carrier')
                    ?? data_get($this->buildRefurbedShippingDefaults(), 'default_carrier');
                $carrierContext = $carrierContext ? $this->normalizeRefurbedCarrier($carrierContext) : null;
                $this->syncRefurbedOrderItems($order, $refurbedApi, $carrierContext);
            }

            if(count($sku) == 1 && count($stock) == 1){
                $order_item = Order_item_model::where('order_id',$order->id)->whereHas('variation', function($q) use ($sku){
                    $q->where('sku',$sku[0]);
                })->first();
                $order_item->stock_id = $stock[0]->id;
                $order_item->linked_id = $stock[0]->last_item()->id;

                $order_item->save();
                if($stock_movement != null){

                $stock_movement->update([
                    'received_at' => Carbon::now(),
                ]);

                }
            }else{

                foreach ($skus as $each) {
                    $inde = 0;
                    foreach ($each as $idt => $s) {
                        $variation = Variation_model::where('sku',$s)->first();
                        $item = Order_item_model::where(['order_id'=>$id, 'variation_id'=>$variation->id])->first();
                        if ($inde != 0) {
                            $new_item = new Order_item_model();
                            $new_item->order_id = $id;
                            $new_item->variation_id = $item->variation_id;
                            $new_item->quantity = $item->quantity;
                            $new_item->status = $item->status;
                            $new_item->price = $item->price;
                        }else{
                            $new_item = $item;
                            $new_item->price = $item->price/count($each);
                        }
                        if($stock[$idt]){
                            $new_item->stock_id = $stock[$idt]->id;
                            $new_item->linked_id = $stock[$idt]->last_item()->id;


                            $stock_movement = Stock_movement_model::where(['stock_id'=>$stock[$idt]->id, 'received_at'=>null])->first();
                            if($stock_movement != null){
                                Stock_movement_model::where(['stock_id'=>$stock[$idt]->id, 'received_at'=>null])->update([
                                    'received_at' => Carbon::now(),
                                ]);
                            }
                        // $new_item->linked_id = Order_item_model::where(['order_id'=>$stock[$idt]->order_id,'stock_id'=>$stock[$idt]->id])->first()->id;
                        }
                        $new_item->save();
                        $inde ++;
                    }
                }
            }

            // print_r($d[6]);
        }

        if($isRefurbed){
            $orderObj = (object) [
                'tracking_number' => $order->tracking_number,
            ];
        }else{
            $orderObj = $this->updateBMOrder($order->reference_id, true, null, false, $bm);
        }
        $order->refresh();
    $resolvedTrackingNumber = $order->tracking_number ?? ($orderObj->tracking_number ?? null);
    $trackingPromptValue = $resolvedTrackingNumber ? strtoupper(trim($resolvedTrackingNumber)) : null;

        $invoice_url = url('export_invoice').'/'.$id;
        $packingEnabled = (string) request('packing') === '1';
        $noInvoice = (string) request('no_invoice') === '1';
        $label_url = null;
        $delivery_print_url = null;

        if ($packingEnabled) {
            $invoice_url .= '/1';

            if ($order->label_url) {
                $labelQuery = ['ids' => [$id], 'packing' => 1];
                if (request()->filled('sort')) {
                    $labelQuery['sort'] = request('sort');
                }
                $label_url = url('export_label') . '?' . http_build_query($labelQuery, '', '&', PHP_QUERY_RFC3986);
            }

            if ($order->delivery_note_url) {
                $delivery_print_url = route('order.packing_delivery_print', ['id' => $id]);
            }
        }

        $refurbedInvoicePrintUrl = $refurbedDocumentLinks['invoice_print'] ?? null;
        $refurbedCommercialInvoicePrintUrl = $refurbedDocumentLinks['commercial_invoice_print'] ?? null;

        // Send invoice via email if no_invoice is requested
        if ($noInvoice && $order->customer && $order->customer->email) {
            $order_items = Order_item_model::where('order_id', $id);
            if($order_items->count() > 1){
                $order_items = $order_items->whereHas('stock', function($q) {
                    $q->where('status', 2)->orWhere('status',null);
                })->get();
            }else{
                $order_items = $order_items->get();
            }

            $data = [
                'order' => $order,
                'customer' => $order->customer,
                'orderItems' => $order_items,
            ];

            // TEMPORARILY DISABLED: InvoiceMail queue calls disabled to prevent queue hogging
            // TODO: Re-enable when queue is stable
            /*
            try {
                Mail::to($order->customer->email)->queue(new InvoiceMail($data));
            } catch (\Exception $e) {
                try {
                    Mail::mailer('smtp_secondary')->to($order->customer->email)->queue(new InvoiceMail($data));
                } catch (\Exception $e2) {
                    Log::error('Failed to send invoice email for order '.$id.': ' . $e->getMessage() . ' | Retry failed: ' . $e2->getMessage());
                }
            }
            */
        }
        // $order = Order_model::find($order->id);
        if(isset($detail->orderlines) && $detail->orderlines[0]->imei == null && $detail->orderlines[0]->serial_number  == null){
            $content = "Hi, here are the IMEIs/Serial numbers for this order. \n";
            foreach ($imeis as $im) {
                $content .= $im . "\n";
            }
            $content .= "Regards \n".session('fname');

            // JavaScript code to automatically copy content to clipboard
            echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    const el = document.createElement("textarea");
                    el.value = "'.$content.'";
                    document.body.appendChild(el);
                    el.select();
                    document.execCommand("copy");
                    document.body.removeChild(el);
                });

                window.open("https://backmarket.fr/bo-seller/orders/all?orderId='.$order->reference_id.'", "_blank");
            </script>';
        }

        // JavaScript to open print tabs with delays and store tracking confirmation marker
        $scriptStatements = [];

        $scriptStatements[] = '(async function() {';
        $scriptStatements[] = '    const delay = ms => new Promise(resolve => setTimeout(resolve, ms));';

        // Only open invoice and delivery note if no_invoice is not set
        if (!$noInvoice) {
            $scriptStatements[] = '    window.open('.json_encode($invoice_url).', "_blank");';
            $scriptStatements[] = '    await delay(300);';
            if ($packingEnabled) {
                if ($label_url) {
                    $scriptStatements[] = '    window.open('.json_encode($label_url).', "_blank");';
                    $scriptStatements[] = '    await delay(300);';
                }
                if ($delivery_print_url) {
                    $scriptStatements[] = '    window.open('.json_encode($delivery_print_url).', "_blank");';
                }
            }
        } else {
            // If no_invoice is set, only open label (not invoice or delivery note)
            if ($packingEnabled && $label_url) {
                $scriptStatements[] = '    window.open('.json_encode($label_url).', "_blank");';
            }
        }

        if ($packingEnabled && $refurbedInvoicePrintUrl) {
            $scriptStatements[] = '    window.open('.json_encode($refurbedInvoicePrintUrl).', "_blank");';
            $scriptStatements[] = '    await delay(300);';
        }

        if ($packingEnabled && $refurbedCommercialInvoicePrintUrl) {
            $scriptStatements[] = '    window.open('.json_encode($refurbedCommercialInvoicePrintUrl).', "_blank");';
            $scriptStatements[] = '    await delay(300);';
        }


        if ($packingEnabled && $trackingPromptValue) {
            $scriptStatements[] = '    try { window.sessionStorage.setItem("packing_tracking_verify", '.json_encode($trackingPromptValue).'); } catch (error) { console.warn("Unable to queue tracking confirmation", error); }';
        }

        $scriptStatements[] = '    await delay(400);';
        $fallbackUrl = url()->previous() ?? url('order');
        $scriptStatements[] = '    var __packingFallback = '.json_encode($fallbackUrl).';';
        $scriptStatements[] = '    var __packingTarget = document.referrer || __packingFallback;';
        $scriptStatements[] = '    if (__packingTarget) { window.location.href = __packingTarget; } else if (window.history.length > 1) { window.history.back(); } else { window.location.href = __packingFallback; }';
        $scriptStatements[] = '})();';

        echo '<script>' . implode("\n", $scriptStatements) . '</script>';

        // Open Label in new tab if request(packing) = 1
        if(request('sort') == 4 && !isset($detail)){
            echo "<script> window.close(); </script>";
        }



    }

    public function refreshRefurbedOrder($orderId)
    {
        $order = Order_model::find($orderId);

        if (! $order) {
            session()->put('error', 'Order not found.');
            return redirect()->back();
        }

        if ((int) $order->marketplace_id !== self::REFURBED_MARKETPLACE_ID) {
            session()->put('error', 'Only Refurbed orders support this action.');
            return redirect()->back();
        }

        $referenceId = trim((string) $order->reference_id);

        if ($referenceId === '') {
            session()->put('error', 'Missing Refurbed reference ID.');
            return redirect()->back();
        }

        try {
            $refurbedApi = new RefurbedAPIController();
        } catch (\Throwable $e) {
            Log::error('Refurbed: unable to initialize API for manual refresh', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            session()->put('error', 'Unable to initialize Refurbed API client.');
            return redirect()->back();
        }

        try {
            $orderResponse = $refurbedApi->getOrder($referenceId);
        } catch (\Throwable $e) {
            Log::error('Refurbed: manual refresh fetch failed', [
                'order_id' => $order->id,
                'reference_id' => $referenceId,
                'error' => $e->getMessage(),
            ]);

            session()->put('error', 'Failed to fetch Refurbed order data.');
            return redirect()->back();
        }

        $orderPayload = $orderResponse['order'] ?? $orderResponse ?? [];

        if (! is_array($orderPayload) || empty($orderPayload)) {
            session()->put('error', 'Refurbed API returned an empty response.');
            return redirect()->back();
        }

        $orderPayload = $this->adaptRefurbedOrderPayload($orderPayload);

        $orderItems = $this->extractRefurbedOrderItemsFromPayload($orderPayload);

        if (! $orderItems) {
            $orderItems = $this->fetchRefurbedOrderItemsPayload($refurbedApi, $referenceId);
        }

        try {
            $orderModel = new Order_model();
            $orderModel->storeRefurbedOrderInDB($orderPayload, $orderItems);
        } catch (\Throwable $e) {
            Log::error('Refurbed: manual refresh persist failed', [
                'order_id' => $order->id,
                'reference_id' => $referenceId,
                'error' => $e->getMessage(),
            ]);

            session()->put('error', 'Failed to persist Refurbed order locally.');
            return redirect()->back();
        }

        session()->put('success', "Refurbed order {$referenceId} refreshed.");

        return redirect()->back();
    }

    protected function fetchRefurbedOrderByReference(string $referenceId): ?Order_model
    {
        $referenceId = trim($referenceId);

        if ($referenceId === '') {
            return null;
        }

        try {
            $refurbedApi = new RefurbedAPIController();
        } catch (\Throwable $e) {
            Log::error('Refurbed: unable to initialize API for missing order fetch', [
                'reference_id' => $referenceId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $orderPayload = $this->pullRefurbedOrderPayload($refurbedApi, $referenceId);

        if (! $orderPayload) {
            $locatedOrder = $this->locateRefurbedOrderByReference($refurbedApi, $referenceId);

            if ($locatedOrder) {
                $resolvedId = (string) ($locatedOrder['id'] ?? '');

                $orderPayload = $resolvedId !== ''
                    ? $this->pullRefurbedOrderPayload($refurbedApi, $resolvedId, $referenceId)
                    : $locatedOrder;

                if (! $orderPayload) {
                    $orderPayload = $locatedOrder;
                }
            }
        }

        if (! $orderPayload) {
            Log::info('Refurbed: unable to locate order for missing reference', [
                'reference_id' => $referenceId,
            ]);

            return null;
        }

        $orderPayload = json_decode(json_encode($orderPayload), true) ?: [];
        $orderPayload = $this->adaptRefurbedOrderPayload($orderPayload);
        $orderItems = $this->extractRefurbedOrderItemsFromPayload($orderPayload);

        if (! $orderItems) {
            $orderItems = $this->fetchRefurbedOrderItemsPayload($refurbedApi, $referenceId);
        }

        try {
            $orderModel = new Order_model();

            return $orderModel->storeRefurbedOrderInDB($orderPayload, $orderItems);
        } catch (\Throwable $e) {
            Log::error('Refurbed: unable to persist missing order fetched from API', [
                'reference_id' => $referenceId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function tryFetchMissingRefurbedOrders(): bool
    {
        if ((int) request('marketplace', 0) !== self::REFURBED_MARKETPLACE_ID) {
            return false;
        }

        $requestedReferences = $this->extractRefurbedReferencesFromRequest();

        if (empty($requestedReferences)) {
            return false;
        }

        $existingReferences = Order_model::query()
            ->where('marketplace_id', self::REFURBED_MARKETPLACE_ID)
            ->whereIn('reference_id', $requestedReferences)
            ->pluck('reference_id')
            ->map(fn ($value) => (string) $value)
            ->all();

        $missingReferences = array_values(array_diff($requestedReferences, $existingReferences));

        if (empty($missingReferences)) {
            return false;
        }

        $fetchedAny = false;
        $failed = [];

        foreach ($missingReferences as $referenceId) {
            $order = $this->fetchRefurbedOrderByReference($referenceId);
            if ($order) {
                $fetchedAny = true;
            } else {
                $failed[] = $referenceId;
            }
        }

        if ($fetchedAny) {
            session()->put('success', 'Fetched missing Refurbed order(s) from Refurbed API.');
        }

        if (! empty($failed)) {
            session()->put('error', 'Unable to load Refurbed order(s): ' . implode(', ', $failed));
        }

        return $fetchedAny;
    }

    protected function extractRefurbedReferencesFromRequest(): array
    {
        $raw = trim((string) request('order_id', ''));

        if ($raw === '') {
            return [];
        }

        if (strpbrk($raw, '<>%') !== false) {
            return [];
        }

        if (str_contains($raw, '-')) {
            return [];
        }

        $parts = preg_split('/[\s,]+/', $raw);

        $references = array_values(array_filter(array_map(function ($value) {
            $value = trim($value);
            return $value !== '' && ctype_digit($value) ? $value : null;
        }, $parts)));

        return array_values(array_unique($references));
    }

    protected function buildOrdersPaginator($ordersQuery, int $perPage)
    {
        return $ordersQuery
            ->clone()
            ->paginate($perPage)
            ->onEachSide(5)
            ->appends(request()->except('page'));
    }

    protected function locateRefurbedOrderByReference(RefurbedAPIController $refurbedApi, string $referenceId): ?array
    {
        $referenceId = trim($referenceId);

        if ($referenceId === '') {
            return null;
        }

        $filters = $this->buildRefurbedOrderSearchFilters($referenceId);

        foreach ($filters as $filter) {
            try {
                $response = $refurbedApi->listOrders($filter, ['page_size' => 1]);
            } catch (\Throwable $e) {
                Log::debug('Refurbed: order search attempt failed', [
                    'reference_id' => $referenceId,
                    'filter' => $filter,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            $orders = $response['orders'] ?? [];

            if (! empty($orders)) {
                return $orders[0];
            }
        }

        return null;
    }

    protected function buildRefurbedOrderSearchFilters(string $referenceId): array
    {
        return [
            ['order_number' => ['equals' => $referenceId]],
            ['order_number' => ['any_of' => [$referenceId]]],
            ['reference' => ['equals' => $referenceId]],
            ['reference' => ['any_of' => [$referenceId]]],
        ];
    }

    protected function pullRefurbedOrderPayload(RefurbedAPIController $refurbedApi, string $identifier, ?string $referenceHint = null): ?array
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        try {
            $response = $refurbedApi->getOrder($identifier);
        } catch (\Throwable $e) {
            Log::debug('Refurbed: direct order fetch failed', [
                'identifier' => $identifier,
                'reference_hint' => $referenceHint,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $payload = $response['order'] ?? $response ?? [];

        if (! is_array($payload) || empty($payload)) {
            return null;
        }

        if ($referenceHint && empty($payload['order_number'])) {
            $payload['order_number'] = $referenceHint;
        }

        return $payload;
    }

    public function reprintRefurbedLabel($orderId)
    {
        $order = Order_model::find($orderId);

        if (! $order) {
            session()->put('error', 'Order not found.');
            return redirect()->back();
        }

        if ((int) $order->marketplace_id !== self::REFURBED_MARKETPLACE_ID) {
            session()->put('error', 'Only Refurbed orders support this action.');
            return redirect()->back();
        }

        if (! $order->reference_id) {
            session()->put('error', 'Missing Refurbed reference ID.');
            return redirect()->back();
        }

        try {
            $refurbedApi = new RefurbedAPIController();
        } catch (\Throwable $e) {
            Log::error('Refurbed: unable to initialize API for label reprint', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            session()->put('error', 'Unable to initialize Refurbed API client.');
            return redirect()->back();
        }

        try {
            $labelsResponse = $refurbedApi->listShippingLabels($order->reference_id);
        } catch (\Throwable $e) {
            Log::error('Refurbed: label reprint fetch failed', [
                'order_id' => $order->id,
                'reference_id' => $order->reference_id,
                'error' => $e->getMessage(),
            ]);

            session()->put('error', 'Failed to fetch Refurbed label.');
            return redirect()->back();
        }

        $metadata = $this->extractRefurbedLabelMetadataFromResponse($labelsResponse);
        $downloadUrl = $metadata['download_url'] ?? null;
        $trackingNumber = isset($metadata['tracking_number']) ? trim((string) $metadata['tracking_number']) : null;

        if (! $downloadUrl) {
            session()->put('error', 'Refurbed did not return a printable label.');
            return redirect()->back();
        }

        $order->label_url = $downloadUrl;
        if ($trackingNumber) {
            $existingTracking = $order->tracking_number ? trim((string) $order->tracking_number) : null;
            $newLooksValid = $this->looksLikeDhlTrackingNumber($trackingNumber);
            $existingLooksValid = $this->looksLikeDhlTrackingNumber($existingTracking);

            if (! $existingTracking || ($newLooksValid && ! $existingLooksValid)) {
                $order->tracking_number = $trackingNumber;
            }
        }
        $order->save();

        $proxyUrl = $this->buildProxyDownloadUrl($downloadUrl) ?? $downloadUrl;

        session()->put('success', 'Refurbed shipping label refreshed.');

        return redirect($proxyUrl);
    }

    public function resendRefurbedShipment($orderId)
    {
        $order = Order_model::find($orderId);

        if (! $order) {
            session()->put('error', 'Order not found.');
            return redirect()->back();
        }

        if ((int) $order->marketplace_id !== self::REFURBED_MARKETPLACE_ID) {
            session()->put('error', 'Only Refurbed orders support this action.');
            return redirect()->back();
        }

        if (! $order->reference_id) {
            session()->put('error', 'Missing Refurbed reference ID.');
            return redirect()->back();
        }

        $service = app(RefurbedOrderLineStateService::class);

        try {
            $result = $service->shipOrderLines($order->reference_id, [
                'force' => true,
                'tracking_number' => $order->tracking_number,
            ]);
        } catch (\Throwable $e) {
            Log::error('Refurbed: resend SHIPPED request failed', [
                'order_id' => $order->id,
                'reference_id' => $order->reference_id,
                'error' => $e->getMessage(),
            ]);

            session()->put('error', 'Failed to resend Refurbed SHIPPED request.');
            return redirect()->back();
        }

        $updated = (int) data_get($result, 'updated', 0);
        $message = $updated > 0
            ? "Resent Refurbed SHIPPED request ({$updated} line(s))."
            : 'No Refurbed order lines were updated.';

        session()->put('success', $message);

        return redirect()->back();
    }

    public function syncRefurbedIdentifiers($orderId)
    {
        $order = Order_model::with('order_items.stock')->find($orderId);

        if (! $order) {
            session()->put('error', 'Order not found.');
            return redirect()->back();
        }

        if ((int) $order->marketplace_id !== self::REFURBED_MARKETPLACE_ID) {
            session()->put('error', 'Only Refurbed orders support this action.');
            return redirect()->back();
        }

        if ($order->order_items->isEmpty()) {
            session()->put('error', 'Order has no items to sync.');
            return redirect()->back();
        }

        try {
            $refurbedApi = new RefurbedAPIController();
        } catch (\Throwable $e) {
            Log::error('Refurbed: unable to initialize API for identifier sync', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            session()->put('error', 'Unable to initialize Refurbed API client.');
            return redirect()->back();
        }

        $service = app(RefurbedShippingService::class);

        try {
            $service->syncOrderItemIdentifiers($order, $refurbedApi);
        } catch (\Throwable $e) {
            Log::error('Refurbed: manual IMEI sync failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            session()->put('error', 'Failed to sync IMEIs with Refurbed.');
            return redirect()->back();
        }

        $identifiedLines = $order->order_items->filter(function ($item) {
            $stock = $item->stock;
            return $stock && ($stock->imei || $stock->serial_number);
        })->count();

        if ($identifiedLines === 0) {
            session()->put('error', 'No IMEI or serial numbers were found on this order.');
        } else {
            session()->put('success', "Synced IMEI data to Refurbed for {$identifiedLines} line(s).");
        }

        return redirect()->back();
    }

    protected function handleRefurbedShipping(Order_model $order, RefurbedAPIController $refurbedApi)
    {
        $service = app(RefurbedShippingService::class);
        app(RefurbedCommercialInvoiceService::class)->ensureCommercialInvoice($order, $refurbedApi);

        $merchantAddressId = $this->resolveRefurbedMerchantAddressId();
        if (empty($merchantAddressId)) {
            return 'Refurbed merchant address ID is required before dispatch. Update marketplace settings or include it in the dispatch form.';
        }

        $parcelWeight = $this->resolveRefurbedParcelWeight($order);
        if ($parcelWeight === null || $parcelWeight <= 0) {
            return 'Refurbed parcel weight is required. Please configure a category default weight or enter it manually.';
        }

        $carrier = request('refurbed_carrier');
        if (empty($carrier)) {
            $carrier = data_get($this->buildRefurbedShippingDefaults(), 'default_carrier');
        }

        $carrier = $this->normalizeRefurbedCarrier($carrier);

        if ($carrier === null || $carrier === '') {
            return 'Refurbed carrier is required. Please enter a carrier in the dispatch form or set a default carrier for the marketplace.';
        }

        return $service->createLabel($order, $refurbedApi, [
            'merchant_address_id' => $merchantAddressId,
            'parcel_weight' => $parcelWeight,
            'carrier' => $carrier,
            'mark_shipped' => true,
            'processed_by' => session('user_id'),
            'sync_identifiers' => true,
            'identifier_options' => [
                'carrier' => $carrier,
            ],
        ]);
    }

    protected function syncRefurbedOrderItems(Order_model $order, RefurbedAPIController $refurbedApi, ?string $carrier = null): void
    {
        $trackingNumber = $order->tracking_number ? trim((string) $order->tracking_number) : null;
        $normalizedCarrier = $carrier ? $this->normalizeRefurbedCarrier($carrier) : null;

        $stateService = app(RefurbedOrderLineStateService::class);

        try {
            $stateService->shipOrderLines($order->reference_id, array_filter([
                'tracking_number' => $trackingNumber,
                'carrier' => $normalizedCarrier,
                'force' => true,
            ], fn ($value) => $value !== null && $value !== ''));
        } catch (\Throwable $e) {
            Log::warning('Refurbed: Unable to update order item states after dispatch', [
                'order_id' => $order->id,
                'reference_id' => $order->reference_id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            app(RefurbedShippingService::class)->syncOrderItemIdentifiers($order, $refurbedApi, array_filter([
                'tracking_number' => $trackingNumber,
                'carrier' => $normalizedCarrier,
            ], fn ($value) => $value !== null && $value !== ''));
        } catch (\Throwable $e) {
            Log::warning('Refurbed: Unable to sync identifier data after dispatch', [
                'order_id' => $order->id,
                'reference_id' => $order->reference_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function ensureRefurbedLabelArtifacts(Order_model $order, RefurbedAPIController $refurbedApi, $dispatchResult = null): void
    {
        $dirty = false;

        if ($dispatchResult) {
            $trackingNumber = data_get($dispatchResult, 'tracking_number');
            $labelUrl = data_get($dispatchResult, 'label_url');

            if ($trackingNumber && empty($order->tracking_number)) {
                $order->tracking_number = trim((string) $trackingNumber);
                $dirty = true;
            }

            if ($labelUrl && empty($order->label_url)) {
                $order->label_url = trim((string) $labelUrl);
                $dirty = true;
            }
        }

        if ($dirty) {
            $order->save();
        }

        if ($order->label_url && $order->tracking_number) {
            return;
        }

        try {
            $labelsResponse = $refurbedApi->listShippingLabels($order->reference_id);
        } catch (\Throwable $e) {
            Log::info('Refurbed: Unable to hydrate shipping labels for order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $labelData = data_get($labelsResponse, 'shipping_labels.0')
            ?? data_get($labelsResponse, 'labels.0')
            ?? data_get($labelsResponse, 'label')
            ?? $labelsResponse;

        $downloadUrl = data_get($labelData, 'download_url')
            ?? data_get($labelData, 'label.download_url')
            ?? data_get($labelData, 'label.content_url');

        $trackingNumber = data_get($labelData, 'tracking_number')
            ?? data_get($labelData, 'label.tracking_number');

        $dirty = false;

        if ($downloadUrl && empty($order->label_url)) {
            $order->label_url = trim((string) $downloadUrl);
            $dirty = true;
        }

        if ($trackingNumber && empty($order->tracking_number)) {
            $order->tracking_number = trim((string) $trackingNumber);
            $dirty = true;
        }

        if ($dirty) {
            $order->save();
        }
    }

    protected function captureRefurbedDocumentLinks(Order_model $order, RefurbedAPIController $refurbedApi): array
    {
        $links = [
            'invoice' => null,
            'invoice_print' => null,
            'commercial_invoice' => null,
            'commercial_invoice_number' => null,
            'commercial_invoice_print' => null,
        ];

        $dirty = false;
        $commercialInvoiceService = app(RefurbedCommercialInvoiceService::class);

        try {
            $invoiceResponse = $refurbedApi->getOrderInvoice($order->reference_id);
            $invoiceUrl = data_get($invoiceResponse, 'url');

            if ($invoiceUrl) {
                if ($order->delivery_note_url !== $invoiceUrl) {
                    $order->delivery_note_url = $invoiceUrl;
                    $dirty = true;
                }

                $links['invoice'] = $invoiceUrl;
                $links['invoice_print'] = $this->buildProxyDownloadUrl($invoiceUrl);
            }
        } catch (\Throwable $e) {
            Log::info('Refurbed: Unable to fetch invoice for dispatch', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        $commercialResponse = null;

        try {
            $commercialResponse = $refurbedApi->getOrderCommercialInvoice($order->reference_id);
        } catch (\Throwable $e) {
            Log::info('Refurbed: Unable to fetch commercial invoice for dispatch', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            $commercialResponse = $commercialInvoiceService->uploadCommercialInvoice($order, $refurbedApi);
        }

        if ($commercialResponse) {
            $commercialUrl = data_get($commercialResponse, 'url');
            $commercialNumber = data_get($commercialResponse, 'commercial_invoice_number');

            if ($commercialUrl) {
                $links['commercial_invoice'] = $commercialUrl;
                $links['commercial_invoice_print'] = $this->buildProxyDownloadUrl($commercialUrl);
            }

            if ($commercialNumber) {
                $links['commercial_invoice_number'] = $commercialNumber;
            }
        }

        if ($dirty) {
            $order->save();
        }

        return array_filter($links);
    }

    protected function extractRefurbedOrderItemsFromPayload(array $orderPayload): ?array
    {
        $items = $orderPayload['order_items'] ?? ($orderPayload['items'] ?? null);

        return is_array($items) ? $items : null;
    }

    protected function fetchRefurbedOrderItemsPayload(RefurbedAPIController $refurbedApi, string $referenceId): ?array
    {
        try {
            $itemsResponse = $refurbedApi->getAllOrderItems($referenceId);
        } catch (\Throwable $e) {
            Log::warning('Refurbed: unable to fetch order items during manual refresh', [
                'reference_id' => $referenceId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $items = $itemsResponse['order_items'] ?? ($itemsResponse['items'] ?? null);

        return is_array($items) ? $items : null;
    }

    protected function extractRefurbedLabelMetadataFromResponse($labelsResponse): array
    {
        if (! is_array($labelsResponse)) {
            return ['download_url' => null, 'tracking_number' => null];
        }

        $labelData = data_get($labelsResponse, 'shipping_labels.0')
            ?? data_get($labelsResponse, 'labels.0')
            ?? data_get($labelsResponse, 'label')
            ?? data_get($labelsResponse, 'shipping_label')
            ?? $labelsResponse;

        $downloadUrl = data_get($labelData, 'download_url')
            ?? data_get($labelData, 'label.download_url')
            ?? data_get($labelData, 'label.content_url')
            ?? data_get($labelData, 'label_printer_url')
            ?? data_get($labelData, 'normal_printer_urls.top_left')
            ?? data_get($labelData, 'normal_printer_urls.top_right')
            ?? data_get($labelData, 'normal_printer_urls.bottom_left')
            ?? data_get($labelData, 'normal_printer_urls.bottom_right')
            ?? data_get($labelData, 'label.url');

        $trackingNumber = data_get($labelData, 'tracking_number')
            ?? data_get($labelData, 'label.tracking_number')
            ?? data_get($labelData, 'tracking_data.tracking_number')
            ?? data_get($labelData, 'label.tracking_data.tracking_number')
            ?? data_get($labelData, 'tracking_data.parcel_tracking_number')
            ?? data_get($labelData, 'label.tracking_data.parcel_tracking_number')
            ?? data_get($labelData, 'tracking_data.parcel_tracking_numbers.0')
            ?? data_get($labelData, 'label.tracking_data.parcel_tracking_numbers.0')
            ?? data_get($labelData, 'parcel_tracking_number')
            ?? data_get($labelData, 'parcel_tracking_numbers.0')
            ?? data_get($labelData, 'tracking_codes.0');

        return [
            'download_url' => $downloadUrl ? trim((string) $downloadUrl) : null,
            'tracking_number' => $trackingNumber ? trim((string) $trackingNumber) : null,
        ];
    }

    protected function looksLikeDhlTrackingNumber(?string $tracking): bool
    {
        if (! $tracking) {
            return false;
        }

        $tracking = trim((string) $tracking);

        if ($tracking === '') {
            return false;
        }

        if (preg_match('/^JD\d{18}$/i', $tracking)) {
            return true;
        }

        if (preg_match('/^[A-Z]{2}\d{9}[A-Z]{2}$/i', $tracking)) {
            return true;
        }

        $hasLetters = preg_match('/[A-Z]/i', $tracking) === 1;

        return $hasLetters && strlen($tracking) >= 12;
    }

    protected function adaptRefurbedOrderPayload(array $orderData): array
    {
        if (! isset($orderData['order_number'])) {
            $orderData['order_number'] = $orderData['id'] ?? null;
        }

        $orderData['currency'] = $orderData['settlement_currency_code']
            ?? $orderData['currency']
            ?? $orderData['currency_code']
            ?? null;

        $orderData['total_amount'] = $orderData['settlement_total_paid']
            ?? $orderData['total_amount']
            ?? $orderData['total_paid']
            ?? $orderData['total_charged']
            ?? null;

        if (! isset($orderData['created_at']) && isset($orderData['released_at'])) {
            $orderData['created_at'] = $orderData['released_at'];
        }

        if (! isset($orderData['updated_at']) && isset($orderData['released_at'])) {
            $orderData['updated_at'] = $orderData['released_at'];
        }

        $shippingRaw = $orderData['shipping_address'] ?? null;
        $billingRaw = $orderData['invoice_address'] ?? null;
        $shippingLookup = is_array($shippingRaw) ? $shippingRaw : [];

        if (! isset($orderData['country'])) {
            $orderData['country'] = $shippingLookup['country_code']
                ?? $shippingLookup['country']
                ?? ($billingRaw['country_code'] ?? null);
        }

        if (! isset($orderData['billing_address']) && $billingRaw) {
            $orderData['billing_address'] = $this->mapRefurbedAddressPayload($billingRaw);
        }

        if ($shippingRaw) {
            $orderData['shipping_address'] = $this->mapRefurbedAddressPayload($shippingRaw);
        }

        if (! isset($orderData['customer'])) {
            $orderData['customer'] = [
                'email' => $orderData['customer_email'] ?? null,
                'first_name' => $shippingLookup['first_name'] ?? $shippingLookup['given_name'] ?? null,
                'last_name' => $shippingLookup['family_name'] ?? $shippingLookup['last_name'] ?? null,
                'phone' => $shippingLookup['phone_number'] ?? $shippingLookup['phone'] ?? null,
            ];
        }

        if (isset($orderData['items']) && ! isset($orderData['order_items'])) {
            $orderData['order_items'] = $orderData['items'];
        }

        return $orderData;
    }

    protected function mapRefurbedAddressPayload(array $address): array
    {
        $streetLine = trim(($address['street_name'] ?? '') . ' ' . ($address['house_no'] ?? ''));

        return [
            'company' => $address['company'] ?? null,
            'first_name' => $address['first_name'] ?? $address['given_name'] ?? null,
            'last_name' => $address['last_name'] ?? $address['family_name'] ?? null,
            'street' => $streetLine ?: ($address['street'] ?? ''),
            'street2' => $address['street2'] ?? $address['street_line2'] ?? '',
            'postal_code' => $address['postal_code'] ?? $address['post_code'] ?? '',
            'country' => $address['country'] ?? $address['country_code'] ?? '',
            'city' => $address['city'] ?? $address['town'] ?? '',
            'phone' => $address['phone'] ?? $address['phone_number'] ?? '',
            'email' => $address['email'] ?? null,
        ];
    }

    protected function buildProxyDownloadUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return url('order/proxy_server') . '?url=' . urlencode($url);
    }
    public function dispatch_allowed($id)
    {
        $order = Order_model::where('id',$id)->first();
        $bm = new BackMarketAPIController();

        // $orderObj = $bm->getOneOrder($order->reference_id);
        $orderObj = $this->updateBMOrder($order->reference_id, false, null, true);
        $tester = request('tester');
        $sku = request('sku');
        $imeis = request('imei');

        // Initialize an empty result array
        $skus = [];

        // Loop through the numbers array
        foreach ($sku as $index => $number) {
            // If the value doesn't exist as a key in the skus array, create it
            if (!isset($skus[$number])) {
                $skus[$number] = [];
            }
            // Add the current number to the skus array along with its index in the original array
            $skus[$number][$index] = $number;
        }
        // print_r(request('imei'));
        $externalState = $this->resolveExternalOrderState($orderObj, $order);

        if ($externalState === null) {
            Log::warning('Dispatch allowed check aborted: missing Back Market state', [
                'order_id' => $order->id,
                'reference_id' => $order->reference_id,
            ]);
            session()->put('error', 'Unable to verify Back Market order state. Please refresh the order before dispatch.');
            return redirect()->back();
        }

        if ((int) $externalState === 3){
            foreach(request('imei') as $i => $imei){

                $variant = Variation_model::where('sku',$sku[$i])->first();
                if($variant->storage != null){
                    $storage2 = $variant->storage_id->name . " - ";
                }else{
                    $storage2 = null;
                }
                if($variant->color != null){
                    $color2 = $variant->color_id->name . " - ";
                }else{
                    $color2 = null;
                }
                $serial_number = null;
                $imei = trim($imei);
                if(!ctype_digit($imei)){
                    $serial_number = $imei;
                    $imei = null;

                }else{

                    if(strlen($imei) != 15){

                        session()->put('error', "IMEI invalid");
                        return redirect()->back();
                    }
                }

                $stock[$i] = Stock_model::where(['imei'=>$imei, 'serial_number'=>$serial_number])->first();

                if(!$stock[$i] || $stock[$i]->status == null){
                    session()->put('error', "Stock not Found");
                    return redirect()->back();

                }
                // if($stock[$i]->status != 1){

                    $last_item = $stock[$i]->last_item();
                    // if(session('user_id') == 1){
                    //     dd($last_item);
                    // }
                    if(in_array($last_item->order->order_type_id,[1,4,6])){

                        if($stock[$i]->status == 2){
                            $stock[$i]->status = 1;
                            $stock[$i]->save();
                        }
                    }else{
                        if($stock[$i]->status == 1){
                            $stock[$i]->status = 2;
                            $stock[$i]->save();
                        }
                        session()->put('error', "Stock Already Sold");
                        return redirect()->back();
                    }
                // }
                if($stock[$i]->order->status < 3){
                    session()->put('error', "Stock List Awaiting Approval");
                    return redirect()->back();
                }
                if($stock[$i]->variation->grade == 17){
                    session()->put('error', "IMEI Flagged | Contact Admin");
                    return redirect()->back();
                }
                if($stock[$i]->variation->storage != null){
                    $storage = $stock[$i]->variation->storage_id->name . " - ";
                }else{
                    $storage = null;
                }
                if($stock[$i]->variation->color != null){
                    $color = $stock[$i]->variation->color_id->name . " - ";
                }else{
                    $color = null;
                }
                if(($stock[$i]->variation->product_id == $variant->product_id) || ($variant->product_id == 144 && $stock[$i]->variation->product_id == 229) || ($variant->product_id == 142 && $stock[$i]->variation->product_id == 143) || ($variant->product_id == 54 && $stock[$i]->variation->product_id == 55) || ($variant->product_id == 55 && $stock[$i]->variation->product_id == 54) || ($variant->product_id == 200 && $stock[$i]->variation->product_id == 160)){
                }else{
                    session()->put('error', "Product Model not matched");
                    // return redirect()->back();
                }
                if(($stock[$i]->variation->storage == $variant->storage) || ($variant->storage == 5 && in_array($stock[$i]->variation->storage,[0,6]) && $variant->product->brand == 2) || (in_array($variant->product_id, [78,58,59]) && $variant->storage == 4 && in_array($stock[$i]->variation->storage,[0,5]))){
                }else{
                    session()->put('error', "Product Storage not matched");
                    // return redirect()->back();
                }
                if($stock[$i]->variation_id != $variant->id){
                    echo "<script>
                    if (confirm('System Model: " . $stock[$i]->variation->product->model . " - " . $storage . $color . $stock[$i]->variation->grade_id->name . "\\nRequired Model: " . $variant->product->model . " - " . $storage2 . $color2 . $variant->grade_id->name . "')) {
                        // User clicked OK, do nothing or perform any other action
                    } else {
                        // User clicked Cancel, redirect to the previous page
                        window.history.back();
                    }
                    </script>";

                    $stock_operation = Stock_operations_model::create([
                        'stock_id' => $stock[$i]->id,
                        'old_variation_id' => $stock[$i]->variation_id,
                        'new_variation_id' => $variant->id,
                        'description' => "Grade changed for Sell",
                        'admin_id' => session('user_id'),
                    ]);
                }
                $stock[$i]->variation_id = $variant->id;
                $stock[$i]->tester = $tester[$i];
                $stock[$i]->sale_order_id = $id;
                $stock[$i]->status = 2;
                $stock[$i]->save();

                $stock[$i]->all_listings_or_topups()->update([
                    'status' => 3,
                ]);

                $stock_movement = Stock_movement_model::where(['stock_id'=>$stock[$i]->id, 'received_at'=>null])->first();
                if($stock_movement != null){
                    Stock_movement_model::where(['stock_id'=>$stock[$i]->id, 'received_at'=>null])->update([
                        'received_at' => Carbon::now(),
                    ]);
                }
                $orderObj = $this->updateBMOrder($order->reference_id, true, $tester[$i], true);
            }
            $order = Order_model::find($order->id);
            $items = $order->order_items;
            if(count($items) > 1 || $items[0]->quantity > 1){
                $indexes = 0;
                foreach($skus as $each_sku){
                    if($indexes == 0 && count($each_sku) == 1){
                        $detail = $bm->shippingOrderlines($order->reference_id,$sku[0],trim($imeis[0]),$orderObj->tracking_number,$serial_number);
                    }elseif($indexes == 0 && count($each_sku) > 1){
                        // dd("Hello");
                        $detail = $bm->shippingOrderlines($order->reference_id,$sku[0],false,$orderObj->tracking_number,$serial_number);
                    }elseif($indexes > 0 && count($each_sku) == 1){
                        $detail = $bm->orderlineIMEI($order->reference_id,trim($imeis[0]),$serial_number);
                    }else{

                    }
                    $indexes++;
                }
            }else{
                $detail = $bm->shippingOrderlines($order->reference_id,$sku[0],trim($imeis[0]),$orderObj->tracking_number,$serial_number);
            }
            // print_r($detail);

            if(is_string($detail)){
                session()->put('error', $detail);
                return redirect()->back();
            }


            foreach ($skus as $each) {
                $inde = 0;
                foreach ($each as $idt => $s) {
                    $variation = Variation_model::where('sku',$s)->first();
                    $item = Order_item_model::where(['order_id'=>$id, 'variation_id'=>$variation->id])->first();
                    if ($inde != 0) {

                        $new_item = new Order_item_model();
                        $new_item->order_id = $id;
                        $new_item->variation_id = $item->variation_id;
                        $new_item->quantity = $item->quantity;
                        $new_item->status = $item->status;
                        $new_item->price = $item->price;
                    }else{
                        $new_item = $item;
                        $new_item->price = $item->price/count($each);
                    }
                    if($stock[$idt]){
                    $new_item->stock_id = $stock[$idt]->id;
                    $new_item->linked_id = $stock[$idt]->last_item()->id;
                    // $new_item->linked_id = Order_item_model::where(['order_id'=>$stock[$idt]->order_id,'stock_id'=>$stock[$idt]->id])->first()->id;
                    }
                    $new_item->save();
                    $inde ++;
                }
            }

            // print_r($d[6]);
        }

        $orderObj = $this->updateBMOrder($order->reference_id, true);
        $order = Order_model::find($order->id);
    $resolvedTrackingNumber = $order->tracking_number ?? ($orderObj->tracking_number ?? null);
    $trackingPromptValue = $resolvedTrackingNumber ? strtoupper(trim($resolvedTrackingNumber)) : null;

        $invoice_url = url('export_invoice').'/'.$id;
        $packingEnabled = (string) request('packing') === '1';
        $label_url = null;
        $delivery_print_url = null;

        if ($packingEnabled) {
            $invoice_url .= '/1';

            if ($order->label_url) {
                $labelQuery = ['ids' => [$id], 'packing' => 1];
                if (request()->filled('sort')) {
                    $labelQuery['sort'] = request('sort');
                }
                $label_url = url('export_label') . '?' . http_build_query($labelQuery, '', '&', PHP_QUERY_RFC3986);
            }

            if ($order->delivery_note_url) {
                $delivery_print_url = route('order.packing_delivery_print', ['id' => $id]);
            }
        }

        $baseScriptStatements = [];
        $baseScriptStatements[] = '(async function() {';
        $baseScriptStatements[] = '    const delay = ms => new Promise(resolve => setTimeout(resolve, ms));';

        if ($packingEnabled) {
            if ($label_url) {
                $baseScriptStatements[] = '    window.open('.json_encode($label_url).', "_blank");';
                $baseScriptStatements[] = '    await delay(600);';
            }
            if ($delivery_print_url) {
                $baseScriptStatements[] = '    window.open('.json_encode($delivery_print_url).', "_blank");';
                $baseScriptStatements[] = '    await delay(600);';
            }
        }
        $baseScriptStatements[] = '    window.open('.json_encode($invoice_url).', "_blank");';

        if ($packingEnabled && $trackingPromptValue) {
            $baseScriptStatements[] = '    try { window.sessionStorage.setItem("packing_tracking_verify", '.json_encode($trackingPromptValue).'); } catch (error) { console.warn("Unable to queue tracking confirmation", error); }';
        }

        $baseScriptStatements[] = '    await delay(400);';
        $fallbackUrlAllowed = url()->previous() ?? url('order');
        $redirectStatement = '    var __packingFallback = '.json_encode($fallbackUrlAllowed).';'
            . '    var __packingTarget = document.referrer || __packingFallback;'
            . '    if (__packingTarget) { window.location.href = __packingTarget; } else if (window.history.length > 1) { window.history.back(); } else { window.location.href = __packingFallback; }';

        if(!isset($detail)){

            $scriptLines = $baseScriptStatements;
            $scriptLines[] = $redirectStatement;
            $scriptLines[] = '})();';

            echo '<script>' . implode("\n", $scriptLines) . '</script>';

        }
        if(!$detail->orderlines){
            dd($detail);
        }
        if($detail->orderlines[0]->imei == null && $detail->orderlines[0]->serial_number  == null){
            $content = "Hi, here are the IMEIs/Serial numbers for this order. \n";
            foreach ($imeis as $im) {
                $content .= $im . "\n";
            }
            $content .= "Regards \n".session('fname');

            // JavaScript code to automatically copy content to clipboard
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    const el = document.createElement('textarea');
                    el.value = '$content';
                    document.body.appendChild(el);
                    el.select();
                    document.execCommand('copy');
                    document.body.removeChild(el);
                });
            </script>";


            // JavaScript to open two tabs and print
            $scriptLines = $baseScriptStatements;
            $scriptLines[] = '    window.open("https://backmarket.fr/bo-seller/orders/all?orderId='.$order->reference_id.'", "_blank");';
            $scriptLines[] = $redirectStatement;
            $scriptLines[] = '})();';

            echo '<script>' . implode("\n", $scriptLines) . '</script>';
        }else{

            $scriptLines = $baseScriptStatements;
            $scriptLines[] = $redirectStatement;
            $scriptLines[] = '})();';

            echo '<script>' . implode("\n", $scriptLines) . '</script>';
        }


    }

    public function packingReprint($id)
    {
        $order = Order_model::find($id);

        if (!$order) {
            session()->flash('error', 'Order not found');
            return redirect(url('order'));
        }

        if ((int) $order->marketplace_id === self::REFURBED_MARKETPLACE_ID) {
            try {
                $refurbedApi = new RefurbedAPIController();
                $this->ensureRefurbedLabelArtifacts($order, $refurbedApi);
                $this->captureRefurbedDocumentLinks($order, $refurbedApi);
                $order->refresh();
            } catch (\Throwable $e) {
                Log::warning('Refurbed: Unable to refresh packing documents during reprint', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $invoiceUrl = url('export_invoice').'/'.$id.'/1';
        $labelUrl = null;
        $deliveryPrintUrl = null;

        if ($order->label_url) {
            $labelQuery = ['ids' => [$id], 'packing' => 1];
            if (request()->filled('sort')) {
                $labelQuery['sort'] = request('sort');
            }
            $labelUrl = url('export_label') . '?' . http_build_query($labelQuery, '', '&', PHP_QUERY_RFC3986);
        }

        if ($order->delivery_note_url) {
            $deliveryPrintUrl = route('order.packing_delivery_print', ['id' => $id]);
        }

        $scriptStatements = [];

        $scriptStatements[] = '(async function() {';
        $scriptStatements[] = '    const delay = ms => new Promise(resolve => setTimeout(resolve, ms));';

        if ($labelUrl) {
            $scriptStatements[] = '    window.open('.json_encode($labelUrl).', "_blank");';
            $scriptStatements[] = '    await delay(600);';
        }

        if ($deliveryPrintUrl) {
            $scriptStatements[] = '    window.open('.json_encode($deliveryPrintUrl).', "_blank");';
            $scriptStatements[] = '    await delay(600);';
        }

        $scriptStatements[] = '    window.open('.json_encode($invoiceUrl).', "_blank");';

        $trackingPromptValue = $order->tracking_number ? strtoupper(trim($order->tracking_number)) : null;
        if ($trackingPromptValue) {
            $scriptStatements[] = '    try { window.sessionStorage.setItem("packing_tracking_verify", '.json_encode($trackingPromptValue).'); } catch (error) { console.warn("Unable to queue tracking confirmation", error); }';
        }

        $missingDocs = [];
        if (!$order->label_url) {
            $missingDocs[] = 'label';
        }
        if (!$order->delivery_note_url) {
            $missingDocs[] = 'delivery note';
        }

        if (!empty($missingDocs)) {
            $missingMessage = 'Missing '.implode(' and ', $missingDocs).' for this order. Only available documents were opened.';
            $scriptStatements[] = '    alert('.json_encode(ucfirst($missingMessage)).');';
        }

        $scriptStatements[] = '    await delay(400);';
        $fallbackUrl = url()->previous() ?? url('order');
        $scriptStatements[] = '    var __packingFallback = '.json_encode($fallbackUrl).';';
        $scriptStatements[] = '    var __packingTarget = document.referrer || __packingFallback;';
        $scriptStatements[] = '    if (__packingTarget) { window.location.href = __packingTarget; } else if (window.history.length > 1) { window.history.back(); } else { window.location.href = __packingFallback; }';
        $scriptStatements[] = '})();';

        $scriptContent = implode("\n", $scriptStatements);

        return response()->make(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Reprinting Packing Documents</title></head><body><script>' . $scriptContent . '</script></body></html>',
            200,
            ['Content-Type' => 'text/html']
        );
    }

    public function delete_item($id){
        Order_item_model::find($id)->delete();
        return redirect()->back();
    }
    public function delete_replacement_item($id){
        $item = Order_item_model::find($id);
        $item->stock->status = 1;
        $item->stock->save();
        $item->delete();
        return redirect()->back();
    }
    public function tracking(){
        $order = Order_model::find(request('tracking')['order_id']);
        if(session('user')->hasPermission('change_order_tracking')){

            $new_tracking = strtoupper(trim(request('tracking')['number']));

            if($order->tracking_number != $new_tracking){
                if(strlen($new_tracking) == 21 && strpos($new_tracking, 'JJ') == 0){
                    $new_tracking = substr($new_tracking, 1);
                }
                if(strlen($new_tracking) != 20){
                    session()->put('error', "Tracking number invalid".strlen($new_tracking));
                    return redirect()->back();
                }
            }
            $message = "Tracking number changed from " . $order->tracking_number . " to " . $new_tracking . " | " . request('tracking')['reason'];

            $order->tracking_number = $new_tracking;
            $order->reference = $message;
            $order->save();

        }
        return redirect()->back();
    }

    public function correction($override = false){
        $item = Order_item_model::find(request('correction')['item_id']);
        if(session('user')->hasPermission('correction')){
            if($item->quantity > 1 && $item->order->order_items->count() == 1){
                for($i=1; $i<=$item->quantity; $i++){

                    if ($i != 1) {

                        $new_item = new Order_item_model();
                        $new_item->order_id = $item->order_id;
                        $new_item->variation_id = $item->variation_id;
                        $new_item->quantity = $item->quantity;
                        $new_item->status = $item->status;
                        $new_item->price = $item->price;
                    }else{
                        $new_item = $item;
                        $new_item->price = $item->price/$item->quantity;
                    }
                    $new_item->save();
                }
            }
            $imei = request('correction')['imei'];

            $serial_number = null;
            if(!ctype_digit($imei)){
                $serial_number = $imei;
                $imei = null;
            }

            if(request('correction')['imei'] != ''){
                $stock = Stock_model::where(['imei'=>$imei, 'serial_number'=>$serial_number])->first();
                if(!$stock || $stock->status == null){
                    session()->put('error', 'Stock not found');
                    return redirect()->back();
                }
                if($stock->order->status != 3){
                    session()->put('error', 'Stock list awaiting approval');
                    return redirect()->back();
                }
                if($stock->variation->grade == 17){
                    session()->put('error', "IMEI Flagged | Contact Admin");
                    return redirect()->back();
                }
                if($stock->variation->storage != null){
                    $storage = $stock->variation->storage_id->name . " - ";
                }else{
                    $storage = null;
                }
                if($stock->variation->color != null){
                    $color = $stock->variation->color_id->name . " - ";
                }else{
                    $color = null;
                }
                $variant = $item->variation;
                if(($stock->variation->product_id == $variant->product_id) || ($variant->product_id == 144 && $stock->variation->product_id == 229) || ($variant->product_id == 142 && $stock->variation->product_id == 143) || ($variant->product_id == 54 && $stock->variation->product_id == 55) || ($variant->product_id == 55 && $stock->variation->product_id == 54) || ($variant->product_id == 58 && $stock->variation->product_id == 59) || ($variant->product_id == 59 && $stock->variation->product_id == 58) || ($variant->product_id == 200 && $stock->variation->product_id == 160)){}else{
                    session()->put('error', "Product Model not matched");
                    if(session('user')->hasPermission('correction_override') && $override){}else{
                        return redirect()->back();
                    }
                }
                if(($stock->variation->storage == $variant->storage) || ($variant->storage == 5 && in_array($stock->variation->storage,[0,6]) && $variant->product->brand == 2) || (in_array($variant->product_id, [78,58,59]) && $variant->storage == 4 && in_array($stock->variation->storage,[0,5]))){}else{
                    session()->put('error', "Product Storage not matched");
                    if(session('user')->hasPermission('correction_override') && $override){}else{
                        return redirect()->back();
                    }
                }
                if(!in_array($stock->variation->grade, [$variant->grade, 7, 9])){
                    session()->put('error', "Product Grade not matched");
                    if(session('user')->hasPermission('correction_override') && $override){}else{
                        return redirect()->back();
                    }
                }
                if($item->stock != null){
                    $previous =  " | Previous IMEI: " . $item->stock->imei . $item->stock->serial_number;
                }else{
                    $previous = null;
                }
                $stock->mark_sold($item->id, request('correction')['tester'], request('correction')['reason'].$previous, $override);
                // $stock->variation_id = $item->variation_id;
                // $stock->tester = request('correction')['tester'];
                // $stock->added_by = session('user_id');
                // if($stock->status == 1){
                //     $stock->status = 2;
                // }
                // $stock->save();
            }
            if($item->stock_id != null){
                if($item->stock_id != 0 && $item->stock && $item->stock->order_id != null){
                    if($item->stock->purchase_item){
                        $last_operation = Stock_operations_model::where('stock_id',$item->stock_id)->orderBy('id','desc')->first();
                        if($last_operation != null){
                            if($last_operation->new_variation_id == $item->stock->variation_id){
                                $last_variation_id = $last_operation->old_variation_id;
                            }else{
                                $last_variation_id = $last_operation->new_variation_id;
                            }
                        }else{
                            $last_variation_id = Order_item_model::where(['order_id'=>$item->stock->order_id,'stock_id'=>$item->stock_id])->first()->variation_id;
                        }
                        $item->stock->mark_available($item->id, $last_variation_id, request('correction')['reason']." ".$item->order->reference_id." ".$imei.$serial_number);
                        // $stock_operation = Stock_operations_model::create([
                        //     'stock_id' => $item->stock->id,
                        //     'order_item_id' => $item->id,
                        //     'old_variation_id' => $item->stock->variation_id,
                        //     'new_variation_id' => $last_variation_id,
                        //     'description' => request('correction')['reason']." ".$item->order->reference_id." ".$imei.$serial_number,
                        //     'admin_id' => session('user_id'),
                        // ]);
                        // $stock_operation->save();
                        // $item->stock->variation_id = $last_variation_id;
                        // if($item->stock->status == 2){
                        //     $item->stock->status = 1;
                        // }
                        // $item->stock->save();
                    }
                }

            }
            if(request('correction')['imei'] != ''){
                $item->stock_id = $stock->id;
                $item->linked_id = $stock->purchase_item->id;

                $stock_movement = Stock_movement_model::where(['stock_id'=>$stock->id, 'received_at'=>null])->first();
                if($stock_movement != null){
                    Stock_movement_model::where(['stock_id'=>$stock->id, 'received_at'=>null])->update([
                        'received_at' => Carbon::now(),
                    ]);
                }

                $message = "Hi, here is the correct IMEI/Serial number for this order. \n".$imei.$serial_number."\n Regards, \n" . session('fname');
                session()->put('success', $message);
                session()->put('copy', $message);
            }else{
                $item->stock_id = 0;
                $item->linked_id = null;
                session()->put('success', 'IMEI removed from Order');
            }
            $item->save();

        }else{
            session()->put('error', 'Permission Denied');
        }
        return redirect()->back();
    }

    public function replacement($london = 0, $allowed = 0){
        $item = Order_item_model::find(request('replacement')['item_id']);
        if(session('user')->hasPermission('replacement')){
            if(!$item->stock->order){
                session()->put('error', 'Stock not purchased');
                return redirect()->back();
            }
            $imei = request('replacement')['imei'];
            $serial_number = null;
            if(!ctype_digit($imei)){
                $serial_number = $imei;
                $imei = null;
            }

            $stock = Stock_model::where(['imei'=>$imei, 'serial_number'=>$serial_number])->first();
            if(!$stock){
                session()->put('error', 'Stock not found');
                return redirect()->back();
            }
            if($stock->id == $item->stock_id){
                session()->put('error', 'Stock same as previous');
                return redirect()->back();
            }
            if($stock->status != 1){
                session()->put('error', 'Stock already sold');
                return redirect()->back();
            }
            if($stock->order->status != 3){
                session()->put('error', 'Stock list awaiting approval');
                return redirect()->back();
            }
            if($stock->variation->storage != null){
                $storage = $stock->variation->storage_id->name . " - ";
            }else{
                $storage = null;
            }
            if($stock->variation->color != null){
                $color = $stock->variation->color_id->name . " - ";
            }else{
                $color = null;
            }
            if($item->variation->storage != null){
                $storage2 = $item->variation->storage_id->name . " - ";
            }else{
                $storage2 = null;
            }
            if($item->variation->color != null){
                $color2 = $item->variation->color_id->name . " - ";
            }else{
                $color2 = null;
            }
            if(($stock->variation->product_id == $item->variation->product_id) || ($item->variation->product_id == 144 && $stock->variation->product_id == 229) || ($item->variation->product_id == 142 && $stock->variation->product_id == 143) || ($item->variation->product_id == 54 && $stock->variation->product_id == 55) || ($item->variation->product_id == 55 && $stock->variation->product_id == 54) || ($item->variation->product_id == 58 && $stock->variation->product_id == 59) || ($item->variation->product_id == 59 && $stock->variation->product_id == 58) || ($item->variation->product_id == 200 && $stock->variation->product_id == 160)){
            }else{
                session()->put('error', "Product Model not matched");
                if($allowed == 0){
                    return redirect()->back();
                }
            }
            if(($stock->variation->storage == $item->variation->storage) || ($item->variation->storage == 5 && in_array($stock->variation->storage,[0,6]) && $item->variation->product->brand == 2) || (in_array($item->variation->product_id, [78,58,59]) && $item->variation->storage == 4 && in_array($stock->variation->storage,[0,5]))){
            }else{
                session()->put('error', "Product Storage not matched");
                if($allowed == 0){
                    return redirect()->back();
                }
            }

            if($london == 1){
                $return_order = Order_model::where(['reference_id'=>2999,'order_type_id'=>4])->first();
            }else{

                $return_order = Order_model::where(['order_type_id'=>4,'status'=>1])->first();
            }
            if(!$return_order){
                $return_order = Order_model::where(['order_type_id'=>4,'status'=>1])->first();
            }

            $check_return = Order_item_model::where(['linked_id'=>$item->id, 'reference_id'=>$item->order->reference_id])->first();
            if($check_return != null){
                $return_order = $check_return->order;
            }
            // if(in_array($item->stock->last_item()->order->order_type_id,[1,4])){
            //     $return_order = $item->stock->last_item()->order;
            // }
            if(!$return_order){
                session()->put('error', 'No Active Return Order Found');
                return redirect()->back();
            }

            $r_item = Order_item_model::where(['order_id'=>$return_order->id, 'stock_id' => $item->stock_id])->first();
            if($r_item){
                $grade = $r_item->variation->grade;

                $stock_operation = Stock_operations_model::where(['stock_id'=>$item->stock_id])->orderBy('id','desc')->first();
                $stock_operation->order_item_id = $r_item->id;
                $stock_operation->description = $stock_operation->description." | Order: ".$item->order->reference_id." | New IMEI: ".$imei.$serial_number;
                $stock_operation->save();
            }else{
                $grade = request('replacement')['grade'];
            }

            $variation = Variation_model::firstOrNew(['product_id' => $item->variation->product_id, 'storage' => $item->variation->storage, 'color' => $item->variation->color, 'grade' => $grade]);

            $variation->stock += 1;
            $variation->status = 1;
            $variation->save();


            // print_r($stock);
            if($r_item == null){
                $return_item = new Order_item_model();
                $return_item->order_id = $return_order->id;
                $return_item->reference_id = request('replacement')['id'];
                $return_item->variation_id = $variation->id;
                $return_item->stock_id = $item->stock_id;
                $return_item->quantity = 1;
                $return_item->currency = $item->order->currency;
                $return_item->price = $item->price;
                $return_item->status = 3;
                $return_item->linked_id = $item->id;
                $return_item->admin_id = session('user_id');
                $return_item->save();

                print_r($return_item);

                // session()->put('success','Item returned');

                $stock_operation = Stock_operations_model::create([
                    'stock_id' => $item->stock_id,
                    'order_item_id' => $return_item->id,
                    'old_variation_id' => $item->variation_id,
                    'new_variation_id' => $variation->id,
                    'description' => request('replacement')['reason']." | Order: ".$item->order->reference_id." | New IMEI: ".$imei.$serial_number,
                    'admin_id' => session('user_id'),
                ]);
                $item->stock->variation_id = $variation->id;
            }else{
                // session()->put('error','Item already returned');

            }
            $stock_operation_2 = Stock_operations_model::create([
                'stock_id' => $stock->id,
                'order_item_id' => $item->id,
                'old_variation_id' => $stock->variation_id,
                'new_variation_id' => $stock->variation_id,
                'description' => "Replacement | Order: ".$item->order->reference_id." | Old IMEI: ".$item->stock->imei.$item->stock->serial_number,
                'admin_id' => session('user_id'),
            ]);
            $item->stock->status = 1;
            $item->stock->save();

            // $stock->variation_id = $item->variation_id;
            $stock->tester = request('replacement')['tester'];
            $stock->added_by = session('user_id');
            if($stock->status == 1){
                $stock->status = 2;

                $stock->all_listings_or_topups()->update([
                    'status' => 3,
                ]);
            }
            $stock->save();

            $order_item = new Order_item_model();
            $order_item->order_id = Order_model::where(['reference_id'=>999,'order_type_id'=>5])->first()->id;
            $order_item->reference_id = $item->order->reference_id;
            $order_item->care_id = $item->id;
            if($allowed == 0){
                $order_item->variation_id = $item->variation_id;
            }else{
                $order_item->variation_id = $stock->variation_id;
            }
            $order_item->stock_id = $stock->id;
            $order_item->quantity = 1;
            $order_item->price = $item->price;
            $order_item->currency = $item->order->currency;
            $order_item->status = 3;
            $order_item->linked_id = $stock->last_item()->id;
            $order_item->admin_id = session('user_id');
            $order_item->save();

            $stock_movement = Stock_movement_model::where(['stock_id'=>$stock->id, 'received_at'=>null])->first();
            if($stock_movement != null){
                Stock_movement_model::where(['stock_id'=>$stock->id, 'received_at'=>null])->update([
                    'received_at' => Carbon::now(),
                ]);
            }


            $message = "Hi, here is the new IMEI/Serial number for this order. \n".$imei.$serial_number."\n Regards, \n" . session('fname');
            session()->put('success', $message);
            session()->put('copy', $message);
        }else{
            session()->put('error', 'Unauthorized');
        }
        return redirect()->back();
    }

    public function recheck($order_id, $refresh = false, $invoice = false, $tester = null, $data = false){

        $bm = new BackMarketAPIController();

        $order_model = new Order_model();
        $order_item_model = new Order_item_model();
        $currency_codes = Currency_model::pluck('id','code')->toArray();
        $country_codes = Country_model::pluck('id','code')->toArray();

        $orderObj = $bm->getOneOrder($order_id);
        if(!isset($orderObj->orderlines)){
            if($data == true){
                dd($orderObj);
            }

        }else{

            if($data == true){
                foreach($orderObj->orderlines as $orderline){
                    $item = Order_item_model::where('reference_id',$orderline->id)->first();
                    if($item->care_id != null){
                        dd($bm->getCare($item->care_id));
                    }
                }
                dd($orderObj);
            }


            $order_model->updateOrderInDB($orderObj, $invoice, $bm, $currency_codes, $country_codes);

            $order_item_model->updateOrderItemsInDB($orderObj, $tester, $bm);
            if($refresh == true){
                $order = Order_model::where('reference_id',$order_id)->first();

                $invoice_url = url('export_invoice').'/'.$order->id;
                // JavaScript to open two tabs and print
                echo '<script>
                var newTab2 = window.open("'.$invoice_url.'", "_blank");
                // var newTab1 = window.open("'.$order->delivery_note_url.'", "_blank");

                // newTab1.onload = function() {
                //     newTab1.print();
                // };

                newTab2.onload = function() {
                    newTab2.print();
                    newTab2.close();
                };

                window.close();
                </script>';
            }
        }
        // return redirect()->back();

    }
    public function import()
    {
        // $bm = new BackMarketAPIController();
        // // Replace 'your-excel-file.xlsx' with the actual path to your Excel file
        // $excelFilePath = storage_path(request('file'));

        // $data = Excel::toArray([], $excelFilePath)[0];
        // if(request('product') != null){
        //     foreach($data as $dr => $d){
        //         // $name = ;
        //     }
        // }else{

        //     // Print or use the resulting array
        //     // dd($data);
        //     $i = 0;
        //     foreach($data as $d){
        //         $orderObj = $bm->getOneOrder($d[1]);
        //         $this->updateBMOrder($d[1]);
        //         if($orderObj->state == 3){
        //             print_r($bm->shippingOrderlines($d[1],trim($d[6]),$orderObj->tracking_number));
        //             // $orderObj = $bm->getOneOrder($d[1]);
        //             // $this->updateBMOrder($d[1]);
        //             $i ++;
        //             print_r($orderObj);
        //             print_r($d[6]);
        //         }
        //         if($i == 100){break;}
        //     }
        // }

    }

    public function export()
    {
        // dd(request());
        // return Excel::download(new OrdersExport, 'your_export_file.xlsx');
        if(request('order') != null){
            $pdfExport = new OrdersExport();
            $pdfExport->generatePdf();
        }
            if(request('ordersheet') != null){
                return Excel::download(new OrdersheetExport, 'orders.xlsx');
            // echo "<script>window.close();</script>";
        }
        if(request('picklist') != null){
            $pdfExport = new PickListExport();
            $pdfExport->generatePdf();
        }
    }
    public function export_label()
    {

        if(request('missing') == 'scan') {

            ini_set('max_execution_time', 300); // 5 minutes

            Order_model::where('scanned',null)->where('status',3)->where('order_type_id',3)->where('tracking_number', '!=', null)->where('created_at', '>', Carbon::now()->subDays(10))->orderByDesc('id')->each(function($order){
                    $this->getLabel($order->reference_id, false, true);
                });

        }

        // return Excel::download(new OrdersExport, 'your_export_file.xlsx');
        // dd(request('ids'));
        $pdfExport = new LabelsExport();
        return $pdfExport->generatePdf();
    }
    public function export_note()
    {
        // return Excel::download(new OrdersExport, 'your_export_file.xlsx');

        $pdfExport = new DeliveryNotesExport();
        $pdfExport->generatePdf();
    }
    public function track_order($order_id){
        $order = Order_model::find($order_id);
        $orderObj = $this->updateBMOrder($order->reference_id, false, null, true);
        return redirect($orderObj->tracking_url);
    }
    public function getLabel($order_id, $data = false, $update = false)
    {
        $bm = new BackMarketAPIController();
        $this->updateBMOrder($order_id);
        $datas = $bm->getOrderLabel($order_id);
        if($update == true){
            // dd($datas);
            if($datas == null || $datas->results == []){
                // print_r($datas);
                // echo 'Hello';
            }elseif($datas->results[0]->hubScanned == true){
                $order = Order_model::where('reference_id',$order_id)->first();
                $order->scanned = 1;
                if($order->delivered_at == null){
                    $order->delivered_at = Carbon::parse($datas->results[0]->dateDelivery);
                    // return $order->delivered_at;
                }
                $order->save();
                // echo 'Order Scanned';
            }
        }
        if($data == true){
            return $datas;
        }else{
            return redirect()->back();
        }
    }
    public function getapiorders($page = null)
    {
        if ($this->isRefurbedRefreshRequest()) {
            return $this->handleRefurbedOrdersRefresh();
        }

        $output = $this->runBackMarketRefreshWorkflow($page);

        return response($output, 200, ['Content-Type' => 'text/html']);
    }

    protected function runBackMarketRefreshWorkflow($page = null): string
    {
        ob_start();

        if ($page == 1) {
            for ($i = 1; $i <= 10; $i++) {
                $j = $i * 20;
                echo $url = url('refresh_order') . '/' . $j;
                echo '<script>
                var newTab1 = window.open("' . $url . '", "_blank");
                </script>';
            }
            $this->updateBMOrdersAll($page);
        } elseif ($page) {
            $this->updateBMOrdersAll($page);
        } else {
            $this->updateBMOrdersAll();
        }

        echo '<script>window.close();</script>';

        return ob_get_clean() ?: '<script>window.close();</script>';
    }

    protected function isRefurbedRefreshRequest(): bool
    {
        $marketplace = request('marketplace');

        if ($marketplace !== null && $marketplace !== '') {
            return (int) $marketplace === self::REFURBED_MARKETPLACE_ID;
        }

        $source = strtolower((string) request('source', ''));

        if ($source === 'refurbed') {
            return true;
        }

        return request()->boolean('refurbed');
    }

    protected function handleRefurbedOrdersRefresh()
    {
        $options = [];

        try {
            $options = $this->buildRefurbedRefreshOptions();

            Artisan::call('refurbed:orders', $options);

            $output = trim((string) Artisan::output());

            session()->put('success', 'Refurbed orders refresh completed.');

            if ($output !== '') {
                session()->put('copy', $output);
            }
        } catch (\Throwable $e) {
            Log::error('Refurbed: manual refresh failed', [
                'error' => $e->getMessage(),
                'options' => $options,
                'user_id' => session('user_id'),
            ]);

            session()->put('error', 'Unable to refresh Refurbed orders: ' . $e->getMessage());
        }

        return response('<script>window.close();</script>', 200, ['Content-Type' => 'text/html']);
    }

    protected function buildRefurbedRefreshOptions(): array
    {
        $options = [];

        $states = array_filter((array) request('state'), fn ($value) => $value !== null && $value !== '');
        if (! empty($states)) {
            $options['--state'] = array_values($states);
        }

        $fulfillment = array_filter((array) request('fulfillment'), fn ($value) => $value !== null && $value !== '');
        if (! empty($fulfillment)) {
            $options['--fulfillment'] = array_values($fulfillment);
        }

        if ($pageSize = request('page_size')) {
            $pageSize = (int) $pageSize;
            if ($pageSize > 0) {
                $options['--page-size'] = min($pageSize, 200);
            }
        }

        if (request()->boolean('skip_items')) {
            $options['--skip-items'] = true;
        }

        return $options;
    }

    public function updateBMOrdersNew($return = false)
    {
        // exec('nohup php artisan refresh:new > /dev/null &');
        // die;
        // return redirect()->back();
        $bm = new BackMarketAPIController();
        $resArray = $bm->getNewOrders();
        $orders = [];
        if ($resArray !== null) {
            foreach ($resArray as $orderObj) {
                if (!empty($orderObj)) {
                    foreach($orderObj->orderlines as $orderline){
                        $this->validateOrderlines($orderObj->order_id, $orderline->listing);
                    }
                    $orders[] = $orderObj->order_id;
                }
            }
            foreach($orders as $or){
                $this->updateBMOrder($or);
            }

        } else {
            echo 'No new orders (in state 0 or 1) exist!';
        }
        $orders2 = Order_model::whereIn('status',[0,1])->where('order_type_id',3)->get();
        foreach($orders2 as $order){
            $this->updateBMOrder($order->reference_id);
        }


        $last_id = Order_item_model::where('care_id','!=',null)->orderBy('reference_id','desc')->first()->care_id;
        $care = $bm->getAllCare(false, ['last_id'=>$last_id,'page-size'=>50]);
        // $care = $bm->getAllCare(false, ['page-size'=>50]);
        // print_r($care);
        $care_line = collect($care)->pluck('id','orderline')->toArray();
        $care_keys = array_keys($care_line);


        // Assuming $care_line is already defined from the previous code
        $careLineKeys = array_keys($care_line);

        // Construct the raw SQL expression for the CASE statement
        // $caseExpression = "CASE ";
        foreach ($care_line as $id => $care) {
            // $caseExpression .= "WHEN reference_id = $id THEN $care ";
            Order_item_model::where('reference_id',$id)->update(['care_id' => $care]);
        }

        if($return = true){
            session()->put('success',count($orders).' Orders Loaded Successfull');
            return redirect()->back();
        }


    }
    public function updateBMOrder($order_id = null, $invoice = false, $tester = null, $data = false, $bm = null, $care = false){
        if(request('reference_id')){
            $order_id = request('reference_id');
        }
        if($bm == null){
            $bm = new BackMarketAPIController();
        }

        $order_model = new Order_model();
        $order_item_model = new Order_item_model();
        $currency_codes = Currency_model::pluck('id','code')->toArray();
        $country_codes = Country_model::pluck('id','code')->toArray();

        $orderObj = $bm->getOneOrder($order_id);
        if(isset($orderObj->delivery_note)){

            if($orderObj->delivery_note == null){
                $orderObj = $bm->getOneOrder($order_id);
            }

            $order_model->updateOrderInDB($orderObj, $invoice, $bm, $currency_codes, $country_codes);

            $order_item_model->updateOrderItemsInDB($orderObj, $tester, $bm, $care);
        }else{
            session()->put('error','Order not Found');
        }
        if($data == true){
            return $orderObj;
        }else{
            return redirect()->back();
        }



    }
    public function updateBMOrdersAll($page = 1)
    {

        $bm = new BackMarketAPIController();

        $order_model = new Order_model();
        $order_item_model = new Order_item_model();
        $currency_codes = Currency_model::pluck('id','code')->toArray();
        $country_codes = Country_model::pluck('id','code')->toArray();



        $resArray = $bm->getAllOrders($page, ['page-size'=>50]);
        if ($resArray !== null) {
            // print_r($resArray);
            foreach ($resArray as $orderObj) {
                if (!empty($orderObj)) {
                // print_r($orderObj);
                $order_model->updateOrderInDB($orderObj, false, $bm, $currency_codes, $country_codes);
                $order_item_model->updateOrderItemsInDB($orderObj,null,$bm);
                // $this->updateOrderItemsInDB($orderObj);
                }
                // print_r($orderObj);
                // if($i == 0){ break; } else { $i++; }
            }
        } else {
            echo 'No orders have been modified in 3 months!';
        }
    }

    private function validateOrderlines($order_id, $sku, $validated = true)
    {
        $bm = new BackMarketAPIController();
        $end_point = 'orders/' . $order_id;
        $new_state = 2;

        // construct the request body
        $request = ['order_id' => $order_id, 'new_state' => $new_state, 'sku' => $sku];
        $request_JSON = json_encode($request);

        $result = $bm->apiPost($end_point, $request_JSON);

        return $result;
    }

    protected function resolveExternalOrderState($orderObj, Order_model $order): ?int
    {
        if ($orderObj === null) {
            return $order->status ? (int) $order->status : null;
        }

        $state = null;

        if (is_object($orderObj) || is_array($orderObj)) {
            $state = data_get($orderObj, 'state');

            if ($state === null) {
                $state = data_get($orderObj, 'status');
            }

            if ($state === null) {
                $state = data_get($orderObj, 'order_status.state');
            }

            if ($state === null) {
                $state = data_get($orderObj, 'order_state');
            }
        }

        if ($state === null && $order->status !== null) {
            $state = (int) $order->status;
        }

        return $state !== null ? (int) $state : null;
    }

    protected function buildRefurbedShippingDefaults(): array
    {
        $defaults = [];
        $marketplace = $this->getRefurbedMarketplace();

        if ($marketplace) {
            $merchantAddress = data_get($marketplace, 'shipping_id');
            if (! empty($merchantAddress)) {
                $defaults['default_merchant_address_id'] = trim($merchantAddress);
            }

            $fallbackCarrier = data_get($marketplace, 'default_shipping_carrier');
            if (! empty($fallbackCarrier)) {
                $defaults['default_carrier'] = $this->normalizeRefurbedCarrier($fallbackCarrier);
            }
        }

        if (! isset($defaults['default_carrier']) || $defaults['default_carrier'] === '') {
            $defaults['default_carrier'] = self::REFURBED_DEFAULT_CARRIER;
        }

        return $defaults;
    }

    protected function resolveRefurbedMerchantAddressId(): ?string
    {
        $addressFromRequest = request('refurbed_merchant_address_id');
        if (! empty($addressFromRequest)) {
            return trim($addressFromRequest);
        }

        $marketplace = $this->getRefurbedMarketplace();
        $addressFromMarketplace = data_get($marketplace, 'shipping_id');
        if (! empty($addressFromMarketplace)) {
            return trim($addressFromMarketplace);
        }

        return $this->autoCreateRefurbedMerchantAddress($marketplace);
    }

    protected function resolveRefurbedParcelWeight(Order_model $order): ?float
    {
        $weightFromRequest = request('refurbed_parcel_weight');
        if ($weightFromRequest !== null && $weightFromRequest !== '') {
            return (float) $weightFromRequest;
        }

        $categoryWeight = $this->extractCategoryWeightFromOrder($order);
        if ($categoryWeight !== null) {
            return $categoryWeight;
        }

        return null;
    }

    protected function extractCategoryWeightFromOrder(Order_model $order): ?float
    {
        $order->loadMissing('order_items.variation.product.category_id');

        foreach ($order->order_items as $item) {
            $variation = $item->variation;
            $product = $variation ? $variation->product : null;
            $category = $product ? $product->category_id : null;
            $weight = $this->extractWeightFromCategory($category);
            if ($weight !== null) {
                return $weight;
            }
        }

        return null;
    }

    protected function extractWeightFromCategory($category): ?float
    {
        if (! $category) {
            return null;
        }

        $fields = [
            'default_shipping_weight',
            'default_weight',
            'shipping_weight',
            'weight',
        ];

        foreach ($fields as $field) {
            $value = data_get($category, $field);
            if ($value !== null && $value !== '' && is_numeric($value)) {
                $numericValue = (float) $value;
                if ($numericValue > 0) {
                    return $numericValue;
                }
            }
        }

        return null;
    }

    protected function normalizeRefurbedCarrier(?string $carrier): ?string
    {
        if ($carrier === null) {
            return null;
        }

        $normalized = strtoupper(str_replace(' ', '_', trim($carrier)));

        if ($normalized === '' || $normalized === 'N/A') {
            return null;
        }

        if ($normalized === 'DHL-EXPRESS') {
            $normalized = 'DHL_EXPRESS';
        }

        return $normalized;
    }

    protected function getRefurbedMarketplace(bool $refresh = false): ?Marketplace_model
    {
        static $cached;

        if ($refresh || $cached === null) {
            $cached = Marketplace_model::query()->find(self::REFURBED_MARKETPLACE_ID);
        }

        return $cached;
    }

    protected function autoCreateRefurbedMerchantAddress(?Marketplace_model $marketplace = null): ?string
    {
        static $attempted = false;

        if ($attempted) {
            return null;
        }

        $attempted = true;

        $payload = $this->buildRefurbedMerchantAddressPayload();
        if ($payload === null) {
            return null;
        }

        try {
            $refurbedApi = new RefurbedAPIController();
        } catch (\Throwable $e) {
            Log::error('Refurbed: Unable to initialize API client for merchant address creation', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        try {
            $response = $refurbedApi->createMerchantAddress($payload);
        } catch (\Throwable $e) {
            Log::error('Refurbed: Failed to auto-create merchant address', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $addressId = data_get($response, 'address.id')
            ?? data_get($response, 'merchant_address.id')
            ?? data_get($response, 'id');

        if (empty($addressId)) {
            Log::warning('Refurbed: Merchant address creation response missing ID', [
                'response' => $response,
            ]);

            return null;
        }

        $marketplace = $marketplace ?: $this->getRefurbedMarketplace(true);
        if ($marketplace) {
            $marketplace->shipping_id = $addressId;
            $marketplace->save();
        }

        return trim((string) $addressId);
    }

    protected function buildRefurbedMerchantAddressPayload(): ?array
    {
        $payload = array_filter(config('services.refurbed.shipping.address', []), function ($value) {
            return $value !== null && $value !== '';
        });

        if ($payload === []) {
            Log::warning('Refurbed: Shipping address config is empty; cannot auto-create merchant address.');
            return null;
        }

        $requiredFields = ['company', 'street', 'postal_code', 'city', 'country'];
        foreach ($requiredFields as $field) {
            if (empty($payload[$field])) {
                Log::warning('Refurbed: Shipping address config missing required field', [
                    'field' => $field,
                ]);

                return null;
            }
        }

        return $payload;
    }


}
