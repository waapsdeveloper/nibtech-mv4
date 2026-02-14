@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Batch Receive' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/parts-inventory/dashboard') }}">Parts Inventory</a></li>
                <li class="breadcrumb-item active" aria-current="page">Batch Receive</li>
            </ol>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">Receive a new batch (bulk purchase)</h5>
                    <form method="POST" action="{{ route('v2.parts-inventory.batch-receive.store') }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Part <span class="text-danger">*</span></label>
                                <select name="repair_part_id" class="form-control form-select" required>
                                    <option value="">Select part</option>
                                    @foreach ($parts as $p)
                                        <option value="{{ $p->id }}" {{ old('repair_part_id') == $p->id ? 'selected' : '' }}>
                                            {{ $p->name }} @if($p->sku)({{ $p->sku }})@endif – {{ $p->product->model ?? '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Batch number <span class="text-danger">*</span></label>
                                <input type="text" name="batch_number" class="form-control" value="{{ old('batch_number') }}" required placeholder="e.g. SCR-2025-001">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Quantity received <span class="text-danger">*</span></label>
                                <input type="number" name="quantity_received" class="form-control" value="{{ old('quantity_received') }}" required min="1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Unit cost <span class="text-danger">*</span></label>
                                <input type="number" name="unit_cost" class="form-control" value="{{ old('unit_cost') }}" required min="0" step="0.01">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Received date</label>
                                <input type="date" name="received_at" class="form-control" value="{{ old('received_at', date('Y-m-d')) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Supplier</label>
                                <input type="text" name="supplier" class="form-control" value="{{ old('supplier') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Notes</label>
                                <input type="text" name="notes" class="form-control" value="{{ old('notes') }}">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Receive batch</button>
                                <a href="{{ route('v2.parts-inventory.dashboard') }}" class="btn btn-secondary">Back to dashboard</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
