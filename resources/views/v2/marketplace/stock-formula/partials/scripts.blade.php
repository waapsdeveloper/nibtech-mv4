<script>
    // Configuration
    window.StockFormulaConfig = {
        urls: {
            search: "{{ url('v2/marketplace/stock-formula/search') }}",
            getStocks: "{{ url('v2/marketplace/stock-formula') }}/",
            saveFormula: "{{ url('v2/marketplace/stock-formula') }}/",
            deleteFormula: "{{ url('v2/marketplace/stock-formula') }}/",
            resetStock: "{{ url('v2/marketplace/stock-formula') }}/",
        },
        csrfToken: "{{ csrf_token() }}",
        selectedVariationId: {{ $selectedVariation->id ?? 'null' }},
        marketplaces: @json($marketplaces->values()->all() ?? [])
    };
</script>
<script src="{{asset('assets/v2/marketplace/js/stock-formula.js')}}"></script>

