<?php

namespace App\Http\Livewire;

use App\Models\Country_model;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Customer_model;
use App\Models\Order_model;
use App\Models\Process_model;

class B2BCustomer extends Component
{
    use WithPagination;

    public function mount()
    {
    }

    public function render()
    {

        $data['title_page'] = "Customers";
        session()->put('page_title', $data['title_page']);
        $customers = Customer_model::with('country_id')->withCount('orders')->whereNotNull('is_vendor')
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

        $data['customers'] = $customers;
        // foreach($data['customers'] as $customer){
        //     if($customer->orders->count() == 0){
        //         $customer->delete();
        //         $customer->forceDelete();
        //     }
        // }
        return view('livewire.b2b_customer')->with($data);
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
