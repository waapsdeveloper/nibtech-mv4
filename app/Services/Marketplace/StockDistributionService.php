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
        
        // Determine if this is a subtraction (negative stock change)
        $isSubtraction = $stockChange < 0;
        $remainingStock = abs($stockChange); // Use abs only for tracking remaining amount to process

        // Get variation to determine total stock if needed
        $variation = \App\Models\Variation_model::find($variationId);
        if (!$totalStock && $variation) {
            $totalStock = $variation->listed_stock;
        }

        // Check min_stock_required before allowing additions (check marketplace stock first, then defaults)
        if (!$isSubtraction && $stockChange > 0) {
            $minStockRequired = null;
            
            // Check if any marketplace stock has min_stock_required set
            foreach ($existingStocks as $stock) {
                if ($stock->min_stock_required !== null) {
                    $minStockRequired = $stock->min_stock_required;
                    break;
                }
            }
            
            // If not found, check defaults (variation default first, then global)
            if ($minStockRequired === null) {
                $defaultThresholds = $this->getDefaultThresholds($variation, 1); // Check marketplace 1 for global defaults
                $minStockRequired = $defaultThresholds['min_stock_required'];
            }
            
            if ($minStockRequired !== null && $totalStock < $minStockRequired) {
                Log::info("Stock addition blocked: total stock ({$totalStock}) is below minimum required ({$minStockRequired}) for variation {$variationId}");
                return [
                    'success' => false, 
                    'message' => "Cannot add stock: Total stock ({$totalStock}) is below minimum required ({$minStockRequired})"
                ];
            }
        }

        // Check if total stock is below min_threshold - if so, only add to BackMarket (marketplace 1)
        // Check marketplace stocks first, then defaults
        $shouldOnlyAddToBackMarket = false;
        if (!$isSubtraction && $stockChange > 0) {
            $minThreshold = null;
            
            // Check if any marketplace stock has a min_threshold
            foreach ($existingStocks as $stock) {
                if ($stock->min_threshold !== null) {
                    $minThreshold = $stock->min_threshold;
                    if ($totalStock < $minThreshold) {
                        $shouldOnlyAddToBackMarket = true;
                        Log::info("Total stock ({$totalStock}) is below min_threshold ({$minThreshold}) for variation {$variationId}. Only adding to BackMarket.");
                        break;
                    }
                }
            }
            
            // If not found in marketplace stocks, check defaults
            if (!$shouldOnlyAddToBackMarket) {
                $defaultThresholds = $this->getDefaultThresholds($variation, 1); // Check marketplace 1 for global defaults
                $minThreshold = $defaultThresholds['min_threshold'];
                if ($minThreshold !== null && $totalStock < $minThreshold) {
                    $shouldOnlyAddToBackMarket = true;
                    Log::info("Total stock ({$totalStock}) is below default min_threshold ({$minThreshold}) for variation {$variationId}. Only adding to BackMarket.");
                }
            }
        }

        // Get admin ID for creating new records
        $adminId = auth()->id() ?? session('user_id') ?? 1;

        // For subtraction, we need to subtract from marketplaces in reverse order (marketplace 1 last)
        // For addition, we process other marketplaces first, then marketplace 1 gets remaining
        $marketplacesToProcess = $isSubtraction 
            ? $allMarketplaces->reverse() 
            : $allMarketplaces;

        // If below threshold and adding stock, skip formula distribution and add all to marketplace 1
        if (!$isSubtraction && $shouldOnlyAddToBackMarket) {
            // Skip the formula loop - we'll add all stock to marketplace 1 at the end
        } else {
            // Apply each marketplace's formula
            foreach ($marketplacesToProcess as $marketplace) {
                // For addition: skip marketplace 1 - it will get remaining stock at the end
                // For subtraction: process marketplace 1 first
                if (!$isSubtraction && $marketplace->id == 1) {
                    continue;
                }

            // Get or create marketplace stock record
            $marketplaceStock = $existingStocks->get($marketplace->id);
            if (!$marketplaceStock) {
                // For subtraction, if marketplace doesn't exist, skip it (can't subtract from 0)
                if ($isSubtraction) {
                    continue;
                }
                $marketplaceStock = MarketplaceStockModel::create([
                    'variation_id' => $variationId,
                    'marketplace_id' => $marketplace->id,
                    'listed_stock' => 0,
                    'locked_stock' => 0,
                    // available_stock will be automatically calculated by model observer (0 - 0 = 0)
                    'admin_id' => $adminId,
                ]);
                $existingStocks->put($marketplace->id, $marketplaceStock);
            }

            $formula = $marketplaceStock->formula;

            // If no specific formula, check for defaults (variation default first, then global default)
            if (!$formula || !isset($formula['value']) || !isset($formula['type'])) {
                $formula = $this->getDefaultFormula($variation, $marketplace->id);
                
                // If we got a default formula, also apply default thresholds if marketplace stock doesn't have them
                if ($formula && isset($formula['value']) && isset($formula['type'])) {
                    $defaultThresholds = $this->getDefaultThresholds($variation, $marketplace->id);
                    if ($marketplaceStock->min_threshold === null && isset($defaultThresholds['min_threshold'])) {
                        $marketplaceStock->min_threshold = $defaultThresholds['min_threshold'];
                    }
                    if ($marketplaceStock->max_threshold === null && isset($defaultThresholds['max_threshold'])) {
                        $marketplaceStock->max_threshold = $defaultThresholds['max_threshold'];
                    }
                    if ($marketplaceStock->min_stock_required === null && isset($defaultThresholds['min_stock_required'])) {
                        $marketplaceStock->min_stock_required = $defaultThresholds['min_stock_required'];
                    }
                }
            }

            // For subtraction without formula, subtract proportionally or from marketplace 1
            if (!$formula || !isset($formula['value']) || !isset($formula['type'])) {
                // For subtraction, if no formula, subtract from marketplace 1 first
                if ($isSubtraction && $marketplace->id == 1 && $remainingStock > 0) {
                    $oldValue = $marketplaceStock->listed_stock;
                    $actualSubtraction = min($remainingStock, $oldValue); // Can't subtract more than available
                    $newValue = max(0, $oldValue - $actualSubtraction); // Don't go below 0

                    $marketplaceStock->reserve_old_value = $oldValue;
                    $marketplaceStock->listed_stock = $newValue;
                    $marketplaceStock->reserve_new_value = $newValue;
                    $marketplaceStock->save();

                    $totalDistributed += $actualSubtraction;
                    $remainingStock -= $actualSubtraction;

                    $distributionResults[] = [
                        'marketplace_id' => $marketplaceStock->marketplace_id,
                        'marketplace_name' => $marketplace->name ?? 'Unknown',
                        'old_value' => $oldValue,
                        'new_value' => $newValue,
                        'distribution' => -$actualSubtraction, // Negative to indicate subtraction
                    ];

                    Log::info("Subtracted {$actualSubtraction} units from marketplace {$marketplaceStock->marketplace_id} ({$marketplace->name}) for variation {$variationId}");
                }
                continue;
            }

            // Determine base value to apply formula to
            // For subtraction with apply_to: total, use absolute value of total stock
            // For subtraction with apply_to: pushed, use absolute value of stock change
            $baseValue = ($formula['apply_to'] ?? 'pushed') === 'total' 
                ? ($isSubtraction ? abs($totalStock) : $totalStock)
                : ($isSubtraction ? abs($stockChange) : $stockChange);

            // Log formula details for debugging
            Log::info("Calculating distribution for marketplace {$marketplace->id} ({$marketplace->name})", [
                'variation_id' => $variationId,
                'formula_type' => $formula['type'],
                'formula_value' => $formula['value'],
                'apply_to' => $formula['apply_to'] ?? 'pushed',
                'base_value' => $baseValue,
                'stock_change' => $stockChange,
                'total_stock' => $totalStock,
                'is_subtraction' => $isSubtraction,
            ]);

            // Calculate distribution based on formula
            $distribution = $this->calculateDistribution(
                $baseValue,
                $formula['type'],
                $formula['value']
            );
            
            Log::info("Distribution calculated for marketplace {$marketplace->id}", [
                'calculated_distribution' => $distribution,
                'is_subtraction' => $isSubtraction,
            ]);

            if ($distribution > 0 && $remainingStock > 0) {
                $oldValue = $marketplaceStock->listed_stock;
                
                if ($isSubtraction) {
                    // For subtraction: subtract the calculated amount (but not more than available)
                    $actualDistribution = min($distribution, $remainingStock, $oldValue);
                    $newValue = max(0, $oldValue - $actualDistribution);
                } else {
                    // For addition: add the calculated amount
                    $actualDistribution = min($distribution, $remainingStock);
                    
                    // Check max_threshold if set
                    if ($marketplaceStock->max_threshold !== null) {
                        $maxAllowed = $marketplaceStock->max_threshold;
                        $potentialNewValue = $oldValue + $actualDistribution;
                        if ($potentialNewValue > $maxAllowed) {
                            $actualDistribution = max(0, $maxAllowed - $oldValue);
                            Log::info("Distribution capped by max_threshold for marketplace {$marketplace->id}: {$actualDistribution} units (max: {$maxAllowed})");
                        }
                    }
                    
                    $newValue = $oldValue + $actualDistribution;
                }

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
                    'distribution' => $isSubtraction ? -$actualDistribution : $actualDistribution,
                ];

                $action = $isSubtraction ? 'Subtracted' : 'Distributed';
                Log::info("{$action} {$actualDistribution} units " . ($isSubtraction ? 'from' : 'to') . " marketplace {$marketplaceStock->marketplace_id} ({$marketplace->name}) for variation {$variationId}");
            }
            }
        }

        // For addition: Distribute remaining stock to marketplace 1 if there's any left (unless ignoreRemaining is true)
        // If below threshold, all stock should go to marketplace 1
        if (!$isSubtraction && $remainingStock > 0 && !$ignoreRemaining) {
            // Get or create marketplace 1 stock record
            $marketplace1Stock = $existingStocks->get(1);
            if (!$marketplace1Stock) {
                $marketplace1 = Marketplace_model::find(1);
                $marketplace1Stock = MarketplaceStockModel::create([
                    'variation_id' => $variationId,
                    'marketplace_id' => 1,
                    'listed_stock' => 0,
                    'locked_stock' => 0,
                    // available_stock will be automatically calculated by model observer (0 - 0 = 0)
                    'admin_id' => $adminId,
                ]);
            }

            $oldValue = $marketplace1Stock->listed_stock;
            
            // If below threshold, all stock goes to marketplace 1
            if ($shouldOnlyAddToBackMarket) {
                $remainingStock = abs($stockChange); // Use all the stock change
            }
            
            // Check max_threshold for marketplace 1
            if ($marketplace1Stock->max_threshold !== null) {
                $maxAllowed = $marketplace1Stock->max_threshold;
                $potentialNewValue = $oldValue + $remainingStock;
                if ($potentialNewValue > $maxAllowed) {
                    $remainingStock = max(0, $maxAllowed - $oldValue);
                    Log::info("Marketplace 1 distribution capped by max_threshold: {$remainingStock} units (max: {$maxAllowed})");
                }
            }
            
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
        } else if (!$isSubtraction && $remainingStock > 0 && $ignoreRemaining) {
            Log::info("Ignoring remaining {$remainingStock} units for variation {$variationId} (exact stock set mode)");
        } else if ($isSubtraction && $remainingStock > 0) {
            Log::warning("Could not subtract all {$remainingStock} units for variation {$variationId} - insufficient stock in marketplaces");
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

    /**
     * Get default formula for a marketplace (checks variation default first, then global default)
     *
     * @param \App\Models\Variation_model $variation
     * @param int $marketplaceId
     * @return array|null Formula array or null if no default found
     */
    private function getDefaultFormula($variation, $marketplaceId)
    {
        // Priority 1: Check variation-specific default formula
        if ($variation && $variation->default_stock_formula && is_array($variation->default_stock_formula)) {
            $variationDefault = $variation->default_stock_formula;
            if (isset($variationDefault['value']) && isset($variationDefault['type'])) {
                Log::info("Using variation default formula for variation {$variation->id}, marketplace {$marketplaceId}");
                return $variationDefault;
            }
        }

        // Priority 2: Check global marketplace default formula
        $globalDefault = \App\Models\MarketplaceDefaultFormula::getActiveForMarketplace($marketplaceId);
        if ($globalDefault && $globalDefault->formula && is_array($globalDefault->formula)) {
            $globalFormula = $globalDefault->formula;
            if (isset($globalFormula['value']) && isset($globalFormula['type'])) {
                Log::info("Using global default formula for marketplace {$marketplaceId}");
                return $globalFormula;
            }
        }

        return null;
    }

    /**
     * Get default thresholds for a marketplace (checks variation default first, then global default)
     *
     * @param \App\Models\Variation_model $variation
     * @param int $marketplaceId
     * @return array Array with min_threshold, max_threshold, min_stock_required keys
     */
    private function getDefaultThresholds($variation, $marketplaceId)
    {
        $thresholds = [
            'min_threshold' => null,
            'max_threshold' => null,
            'min_stock_required' => null,
        ];

        // Priority 1: Check variation-specific default thresholds
        if ($variation) {
            if ($variation->default_min_threshold !== null) {
                $thresholds['min_threshold'] = $variation->default_min_threshold;
            }
            if ($variation->default_max_threshold !== null) {
                $thresholds['max_threshold'] = $variation->default_max_threshold;
            }
            if ($variation->default_min_stock_required !== null) {
                $thresholds['min_stock_required'] = $variation->default_min_stock_required;
            }
        }

        // Priority 2: Check global marketplace default thresholds (only if variation doesn't have them)
        $globalDefault = \App\Models\MarketplaceDefaultFormula::getActiveForMarketplace($marketplaceId);
        if ($globalDefault) {
            if ($thresholds['min_threshold'] === null && $globalDefault->min_threshold !== null) {
                $thresholds['min_threshold'] = $globalDefault->min_threshold;
            }
            if ($thresholds['max_threshold'] === null && $globalDefault->max_threshold !== null) {
                $thresholds['max_threshold'] = $globalDefault->max_threshold;
            }
            if ($thresholds['min_stock_required'] === null && $globalDefault->min_stock_required !== null) {
                $thresholds['min_stock_required'] = $globalDefault->min_stock_required;
            }
        }

        return $thresholds;
    }
}

