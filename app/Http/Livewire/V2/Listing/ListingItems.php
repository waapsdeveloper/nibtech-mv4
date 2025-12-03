<?php

namespace App\Http\Livewire\V2\Listing;

use Livewire\Component;

class ListingItems extends Component
{
    public array $variationData = []; // Array of ['id' => int, 'variation_data' => array|null]
    
    // Reference data
    public array $storages = [];
    public array $colors = [];
    public array $grades = [];
    public array $exchangeRates = [];
    public float $eurGbp = 0;
    public array $currencies = [];
    public array $currencySign = [];
    public array $countries = [];
    public array $marketplaces = [];
    public ?string $processId = null;

    public function mount(
        array $variationData,
        array $storages,
        array $colors,
        array $grades,
        array $exchangeRates,
        float $eurGbp,
        array $currencies,
        array $currencySign,
        array $countries,
        array $marketplaces,
        ?string $processId = null
    ): void {
        $this->variationData = $variationData;
        $this->storages = $storages;
        $this->colors = $colors;
        $this->grades = $grades;
        $this->exchangeRates = $exchangeRates;
        $this->eurGbp = $eurGbp;
        $this->currencies = $currencies;
        $this->currencySign = $currencySign;
        $this->countries = $countries;
        $this->marketplaces = $marketplaces;
        $this->processId = $processId;
    }

    public function render()
    {
        return view('livewire.v2.listing.listing-items');
    }
}

