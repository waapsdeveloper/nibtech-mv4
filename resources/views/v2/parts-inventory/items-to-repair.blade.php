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
                <strong>Items to repair:</strong> Stock in aftersale status (Repair / Hold) that may need parts. Same pool as the dashboard Aftersale Inventory. Use <strong>Record usage</strong> on <a href="{{ url('v2/parts-inventory/usage') }}">Usage History</a> when you use a part on one of these items.
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="mb-3">Filter</h6>
                    <form method="GET" action="{{ route('v2.parts-inventory.items-to-repair') }}" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Grade</label>
                            <select name="grade[]" class="form-select" multiple size="3">
                                @foreach($gradeNames as $id => $name)
                                    <option value="{{ $id }}" {{ in_array($id, (array) request('grade', [])) ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Leave empty for all (Repair, Hold, Other)</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">IMEI / Serial</label>
                            <input type="text" name="imei" class="form-control" value="{{ request('imei') }}" placeholder="Search by IMEI or serial">
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
                                    <th>Order ref</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($items as $s)
                                    <tr>
                                        <td>
                                            @php
                                                $imei = $s->imei ?? $s->serial_number ?? '–';
                                            @endphp
                                            <a href="{{ url('imei') }}?imei={{ urlencode($imei) }}" target="_blank">{{ $imei }}</a>
                                        </td>
                                        <td>{{ optional($s->variation)->product ? $s->variation->product->model : '–' }}</td>
                                        <td>{{ $s->variation ? trim(($s->variation->storage ?? '') . ' ' . ($s->variation->color ?? '')) : '–' }}</td>
                                        <td>{{ $gradeNames[optional($s->variation)->grade ?? 0] ?? ('#' . (optional($s->variation)->grade ?? '–')) }}</td>
                                        <td>
                                            @if (isset($assignmentsByStock[$s->id]))
                                                <span class="badge bg-success">Assigned</span>
                                                @if ($assignmentsByStock[$s->id]->repairPart ?? null)
                                                    <br><small class="text-muted">{{ $assignmentsByStock[$s->id]->repairPart->name }}</small>
                                                @endif
                                            @else
                                                <span class="text-muted">–</span>
                                            @endif
                                        </td>
                                        <td>{{ optional($s->sale_order)->reference_id ?? '–' }}</td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions">
                                                    <i class="fe fe-more-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="{{ route('v2.parts-inventory.items-to-repair.assign', $s->id) }}">Set for repair</a></li>
                                                    <li>
                                                        <form method="POST" action="{{ route('v2.parts-inventory.items-to-repair.mark-repaired', $s->id) }}" class="d-inline" onsubmit="return confirm('Mark as repaired? Item will move to available (status 1).');">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item">Mark as repaired</button>
                                                        </form>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item" href="{{ url('belfast_inventory') }}?grade[]={{ optional($s->variation)->grade ?? 8 }}&status={{ $s->status }}" target="_blank">Belfast Inventory</a></li>
                                                    <li><a class="dropdown-item" href="{{ url('imei') }}?imei={{ urlencode($imei) }}" target="_blank">View IMEI</a></li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">No items to repair found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $items->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
