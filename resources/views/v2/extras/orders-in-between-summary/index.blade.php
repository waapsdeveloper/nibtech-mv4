@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $data['title_page'] ?? 'Orders In Between Summary' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/extras/orders-in-between-summary') }}">Extras</a></li>
                <li class="breadcrumb-item active" aria-current="page">Orders In Between Summary</li>
            </ol>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Last run summary</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">One row updated each time <code>listed-stock:orders-in-between</code> runs (scheduler or manual).</p>
                    <table class="table table-bordered mb-0">
                        <tr>
                            <th width="180">Last run at</th>
                            <td>{{ $summary->last_run_at ? $summary->last_run_at->format('Y-m-d H:i:s') : '—' }}</td>
                        </tr>
                        <tr>
                            <th>Total updated</th>
                            <td>{{ $summary->total_updated }}</td>
                        </tr>
                        <tr>
                            <th>Days back</th>
                            <td>{{ $summary->days_back === null ? 'All' : $summary->days_back }}</td>
                        </tr>
                        <tr>
                            <th>Backfill only</th>
                            <td>{{ $summary->backfill_only ? 'Yes' : 'No' }}</td>
                        </tr>
                        <tr>
                            <th>Updated at</th>
                            <td>{{ $summary->updated_at ? $summary->updated_at->format('Y-m-d H:i:s') : '—' }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Edit summary</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('v2.extras.orders-in-between-summary.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label for="last_run_at" class="form-label">Last run at</label>
                            <input type="datetime-local" class="form-control" id="last_run_at" name="last_run_at"
                                   value="{{ $summary->last_run_at ? $summary->last_run_at->format('Y-m-d\TH:i') : '' }}" step="1">
                            <small class="text-muted">Leave empty to keep as is.</small>
                        </div>
                        <div class="mb-3">
                            <label for="total_updated" class="form-label">Total updated</label>
                            <input type="number" class="form-control" id="total_updated" name="total_updated" min="0" value="{{ old('total_updated', $summary->total_updated) }}" required>
                        </div>
                        <div class="mb-3">
                            <label for="days_back" class="form-label">Days back</label>
                            <input type="number" class="form-control" id="days_back" name="days_back" min="0" value="{{ old('days_back', $summary->days_back) }}" placeholder="e.g. 30 or empty for all">
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="hidden" name="backfill_only" value="0">
                                <input type="checkbox" class="form-check-input" id="backfill_only" name="backfill_only" value="1" {{ old('backfill_only', $summary->backfill_only) ? 'checked' : '' }}>
                                <label class="form-check-label" for="backfill_only">Backfill only</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Update summary</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
