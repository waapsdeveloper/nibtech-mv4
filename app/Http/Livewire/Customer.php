<?php

namespace App\Http\Livewire;

use App\Models\Account_transaction_model;
use App\Models\Country_model;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Customer_model;
use App\Models\Order_item_model;
use App\Models\Order_model;
use App\Models\Process_model;
use App\Models\Process_stock_model;
use App\Models\Product_storage_sort_model;

class Customer extends Component
{
    use WithPagination;
    protected $customers;

    public function mount()
    {
        $this->customers = Customer_model::with('country_id')->withCount('orders')
        ->when(request('type') && request('type') != 0, function($q){
            if(request('type') == 1){
                return $q->where('is_vendor',null);
            }else{
                return $q->whereNotNull('is_vendor');
            }
        })
        ->when(request('order_id') != '', function ($q) {
            return $q->whereHas('orders', function ($q) {
                $q->where('reference_id', 'LIKE', '%' . request('order_id') . '%');
            });
        })
        ->when(request('company') != '', function ($q) {
            return $q->where('company', 'LIKE', '%' . request('company') . '%');
        })
        ->when(request('first_name') != '', function ($q) {
            return $q->where('first_name', 'LIKE', '%' . request('first_name') . '%');
        })
        ->when(request('last_name') != '', function ($q) {
            return $q->where('last_name', 'LIKE', '%' . request('last_name') . '%');
        })
        ->when(request('phone') != '', function ($q) {
            return $q->where('phone', 'LIKE', '%' . request('phone') . '%');
        })
        ->when(request('email') != '', function ($q) {
            return $q->where('email', 'LIKE', '%' . request('email') . '%');
        })
        ->paginate(50)
        ->onEachSide(5)
        ->appends(request()->except('page'));

        // Redirect if only one customer is found and order_id is present
        if ($this->customers->count() == 1 && request('order_id') != '') {
            return redirect()->to(url('edit-customer') . '/' . $this->customers->first()->id);
        }
    }
    public function render()
    {

        $data['title_page'] = "Customers";
        session()->put('page_title', $data['title_page']);
        $data['customers'] = $this->customers;
        // foreach($data['customers'] as $customer){
        //     if($customer->orders->count() == 0){
        //         $customer->delete();
        //         $customer->forceDelete();
        //     }
        // }
        return view('livewire.customer')->with($data);
    }

