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
    $totalStock = $selectedVariation->listed_stock ?? 0;
@endphp

{{-- Update modal title and show footer --}}
<script>
    $(document).ready(function() {
        // Update modal title with full variation information
        const titleText = '{{ $sku }} - {{ $productModel }} {{ $storageName }} {{ $colorName }} {{ $gradeName }} - Stock Formula';
        $('#stockFormulaModalTitle').text(titleText);
        
        // Show footer
        const footer = document.getElementById('stockFormulaModalFooter');
        if (footer) {
            footer.style.display = 'flex';
        }
    });
</script>

{{-- Variation Default Formula Section --}}
@if($selectedVariation->default_stock_formula)
<div class="mb-3 p-2 border rounded bg-light">
    <small class="text-muted d-block mb-1"><strong>Variation Default:</strong></small>
    @php
        $varDefault = $selectedVariation->default_stock_formula;
        $varDefaultDisplay = ($varDefault['type'] ?? 'percentage') == 'percentage' 
            ? '(' . ($varDefault['value'] ?? '') . '%)' 
            : '(' . ($varDefault['value'] ?? '') . 'P)';
    @endphp
    <small class="text-dark">{{ $varDefaultDisplay }}</small>
    @if($selectedVariation->default_min_threshold !== null || $selectedVariation->default_max_threshold !== null)
        <small class="text-dark">
            @if($selectedVariation->default_min_threshold !== null && $selectedVariation->default_max_threshold !== null)
                {{ $selectedVariation->default_min_threshold }}~{{ $selectedVariation->default_max_threshold }}
            @elseif($selectedVariation->default_min_threshold !== null)
                {{ $selectedVariation->default_min_threshold }}~
            @else
                ~{{ $selectedVariation->default_max_threshold }}
            @endif
        </small>
    @endif
</div>
@endif

{{-- Marketplace Cards --}}
<div class="marketplace-formulas-list">
    @foreach($marketplaceStocks as $marketplaceId => $marketplaceStock)
        @php
            $globalDefault = $globalDefaults[$marketplaceId] ?? null;
            $isUsingDefault = !$marketplaceStock['has_formula'] && (
                ($selectedVariation->default_stock_formula !== null) || 
                ($globalDefault !== null)
            );
        @endphp
        @include('v2.marketplace.stock-formula.partials.marketplace-formula-card-modal', [
            'marketplaceStock' => $marketplaceStock,
            'variationId' => $variationId,
            'isUsingDefault' => $isUsingDefault,
            'globalDefault' => $globalDefault,
            'variationDefault' => $selectedVariation->default_stock_formula
        ])
    @endforeach
</div>

{{-- Load stock formula scripts --}}
<script>
    // Initialize stock formula configuration for this modal
    window.StockFormulaConfig = {
        urls: {
            search: "{{ url('v2/marketplace/stock-formula/search') }}",
            getStocks: "{{ url('v2/marketplace/stock-formula') }}/",
            saveFormula: "{{ url('v2/marketplace/stock-formula') }}/",
            deleteFormula: "{{ url('v2/marketplace/stock-formula') }}/",
            resetStock: "{{ url('v2/marketplace/stock-formula') }}/",
        },
        csrfToken: "{{ csrf_token() }}",
        selectedVariationId: {{ $variationId }},
        marketplaces: @json($marketplaces->values()->all() ?? [])
    };
    
    // Load stock formula JS if not already loaded
    if (!window.stockFormulaScriptLoaded) {
        $.getScript("{{ asset('assets/v2/marketplace/js/stock-formula.js') }}", function() {
            window.stockFormulaScriptLoaded = true;
            // Initialize after script loads (for dynamic content)
            if (typeof window.initializeStockFormula === 'function') {
                window.initializeStockFormula();
            }
        });
    } else {
        // Script already loaded, just initialize
        if (typeof window.initializeStockFormula === 'function') {
            window.initializeStockFormula();
        }
    }
</script>

<style>
    .marketplace-formulas-list {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .marketplace-formula-card-modal {
        padding: 0.75rem;
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
        background-color: #fff;
    }
</style>
