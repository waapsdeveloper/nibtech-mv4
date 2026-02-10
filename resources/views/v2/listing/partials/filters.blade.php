<!-- Filters Section -->
<form method="GET" action="{{ route('v2.view_listing') }}" id="filterForm">
    <div class="row">
        <div class="col-md col-sm-6">
            <div class="form-floating">
                <input type="text" class="form-control" id="reference_id" name="reference_id" placeholder="Enter IMEI" value="@isset($_GET['reference_id']){{$_GET['reference_id']}}@endisset">
                <label for="reference_id">Reference ID</label>
            </div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="form-floating">
                <input type="text" class="form-control" id="product_name" name="product_name" placeholder="Enter Product Name" value="@isset($_GET['product_name']){{$_GET['product_name']}}@endisset">
                <label for="product_name">Search</label>
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
        <div class="col-md col-sm-6">
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
        <div class="col-md-3 col-sm-6">
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
                <option value="">Published</option>
                <option value="10" @if(isset($_GET['state']) && $_GET['state'] == 10) {{'selected'}}@endif>All</option>
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
                <option value="3" @if(isset($_GET['handler_status']) && $_GET['handler_status'] == 3) {{'selected'}}@endif>ReActived</option>
            </select>
        </div>
        <div class="col-md col-sm-6">
            {{-- <div class="card-header">
                <h4 class="card-title mb-1">Brand</h4>
            </div> --}}
            <select name="marketplace" class="form-control form-select" data-bs-placeholder="Select Marketplace">
                <option value="">Marketplace</option>
                @foreach ($marketplaces_dropdown as $id => $name)
                    <option value="{{$id}}" @if(isset($_GET['marketplace']) && $id == $_GET['marketplace']) {{'selected'}}@endif>{{$name}}</option>
                @endforeach
            </select>
        </div>
    </div>
    <br>
    <div class="row mb-3">
        <!-- marketplaces toggle section -->
        <div class="d-flex flex-row justify-content-between align-items-center w-100 gap-2 flex-wrap">
            @if(isset($global_marketplace_counts) && count($global_marketplace_counts) > 0)
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    @foreach($global_marketplace_counts as $mpId => $mpData)
                        @php
                            $marketplaceIdInt = (int)$mpId;
                            $mpName = $mpData['name'] ?? 'Marketplace ' . $marketplaceIdInt;
                            $totalCount = $mpData['total_count'] ?? 0;
                            $listingCountText = '(' . $totalCount . ')';
                            $isFirst = $loop->first; // Marketplace 1 is active by default
                        @endphp
                        <span 
                            class="badge global-marketplace-toggle-badge {{ $isFirst ? 'badge-active' : 'badge-inactive' }}" 
                            style="cursor: pointer; user-select: none; background-color: transparent; border: 1px solid {{ $isFirst ? '#28a745' : '#000' }}; color: {{ $isFirst ? '#28a745' : '#000' }}; font-size: 0.9rem; font-weight: 500; padding: 0.35em 0.65em;"
                            data-marketplace-id="{{ $marketplaceIdInt }}"
                            data-initial-state="{{ $isFirst ? 'active' : 'inactive' }}"
                            onclick="toggleGlobalMarketplace({{ $marketplaceIdInt }}, this)"
                            title="Click to show/hide {{ $mpName }} for all listings">
                            {{ $mpName }} 
                        </span>
                    @endforeach
                    <button class="btn btn-sm btn-outline-secondary" onclick="clearMarketplaceState()" title="Clear marketplace selection state">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            @endif
            
            <div class="d-flex flex-row align-items-center gap-4 flex-wrap">
                <div class="form-check form-switch d-flex align-items-center gap-2 mr-2">
                    <input class="form-check-input" type="checkbox" id="sale_40" name="sale_40" value="1" @if (request('sale_40')) {{'checked'}} @endif>
                    <label class="form-check-label" for="sale_40">Sales Below 5%</label>
                </div>
                <div class="form-check form-switch d-flex align-items-center gap-2">
                    <input class="form-check-input" type="checkbox" id="stock_mismatch" name="stock_mismatch" value="1" @if (request('stock_mismatch')) {{'checked'}} @endif>
                    <label class="form-check-label" for="stock_mismatch" title="Variations where (available - pending orders) != listed stock (total or Backmarket). Same logic as stock_mismatch_report.log">Stock mismatch ((available - pending) != listed)</label>
                </div>
                <button class="btn btn-primary pd-x-20" type="submit">{{ __('locale.Search') }}</button>
                <a href="{{ route('v2.view_listing') }}?per_page=10" class="btn btn-default pd-x-20">Reset</a>
            </div>
        </div>
    </div>

    <input type="hidden" name="page" value="{{ Request::get('page') }}">
    <input type="hidden" name="per_page" value="{{ Request::get('per_page') }}">
    <input type="hidden" name="sort" value="{{ Request::get('sort') ?? 1 }}">
    @if(Request::get('process_id'))
        <input type="hidden" name="process_id" value="{{ Request::get('process_id') }}">
    @endif
</form>
<!-- /Filters Section -->