    public function profile($id)
    {
        if(str_contains(url()->previous(),url('customer')) && !str_contains(url()->previous(),'profile')){
            session()->put('previous', url()->previous());
        }
        $data['title_page'] = "Customer Profile";
        session()->put('page_title', $data['title_page']);
        $customer = Customer_model::find($id);
        $orders = Order_model::with(['order_items.variation', 'order_items.variation.grade_id', 'order_items.stock'])
        ->withCount('order_items')->withSum('order_items','price')
        ->when(request('start_date') != '', function ($q) {
            return $q->whereDate('created_at', '>=', request('start_date'));
        })
        ->when(request('end_date') != '', function ($q) {
            return $q->whereDate('created_at', '<=', request('end_date') . ' 23:59:59');
        })
        ->where('orders.customer_id',$id)
        ->orderBy('orders.created_at', 'desc')
        ->get();

        $repairs = Process_model::where('process_type_id', 9)
        ->when(request('start_date') != '', function ($q) {
            return $q->whereDate('created_at', '>=', request('start_date'));
        })
        ->when(request('end_date') != '', function ($q) {
            return $q->whereDate('created_at', '<=', request('end_date') . ' 23:59:59');
        })
        ->where('customer_id', $id)
        ->orderBy('id', 'desc')
        ->get();

        $data['customer'] = $customer;
        $data['orders'] = $orders;
        $data['repairs'] = $repairs;

        $total_purchase = $orders->where('order_type_id', 1)->sum('order_items_sum_price');
        $total_rma = $orders->where('order_type_id', 2)->sum('order_items_sum_price');
        $total_ws = $orders->where('order_type_id', 5)->sum('order_items_sum_price');
        $total_ws_return = $orders->where('order_type_id', 6)->sum('order_items_sum_price');

        $total_purchase_items = $orders->where('order_type_id', 1)->sum('order_items_count');
        $total_rma_items = $orders->where('order_type_id', 2)->sum('order_items_count');
        $total_ws_items = $orders->where('order_type_id', 5)->sum('order_items_count');
        $total_ws_return_items = $orders->where('order_type_id', 6)->sum('order_items_count');

        $total_purchase_orders = $orders->where('order_type_id', 1)->count();
        $total_rma_orders = $orders->where('order_type_id', 2)->count();
        $total_ws_orders = $orders->where('order_type_id', 5)->count();
        $total_ws_return_orders = $orders->where('order_type_id', 6)->count();

        if($total_purchase_orders > 0){
            $data['totals'][] = [
                'type' => 'Purchase',
                'total_price' => $total_purchase,
                'total_items' => $total_purchase_items,
                'total_orders' => $total_purchase_orders,
            ];
        }
        if($total_rma_orders > 0){
            $data['totals'][] = [
                'type' => 'RMA',
                'total_price' => $total_rma,
                'total_items' => $total_rma_items,
                'total_orders' => $total_rma_orders,
            ];
        }
        if($total_ws_orders > 0){
            $data['totals'][] = [
                'type' => 'Sale',
                'total_price' => $total_ws,
                'total_items' => $total_ws_items,
                'total_orders' => $total_ws_orders,
            ];
        }
        if($total_ws_return_orders > 0){
            $data['totals'][] = [
                'type' => 'Return',
                'total_price' => $total_ws_return,
                'total_items' => $total_ws_return_items,
                'total_orders' => $total_ws_return_orders,
            ];
        }
        $data['totals'][] = [
            'type' => 'Total',
            'total_price' => - $total_purchase + $total_rma + $total_ws - $total_ws_return,
            'total_items' => - $total_purchase_items + $total_rma_items + $total_ws_items - $total_ws_return_items,
            'total_orders' => $total_purchase_orders + $total_rma_orders + $total_ws_orders + $total_ws_return_orders,
        ];
        // $total_order_price = $orders->sum('order_items_sum_price');
        // $total_order_items = $orders->sum('order_items');

        // $data['total_order_price'] = $total_order_price;
        // $data['total_order_items'] = $total_order_items;

        if(session('user')->hasPermission('view_customer_repairs') && request('page') == 'sent_repair_summery'){


            $processes = $repairs->where('status',2);
            $process_ids = $processes->pluck('id');
            $all_stock_ids = Process_stock_model::whereIn('process_id',$process_ids)->where('status',1)->pluck('stock_id')->unique()->toArray();


            $product_storage_sort = Product_storage_sort_model::whereHas('stocks', function($q) use ($all_stock_ids){
                $q->whereIn('stock.id', $all_stock_ids)->where('stock.deleted_at',null);
            })->orderBy('product_id')->orderBy('storage')->get();

            $result = [];
            foreach($product_storage_sort as $pss){
                $product = $pss->product;
                $storage = $pss->storage_id->name ?? null;

                $stocks = $pss->stocks->whereIn('id',$all_stock_ids)->where('deleted_at',null);
                $stock_ids = $stocks->pluck('id');


                // $scanned_stock_ids = Process_stock_model::where('process_id',$process_id)->where('status',1)->whereIn('stock_id',$stock_ids)->pluck('stock_id');
                $stock_imeis = $stocks->whereIn('id',$stock_ids)->whereNotNull('imei')->pluck('imei');
                $stock_serials = $stocks->whereIn('id',$stock_ids)->whereNotNull('serial_number')->pluck('serial_number');

                $purchase_items = Order_item_model::whereIn('stock_id', $stock_ids)->whereHas('order', function ($q) {
                    $q->where('order_type_id', 1);
                })->sum('price');

                $datas = [];
                $datas['pss_id'] = $pss->id;
                $datas['product_id'] = $pss->product_id;
                $datas['storage'] = $pss->storage;
                $datas['model'] = $product->model.' '.$storage;
                $datas['quantity'] = count($stock_ids);
                $datas['stock_ids'] = $stock_ids->toArray();
                $datas['stock_imeis'] = $stock_imeis->toArray() + $stock_serials->toArray();
                // $datas['average_cost'] = $purchase_items->avg('price');
                $datas['total_cost'] = $purchase_items;

                $result[] = $datas;
            }

            $data['sent_stock_summery'] = $result;



        }

        if(session('user')->hasPermission('view_customer_transactions') && request('page') == 'transactions'){

            $transactions = Account_transaction_model::where('customer_id',$id)->orderBy('id','desc')->get();
            $data['transactions'] = $transactions;




        }



        return view('livewire.customer-profile')->with($data);
    }

    public function add_customer()
    {

        $data['countries'] = Country_model::all();
        return view('livewire.add-customer')->with($data);
    }

    public function insert_customer()
    {

        // $parent_id = request()->input('parent');
        // $role_id = request()->input('role');
        // $username = request()->input('username');
        // $f_name = request()->input('fname');
        // $l_name = request()->input('lname');
        // $email = request()->input('email');
        // $password = request()->input('password');
        // if(Customer_model::where('username',$username)->first() != null){

        //     session()->put('error',"Username Already Exist");
        //     return redirect('customer');
        // }
        // if(Customer_model::where('email',$email)->first() != null){

        //     session()->put('error',"Email Already Exist");
        //     return redirect('customer');
        // }
        // $data = array(
        //     'parent_id' =>$parent_id,
        //     'role_id' =>$role_id,
        //     'username' =>$username,
        //     'first_name'=> $f_name,
        //     'last_name'=> $l_name,
        //     'email'=> $email,
        //     'password'=> Hash::make($password),
        // );
        Customer_model::insert(request('customer'));
        session()->put('success',"Customer has been added successfully");
        return redirect('customer');
    }

    public function edit_customer($id)
    {

        $data['countries'] = Country_model::all();
        $data['customer'] = Customer_model::where('id',$id)->first();

        $orders = Order_model::with(['order_items.variation', 'order_items.variation.grade_id', 'order_items.stock'])
        ->withCount('order_items')->withSum('order_items','price')
        ->where('orders.customer_id',$id)
        ->orderBy('orders.created_at', 'desc')
        ->get();
        $data['orders'] = $orders;
        // dd($orders);


        $data['repairs'] = Process_model::where('process_type_id', 9)
        ->where('customer_id', $id)
        ->orderBy('id', 'desc')
        ->get();

        return view('livewire.edit-customer')->with($data);
    }
    public function delete_customer($id){
        Customer_model::find($id)->delete();
        session()->put('success',"Customer has been deleted successfully");
        return redirect(url('customer'));
    }
    public function update_customer($id)
    {
        $data = request('customer');
        $data['is_vendor'] = $data['type'];
        Customer_model::where('id',$id)->update($data);
        session()->put('success',"Customer has been updated successfully");
        return redirect()->back();
    }
}
