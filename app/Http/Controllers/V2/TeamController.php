<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Admin_customer_model;
use App\Models\Admin_model;
use App\Models\Admin_permission_model;
use App\Models\Customer_model;
use App\Models\Role_model;
use App\Models\Permission_model;
use App\Models\Role_permission_model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TeamController extends Controller
{
    /**
     * Display the team listing page
     */
    public function index()
    {
        $data['title_page'] = "Team";
        session()->put('page_title', $data['title_page']);
        $data['roles'] = Role_model::where('id', '>=', session('user')->role_id)->get();
        $data['permissions'] = Permission_model::all();
        $data['admin_team'] = Admin_model::with('role')->where('parent_id', '>=', session('user_id'))->paginate(50);
        
        return view('v2.options.teams.index')->with($data);
    }

    /**
     * Show the form for adding a new member
     */
    public function add_member()
    {
        $data['title_page'] = "Add Member";
        session()->put('page_title', $data['title_page']);
        $data['parents'] = Admin_model::where('id', '>=', Admin_model::find(session('user_id'))->role_id)->get();
        $data['roles'] = Role_model::where('id', '>=', Admin_model::find(session('user_id'))->role_id)->get();
        $data['parents'] = Admin_model::whereIn('role_id', $data['roles']->pluck('id')->toArray())->get();
        
        return view('v2.options.teams.add')->with($data);
    }

    /**
     * Store a newly created member
     */
    public function insert_member(Request $request)
    {
        $parent_id = $request->input('parent');
        $role_id = $request->input('role');
        $username = $request->input('username');
        $f_name = $request->input('fname');
        $l_name = $request->input('lname');
        $email = $request->input('email');
        $password = $request->input('password');

        if (Admin_model::where('username', $username)->first() != null) {
            session()->put('error', "Username Already Exist");
            return redirect('v2/options/teams');
        }

        if (Admin_model::where('email', $email)->first() != null) {
            session()->put('error', "Email Already Exist");
            return redirect('v2/options/teams');
        }

        $data = array(
            'parent_id' => $parent_id,
            'role_id' => $role_id,
            'username' => $username,
            'first_name' => $f_name,
            'last_name' => $l_name,
            'email' => $email,
            'password' => Hash::make($password),
        );

        Admin_model::insert($data);
        session()->put('success', "Member has been added successfully");
        return redirect('v2/options/teams');
    }

    /**
     * Update member status (activate/deactivate)
     */
    public function update_status($id)
    {
        if (session('user')->hasPermission('change_member_status')) {
            $member = Admin_model::where('id', $id)->first();
            $status = $member->status;
            
            if ($status == 1) {
                Admin_model::where('id', $id)->update(['status' => 0]);
                session()->put('success', "Member has been Activated successfully");
                return redirect('v2/options/teams');
            } else {
                Admin_model::where('id', $id)->update(['status' => 1]);
                session()->put('success', "Member has been Deactivated successfully");
                return redirect('v2/options/teams');
            }
        } else {
            session()->put('error', "Permission Denied");
            return redirect('v2/options/teams');
        }
    }

    /**
     * Show the form for editing a member
     */
    public function edit_member($id)
    {
        $data['title_page'] = "Edit Member";
        session()->put('page_title', $data['title_page']);
        $data['user'] = session('user');
        $data['roles'] = Role_model::where('id', '>=', $data['user']->role_id)->get();
        $data['parents'] = Admin_model::where('role_id', '>=', $data['user']->role_id)->get();
        $data['permissions'] = Permission_model::all();
        $data['member'] = Admin_model::where('id', $id)->first();
        $data['customer_restrictions'] = Admin_customer_model::where('admin_id', $id)->pluck('customer_id')->toArray();
        $data['vendors'] = Customer_model::whereNotNull('is_vendor')->get();
        
        return view('v2.options.teams.edit')->with($data);
    }

    /**
     * Update the specified member
     */
    public function update_member($id, Request $request)
    {
        $parent_id = $request->input('parent');
        $role_id = $request->input('role');
        $username = $request->input('username');
        $f_name = $request->input('fname');
        $l_name = $request->input('lname');
        $email = $request->input('email');
        $password = $request->input('password');
        $customer_restrictions = $request->input('customer_restriction');

        if (Admin_model::where('username', $username)->where('id', '!=', $id)->first() != null) {
            session()->put('error', "Username Already Exist");
            return redirect('v2/options/teams');
        }

        if (Admin_model::where('email', $email)->where('id', '!=', $id)->first() != null) {
            session()->put('error', "Email Already Exist");
            return redirect('v2/options/teams');
        }

        $data = array(
            'parent_id' => $parent_id,
            'role_id' => $role_id,
            'username' => $username,
            'first_name' => $f_name,
            'last_name' => $l_name,
            'email' => $email,
        );

        if ($password != null) {
            $data['password'] = Hash::make($password);
        }

        Admin_model::where('id', $id)->update($data);

        if ($customer_restrictions != null) {
            foreach ($customer_restrictions as $customer_id) {
                $admin_customer = Admin_customer_model::where('admin_id', $id)->where('customer_id', $customer_id)->first();
                if ($admin_customer == null) {
                    Admin_customer_model::insert(['admin_id' => $id, 'customer_id' => $customer_id, 'added_by' => session('user_id')]);
                }
            }
            $remaining_customers = Admin_customer_model::where('admin_id', $id)->whereNotIn('customer_id', $customer_restrictions)->delete();
        } else {
            Admin_customer_model::where('admin_id', $id)->delete();
        }

        session()->put('success', "Member has been updated successfully");
        return redirect('v2/options/teams');
    }

    /**
     * Get permissions for a role (AJAX)
     */
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

    /**
     * Toggle role permission (AJAX)
     */
    public function toggle_role_permission($roleId, $permissionId, $isChecked)
    {
        if (session('user')->hasPermission('change_role_permission')) {
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
                    Role_permission_model::create(['role_id' => $roleId, 'permission_id' => $permissionId]);
                } else {
                    Role_permission_model::where('role_id', $roleId)->where('permission_id', $permissionId)->delete();
                }
            } else {
                return response()->json(['error' => 'Permission Denied']);
            }
        } else {
            return response()->json(['error' => 'Permission Denied']);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get user permissions (AJAX)
     * Returns both role permissions and direct user permissions
     */
    public function get_user_permissions($userId)
    {
        $user = Admin_model::findOrFail($userId);
        $admin_id = session('user_id');
        $currentAdmin = Admin_model::findOrFail($admin_id);
        
        // Get role permissions
        $rolePermissions = $user->role->permissions()->pluck('name')->toArray();
        
        // Get direct user permissions
        $userPermissions = $user->permissions()->pluck('name')->toArray();
        
        // Get current admin permissions (for checking if they can assign)
        $currentAdminPermissions = $currentAdmin->permissions()->pluck('name')->toArray();
        $currentAdminRolePermissions = $currentAdmin->role->permissions()->pluck('name')->toArray();
        $allCurrentAdminPermissions = array_unique(array_merge($currentAdminPermissions, $currentAdminRolePermissions));

        return response()->json([
            'role_permissions' => $rolePermissions,
            'user_permissions' => $userPermissions,
            'current_admin_permissions' => $allCurrentAdminPermissions,
        ]);
    }

    /**
     * Toggle user permission for a specific user (AJAX)
     */
    public function toggle_user_permission($userId, $permissionId, $isChecked)
    {
        // Only super admin or users with change_permission can do this
        if (!session('user')->hasPermission('change_permission') && session('user_id') != 1) {
            return response()->json(['error' => 'Permission Denied'], 403);
        }

        // Convert string values to boolean
        $lowercase = strtolower($isChecked);
        if ($lowercase === 'true') {
            $check = true;
        } else {
            $check = false;
        }

        $permission = Permission_model::findOrFail($permissionId);
        $user = Admin_model::findOrFail($userId);

        // Check if current admin has this permission (they can only assign permissions they have)
        if ($permission && !session('user')->hasPermission($permission->name) && session('user_id') != 1) {
            return response()->json(['error' => 'You can only assign permissions that you have'], 403);
        }

        // Create or delete user permission based on $isChecked value
        if ($check) {
            // Check if already exists
            $existing = Admin_permission_model::where('admin_id', $userId)
                ->where('permission_id', $permissionId)
                ->first();
            
            if (!$existing) {
                Admin_permission_model::create(['admin_id' => $userId, 'permission_id' => $permissionId]);
            }
        } else {
            Admin_permission_model::where('admin_id', $userId)
                ->where('permission_id', $permissionId)
                ->delete();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Toggle allow_unknown_ip permission for a specific user (AJAX)
     * This allows the user to access from any IP address
     */
    public function toggle_allow_unknown_ip($userId, Request $request)
    {
        // Only super admin or users with change_permission can do this
        if (!session('user')->hasPermission('change_permission') && session('user_id') != 1) {
            return response()->json(['error' => 'Permission Denied'], 403);
        }

        $user = Admin_model::findOrFail($userId);
        
        // Get or create the allow_unknown_ip permission
        $permission = Permission_model::firstOrNew(['name' => 'allow_unknown_ip']);
        if ($permission->id == null) {
            $permission->save();
        }

        $isChecked = filter_var($request->input('is_checked', false), FILTER_VALIDATE_BOOLEAN);

        // Check if user already has this permission
        $existingPermission = Admin_permission_model::where('admin_id', $userId)
            ->where('permission_id', $permission->id)
            ->first();

        if ($isChecked) {
            // Add permission if not exists
            if (!$existingPermission) {
                Admin_permission_model::create([
                    'admin_id' => $userId,
                    'permission_id' => $permission->id
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Dynamic IP access enabled for ' . $user->first_name . ' ' . $user->last_name
                ]);
            }
        } else {
            // Remove permission if exists
            if ($existingPermission) {
                $existingPermission->delete();
                return response()->json([
                    'success' => true,
                    'message' => 'Dynamic IP access disabled for ' . $user->first_name . ' ' . $user->last_name
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'No change needed'
        ]);
    }

    /**
     * Check if a user has allow_unknown_ip permission (AJAX)
     */
    public function check_allow_unknown_ip($userId)
    {
        $user = Admin_model::findOrFail($userId);
        
        // Get the permission
        $permission = Permission_model::where('name', 'allow_unknown_ip')->first();
        
        if (!$permission) {
            return response()->json(['has_permission' => false]);
        }

        // Check if user has this permission directly
        $hasPermission = Admin_permission_model::where('admin_id', $userId)
            ->where('permission_id', $permission->id)
            ->exists();

        // Also check if user has it through role
        $hasRolePermission = $user->role->permissions->contains('name', 'allow_unknown_ip');

        return response()->json([
            'has_permission' => $hasPermission || $hasRolePermission
        ]);
    }
}

