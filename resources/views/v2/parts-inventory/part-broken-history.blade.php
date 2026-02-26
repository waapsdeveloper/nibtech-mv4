@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Broken parts history' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/parts-inventory/dashboard') }}">Parts Inventory</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ route('v2.parts-inventory.inventory') }}">Batches</a></li>
                <li class="breadcrumb-item active" aria-current="page">Broken parts history</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <p class="mb-0"><strong>Part:</strong> {{ $part->name }} @if($part->sku)<span class="text-muted">({{ $part->sku }})</span>@endif</p>
                        <p class="mb-0 mt-1">
                            <a href="{{ route('v2.parts-inventory.inventory') }}" class="btn btn-sm btn-outline-secondary">← Back to Batches</a>
                        </p>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="mb-3">Filters</h6>
                    <form method="GET" action="{{ route('v2.parts-inventory.part-broken.history', $part->id) }}" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Date from</label>
                            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date to</label>
                            <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Filter</button>
                            <a href="{{ route('v2.parts-inventory.part-broken.history', $part->id) }}" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Quantity</th>
                                    <th>Batch</th>
                                    <th>Responsible person</th>
                                    <th>Notes</th>
                                    <th>Recorded by</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($records as $r)
                                    <tr>
                                        <td>{{ $r->created_at->format('Y-m-d H:i') }}</td>
                                        <td>{{ $r->quantity }}</td>
                                        <td>@if($r->partBatch)<code>{{ $r->partBatch->batch_number }}</code>@else<span class="text-muted">–</span>@endif</td>
                                        <td>{{ $r->responsible_person ?? '–' }}</td>
                                        <td>{{ $r->notes ? Str::limit($r->notes, 80) : '–' }}</td>
                                        <td>{{ $r->admin ? trim(($r->admin->first_name ?? '') . ' ' . ($r->admin->last_name ?? '')) : '–' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No broken parts recorded yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $records->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
