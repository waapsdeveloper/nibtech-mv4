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
            'request_type' => 'nullable|in:delegate,temporary,permanent',
            'note' => 'nullable|string',
            'expires_at' => 'nullable|date',
            'delegate_on_behalf' => 'nullable|boolean',
            'action_url' => 'nullable|string',
            'action_method' => 'nullable|string',
            'action_payload' => 'nullable|string',
        ]);

        $adminId = session('user_id');
        if (! $adminId) {
            return redirect('signin');
        }

        $permission = $request->input('permission');
        $requestType = $request->input('request_type', 'delegate');
        $expiresAt = $request->input('expires_at');

        $existing = PermissionRequest::where('admin_id', $adminId)
            ->where('permission', $permission)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return back()->with('success', 'Permission request is already pending for this action.');
        }

        $note = $request->input('note');
        $delegateRequested = $requestType === 'delegate' || $request->boolean('delegate_on_behalf');
        if ($delegateRequested) {
            $note = trim(($note ? $note.' ' : '').'(Requested admin to perform this action on their behalf.)');
        }

        $actionUrl = $request->input('action_url');
        $actionMethod = $request->input('action_method');
        $actionPayload = $request->input('action_payload');

        PermissionRequest::create([
            'admin_id' => $adminId,
            'permission' => $permission,
            'status' => 'pending',
            'request_type' => $requestType,
            'expires_at' => $requestType === 'temporary' ? $expiresAt : null,
            'note' => $note,
            'action_url' => $actionUrl,
            'action_method' => $actionMethod,
            'action_payload' => $actionPayload,
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

        $replayed = false;
        $replayError = null;

        $permissionRequest->status = 'approved';
        $permissionRequest->approved_by = $admin->id;
        $permissionRequest->save();

        if ($permissionRequest->request_type === 'delegate' && $permissionRequest->action_url) {
            $payload = [];
            if ($permissionRequest->action_payload) {
                $decoded = json_decode($permissionRequest->action_payload, true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            // Impersonate approver for replay
            $previousUser = session('user');
            $previousUserId = session('user_id');
            session(['user_id' => $admin->id, 'user' => $admin]);
            try {
                // Add CSRF token for POST/PUT/PATCH/DELETE to avoid 419 during replay
                $method = strtoupper($permissionRequest->action_method ?? 'GET');
                if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                    $payload['_token'] = csrf_token();
                }

                $replayRequest = Request::create($permissionRequest->action_url, $method, $payload);
                $replayRequest->setLaravelSession($request->session());
                if (isset($payload['_token'])) {
                    $replayRequest->headers->set('X-CSRF-TOKEN', $payload['_token']);
                }
                $replayRequest->server->set('REMOTE_ADDR', $request->ip());
                $response = app('router')->dispatch($replayRequest);
                $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 0;
                if ($status >= 400) {
                    $replayError = 'Replay returned status '.$status;
                } else {
                    $replayed = true;
                }
            } catch (\Throwable $e) {
                $replayError = $e->getMessage();
            } finally {
                session(['user_id' => $previousUserId, 'user' => $previousUser]);
            }
        }

        $message = 'Request approved. ';
        if ($permissionRequest->request_type === 'delegate') {
            $message .= $replayed ? 'Action replayed as admin.' : 'Please perform the requested action on their behalf.';
            if ($replayError) {
                $message .= ' Replay failed: '.$replayError;
            }
        } else {
            $message .= 'Please complete the task on their behalf (permission not granted).';
        }

        return back()->with('success', $message);
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
