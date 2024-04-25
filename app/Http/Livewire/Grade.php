<?php

namespace App\Http\Livewire;

use App\Models\Country_model;
use Livewire\Component;
use App\Models\Grade_model;
use App\Models\Order_model;
use App\Models\Role_model;


class Grade extends Component
{
    public function render()
    {
        $data['grades'] = Grade_model::all();

        // foreach($data['grades'] as $grade){
        //     if($grade->orders->count() == 0){
        //         $grade->delete();
        //         $grade->forceDelete();
        //     }
        // }
        return view('livewire.grade')->with($data);
    }
    public function add_grade()
    {

        $data['countries'] = Country_model::all();
        return view('livewire.add-grade')->with($data);
    }

    public function insert_grade()
    {

        // $parent_id = request()->input('parent');
        // $role_id = request()->input('role');
        // $username = request()->input('username');
        // $f_name = request()->input('fname');
        // $l_name = request()->input('lname');
        // $email = request()->input('email');
        // $password = request()->input('password');
        // if(Grade_model::where('username',$username)->first() != null){

        //     session()->put('error',"Username Already Exist");
        //     return redirect('grade');
        // }
        // if(Grade_model::where('email',$email)->first() != null){

        //     session()->put('error',"Email Already Exist");
        //     return redirect('grade');
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
        Grade_model::insert(['name'=>request('name')]);
        session()->put('success',"Grade has been added successfully");
        return redirect('grade');
    }

    public function edit_grade($id)
    {

        $data['countries'] = Country_model::all();
        $data['grade'] = Grade_model::where('id',$id)->first();

        $orders = Order_model::join('order_items', 'orders.id', '=', 'order_items.order_id')
        ->join('variation', 'order_items.variation_id', '=', 'variation.id')
        ->join('products', 'variation.product_id', '=', 'products.id')
        ->with(['order_items.variation', 'order_items.variation.grade_id', 'order_items.stock'])
        ->where('orders.grade_id',$id)

        ->orderBy('orders.reference_id', 'desc')
        ->select('orders.*')->get();
        $data['orders'] = $orders;
        // dd($orders);

        return view('livewire.edit-grade')->with($data);
    }
    public function update_grade($id)
    {

        // $parent_id = request()->input('parent');
        // $role_id = request()->input('role');
        // $username = request()->input('username');
        // $f_name = request()->input('fname');
        // $l_name = request()->input('lname');
        // $email = request()->input('email');
        // $password = request()->input('password');

        // $data = array(
        //     'parent_id' =>$parent_id,
        //     'role_id' =>$role_id,
        //     'username' =>$username,
        //     'first_name'=> $f_name,
        //     'last_name'=> $l_name,
        //     'email'=> $email,
        //     'password'=> Hash::make($password),
        // );
        Grade_model::where('id',$id)->update(request('grade'));
        session()->put('success',"Grade has been updated successfully");
        return redirect('grade');
    }
    public function get_permissions($roleId)
    {
        $role = Role_model::findOrFail($roleId);
        $permissions = $role->permissions()->pluck('name')->toArray();
        return response()->json($permissions);
    }
    public function toggle_role_permission($roleId, $permissionId, $isChecked)
    {

        // Debugging: Print the value of $isChecked
        var_dump($isChecked);

        // Convert string values to boolean
        $lowercase = strtolower($isChecked);
        if ($lowercase === 'true') {
            $check = true;
        } else {
            $check = false;
        }
        // Create or delete role permission based on $isChecked value
        if ($check) {
            echo "Hello";
            echo Role_permission_model::create(['role_id' => $roleId, 'permission_id' => $permissionId]);
        } else {
            echo "Ho";
            echo Role_permission_model::where('role_id', $roleId)->where('permission_id', $permissionId)->delete();
        }

        // Return response
        return response()->json(['success' => true]);
    }
}
