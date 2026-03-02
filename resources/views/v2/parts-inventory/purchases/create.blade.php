@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between" style="border-bottom: 1px solid rgb(216, 212, 212);">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Create Purchase Order' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/parts-inventory/dashboard') }}">Parts Inventory</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ route('v2.parts-inventory.purchases') }}">Purchases</a></li>
                <li class="breadcrumb-item active" aria-current="page">Add Purchase Order</li>
            </ol>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            {{ session('info') }}
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
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Select vendor and reference</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Creating purchase order for batch <strong>{{ $batch->batch_number }}</strong>@if($batch->repairPart) ({{ $batch->repairPart->name ?? $batch->repairPart->sku }})@endif. Choose vendor and optional reference like the main purchase list.</p>
                    <form method="POST" action="{{ route('v2.parts-inventory.purchases.create.store') }}" class="row g-3">
                        @csrf
                        <input type="hidden" name="batch_id" value="{{ $batch->id }}">
                        <div class="col-md-6">
                            <label class="form-label">Vendor</label>
                            <select name="customer_id" class="form-select">
                                <option value="">— Select vendor —</option>
                                @foreach ($vendors as $id => $label)
                                    <option value="{{ $id }}" {{ old('customer_id') == $id ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reference</label>
                            <input type="text" name="reference" class="form-control" value="{{ old('reference') }}" placeholder="e.g. 121PCS MALAYSIA 18.11" maxlength="255">
                            <small class="form-text text-muted">Vendor reference or description.</small>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-success">Create purchase order</button>
                            <a href="{{ route('v2.parts-inventory.purchases') }}" class="btn btn-secondary">Back to Purchases</a>
                            <a href="{{ route('v2.parts-inventory.inventory') }}" class="btn btn-outline-secondary">Batches list</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
