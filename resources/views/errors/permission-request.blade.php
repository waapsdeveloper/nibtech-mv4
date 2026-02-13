@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Permission Required</div>
                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success" role="alert">{{ session('success') }}</div>
                    @endif
                    <p class="mb-3">You do not have permission to access this page.</p>
                    <p class="mb-4"><strong>Permission:</strong> {{ $permission }}</p>

                    @if ($alreadyRequested)
                        <div class="alert alert-info" role="alert">A request for this permission is already pending approval.</div>
                    @else
                        <form method="POST" action="{{ route('permission_requests.store') }}">
                            @csrf
                            <input type="hidden" name="permission" value="{{ $permission }}">

                            <div class="mb-3">
                                <label class="form-label">Request type</label>
                                <div class="d-flex gap-3">
                                    <label class="form-check">
                                        <input class="form-check-input" type="radio" name="request_type" value="temporary">
                                        <span class="form-check-label">Temporary</span>
                                    </label>
                                    <label class="form-check">
                                        <input class="form-check-input" type="radio" name="request_type" value="permanent" checked>
                                        <span class="form-check-label">Permanent</span>
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="expires_at" class="form-label">Expires at (temporary only)</label>
                                <input type="datetime-local" class="form-control" id="expires_at" name="expires_at">
                            </div>

                            <div class="mb-3">
                                <label for="note" class="form-label">Message to admin (optional)</label>
                                <textarea class="form-control" id="note" name="note" rows="3"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">Request Permission</button>
                            <a href="{{ url('/') }}" class="btn btn-link">Back to dashboard</a>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
