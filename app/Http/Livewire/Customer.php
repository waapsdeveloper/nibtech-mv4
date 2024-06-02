<?php

namespace App\Http\Livewire;

use App\Models\Country_model;
use Livewire\Component;
use App\Models\Customer_model;
use App\Models\Order_model;
use App\Models\Role_model;
use App\Models\Permission_model;
use App\Models\Role_permission_model;
use Illuminate\Support\Facades\Hash;


class Customer extends Component
{
    public function render()
    {

        $data['title_page'] = "Customers";
        $data['customers'] = Customer_model::
        when(request('type') && request('type') != 0, function($q){
            if(request('type') == 4){
                return $q->where('is_vendor',null);
            }else{
                return $q->where('is_vendor',request('type'));
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
        ->paginate(50);

        // foreach($data['customers'] as $customer){
        //     if($customer->orders->count() == 0){
        //         $customer->delete();
        //         $customer->forceDelete();
        //     }
        // }
        return view('livewire.customer')->with($data);
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

        $orders = Order_model::join('order_items', 'orders.id', '=', 'order_items.order_id')
        ->join('variation', 'order_items.variation_id', '=', 'variation.id')
        ->join('products', 'variation.product_id', '=', 'products.id')
        ->with(['order_items.variation', 'order_items.variation.grade_id', 'order_items.stock'])
        ->where('orders.customer_id',$id)

        ->orderBy('orders.reference_id', 'desc')
        ->select('orders.*')->get();
        $data['orders'] = $orders;
        // dd($orders);

        return view('livewire.edit-customer')->with($data);
    }
    public function update_customer($id)
    {

        Customer_model::where('id',$id)->update(request('customer'));
        session()->put('success',"Customer has been updated successfully");
        return redirect()->back();
    }
}
