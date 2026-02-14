@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Attach IMEI' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/parts-inventory/dashboard') }}">Parts Inventory</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ route('v2.parts-inventory.catalog') }}">Part Catalog</a></li>
                <li class="breadcrumb-item active" aria-current="page">Attach IMEI</li>
            </ol>
        </div>
    </div>

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
                    <p class="text-muted">Part: <strong>{{ $part->name }}</strong> @if($part->sku)<code>{{ $part->sku }}</code>@endif</p>
                    <p class="text-muted small">Enter an IMEI from your <a href="{{ url('/inventory') }}" target="_blank">Inventory</a>. The part will be linked to that device model (product).</p>
                    <form method="POST" action="{{ route('v2.parts-inventory.catalog.attach-imei.store', $part->id) }}">
                        @csrf
                        <div class="mb-3">
                            <label for="imei" class="form-label">IMEI <span class="text-danger">*</span></label>
                            <input type="text" name="imei" id="imei" class="form-control" value="{{ old('imei') }}" required placeholder="e.g. from Inventory" maxlength="255">
                        </div>
                        <button type="submit" class="btn btn-primary">Attach IMEI</button>
                        <a href="{{ route('v2.parts-inventory.catalog') }}" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
