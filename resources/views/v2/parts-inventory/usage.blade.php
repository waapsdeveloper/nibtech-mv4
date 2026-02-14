@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Usage History' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/parts-inventory/dashboard') }}">Parts Inventory</a></li>
                <li class="breadcrumb-item active" aria-current="page">Usage History</li>
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

    <div class="row">
        <div class="col-12">
            <div class="alert alert-info mb-3">
                <strong>Parts usage:</strong> When you use a part from parts inventory to fix a stock item (e.g. a phone with a faulty battery), record it via the button below. Each record links the part used to the stock item (IMEI/serial). Use the filters to find usages by part or by IMEI.
            </div>

            <div class="card mb-4">
                <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0">Usage history</h6>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#recordUsageModal">Record usage</button>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="mb-3">Filter usage history</h6>
                    <form method="GET" action="{{ route('v2.parts-inventory.usage') }}" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">IMEI / Serial</label>
                            <input type="text" name="imei" class="form-control" value="{{ request('imei') }}" placeholder="Stock IMEI">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Part</label>
                            <select name="part_id" class="form-control form-select">
                                <option value="">All parts</option>
                                @foreach ($partsForFilter as $id => $name)
                                    <option value="{{ $id }}" {{ request('part_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
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
                            <a href="{{ route('v2.parts-inventory.usage') }}" class="btn btn-secondary">Reset</a>
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
                                    <th>Part</th>
                                    <th>Batch</th>
                                    <th>Qty</th>
                                    <th>Unit cost</th>
                                    <th>Total cost</th>
                                    <th>Stock / IMEI (item fixed)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($usages as $u)
                                    <tr>
                                        <td>{{ $u->created_at->format('Y-m-d H:i') }}</td>
                                        <td>{{ $u->part->name ?? '–' }}</td>
                                        <td>{{ $u->batch->batch_number ?? '–' }}</td>
                                        <td>{{ $u->qty }}</td>
                                        <td>{{ number_format($u->unit_cost, 2) }}</td>
                                        <td>{{ number_format($u->total_cost, 2) }}</td>
                                        <td>
                                            @if ($u->stock_id && $u->stock)
                                                <a href="{{ url('imei') }}?imei={{ $u->stock->imei ?? '' }}{{ $u->stock->serial_number ?? '' }}" target="_blank">{{ $u->stock->imei ?? '' }}{{ $u->stock->serial_number ?? '' }}</a>
                                            @else
                                                –
                                            @endif
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Actions</button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item usage-detail-btn" href="#" data-usage-id="{{ $u->id }}">Detail</a></li>
                                                    <li><a class="dropdown-item usage-edit-btn" href="#" data-usage-id="{{ $u->id }}" data-imei="{{ $u->stock ? ($u->stock->imei ?? $u->stock->serial_number ?? '') : '' }}" data-process-id="{{ $u->process_id ?? '' }}" data-technician-id="{{ $u->technician_id ?? '' }}" data-notes="{{ e($u->notes ?? '') }}">Edit</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="POST" action="{{ route('v2.parts-inventory.usage.delete', $u->id) }}" class="d-inline" onsubmit="return confirm('Delete this usage record?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="dropdown-item text-danger">Delete</button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">No usage records found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $usages->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Record usage --}}
<div class="modal fade" id="recordUsageModal" tabindex="-1" aria-labelledby="recordUsageModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="recordUsageModalLabel">Record usage (part used to fix a stock item)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('v2.parts-inventory.usage.store') }}">
                @csrf
                <div class="modal-body">
                    <p class="text-muted small">Link a part from parts inventory to a stock item (IMEI/serial) you fixed. The stock item must exist in <a href="{{ url('/inventory') }}" target="_blank">Inventory</a>.</p>
                    <div class="mb-3">
                        <label class="form-label">IMEI / Serial <span class="text-danger">*</span></label>
                        <input type="text" name="imei" class="form-control" value="{{ old('imei') }}" required placeholder="From Inventory" maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Part <span class="text-danger">*</span></label>
                        <select name="repair_part_id" class="form-control form-select" required>
                            <option value="">Select part</option>
                            @foreach ($partsForRecord as $p)
                                <option value="{{ $p->id }}" {{ old('repair_part_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}{{ $p->sku ? ' (' . $p->sku . ')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Qty <span class="text-danger">*</span></label>
                        <input type="number" name="qty" class="form-control" value="{{ old('qty', 1) }}" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="e.g. Replaced battery" maxlength="500">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Record usage</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Detail modal --}}
<div class="modal fade" id="usageDetailModal" tabindex="-1" aria-labelledby="usageDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="usageDetailModalLabel">Usage details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="usageDetailBody">
                <div class="text-center py-4 text-muted">Loading…</div>
            </div>
        </div>
    </div>
</div>

{{-- Edit modal --}}
<div class="modal fade" id="usageEditModal" tabindex="-1" aria-labelledby="usageEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="" id="usageEditForm">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="usageEditModalLabel">Edit usage</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editImei" class="form-label">IMEI / Serial (stock)</label>
                        <input type="text" class="form-control" id="editImei" name="imei" placeholder="IMEI or serial number">
                    </div>
                    <div class="mb-3">
                        <label for="editProcessId" class="form-label">Process</label>
                        <select class="form-select" id="editProcessId" name="process_id">
                            <option value="">— Select —</option>
                            @foreach($processes ?? [] as $pid => $pref)
                                <option value="{{ $pid }}">{{ $pref }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editTechnicianId" class="form-label">Technician</label>
                        <select class="form-select" id="editTechnicianId" name="technician_id">
                            <option value="">— Select —</option>
                            @foreach($technicians ?? [] as $tid => $tname)
                                <option value="{{ $tid }}">{{ $tname }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editNotes" class="form-label">Notes</label>
                        <textarea class="form-control" id="editNotes" name="notes" rows="2" maxlength="500"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if (session('open_record_usage_modal') || $errors->any())
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('recordUsageModal');
    if (modal) {
        var m = new bootstrap.Modal(modal);
        m.show();
    }
});
</script>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    var detailModalEl = document.getElementById('usageDetailModal');
    var detailBody = document.getElementById('usageDetailBody');
    var detailUrlBase = '{{ route("v2.parts-inventory.usage.detail", ["id" => "__ID__"]) }}';

    document.querySelectorAll('.usage-detail-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var id = this.getAttribute('data-usage-id');
            if (!id) return;
            detailBody.innerHTML = '<div class="text-center py-4 text-muted">Loading…</div>';
            var modal = new bootstrap.Modal(detailModalEl);
            modal.show();
            fetch(detailUrlBase.replace('__ID__', id), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    detailBody.innerHTML =
                        '<table class="table table-sm table-borderless mb-0">' +
                        '<tr><td class="text-muted">Date</td><td>' + (d.created_at || '–') + '</td></tr>' +
                        '<tr><td class="text-muted">Part</td><td>' + (d.part || '–') + (d.part_sku ? ' (' + d.part_sku + ')' : '') + '</td></tr>' +
                        '<tr><td class="text-muted">Batch</td><td>' + (d.batch || '–') + '</td></tr>' +
                        '<tr><td class="text-muted">Qty</td><td>' + (d.qty != null ? d.qty : '–') + '</td></tr>' +
                        '<tr><td class="text-muted">Unit cost</td><td>' + (d.unit_cost != null ? parseFloat(d.unit_cost).toFixed(2) : '–') + '</td></tr>' +
                        '<tr><td class="text-muted">Total cost</td><td>' + (d.total_cost != null ? parseFloat(d.total_cost).toFixed(2) : '–') + '</td></tr>' +
                        '<tr><td class="text-muted">IMEI / Serial</td><td>' + (d.imei || '–') + '</td></tr>' +
                        '<tr><td class="text-muted">Process</td><td>' + (d.process || '–') + '</td></tr>' +
                        '<tr><td class="text-muted">Technician</td><td>' + (d.technician || '–') + '</td></tr>' +
                        '<tr><td class="text-muted">Notes</td><td>' + (d.notes ? d.notes.replace(/</g, '&lt;') : '–') + '</td></tr>' +
                        '</table>';
                })
                .catch(function () {
                    detailBody.innerHTML = '<p class="text-danger mb-0">Failed to load details.</p>';
                });
        });
    });

    var editModalEl = document.getElementById('usageEditModal');
    var editForm = document.getElementById('usageEditForm');
    document.querySelectorAll('.usage-edit-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var id = this.getAttribute('data-usage-id');
            var imei = this.getAttribute('data-imei') || '';
            var processId = this.getAttribute('data-process-id') || '';
            var technicianId = this.getAttribute('data-technician-id') || '';
            var notes = (this.getAttribute('data-notes') || '').replace(/&quot;/g, '"');
            if (!id) return;
            editForm.action = '{{ route("v2.parts-inventory.usage.update", ["id" => "__ID__"]) }}'.replace('__ID__', id);
            document.getElementById('editImei').value = imei;
            document.getElementById('editProcessId').value = processId;
            document.getElementById('editTechnicianId').value = technicianId;
            document.getElementById('editNotes').value = notes;
            var modal = new bootstrap.Modal(editModalEl);
            modal.show();
        });
    });
});
</script>
@endsection
