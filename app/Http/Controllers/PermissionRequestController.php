<?php

namespace App\Http\Controllers;

use App\Models\PermissionRequest;
use App\Models\Admin_permission_model;
use App\Models\Permission_model;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PermissionRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'permission' => 'required|string',
            'request_type' => 'nullable|in:temporary,permanent',
            'note' => 'nullable|string',
            'expires_at' => 'nullable|date',
        ]);

        $adminId = session('user_id');
        if (! $adminId) {
            return redirect('signin');
        }

        $permission = $request->input('permission');
        $requestType = $request->input('request_type', 'permanent');
        $expiresAt = $request->input('expires_at');

        $existing = PermissionRequest::where('admin_id', $adminId)
            ->where('permission', $permission)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return back()->with('success', 'Permission request is already pending for this action.');
        }

        PermissionRequest::create([
            'admin_id' => $adminId,
            'permission' => $permission,
            'status' => 'pending',
            'request_type' => $requestType,
            'expires_at' => $requestType === 'temporary' ? $expiresAt : null,
            'note' => $request->input('note'),
        ]);

        return back()->with('success', 'Request submitted to admin.');
    }

    public function approve(Request $request, PermissionRequest $permissionRequest): RedirectResponse
    {
        $admin = session('user');
        if (! $admin || $admin->role_id != 2) {
            abort(403, 'Unauthorized');
        }

        if ($permissionRequest->status !== 'pending') {
            return back()->with('success', 'Request already processed.');
        }

        $permissionName = $permissionRequest->permission;
        $expiresAt = $request->input('expires_at');

        $permission = Permission_model::firstOrCreate(['name' => $permissionName]);
        Admin_permission_model::firstOrCreate([
            'admin_id' => $permissionRequest->admin_id,
            'permission_id' => $permission->id,
        ]);

        $permissionRequest->status = 'approved';
        $permissionRequest->approved_by = $admin->id;
        if ($permissionRequest->request_type === 'temporary') {
            $permissionRequest->expires_at = $expiresAt ?: $permissionRequest->expires_at;
        }
        $permissionRequest->save();

        return back()->with('success', 'Permission approved and granted.');
    }

    public function deny(PermissionRequest $permissionRequest): RedirectResponse
    {
        $admin = session('user');
        if (! $admin || $admin->role_id != 2) {
            abort(403, 'Unauthorized');
        }

        if ($permissionRequest->status !== 'pending') {
            return back()->with('success', 'Request already processed.');
        }

        $permissionRequest->status = 'denied';
        $permissionRequest->approved_by = $admin->id;
        $permissionRequest->save();

        return back()->with('success', 'Permission request denied.');
    }
}
