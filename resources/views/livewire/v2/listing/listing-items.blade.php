<div>
    @foreach($variationIds as $index => $variationId)
        @livewire('v2.listing.listing-item', [
            'variationId' => $variationId,
            'rowNumber' => $index + 1,
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

