@php
    $variationId = $selectedVariation->id;
    $sku = $selectedVariation->sku ?? 'N/A';
    $colorId = $selectedVariation->color ?? null;
    $colorName = isset($colors[$colorId]) ? $colors[$colorId] : '';
    
    // Convert color name to CSS color
    $colorCode = $colorName;
    $colorNameLower = strtolower($colorName);
    $colorMap = [
        'purple' => 'purple',
        'deep purple' => '#663399',
        'dark purple' => '#4B0082',
        'red' => 'red',
        'blue' => 'blue',
        'black' => 'black',
        'white' => 'white',
        'gold' => '#FFD700',
        'silver' => '#C0C0C0',
        'pink' => 'pink',
        'gray' => 'gray',
        'grey' => 'gray',
        'space gray' => '#717378',
        'midnight' => '#1C1C1E',
        'starlight' => '#F5F5DC',
        'beige' => '#F5F5DC',
    ];
    foreach($colorMap as $key => $value) {
        if(str_contains($colorNameLower, $key)) {
            $colorCode = $value;
            break;
        }
    }
    if(empty($colorCode) || !preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$|^[a-z]+$/i', $colorCode)) {
        $colorCode = '#ccc';
    }
    
    $storageId = $selectedVariation->storage ?? null;
    $storageName = isset($storages[$storageId]) ? $storages[$storageId] : '';
    $gradeId = $selectedVariation->grade ?? null;
    $gradeName = isset($grades[$gradeId]) ? $grades[$gradeId] : '';
    $productModel = $selectedVariation->product->model ?? 'N/A';
    $productId = $selectedVariation->product_id ?? 0;
    
    // Get total stock from variation (source of truth, not calculated from children)
    $totalStock = $selectedVariation->listed_stock ?? 0;
    
    $availableStocks = $selectedVariation->available_stocks ?? collect();
    $pendingOrders = $selectedVariation->pending_orders ?? collect();
    $pendingBmOrders = $selectedVariation->pending_bm_orders ?? collect();
    $availableCount = $availableStocks->count();
    $pendingCount = $pendingOrders->count();
    $pendingBmCount = $pendingBmOrders->count();
    $difference = $availableCount - $pendingCount;
@endphp

<div class="row mt-3">
    <div class="col-xl-12">
        <div class="card" style="padding-left: 5px; padding-right: 5px;">
            <div class="card-header py-0 d-flex justify-content-between" style="padding-left: 5px; padding-right: 5px;">
                <div>
                    <h5>
                        <a href="{{url('inventory')}}?sku={{ $sku }}" title="View Inventory" target="_blank">
                            <span style="background-color: {{ $colorCode }}; width: 30px; height: 16px; display: inline-block;"></span>
                            {{ $sku }}
                        </a>
                        <a href="https://www.backmarket.fr/bo-seller/listings/active?sku={{ $sku }}" title="View BM Ad" target="_blank">
                            - {{ $productModel }} {{ $storageName }} {{ $colorName }} {{ $gradeName }}
                        </a>
                    </h5>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="d-flex align-items-center justify-content-start gap-2 flex-wrap">
                            <h6 class="mb-0">
                                <a class="" href="{{url('order').'?sku='}}{{$sku}}&status=2" target="_blank">
                                    Pending Order Items: {{ $pendingCount }} (BM Orders: {{ $pendingBmCount }})
                                </a>
                            </h6>
                            <span class="text-muted">|</span>
                            <h6 class="mb-0">
                                <a href="{{url('inventory').'?product='}}{{$productId}}&storage={{$storageId}}&color={{$colorId}}&grade[]={{$gradeId}}" target="_blank">
                                    Available: {{ $availableCount }}
                                </a>
                            </h6>
                            <span class="text-muted">|</span>
                            <h6 class="mb-0">Difference: {{ $difference }}</h6>
                            <span class="text-muted">|</span>
                            <h6 class="mb-0">Total Stock: {{ $totalStock }}</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

