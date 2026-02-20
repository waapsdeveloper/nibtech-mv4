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

        $rawPayload = $request->input('action_payload');
        $normalizedPayload = $rawPayload;
        if ($rawPayload) {
            $decodedPayload = json_decode($rawPayload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $decodedPayload = json_decode(html_entity_decode($rawPayload), true);
            }
            if (is_array($decodedPayload)) {
                // Store normalized JSON to avoid HTML entity issues later
                $normalizedPayload = json_encode($decodedPayload, JSON_UNESCAPED_SLASHES);
            }
        }

        PermissionRequest::create([
            'admin_id' => $adminId,
            'permission' => $permission,
            'status' => 'pending',
            'request_type' => $requestType,
            'expires_at' => $requestType === 'temporary' ? $expiresAt : null,
            'note' => $note,
            'action_url' => $actionUrl,
            'action_method' => $actionMethod,
            'action_payload' => $normalizedPayload,
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

        $permissionRequest->approved_by = $admin->id;

        if ($permissionRequest->request_type === 'delegate' && $permissionRequest->action_url) {
            $payload = [];
            if ($permissionRequest->action_payload) {
                $decoded = json_decode($permissionRequest->action_payload, true);
                if (! is_array($decoded)) {
                    $decoded = json_decode(html_entity_decode($permissionRequest->action_payload), true);
                }
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            // Normalize JSON string payloads into arrays if possible
            if (! is_array($payload) && is_string($payload)) {
                $decodedGeneric = json_decode($payload, true);
                if (is_array($decodedGeneric)) {
                    $payload = $decodedGeneric;
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
                // Ensure payload is in the request bag (some routes rely on request('update'))
                $replayRequest->request->replace($payload);
                if (isset($payload['update'])) {
                    $replayRequest->request->set('update', $payload['update']);
                }
                // Provide JSON body and header for downstream fallbacks
                $replayRequest->headers->set('X-Delegate-Payload', json_encode($payload));
                $replayRequest->setJson(new \Symfony\Component\HttpFoundation\ParameterBag($payload));

                $replayRequest->setLaravelSession($request->session());
                if (isset($payload['_token'])) {
                    $replayRequest->headers->set('X-CSRF-TOKEN', $payload['_token']);
                }
                $replayRequest->server->set('REMOTE_ADDR', $request->ip());
                $originalRequestInstance = app('request');
                app()->instance('request', $replayRequest);
                $response = app('router')->dispatch($replayRequest);
                app()->instance('request', $originalRequestInstance);
                $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 0;
                if ($status >= 400) {
                    $bodySnippet = method_exists($response, 'getContent') ? mb_substr($response->getContent(), 0, 500) : '';
                    $payloadSnippet = json_encode($payload);
                    $replayError = 'Replay returned status '.$status.
                        ($bodySnippet ? ' Body: '.preg_replace('/\s+/', ' ', $bodySnippet) : '') .
                        ($payloadSnippet ? ' Payload: '.$payloadSnippet : '');
                    Log::warning('Delegate replay failed', [
                        'request_id' => $permissionRequest->id,
                        'url' => $permissionRequest->action_url,
                        'method' => $method,
                        'status' => $status,
                        'body' => $bodySnippet,
                        'payload' => $payload,
                    ]);
                } else {
                    $replayed = true;
                }
            } catch (\Throwable $e) {
                $replayError = $e->getMessage();
                Log::error('Delegate replay exception', [
                    'request_id' => $permissionRequest->id,
                    'url' => $permissionRequest->action_url,
                    'method' => $method ?? null,
                    'payload' => $payload,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            } finally {
                session(['user_id' => $previousUserId, 'user' => $previousUser]);
            }
            if (! $replayed) {
                // Do not approve if the action could not be performed
                return back()->with('error', 'Replay failed: '.$replayError.'. Request remains pending.');
            }

            $permissionRequest->status = 'approved';
            $permissionRequest->save();

            $message = 'Request approved. Action replayed as admin.';
            return back()->with('success', $message);
        }

        // Non-delegate flows: just approve
        $permissionRequest->status = 'approved';
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
