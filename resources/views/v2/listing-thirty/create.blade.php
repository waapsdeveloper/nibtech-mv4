@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Create Listing-30' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listing-thirty') }}">Extras</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ route('v2.listing-thirty.index') }}">Listing-30</a></li>
                <li class="breadcrumb-item active" aria-current="page">Create</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('v2.listing-thirty.store') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>Variation</label>
                                    <select name="variation_id" class="form-control form-select">
                                        <option value="">— None —</option>
                                        @foreach($variations as $v)
                                            <option value="{{ $v->id }}" {{ old('variation_id') == $v->id ? 'selected' : '' }}>{{ $v->sku }} {{ $v->name ? '- ' . Str::limit($v->name, 40) : '' }}</option>
                                        @endforeach
                                    </select>
                                    @error('variation_id')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>BM Listing ID <span class="text-danger">*</span></label>
                                    <input type="text" name="bm_listing_id" class="form-control" value="{{ old('bm_listing_id') }}" required maxlength="255">
                                    @error('bm_listing_id')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label>Country code</label>
                                    <input type="text" name="country_code" class="form-control" value="{{ old('country_code') }}" maxlength="10" placeholder="e.g. FR">
                                    @error('country_code')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label>SKU</label>
                                    <input type="text" name="sku" class="form-control" value="{{ old('sku') }}" maxlength="255">
                                    @error('sku')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label>Source <span class="text-danger">*</span></label>
                                    <select name="source" class="form-control form-select" required>
                                        <option value="get_listings" {{ old('source', 'get_listings') == 'get_listings' ? 'selected' : '' }}>get_listings</option>
                                        <option value="get_listingsBi" {{ old('source') == 'get_listingsBi' ? 'selected' : '' }}>get_listingsBi</option>
                                    </select>
                                    @error('source')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label>Quantity <span class="text-danger">*</span></label>
                                    <input type="number" name="quantity" class="form-control" value="{{ old('quantity', 0) }}" min="0" required>
                                    @error('quantity')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label>Publication state (0-4)</label>
                                    <input type="number" name="publication_state" class="form-control" value="{{ old('publication_state') }}" min="0" max="4">
                                    @error('publication_state')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label>State (grade)</label>
                                    <input type="number" name="state" class="form-control" value="{{ old('state') }}">
                                    @error('state')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group mb-3">
                                    <label>Title</label>
                                    <input type="text" name="title" class="form-control" value="{{ old('title') }}" maxlength="500">
                                    @error('title')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group mb-3">
                                    <label>Price amount</label>
                                    <input type="number" name="price_amount" class="form-control" value="{{ old('price_amount') }}" step="0.01">
                                    @error('price_amount')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group mb-3">
                                    <label>Price currency</label>
                                    <input type="text" name="price_currency" class="form-control" value="{{ old('price_currency') }}" maxlength="10" placeholder="EUR">
                                    @error('price_currency')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group mb-3">
                                    <label>Min price</label>
                                    <input type="number" name="min_price" class="form-control" value="{{ old('min_price') }}" step="0.01">
                                    @error('min_price')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group mb-3">
                                    <label>Max price</label>
                                    <input type="number" name="max_price" class="form-control" value="{{ old('max_price') }}" step="0.01">
                                    @error('max_price')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group mb-3">
                                    <label>BM Listing UUID</label>
                                    <input type="text" name="bm_listing_uuid" class="form-control" value="{{ old('bm_listing_uuid') }}" maxlength="255">
                                    @error('bm_listing_uuid')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Create</button>
                            <a href="{{ route('v2.listing-thirty.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
