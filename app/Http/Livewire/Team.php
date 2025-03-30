<?php

namespace App\Http\Livewire;

use App\Models\Admin_customer_model;
use Livewire\Component;
use App\Models\Admin_model;
use App\Models\Admin_permission_model;
use App\Models\Customer_model;
use App\Models\Role_model;
use App\Models\Permission_model;
use App\Models\Role_permission_model;
use Illuminate\Support\Facades\Hash;


class Team extends Component
{
    public function render()
    {

        $data['title_page'] = "Team";
        session()->put('page_title', $data['title_page']);
        $data['roles'] = Role_model::where('id','>=',session('user')->role_id)->get();
        $data['permissions'] = Permission_model::all();
        $data['admin_team'] = Admin_model::where('parent_id','>=',session('user_id'))->Paginate(50);
        return view('livewire.team')->with($data);
    }
    public function add_member()
    {

        $data['title_page'] = "Add Member";
        session()->put('page_title', $data['title_page']);
        $data['parents'] = Admin_model::where('id','>=',Admin_model::find(session('user_id'))->role_id)->get();
        $data['roles'] = Role_model::where('id','>=',Admin_model::find(session('user_id'))->role_id)->get();
        $data['parents'] = Admin_model::whereIn('role_id',$data['roles']->pluck('id')->toArray())->get();
        return view('livewire.add-team')->with($data);
    }

    public function insert_member()
    {

        $parent_id = request()->input('parent');
        $role_id = request()->input('role');
        $username = request()->input('username');
        $f_name = request()->input('fname');
        $l_name = request()->input('lname');
        $email = request()->input('email');
        $password = request()->input('password');
        if(Admin_model::where('username',$username)->first() != null){

            session()->put('error',"Username Already Exist");
            return redirect('team');
        }
        if(Admin_model::where('email',$email)->first() != null){

            session()->put('error',"Email Already Exist");
            return redirect('team');
        }
        $data = array(
            'parent_id' =>$parent_id,
            'role_id' =>$role_id,
            'username' =>$username,
            'first_name'=> $f_name,
            'last_name'=> $l_name,
            'email'=> $email,
            'password'=> Hash::make($password),
        );
        Admin_model::insert($data);
        session()->put('success',"Member has been added successfully");
        return redirect('team');
    }

