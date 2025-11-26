<?php

namespace App\Services\V2;

use App\Models\Color_model;
use App\Models\Country_model;
use App\Models\Currency_model;
use App\Models\Grade_model;
use App\Models\Marketplace_model;
use App\Models\Storage_model;
use Illuminate\Support\Facades\Cache;

class ListingDataService
{
    /**
     * Get all dropdown/reference data for listings
     * Uses caching for performance
     */
    public function getReferenceData(): array
    {
        return Cache::remember('listing_reference_data', 3600, function () {
            return [
                'storages' => $this->getStorages(),
                'colors' => $this->getColors(),
                'grades' => $this->getGrades(),
                'currencies' => $this->getCurrencies(),
                'currency_sign' => $this->getCurrencySigns(),
                'countries' => $this->getCountries(),
                'marketplaces' => $this->getMarketplaces(),
            ];
        });
    }

    /**
     * Get storages data
     */
    private function getStorages(): array
    {
        $sessionStorages = session('dropdown_data')['storages'] ?? null;
        if ($sessionStorages) {
            return $sessionStorages->toArray();
        }

        return Storage_model::pluck('name', 'id')->toArray();
    }

    /**
     * Get colors data
     */
    private function getColors(): array
    {
        $sessionColors = session('dropdown_data')['colors'] ?? null;
        if ($sessionColors) {
            return $sessionColors->toArray();
        }

        return Color_model::pluck('name', 'id')->toArray();
    }

    /**
     * Get grades data (only active grades)
     */
    private function getGrades(): array
    {
        return Grade_model::where('id', '<', 6)->pluck('name', 'id')->toArray();
    }

    /**
     * Get currencies data
     */
    private function getCurrencies(): array
    {
        return Currency_model::pluck('code', 'id')->toArray();
    }

    /**
     * Get currency signs
     */
    private function getCurrencySigns(): array
    {
        return Currency_model::pluck('sign', 'id')->toArray();
    }

    /**
     * Get countries data as keyed array
     */
    private function getCountries(): array
    {
        return Country_model::all()->mapWithKeys(function ($country) {
            return [$country->id => $country];
        })->toArray();
    }

    /**
     * Get marketplaces data as keyed array
     */
    private function getMarketplaces(): array
    {
        return Marketplace_model::all()->mapWithKeys(function ($marketplace) {
            return [$marketplace->id => $marketplace];
        })->toArray();
    }

    /**
     * Clear reference data cache
     */
    public function clearCache(): void
    {
        Cache::forget('listing_reference_data');
    }
}

