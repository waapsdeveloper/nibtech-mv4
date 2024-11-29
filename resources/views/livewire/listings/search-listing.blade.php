
    <div class="row">
        <div class="col-md col-sm-6">
            <div class="form-floating">
                <input type="text" class="form-control" id="reference_id" name="reference_id" placeholder="Enter IMEI" value="@isset($_GET['reference_id']){{$_GET['reference_id']}}@endisset">
                <label for="reference_id">Reference ID</label>
            </div>
        </div>
        <div class="col-md col-sm-6">
            <div class="form-floating">
                <input type="text" id="product" name="product" list="products" class="form-control" data-bs-placeholder="Select Status" value="@isset($_GET['product']){{$_GET['product']}}@endisset">
                <label for="product">Product</label>
            </div>
                <datalist id="products">
                    @foreach ($products as $product)
                        <option value="{{$product->id}}" @if(isset($_GET['product']) && $product->id == $_GET['product']) {{'selected'}}@endif>{{$product->model}}</option>
                    @endforeach
                </datalist>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="form-floating">
                <input type="text" class="form-control" name="sku" placeholder="Enter IMEI" value="@isset($_GET['sku']){{$_GET['sku']}}@endisset">
                <label for="">SKU</label>
            </div>
        </div>
        <div class="col-md col-sm-6">
            <select name="color" class="form-control form-select" data-bs-placeholder="Select Status">
                <option value="">Color</option>
                @foreach ($colors as $id => $color)
                    <option value="{{$id}}" @if(isset($_GET['color']) && $id == $_GET['color']) {{'selected'}}@endif>{{$color}}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md col-sm-6">
            <select name="storage" class="form-control form-select" data-bs-placeholder="Select Status">
                <option value="">Storage</option>
                @foreach ($storages as $id => $storage)
                    <option value="{{$id}}" @if(isset($_GET['storage']) && $id == $_GET['storage']) {{'selected'}}@endif>{{$storage}}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 col-sm-6">
            <select name="grade[]" class="form-control form-select select2" data-bs-placeholder="Select Status" multiple>
                <option value="">Grade</option>
                @foreach ($grades as $id => $grade)
                    <option value="{{$id}}" @if(isset($_GET['grade']) && in_array($id,$_GET['grade'])) {{'selected'}}@endif>{{$grade}}</option>
                @endforeach
            </select>
        </div>
    </div>
    <br>
    <div class="row">
        <div class="col-md col-sm-6">
            {{-- <div class="card-header">
                <h4 class="card-title mb-1">Category</h4>
            </div> --}}
            <select name="category" class="form-control form-select" data-bs-placeholder="Select Category">
                <option value="">Category</option>
                @foreach ($categories as $category)
                    <option value="{{$category->id}}" @if(isset($_GET['category']) && $category->id == $_GET['category']) {{'selected'}}@endif>{{$category->name}}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md col-sm-6">
            {{-- <div class="card-header">
                <h4 class="card-title mb-1">Brand</h4>
            </div> --}}
            <select name="brand" class="form-control form-select" data-bs-placeholder="Select Brand">
                <option value="">Brand</option>
                @foreach ($brands as $brand)
                    <option value="{{$brand->id}}" @if(isset($_GET['brand']) && $brand->id == $_GET['brand']) {{'selected'}}@endif>{{$brand->name}}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md col-sm-6">
            <select name="listed_stock" class="form-control form-select" data-bs-placeholder="Select listed Stock">
                <option value="">Listed Stock</option>
                <option value="1" @if(isset($_GET['listed_stock']) && $_GET['listed_stock'] == 1) {{'selected'}}@endif>With Listing</option>
                <option value="2" @if(isset($_GET['listed_stock']) && $_GET['listed_stock'] == 2) {{'selected'}}@endif>Without Listing</option>
            </select>
        </div>
        <div class="col-md col-sm-6">
            <select name="available_stock" class="form-control form-select" data-bs-placeholder="Select Available Stock">
                <option value="">Available Stock</option>
                <option value="1" @if(isset($_GET['available_stock']) && $_GET['available_stock'] == 1) {{'selected'}}@endif>With Stock</option>
                <option value="2" @if(isset($_GET['available_stock']) && $_GET['available_stock'] == 2) {{'selected'}}@endif>Without Stock</option>
            </select>
        </div>
        <div class="col-md col-sm-6">
            <select name="state" class="form-control form-select" data-bs-placeholder="Select Publication State">
                <option value="">Publication State</option>
                <option value="0" @if(isset($_GET['state']) && $_GET['state'] == 0) {{'selected'}}@endif>Missing price or comment</option>
                <option value="1" @if(isset($_GET['state']) && $_GET['state'] == 1) {{'selected'}}@endif>Pending validation</option>
                <option value="2" @if(isset($_GET['state']) && $_GET['state'] == 2) {{'selected'}}@endif>Online</option>
                <option value="3" @if(isset($_GET['state']) && $_GET['state'] == 3) {{'selected'}}@endif>Offline</option>
                <option value="4" @if(isset($_GET['state']) && $_GET['state'] == 4) {{'selected'}}@endif>Deactivated</option>
            </select>
        </div>
        <div class="col-md col-sm-6">
            <select name="handler_status" class="form-select" data-bs-placeholder="Select Handler Status">
                <option value="">Price Handler</option>
                <option value="1" @if(isset($_GET['handler_status']) && $_GET['handler_status'] == 1) {{'selected'}}@endif>Active</option>
                <option value="2" @if(isset($_GET['handler_status']) && $_GET['handler_status'] == 2) {{'selected'}}@endif>Inactive</option>
            </select>
        </div>
        <div class="">
            <button class="btn btn-primary pd-x-20" type="submit">{{ __('locale.Search') }}</button>
            <a href="{{url('listing')}}?per_page=10" class="btn btn-default pd-x-20">Reset</a>
        </div>
    </div>

    <input type="hidden" name="page" value="{{ Request::get('page') }}">
    <input type="hidden" name="per_page" value="{{ Request::get('per_page') }}">
    <input type="hidden" name="sort" value="{{ Request::get('sort') }}">
<br>
@if (session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <span class="alert-inner--icon"><i class="fe fe-thumbs-up"></i></span>
    <span class="alert-inner--text"><strong>{{session('success')}}</strong></span>
    <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
</div>
<br>
@php
session()->forget('success');
@endphp
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <span class="alert-inner--icon"><i class="fe fe-thumbs-down"></i></span>
        <span class="alert-inner--text"><strong>{{session('error')}}</strong></span>
        <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
    </div>
<br>
@php
session()->forget('error');
@endphp
@endif
