@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Add broken parts' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/parts-inventory/dashboard') }}">Parts Inventory</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ route('v2.parts-inventory.inventory') }}">Inventory</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ route('v2.parts-inventory.part-batches-page', $part->id) }}">{{ $part->name }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">Add broken parts</li>
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
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <p class="mb-2"><strong>Part:</strong> {{ $part->name }} @if($part->sku)<span class="text-muted">({{ $part->sku }})</span>@endif</p>
                    <p class="mb-0">
                        <a href="{{ route('v2.parts-inventory.part-batches-page', $part->id) }}" class="btn btn-sm btn-outline-secondary">← Back to Batches</a>
                        <a href="{{ route('v2.parts-inventory.part-broken.history', $part->id) }}" class="btn btn-sm btn-outline-primary ms-1" title="View broken parts history"><i class="fe fe-eye"></i> View history</a>
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Record broken parts</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('v2.parts-inventory.part-broken.store', $part->id) }}">
                        @csrf
                        @if ($batch)
                            <div class="mb-3">
                                <label class="form-label">Batch</label>
                                <p class="mb-0"><code>{{ $batch->batch_number }}</code> — received {{ $batch->received_at ? $batch->received_at->format('Y-m-d') : '–' }}, remaining: {{ $batch->quantity_remaining }}</p>
                                <input type="hidden" name="part_batch_id" value="{{ $batch->id }}">
                            </div>
                        @else
                            <div class="mb-3">
                                <label for="part_batch_id" class="form-label">Batch (optional)</label>
                                <select name="part_batch_id" id="part_batch_id" class="form-select">
                                    <option value="">— Not sure / record without batch —</option>
                                    @foreach ($batchesForDropdown as $b)
                                        <option value="{{ $b->id }}" {{ old('part_batch_id') == $b->id ? 'selected' : '' }}>
                                            {{ $b->batch_number }} — remaining: {{ $b->quantity_remaining }}{{ $b->received_at ? ', received ' . $b->received_at->format('Y-m-d') : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Leave blank if you don't know which batch the broken parts came from.</small>
                            </div>
                        @endif
                        <div class="mb-3">
                            <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" id="quantity" class="form-control" value="{{ old('quantity', 1) }}" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label for="responsible_person" class="form-label">Responsible person</label>
                            <input type="text" name="responsible_person" id="responsible_person" class="form-control" value="{{ old('responsible_person') }}" maxlength="255" placeholder="Person who broke the part or who received it already broken">
                            <small class="text-muted">Optional: name of who broke it or e.g. "Received broken from supplier".</small>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea name="notes" id="notes" class="form-control" rows="2" maxlength="1000" placeholder="Optional">{{ old('notes') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Save broken parts</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
