<div>
    @foreach($variationData as $index => $variationItem)
        @php
            $variationId = $variationItem['id'] ?? null;
            // Pass the entire variation item which includes both variation_data and calculated_stats
            $preloadedVariationData = $variationItem ?? null;
        @endphp
        @livewire('v2.listing.listing-item', [
            'variationId' => $variationId,
            'rowNumber' => $index + 1,
            'preloadedVariationData' => $preloadedVariationData,
            'storages' => $storages,
            'colors' => $colors,
            'grades' => $grades,
            'exchangeRates' => $exchangeRates,
            'eurGbp' => $eurGbp,
            'currencies' => $currencies,
            'currencySign' => $currencySign,
            'countries' => $countries,
            'marketplaces' => $marketplaces,
            'processId' => $processId,
        ], key('v2-listing-item-' . $variationId))
    @endforeach
</div>