    public function update_status($id)
    {
        if (session('user')->hasPermission('change_member_status')){

            $member = Admin_model::where('id',$id)->first();
            $status = $member->status;
            if($status == 1){
                Admin_model::where('id',$id)->update(['status'=> 0]);
                session()->put('success',"Member has been Activated successfully");
                return redirect('team');
            }else{
                Admin_model::where('id',$id)->update(['status'=> 1]);
                session()->put('success',"Member has been Deactivated successfully");
                return redirect('team');
            }
        }else{
            session()->put('error',"Permission Denied");
            return redirect('team');
        }
    }
    public function edit_member($id)
    {

        $data['title_page'] = "Edit Member";
        session()->put('page_title', $data['title_page']);
        $data['user'] = session('user');
        $data['roles'] = Role_model::where('id','>=',$data['user']->role_id)->get();
        $data['parents'] = Admin_model::where('role_id','>=',$data['user']->role_id)->get();
        $data['permissions'] = Permission_model::all();
        $data['member'] = Admin_model::where('id',$id)->first();
        $data['customer_restrictions'] = Admin_customer_model::where('admin_id',$id)->pluck('customer_id')->toArray();
        $data['vendors'] = Customer_model::whereNotNull('is_vendor')->get();
        return view('livewire.edit-team')->with($data);
    }
    public function update_member($id)
    {

        $parent_id = request()->input('parent');
        $role_id = request()->input('role');
        $username = request()->input('username');
        $f_name = request()->input('fname');
        $l_name = request()->input('lname');
        $email = request()->input('email');
        $password = request()->input('password');
        $customer_restrictions = request()->input('customer_restriction');

        if(Admin_model::where('username',$username)->where('id','!=',$id)->first() != null){

            session()->put('error',"Username Already Exist");
            return redirect('team');
        }
        if(Admin_model::where('email',$email)->where('id','!=',$id)->first() != null){

            session()->put('error',"Email Already Exist");
            return redirect('team');
        }
        $data = array(
            'parent_id' =>$parent_id,
            'role_id' =>$role_id,
            'username' =>$username,
            'first_name'=> $f_name,
            'last_name'=> $l_name,
            'email'=> $email,
        );
        if($password != null){
            $data['password'] = Hash::make($password);
        }
        Admin_model::where('id',$id)->update($data);
        if($customer_restrictions != null){
            foreach($customer_restrictions as $customer_id){
                $admin_customer = Admin_customer_model::where('admin_id',$id)->where('customer_id',$customer_id)->first();
                if($admin_customer == null){
                    Admin_customer_model::insert(['admin_id'=>$id,'customer_id'=>$customer_id,'added_by'=>session('user_id')]);
                }
            }
            $remaining_customers = Admin_customer_model::where('admin_id',$id)->whereNotIn('customer_id',$customer_restrictions)->delete();
        }else{
            Admin_customer_model::where('admin_id',$id)->delete();
        }


        session()->put('success',"Member has been updated successfully");
        return redirect('team');
    }
    public function get_permissions($roleId)
    {
        $role = Role_model::findOrFail($roleId);
        $admin_id = session('user_id');
        $admin = Admin_model::findOrFail($admin_id);
        $permissions = $role->permissions()->pluck('name')->toArray();
        $admin_permissions = $admin->permissions()->pluck('name')->toArray();

        return response()->json([
            'permissions' => $permissions,
            'admin_permissions' => $admin_permissions,
        ]);
    }
    public function toggle_role_permission($roleId, $permissionId, $isChecked)
    {

        if (session('user')->hasPermission('change_role_permission')){

            // Debugging: Print the value of $isChecked
            var_dump($isChecked);

            // Convert string values to boolean
            $lowercase = strtolower($isChecked);
            if ($lowercase === 'true') {
                $check = true;
            } else {
                $check = false;
            }
            $permission = Permission_model::findOrFail($permissionId);

            if ($permission && session('user')->hasPermission($permission->name)) {


                // Create or delete role permission based on $isChecked value
                if ($check) {
                    echo "Hello";
                    echo Role_permission_model::create(['role_id' => $roleId, 'permission_id' => $permissionId]);
                } else {
                    echo "Ho";
                    echo Role_permission_model::where('role_id', $roleId)->where('permission_id', $permissionId)->delete();
                }
            }else{
                // Return response
                return response()->json(['error' => 'Permission Denied']);
            }
        }else{

            return response()->json(['error' => 'Permission Denied']);
        }

            // Return response
            return response()->json(['success' => true]);
    }
    public function toggle_user_permission($permissionId, $isChecked)
    {

        if (session('user')->hasPermission('change_permission')){

            $admin_id = session('user_id');
            // Debugging: Print the value of $isChecked
            var_dump($isChecked);

            // Convert string values to boolean
            $lowercase = strtolower($isChecked);
            if ($lowercase === 'true') {
                $check = true;
            } else {
                $check = false;
            }
            $permission = Permission_model::findOrFail($permissionId);

            if ($permission && session('user')->hasPermission($permission->name)) {


                // Create or delete role permission based on $isChecked value
                if ($check) {
                    echo "Hello";
                    Admin_permission_model::create(['admin_id' => $admin_id, 'permission_id' => $permissionId]);
                } else {
                    echo "Ho";
                    Admin_permission_model::where('admin_id', $admin_id)->where('permission_id', $permissionId)->delete();
                }
            }else{
                // Return response
                return response()->json(['error' => 'Permission Denied']);
            }
        }else{

            return response()->json(['error' => 'Permission Denied']);
        }

            // Return response
            return response()->json(['success' => 'sad']);
    }
}
