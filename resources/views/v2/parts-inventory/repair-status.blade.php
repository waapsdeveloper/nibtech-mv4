@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Repair status' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ route('internal_repair') }}">Internal Repair</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/parts-inventory/dashboard') }}">Parts Inventory</a></li>
                <li class="breadcrumb-item active" aria-current="page">Repair status</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <strong>IMEI/Serial:</strong> {{ $imei ?: '–' }}
                    @if ($stock && $stock->variation)
                        &nbsp;|&nbsp; {{ $stock->variation->product->model ?? '–' }} – {{ trim(($stock->variation->storage ?? '') . ' ' . ($stock->variation->color ?? '')) }}
                    @endif
                </div>
                <div class="card-body">
                    @if ($assignments->isNotEmpty())
                        <p class="text-muted mb-3">Repair record(s) for this internal repair line:</p>
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Reference ID</th>
                                    <th>Repairer</th>
                                    <th>Part</th>
                                    <th>Batch</th>
                                    <th>Unit cost</th>
                                    <th>Assigned at</th>
                                    <th>Repaired at</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($assignments as $a)
                                    <tr>
                                        <td>
                                            @if ($a->repaired_at)
                                                <span class="badge bg-secondary">Repaired</span>
                                            @else
                                                <span class="badge bg-success">Assigned</span>
                                            @endif
                                        </td>
                                        <td>{{ $a->reference_id ?? '–' }}</td>
                                        <td>{{ $a->customer ? ($a->customer->company ?: trim(($a->customer->first_name ?? '') . ' ' . ($a->customer->last_name ?? '')) ?: '–') : '–' }}</td>
                                        <td>{{ $a->repairPart ? $a->repairPart->name : '–' }}</td>
                                        <td>{{ $a->partBatch ? $a->partBatch->batch_number : '–' }}</td>
                                        <td>{{ $a->unit_cost !== null ? number_format($a->unit_cost, 2) : '–' }}</td>
                                        <td>{{ $a->assigned_at ? $a->assigned_at->format('Y-m-d H:i') : '–' }}</td>
                                        <td>{{ $a->repaired_at ? $a->repaired_at->format('Y-m-d H:i') : '–' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-muted mb-3">No repair record for this line yet.</p>
                    @endif
                    <div class="mt-3">
                        <a href="{{ route('v2.parts-inventory.repair', ['imei' => $imei]) }}" class="btn btn-primary">{{ $assignments->isEmpty() ? 'Submit repair' : 'Submit another repair (same IMEI)' }}</a>
                        <a href="{{ route('v2.parts-inventory.items-to-repair') }}" class="btn btn-secondary">Items to repair list</a>
                        <a href="{{ route('internal_repair') }}?imei={{ urlencode($imei) }}" class="btn btn-outline-secondary">Back to Internal Repair</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
