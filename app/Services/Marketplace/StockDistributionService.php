<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStockModel;
use App\Models\Marketplace_model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class StockDistributionService
{
    /**
     * Calculate and distribute stock to marketplaces based on formulas
     *
     * @param int $variationId
     * @param int $stockChange The amount of stock added/removed (positive or negative)
     * @return array Distribution results
     */
    public function distributeStock($variationId, $stockChange)
    {
        if ($stockChange == 0) {
            return ['success' => false, 'message' => 'No stock change to distribute'];
        }

        // Get all marketplace stocks for this variation
        $marketplaceStocks = MarketplaceStockModel::where('variation_id', $variationId)
            ->with('marketplace')
            ->get();

        if ($marketplaceStocks->isEmpty()) {
            Log::info("No marketplace stocks found for variation {$variationId}");
            return ['success' => false, 'message' => 'No marketplace stocks found'];
        }

        $distributionResults = [];
        $totalDistributed = 0;
        $remainingStock = abs($stockChange);

        // First pass: Apply formulas to marketplaces
        foreach ($marketplaceStocks as $marketplaceStock) {
            $formula = $marketplaceStock->formula;

            if (!$formula || !isset($formula['marketplaces'])) {
                continue;
            }

            // Find this marketplace in the formula
            $marketplaceConfig = collect($formula['marketplaces'])
                ->firstWhere('marketplace_id', $marketplaceStock->marketplace_id);

            if (!$marketplaceConfig) {
                continue;
            }

            $oldValue = $marketplaceStock->listed_stock;
            $distribution = $this->calculateDistribution(
                $stockChange,
                $formula['type'],
                $marketplaceConfig['value']
            );

            // Only distribute if we have remaining stock
            if ($remainingStock > 0 && $distribution > 0) {
                $actualDistribution = min($distribution, $remainingStock);
                $newValue = $oldValue + $actualDistribution;

                // Store reserve values
                $marketplaceStock->reserve_old_value = $oldValue;
                $marketplaceStock->listed_stock = $newValue;
                $marketplaceStock->reserve_new_value = $newValue;
                $marketplaceStock->save();

                $totalDistributed += $actualDistribution;
                $remainingStock -= $actualDistribution;

                $distributionResults[] = [
                    'marketplace_id' => $marketplaceStock->marketplace_id,
                    'marketplace_name' => $marketplaceStock->marketplace->name ?? 'Unknown',
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'distribution' => $actualDistribution,
                ];

                Log::info("Distributed {$actualDistribution} units to marketplace {$marketplaceStock->marketplace_id} for variation {$variationId}");
            }
        }

        // Second pass: Distribute remaining stock to marketplace 1 if enabled
        if ($remainingStock > 0) {
            $marketplace1Stock = $marketplaceStocks->firstWhere('marketplace_id', 1);

            if ($marketplace1Stock) {
                $formula = $marketplace1Stock->formula;
                $shouldDistributeRemaining = $formula['remaining_to_marketplace_1'] ?? true;

                if ($shouldDistributeRemaining) {
                    $oldValue = $marketplace1Stock->listed_stock;
                    $newValue = $oldValue + $remainingStock;

                    $marketplace1Stock->reserve_old_value = $oldValue;
                    $marketplace1Stock->listed_stock = $newValue;
                    $marketplace1Stock->reserve_new_value = $newValue;
                    $marketplace1Stock->save();

                    $totalDistributed += $remainingStock;

                    $distributionResults[] = [
                        'marketplace_id' => 1,
                        'marketplace_name' => $marketplace1Stock->marketplace->name ?? 'Marketplace 1',
                        'old_value' => $oldValue,
                        'new_value' => $newValue,
                        'distribution' => $remainingStock,
                        'is_remaining' => true,
                    ];

                    Log::info("Distributed remaining {$remainingStock} units to marketplace 1 for variation {$variationId}");
                    $remainingStock = 0;
                }
            }
        }

        return [
            'success' => true,
            'variation_id' => $variationId,
            'stock_change' => $stockChange,
            'total_distributed' => $totalDistributed,
            'remaining_stock' => $remainingStock,
            'distributions' => $distributionResults,
        ];
    }

    /**
     * Calculate distribution amount based on formula type
     *
     * @param int $stockChange
     * @param string $type 'percentage' or 'fixed'
     * @param float $value The percentage or fixed value
     * @return int
     */
    private function calculateDistribution($stockChange, $type, $value)
    {
        if ($type === 'percentage') {
            // Calculate percentage of the stock change
            return (int) round(($stockChange * $value) / 100);
        } elseif ($type === 'fixed') {
            // Fixed amount, but don't exceed the stock change
            return min((int) $value, abs($stockChange));
        }

        return 0;
    }

    /**
     * Validate formula structure
     *
     * @param array $formula
     * @return array ['valid' => bool, 'errors' => []]
     */
    public function validateFormula($formula)
    {
        $errors = [];

        if (!is_array($formula)) {
            return ['valid' => false, 'errors' => ['Formula must be an array']];
        }

        if (!isset($formula['type']) || !in_array($formula['type'], ['percentage', 'fixed'])) {
            $errors[] = 'Formula type must be either "percentage" or "fixed"';
        }

        if (!isset($formula['marketplaces']) || !is_array($formula['marketplaces'])) {
            $errors[] = 'Formula must contain a "marketplaces" array';
        } else {
            foreach ($formula['marketplaces'] as $index => $marketplace) {
                if (!isset($marketplace['marketplace_id'])) {
                    $errors[] = "Marketplace at index {$index} is missing marketplace_id";
                }
                if (!isset($marketplace['value']) || !is_numeric($marketplace['value'])) {
                    $errors[] = "Marketplace at index {$index} is missing or invalid value";
                }
            }

            // Validate percentage totals
            if (isset($formula['type']) && $formula['type'] === 'percentage') {
                $totalPercentage = array_sum(array_column($formula['marketplaces'], 'value'));
                if ($totalPercentage > 100) {
                    $errors[] = "Total percentage exceeds 100% ({$totalPercentage}%)";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get all marketplace stocks for a variation with their formulas
     *
     * @param int $variationId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getMarketplaceStocksWithFormulas($variationId)
    {
        return MarketplaceStockModel::where('variation_id', $variationId)
            ->with('marketplace')
            ->get()
            ->map(function ($stock) {
                return [
                    'id' => $stock->id,
                    'marketplace_id' => $stock->marketplace_id,
                    'marketplace_name' => $stock->marketplace->name ?? 'Unknown',
                    'listed_stock' => $stock->listed_stock,
                    'formula' => $stock->formula,
                    'reserve_old_value' => $stock->reserve_old_value,
                    'reserve_new_value' => $stock->reserve_new_value,
                ];
            });
    }
}

