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
     * @param int|null $totalStock The total stock after change (for apply_to: total)
     * @param bool $ignoreRemaining If true, don't add remaining stock to marketplace 1
     * @return array Distribution results
     */
    public function distributeStock($variationId, $stockChange, $totalStock = null, $ignoreRemaining = false)
    {
        if ($stockChange == 0) {
            return ['success' => false, 'message' => 'No stock change to distribute'];
        }

        // Get all marketplaces
        $allMarketplaces = Marketplace_model::orderBy('id', 'ASC')->get();
        
        if ($allMarketplaces->isEmpty()) {
            Log::info("No marketplaces found for variation {$variationId}");
            return ['success' => false, 'message' => 'No marketplaces found'];
        }

        // Get existing marketplace stocks for this variation
        $existingStocks = MarketplaceStockModel::where('variation_id', $variationId)
            ->with('marketplace')
            ->get()
            ->keyBy('marketplace_id');

        $distributionResults = [];
        $totalDistributed = 0;
        $remainingStock = abs($stockChange);

        // Get variation to determine total stock if needed
        $variation = \App\Models\Variation_model::find($variationId);
        if (!$totalStock && $variation) {
            $totalStock = $variation->listed_stock;
        }

        // Get admin ID for creating new records
        $adminId = auth()->id() ?? session('user_id') ?? 1;

        // Apply each marketplace's formula (skip marketplace 1 as it gets remaining stock)
        foreach ($allMarketplaces as $marketplace) {
            // Skip marketplace 1 - it will get remaining stock at the end
            if ($marketplace->id == 1) {
                continue;
            }

            // Get or create marketplace stock record
            $marketplaceStock = $existingStocks->get($marketplace->id);
            if (!$marketplaceStock) {
                $marketplaceStock = MarketplaceStockModel::create([
                    'variation_id' => $variationId,
                    'marketplace_id' => $marketplace->id,
                    'listed_stock' => 0,
                    'admin_id' => $adminId,
                ]);
                $existingStocks->put($marketplace->id, $marketplaceStock);
            }

            $formula = $marketplaceStock->formula;

            // Skip if no formula
            if (!$formula || !isset($formula['value']) || !isset($formula['type'])) {
                continue;
            }

            // Determine base value to apply formula to
            $baseValue = ($formula['apply_to'] ?? 'pushed') === 'total' ? $totalStock : $stockChange;

            // Log formula details for debugging
            Log::info("Calculating distribution for marketplace {$marketplace->id} ({$marketplace->name})", [
                'variation_id' => $variationId,
                'formula_type' => $formula['type'],
                'formula_value' => $formula['value'],
                'apply_to' => $formula['apply_to'] ?? 'pushed',
                'base_value' => $baseValue,
                'stock_change' => $stockChange,
                'total_stock' => $totalStock,
            ]);

            // Calculate distribution based on formula
            $distribution = $this->calculateDistribution(
                $baseValue,
                $formula['type'],
                $formula['value']
            );
            
            Log::info("Distribution calculated for marketplace {$marketplace->id}", [
                'calculated_distribution' => $distribution,
            ]);

            if ($distribution > 0 && $remainingStock > 0) {
                $oldValue = $marketplaceStock->listed_stock;
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
                    'marketplace_name' => $marketplace->name ?? 'Unknown',
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'distribution' => $actualDistribution,
                ];

                Log::info("Distributed {$actualDistribution} units to marketplace {$marketplaceStock->marketplace_id} ({$marketplace->name}) for variation {$variationId}");
            }
        }

        // Distribute remaining stock to marketplace 1 if there's any left (unless ignoreRemaining is true)
        if ($remainingStock > 0 && !$ignoreRemaining) {
            // Get or create marketplace 1 stock record
            $marketplace1Stock = $existingStocks->get(1);
            if (!$marketplace1Stock) {
                $marketplace1 = Marketplace_model::find(1);
                $marketplace1Stock = MarketplaceStockModel::create([
                    'variation_id' => $variationId,
                    'marketplace_id' => 1,
                    'listed_stock' => 0,
                    'admin_id' => $adminId,
                ]);
            }

            $oldValue = $marketplace1Stock->listed_stock;
            $newValue = $oldValue + $remainingStock;

            $marketplace1Stock->reserve_old_value = $oldValue;
            $marketplace1Stock->listed_stock = $newValue;
            $marketplace1Stock->reserve_new_value = $newValue;
            $marketplace1Stock->save();

            $totalDistributed += $remainingStock;

            $marketplace1Name = $marketplace1Stock->marketplace->name ?? 'Marketplace 1';
            $distributionResults[] = [
                'marketplace_id' => 1,
                'marketplace_name' => $marketplace1Name,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'distribution' => $remainingStock,
                'is_remaining' => true,
            ];

            Log::info("Distributed remaining {$remainingStock} units to marketplace 1 ({$marketplace1Name}) for variation {$variationId}");
            $remainingStock = 0;
        } else if ($remainingStock > 0 && $ignoreRemaining) {
            Log::info("Ignoring remaining {$remainingStock} units for variation {$variationId} (exact stock set mode)");
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
     * @param int $baseValue The base value to apply formula to (stockChange or totalStock)
     * @param string $type 'percentage' or 'fixed'
     * @param float $value The percentage or fixed value
     * @return int
     */
    private function calculateDistribution($baseValue, $type, $value)
    {
        if ($type === 'percentage') {
            // Calculate percentage of the base value
            // Value should be stored as a number like 5 for 5%, not 0.05
            $result = (int) round(($baseValue * $value) / 100);
            
            Log::info("Percentage calculation", [
                'base_value' => $baseValue,
                'percentage_value' => $value,
                'calculation' => "($baseValue * $value) / 100",
                'result' => $result,
            ]);
            
            return $result;
        } elseif ($type === 'fixed') {
            // Fixed amount (value is the exact number to distribute)
            return (int) $value;
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

