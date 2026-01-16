@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title_page ?? 'Edit Stock Deduction Log' }}</span>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('v2.stock-deduction-logs.update', $log->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>Variation <span class="text-danger">*</span></label>
                                    <select name="variation_id" class="form-control form-select" required>
                                        <option value="">Select Variation</option>
                                        @foreach($variations as $variation)
                                            <option value="{{ $variation->id }}" {{ old('variation_id', $log->variation_id) == $variation->id ? 'selected' : '' }}>
                                                {{ $variation->sku }} - {{ $variation->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('variation_id')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>Marketplace <span class="text-danger">*</span></label>
                                    <select name="marketplace_id" class="form-control form-select" required>
                                        <option value="">Select Marketplace</option>
                                        @foreach($marketplaces as $mp)
                                            <option value="{{ $mp->id }}" {{ old('marketplace_id', $log->marketplace_id) == $mp->id ? 'selected' : '' }}>{{ $mp->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('marketplace_id')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>Order</label>
                                    <select name="order_id" class="form-control form-select">
                                        <option value="">Select Order</option>
                                        @foreach($orders as $order)
                                            <option value="{{ $order->id }}" {{ old('order_id', $log->order_id) == $order->id ? 'selected' : '' }}>
                                                {{ $order->reference_id }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('order_id')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>Order Reference ID</label>
                                    <input type="text" name="order_reference_id" class="form-control" value="{{ old('order_reference_id', $log->order_reference_id) }}">
                                    @error('order_reference_id')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="form-group mb-3">
                                    <label>Before Variation Stock <span class="text-danger">*</span></label>
                                    <input type="number" name="before_variation_stock" class="form-control" value="{{ old('before_variation_stock', $log->before_variation_stock) }}" required>
                                    @error('before_variation_stock')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="form-group mb-3">
                                    <label>After Variation Stock <span class="text-danger">*</span></label>
                                    <input type="number" name="after_variation_stock" class="form-control" value="{{ old('after_variation_stock', $log->after_variation_stock) }}" required>
                                    @error('after_variation_stock')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="form-group mb-3">
                                    <label>Before Marketplace Stock <span class="text-danger">*</span></label>
                                    <input type="number" name="before_marketplace_stock" class="form-control" value="{{ old('before_marketplace_stock', $log->before_marketplace_stock) }}" required>
                                    @error('before_marketplace_stock')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="form-group mb-3">
                                    <label>After Marketplace Stock <span class="text-danger">*</span></label>
                                    <input type="number" name="after_marketplace_stock" class="form-control" value="{{ old('after_marketplace_stock', $log->after_marketplace_stock) }}" required>
                                    @error('after_marketplace_stock')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>Deduction Reason <span class="text-danger">*</span></label>
                                    <select name="deduction_reason" class="form-control form-select" required>
                                        <option value="">Select Reason</option>
                                        @foreach($deduction_reasons as $key => $label)
                                            <option value="{{ $key }}" {{ old('deduction_reason', $log->deduction_reason) == $key ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('deduction_reason')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="form-group mb-3">
                                    <label>Order Status</label>
                                    <input type="number" name="order_status" class="form-control" value="{{ old('order_status', $log->order_status) }}" min="1" max="7">
                                    @error('order_status')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="form-group mb-3">
                                    <label>Old Order Status</label>
                                    <input type="number" name="old_order_status" class="form-control" value="{{ old('old_order_status', $log->old_order_status) }}" min="1" max="7">
                                    @error('old_order_status')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>
                                        <input type="checkbox" name="is_new_order" value="1" {{ old('is_new_order', $log->is_new_order) ? 'checked' : '' }}>
                                        Is New Order
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-12">
                                <div class="form-group mb-3">
                                    <label>Notes</label>
                                    <textarea name="notes" class="form-control" rows="3">{{ old('notes', $log->notes) }}</textarea>
                                    @error('notes')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Update</button>
                            <a href="{{ route('v2.stock-deduction-logs.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
