@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Items to Repair' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/parts-inventory/dashboard') }}">Parts Inventory</a></li>
                <li class="breadcrumb-item active" aria-current="page">Items to Repair</li>
            </ol>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            {{ session('info') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="alert alert-info mb-3">
                <strong>Repair records:</strong> Only items submitted via <a href="{{ route('v2.parts-inventory.repair') }}">Submit repair</a> (from Internal Repair → Repair). Each row is one repair record linked to an internal repair line (stock). Use <strong>Repair status</strong> on <a href="{{ route('internal_repair') }}">Internal Repair</a> to see status for a line.
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="mb-3">Filter</h6>
                    <form method="GET" action="{{ route('v2.parts-inventory.items-to-repair') }}" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All</option>
                                <option value="assigned" {{ request('status') === 'assigned' ? 'selected' : '' }}>Assigned</option>
                                <option value="repaired" {{ request('status') === 'repaired' ? 'selected' : '' }}>Repaired</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">IMEI / Serial</label>
                            <input type="text" name="imei" class="form-control" value="{{ request('imei') }}" placeholder="Search by IMEI or serial">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Reference ID</label>
                            <input type="text" name="reference_id" class="form-control" value="{{ request('reference_id') }}" placeholder="Reference">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Filter</button>
                            <a href="{{ route('v2.parts-inventory.items-to-repair') }}" class="btn btn-secondary">Reset</a>
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
                                    <th>IMEI / Serial</th>
                                    <th>Product</th>
                                    <th>Variation</th>
                                    <th>Grade</th>
                                    <th>Status</th>
                                    <th>Reference ID</th>
                                    <th>Repairer</th>
                                    <th>Part</th>
                                    <th>Batch</th>
                                    <th>Unit cost</th>
                                    <th>Order ref</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($assignments as $a)
                                    @php
                                        $s = $a->stock;
                                        $imei = $s ? (($s->imei ?? '') . ($s->serial_number ?? '')) : '–';
                                        $imeiForInternal = $s ? (trim($s->imei ?? '') !== '' ? $s->imei : ($s->serial_number ?? '')) : '';
                                    @endphp
                                    <tr>
                                        <td>
                                            @if ($s)
                                                <a href="{{ url('imei') }}?imei={{ urlencode($imei) }}" target="_blank">{{ $imei ?: '–' }}</a>
                                            @else
                                                –
                                            @endif
                                        </td>
                                        <td>{{ $s && $s->variation && $s->variation->product ? $s->variation->product->model : '–' }}</td>
                                        <td>{{ $s && $s->variation ? trim(($s->variation->storage ?? '') . ' ' . ($s->variation->color ?? '')) : '–' }}</td>
                                        <td>{{ $s && $s->variation ? ($gradeNames[$s->variation->grade ?? 0] ?? ('#' . ($s->variation->grade ?? '–'))) : '–' }}</td>
                                        <td>
                                            @if ($a->repaired_at)
                                                <span class="badge bg-secondary">Repaired</span>
                                                <br><small class="text-muted">{{ $a->repaired_at->format('Y-m-d H:i') }}</small>
                                            @else
                                                <span class="badge bg-success">Assigned</span>
                                            @endif
                                        </td>
                                        <td>{{ $a->reference_id ?? '–' }}</td>
                                        <td>{{ $a->customer ? ($a->customer->company ?: trim(($a->customer->first_name ?? '') . ' ' . ($a->customer->last_name ?? '')) ?: '–') : '–' }}</td>
                                        <td>{{ $a->repairPart ? $a->repairPart->name : '–' }}</td>
                                        <td>{{ $a->partBatch ? $a->partBatch->batch_number : '–' }}</td>
                                        <td>{{ $a->unit_cost !== null ? number_format($a->unit_cost, 2) : '–' }}</td>
                                        <td>{{ $s && $s->sale_order ? $s->sale_order->reference_id : '–' }}</td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions">
                                                    <i class="fe fe-more-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="{{ url('repair/internal') }}?imei={{ urlencode($imeiForInternal) }}"><i class="fe fe-external-link me-2"></i>Internal Repair (line)</a></li>
                                                    @if (!$a->repaired_at && $s)
                                                        <li>
                                                            <form method="POST" action="{{ route('v2.parts-inventory.items-to-repair.mark-repaired', $s->id) }}" class="d-inline" onsubmit="return confirm('Mark as repaired? Item will move to available (status 1).');">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item">Mark as repaired</button>
                                                            </form>
                                                        </li>
                                                    @endif
                                                    <li><hr class="dropdown-divider"></li>
                                                    @if ($s)
                                                        <li><a class="dropdown-item" href="{{ url('belfast_inventory') }}?grade[]={{ $s->variation->grade ?? 8 }}&status={{ $s->status }}" target="_blank">Belfast Inventory</a></li>
                                                        <li><a class="dropdown-item" href="{{ url('imei') }}?imei={{ urlencode($imei) }}" target="_blank">View IMEI</a></li>
                                                    @endif
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="12" class="text-center">No repair records. Submit a repair from <a href="{{ route('internal_repair') }}">Internal Repair</a> → Repair → Submit repair.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $assignments->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
