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

        return back()->with('success', 'Request submitted to admin. An authorized admin will complete this action for you.');
    }

    public function approve(Request $request, PermissionRequest $permissionRequest): RedirectResponse
    {
        $admin = session('user');
            // Allow role_id 2 and higher-privileged (numerically lower) roles to approve
            if (! $admin || $admin->role_id > 2) {
            abort(403, 'Unauthorized');
        }

        if ($permissionRequest->status !== 'pending') {
            return back()->with('success', 'Request already processed.');
        }

        $permissionRequest->status = 'approved';
        $permissionRequest->approved_by = $admin->id;
        $permissionRequest->save();

        return back()->with('success', 'Request approved. Please complete the task on their behalf (permission not granted).');
    }

    public function deny(PermissionRequest $permissionRequest): RedirectResponse
    {
        $admin = session('user');
        // Allow role_id 2 and higher-privileged (numerically lower) roles to deny
        if (! $admin || $admin->role_id > 2) {
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
