<?php

namespace App\Services\V2;

use App\Models\Variation_model;
use App\Models\ExchangeRate;
use Illuminate\Support\Collection;

class ListingCalculationService
{
    /**
     * Calculate variation statistics
     */
    public function calculateVariationStats(Variation_model $variation): array
    {
        $availableStocksCount = $variation->available_stocks ? $variation->available_stocks->count() : 0;
        $pendingOrdersCount = $variation->pending_orders ? $variation->pending_orders->count() : 0;
        $stockDifference = $availableStocksCount - $pendingOrdersCount;

        return [
            'available_stocks' => $availableStocksCount,
            'pending_orders' => $pendingOrdersCount,
            'stock_difference' => $stockDifference,
            'has_stock_issue' => $stockDifference < 0,
            'listed_stock' => $variation->listed_stock ?? 0,
        ];
    }

    /**
     * Calculate pricing information for listings
     */
    public function calculatePricingInfo(Collection $listings, array $exchangeRates, float $eurGbp): array
    {
        $pricingInfo = [
            'best_min_price' => null,
            'best_price' => null,
            'has_buybox' => false,
            'without_buybox_count' => 0,
            'total_listings' => $listings->count(),
        ];

        if ($listings->isEmpty()) {
            return $pricingInfo;
        }

        // Filter listings for France (country 73)
        $franceListings = $listings->filter(fn($listing) => $listing->country === 73);

        if ($franceListings->isNotEmpty()) {
            $pricingInfo['best_min_price'] = $franceListings->min('min_price');
            $pricingInfo['best_price'] = $franceListings->min('price');
        }

        $pricingInfo['has_buybox'] = $listings->contains(fn($listing) => $listing->buybox === 1);
        $pricingInfo['without_buybox_count'] = $listings->filter(fn($listing) => $listing->buybox !== 1)->count();

        return $pricingInfo;
    }

    /**
     * Calculate average cost from stocks
     */
    public function calculateAverageCost(Collection $stocks): float
    {
        if ($stocks->isEmpty()) {
            return 0.0;
        }

        $totalCost = $stocks->sum('cost');
        $count = $stocks->count();

        return $count > 0 ? round($totalCost / $count, 2) : 0.0;
    }

    /**
     * Format variation state
     */
    public function formatVariationState(?int $state): string
    {
        return match ($state) {
            0 => 'Missing price/comment',
            1 => 'Pending validation',
            2 => 'Online',
            3 => 'Offline',
            4 => 'Deactivated',
            default => 'Unknown',
        };
    }

    /**
     * Format handler status
     */
    public function formatHandlerStatus(?int $status): string
    {
        return match ($status) {
            1 => 'Active',
            2 => 'Error',
            3 => 'Inactive',
            default => 'Unknown',
        };
    }

    /**
     * Get exchange rate data
     */
    public function getExchangeRateData(): array
    {
        $eurGbp = ExchangeRate::where('target_currency', 'GBP')->first()?->rate ?? 0;
        $exchangeRates = ExchangeRate::pluck('rate', 'target_currency')->toArray();

        return [
            'eur_gbp' => $eurGbp,
            'exchange_rates' => $exchangeRates,
        ];
    }
}

