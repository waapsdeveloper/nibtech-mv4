@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Edit batch' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/parts-inventory/dashboard') }}">Parts Inventory</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ route('v2.parts-inventory.inventory') }}">Batches</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit batch</li>
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
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card">
                <div class="card-body">
                    @if ($batch->repairPart)
                        <p class="text-muted mb-3"><strong>Part:</strong> {{ $batch->repairPart->name }} @if($batch->repairPart->sku)({{ $batch->repairPart->sku }})@endif</p>
                    @endif
                    <form method="POST" action="{{ route('v2.parts-inventory.batch.update', $batch->id) }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label for="batch_number" class="form-label">Batch number</label>
                            <input type="text" name="batch_number" id="batch_number" class="form-control" value="{{ old('batch_number', $batch->batch_number) }}" required maxlength="64">
                        </div>
                        <div class="mb-3">
                            <label for="quantity_remaining" class="form-label">Quantity remaining</label>
                            <input type="number" name="quantity_remaining" id="quantity_remaining" class="form-control" value="{{ old('quantity_remaining', $batch->quantity_remaining) }}" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="unit_cost" class="form-label">Unit cost</label>
                            <input type="number" name="unit_cost" id="unit_cost" class="form-control" value="{{ old('unit_cost', $batch->unit_cost) }}" min="0" step="0.01" placeholder="Optional">
                        </div>
                        <div class="mb-3">
                            <label for="received_at" class="form-label">Received at</label>
                            <input type="datetime-local" name="received_at" id="received_at" class="form-control" value="{{ old('received_at', $batch->received_at ? $batch->received_at->format('Y-m-d\TH:i') : '') }}">
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea name="notes" id="notes" class="form-control" rows="3" maxlength="1000">{{ old('notes', $batch->notes) }}</textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="{{ route('v2.parts-inventory.inventory') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
