@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Inventory' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/parts-inventory/dashboard') }}">Parts Inventory</a></li>
                <li class="breadcrumb-item active" aria-current="page">Inventory</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('v2.parts-inventory.inventory') }}" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Part name, SKU, product">
                        </div>
                        <div class="col-md-2">
                            <div class="form-check mt-4">
                                <input type="checkbox" name="low_stock" value="1" class="form-check-input" id="low_stock" {{ request('low_stock') === '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="low_stock">Low stock only</label>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Filter</button>
                            <a href="{{ route('v2.parts-inventory.inventory') }}" class="btn btn-secondary">Reset</a>
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
                                    <th>Part</th>
                                    <th>Product</th>
                                    <th>On hand</th>
                                    <th>Reorder level</th>
                                    <th>Unit cost</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($parts as $part)
                                    <tr class="{{ $part->on_hand <= $part->reorder_level ? 'table-warning' : '' }}">
                                        <td>
                                            <strong>{{ $part->name }}</strong>
                                            @if ($part->sku)
                                                <br><small class="text-muted">{{ $part->sku }}</small>
                                            @endif
                                        </td>
                                        <td>{{ $part->product->model ?? '–' }}</td>
                                        <td>{{ $part->on_hand }}</td>
                                        <td>{{ $part->reorder_level }}</td>
                                        <td>{{ number_format($part->unit_cost, 2) }}</td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Actions</button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item batches-modal-btn" href="#" data-part-id="{{ $part->id }}" data-part-name="{{ e($part->name) }}" data-part-sku="{{ e($part->sku ?? '') }}">Batches (in stock)</a></li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No parts found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $parts->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Batches (in stock) --}}
<div class="modal fade" id="batchesModal" tabindex="-1" aria-labelledby="batchesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="batchesModalLabel">Batches (in stock)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-2" id="batchesModalPartInfo"></p>
                <div id="batchesModalLoading" class="text-center py-4 d-none">
                    <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                </div>
                <div id="batchesModalContent" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Batch number</th>
                                    <th>Quantity remaining</th>
                                    <th>Received at</th>
                                </tr>
                            </thead>
                            <tbody id="batchesModalTableBody"></tbody>
                        </table>
                    </div>
                    <nav id="batchesModalPagination" class="mt-2" aria-label="Batches pagination"></nav>
                </div>
                <div id="batchesModalEmpty" class="text-muted py-3 d-none">No batches in stock.</div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = new bootstrap.Modal(document.getElementById('batchesModal'));
    var partId = null;

    function loadBatches(page) {
        page = page || 1;
        var loading = document.getElementById('batchesModalLoading');
        var content = document.getElementById('batchesModalContent');
        var empty = document.getElementById('batchesModalEmpty');
        loading.classList.remove('d-none');
        content.classList.add('d-none');
        empty.classList.add('d-none');

        fetch('{{ url("v2/parts-inventory/parts") }}/' + partId + '/batches?page=' + page, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            loading.classList.add('d-none');
            var tbody = document.getElementById('batchesModalTableBody');
            tbody.innerHTML = '';
            if (data.batches && data.batches.length > 0) {
                content.classList.remove('d-none');
                data.batches.forEach(function (b) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td><code>' + (b.batch_number || '') + '</code></td><td>' + (b.quantity_remaining ?? '') + '</td><td>' + (b.received_at || '–') + '</td>';
                    tbody.appendChild(tr);
                });
                renderPagination(data.pagination, page);
            } else {
                empty.classList.remove('d-none');
            }
        })
        .catch(function () {
            loading.classList.add('d-none');
            empty.classList.remove('d-none');
            document.getElementById('batchesModalEmpty').textContent = 'Error loading batches.';
        });
    }

    function renderPagination(pagination, currentPage) {
        var nav = document.getElementById('batchesModalPagination');
        nav.innerHTML = '';
        if (!pagination || pagination.last_page <= 1) return;
        var ul = document.createElement('ul');
        ul.className = 'pagination pagination-sm mb-0';
        for (var i = 1; i <= pagination.last_page; i++) {
            var li = document.createElement('li');
            li.className = 'page-item' + (i === currentPage ? ' active' : '');
            var a = document.createElement('a');
            a.className = 'page-link';
            a.href = '#';
            a.textContent = i;
            a.addEventListener('click', function (e) {
                e.preventDefault();
                loadBatches(parseInt(this.textContent, 10));
            });
            li.appendChild(a);
            ul.appendChild(li);
        }
        nav.appendChild(ul);
    }

    document.querySelectorAll('.batches-modal-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            partId = this.getAttribute('data-part-id');
            var name = this.getAttribute('data-part-name');
            var sku = this.getAttribute('data-part-sku');
            document.getElementById('batchesModalPartInfo').textContent = (name || 'Part') + (sku ? ' (' + sku + ')' : '');
            document.getElementById('batchesModalLabel').textContent = 'Batches (in stock)';
            modal.show();
            loadBatches(1);
        });
    });
});
</script>
@endsection
