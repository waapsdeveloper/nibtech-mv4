<?php

namespace App\Services\V2;

use App\Models\Variation_model;
use App\Models\ExchangeRate;
use App\Models\Order_item_model;
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
            'buybox_count' => 0,
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
        $pricingInfo['buybox_count'] = $listings->filter(fn($listing) => $listing->buybox === 1)->count();
        $pricingInfo['without_buybox_count'] = $listings->filter(fn($listing) => $listing->buybox !== 1)->count();

        return $pricingInfo;
    }

    /**
     * Calculate total orders count for a variation (sales orders only)
     */
    public function calculateTotalOrdersCount(int $variationId): int
    {
        return \App\Models\Order_item_model::where('variation_id', $variationId)
            ->whereHas('order', function($q) {
                $q->where('order_type_id', 3); // Sales orders only
            })
            ->count();
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
     * Calculate marketplace order summaries for a variation
     * Returns array keyed by marketplace ID with order summary data
     * Includes ALL marketplaces that have listings, even if they have no orders
     */
    public function calculateMarketplaceSummaries(int $variationId, Collection $listings): array
    {
        // Get unique marketplace IDs from listings
        $marketplaceIds = $listings->pluck('marketplace_id')
            ->filter()
            ->unique()
            ->values();

        // Also include marketplace_id = 1 (BackMarket) if not already in the list
        // This ensures we show data for BackMarket even if there are no listings
        // since most orders are likely from BackMarket (marketplace_id = 1)
        if (!$marketplaceIds->contains(1)) {
            $marketplaceIds->push(1);
        }

        $summaries = [];

        foreach ($marketplaceIds as $marketplaceId) {
            // Initialize with empty values
            $summaries[$marketplaceId] = [
                'today_count' => 0,
                'today_total' => 0.0,
                'yesterday_count' => 0,
                'yesterday_total' => 0.0,
                'last_7_days_count' => 0,
                'last_7_days_total' => 0.0,
                'last_14_days_count' => 0,
                'last_14_days_total' => 0.0,
                'last_30_days_count' => 0,
                'last_30_days_total' => 0.0,
                'pending_count' => 0,
            ];
            
            // IMPORTANT: For marketplace 1, combine orders with marketplace_id = null OR marketplace_id = 1
            // For other marketplaces (2, 3, 4, etc.), ONLY get orders with that specific marketplace_id
            // This ensures null marketplace orders are ONLY counted for marketplace 1, not duplicated across other marketplaces
            $effectiveMarketplaceId = ($marketplaceId === null || $marketplaceId == 1) ? 1 : $marketplaceId;
            
            \Log::debug('Calculating marketplace summary', [
                'variation_id' => $variationId,
                'marketplace_id' => $marketplaceId,
                'effective_marketplace_id' => $effectiveMarketplaceId,
                'will_include_null' => $effectiveMarketplaceId == 1
            ]);
            
            // Today's orders
            $todayOrders = \App\Models\Order_item_model::where('variation_id', $variationId)
                ->whereHas('order', function($q) use ($effectiveMarketplaceId) {
                    if ($effectiveMarketplaceId == 1) {
                        // For marketplace 1, include null and 1
                        $q->where(function($query) {
                            $query->whereNull('marketplace_id')
                                  ->orWhere('marketplace_id', 1);
                        });
                    } else {
                        // For other marketplaces, only that specific ID
                        $q->where('marketplace_id', $effectiveMarketplaceId);
                    }
                    $q->where('order_type_id', 3)
                      ->whereBetween('created_at', [now()->startOfDay(), now()]);
                })
                ->with('order.currency_id')
                ->get();

            // Yesterday's orders
            $yesterdayOrders = \App\Models\Order_item_model::where('variation_id', $variationId)
                ->whereHas('order', function($q) use ($effectiveMarketplaceId) {
                    if ($effectiveMarketplaceId == 1) {
                        $q->where(function($query) {
                            $query->whereNull('marketplace_id')
                                  ->orWhere('marketplace_id', 1);
                        });
                    } else {
                        $q->where('marketplace_id', $effectiveMarketplaceId);
                    }
                    $q->where('order_type_id', 3)
                      ->whereBetween('created_at', [now()->yesterday()->startOfDay(), now()->yesterday()->endOfDay()]);
                })
                ->with('order.currency_id')
                ->get();

            // Last 7 days orders
            $last7DaysOrders = \App\Models\Order_item_model::where('variation_id', $variationId)
                ->whereHas('order', function($q) use ($effectiveMarketplaceId) {
                    if ($effectiveMarketplaceId == 1) {
                        $q->where(function($query) {
                            $query->whereNull('marketplace_id')
                                  ->orWhere('marketplace_id', 1);
                        });
                    } else {
                        $q->where('marketplace_id', $effectiveMarketplaceId);
                    }
                    $q->where('order_type_id', 3)
                      ->whereBetween('created_at', [now()->subDays(7)->startOfDay(), now()->yesterday()->endOfDay()]);
                })
                ->with('order.currency_id')
                ->get();

            // Last 14 days orders
            $last14DaysOrders = \App\Models\Order_item_model::where('variation_id', $variationId)
                ->whereHas('order', function($q) use ($effectiveMarketplaceId) {
                    if ($effectiveMarketplaceId == 1) {
                        $q->where(function($query) {
                            $query->whereNull('marketplace_id')
                                  ->orWhere('marketplace_id', 1);
                        });
                    } else {
                        $q->where('marketplace_id', $effectiveMarketplaceId);
                    }
                    $q->where('order_type_id', 3)
                      ->whereBetween('created_at', [now()->subDays(14)->startOfDay(), now()->yesterday()->endOfDay()]);
                })
                ->with('order.currency_id')
                ->get();

            // Last 30 days orders
            $last30DaysOrders = \App\Models\Order_item_model::where('variation_id', $variationId)
                ->whereHas('order', function($q) use ($effectiveMarketplaceId) {
                    if ($effectiveMarketplaceId == 1) {
                        $q->where(function($query) {
                            $query->whereNull('marketplace_id')
                                  ->orWhere('marketplace_id', 1);
                        });
                    } else {
                        $q->where('marketplace_id', $effectiveMarketplaceId);
                    }
                    $q->where('order_type_id', 3)
                      ->whereBetween('created_at', [now()->subDays(30)->startOfDay(), now()->yesterday()->endOfDay()]);
                })
                ->with('order.currency_id')
                ->get();

            // Calculate pending orders
            $pendingOrders = \App\Models\Order_model::whereHas('order_items', function($q) use ($variationId) {
                    $q->where('variation_id', $variationId);
                })
                ->where(function($q) use ($effectiveMarketplaceId) {
                    if ($effectiveMarketplaceId == 1) {
                        $q->whereNull('marketplace_id')
                          ->orWhere('marketplace_id', 1);
                    } else {
                        $q->where('marketplace_id', $effectiveMarketplaceId);
                    }
                })
                ->where('status', 2)
                ->where('order_type_id', 3)
                ->count();

            // Update with actual values (if any orders found)
            // Convert prices to EUR before summing (same logic as sales data)
            $summaries[$marketplaceId]['today_count'] = $todayOrders->count();
            $summaries[$marketplaceId]['today_total'] = round($todayOrders->sum(function($item) {
                if ($item->order && $item->order->currency != 4 && $item->order->currency_id) {
                    $rate = ExchangeRate::where('target_currency', $item->order->currency_id->code)->first()?->rate ?? 1;
                    return $item->price / $rate;
                }
                return $item->price;
            }), 2);
            
            $summaries[$marketplaceId]['yesterday_count'] = $yesterdayOrders->count();
            $summaries[$marketplaceId]['yesterday_total'] = round($yesterdayOrders->sum(function($item) {
                if ($item->order && $item->order->currency != 4 && $item->order->currency_id) {
                    $rate = ExchangeRate::where('target_currency', $item->order->currency_id->code)->first()?->rate ?? 1;
                    return $item->price / $rate;
                }
                return $item->price;
            }), 2);
            
            $summaries[$marketplaceId]['last_7_days_count'] = $last7DaysOrders->count();
            $summaries[$marketplaceId]['last_7_days_total'] = round($last7DaysOrders->sum(function($item) {
                if ($item->order && $item->order->currency != 4 && $item->order->currency_id) {
                    $rate = ExchangeRate::where('target_currency', $item->order->currency_id->code)->first()?->rate ?? 1;
                    return $item->price / $rate;
                }
                return $item->price;
            }), 2);
            
            $summaries[$marketplaceId]['last_14_days_count'] = $last14DaysOrders->count();
            $summaries[$marketplaceId]['last_14_days_total'] = round($last14DaysOrders->sum(function($item) {
                if ($item->order && $item->order->currency != 4 && $item->order->currency_id) {
                    $rate = ExchangeRate::where('target_currency', $item->order->currency_id->code)->first()?->rate ?? 1;
                    return $item->price / $rate;
                }
                return $item->price;
            }), 2);
            
            $summaries[$marketplaceId]['last_30_days_count'] = $last30DaysOrders->count();
            $summaries[$marketplaceId]['last_30_days_total'] = round($last30DaysOrders->sum(function($item) {
                if ($item->order && $item->order->currency != 4 && $item->order->currency_id) {
                    $rate = ExchangeRate::where('target_currency', $item->order->currency_id->code)->first()?->rate ?? 1;
                    return $item->price / $rate;
                }
                return $item->price;
            }), 2);
            
            $summaries[$marketplaceId]['pending_count'] = $pendingOrders;
        }

        \Log::info('Marketplace summaries calculated in ListingCalculationService', [
            'variation_id' => $variationId,
            'summaries' => $summaries
        ]);
        return $summaries;
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

    /**
     * Calculate sales data for a variation (matching get_sales format)
     * Returns formatted HTML string with sales averages
     */
    public function calculateSalesData(int $variationId): string
    {
        $today = $this->getTodayAverage($variationId);
        $yesterday = $this->getYesterdayAverage($variationId);
        $last7Days = $this->getLastWeekAverage($variationId);
        $last14Days = $this->get2WeekAverage($variationId);
        $last30Days = $this->get30DaysAverage($variationId);

        return "Avg: " . $today . " - " . $yesterday . "<br>" . $last7Days . " - " . $last14Days . " - " . $last30Days;
    }

    /**
     * Get today's average price and count
     */
    private function getTodayAverage(int $variationId): string
    {
        $orderItems = Order_item_model::where('variation_id', $variationId)
            ->whereHas('order', function($q) {
                // Include ALL marketplaces (null, 1, 2, 3, 4, etc.) for parent total calculation
                $q->whereBetween('created_at', [now()->startOfDay(), now()])
                  ->where('order_type_id', 3);
                  // No marketplace filter - include all marketplaces
            })
            ->with('order.currency_id')
            ->get();

        // Calculate total first (same way as marketplace summaries), then calculate average
        // This ensures consistency between total and average calculations
        $total = $orderItems->sum(function($item) {
            if ($item->order && $item->order->currency != 4 && $item->order->currency_id) {
                $rate = ExchangeRate::where('target_currency', $item->order->currency_id->code)->first()?->rate ?? 1;
                return $item->price / $rate;
            }
            return $item->price;
        });

        $count = $orderItems->count();
        $avgPrice = $count > 0 ? $total / $count : 0;
        $formatted = $avgPrice ? number_format($avgPrice, 2) : '0.00';

        return "Today: €" . $formatted . " (" . $count . ")";
    }

    /**
     * Get yesterday's average price and count
     */
    private function getYesterdayAverage(int $variationId): string
    {
        $orderItems = Order_item_model::where('variation_id', $variationId)
            ->whereHas('order', function($q) {
                // Include ALL marketplaces (null, 1, 2, 3, 4, etc.) for parent total calculation
                $q->whereBetween('created_at', [now()->yesterday()->startOfDay(), now()->yesterday()->endOfDay()])
                  ->where('order_type_id', 3);
                  // No marketplace filter - include all marketplaces
            })
            ->with('order.currency_id')
            ->get();

        // Calculate total first (same way as marketplace summaries), then calculate average
        // This ensures consistency between total and average calculations
        $total = $orderItems->sum(function($item) {
            if ($item->order && $item->order->currency != 4 && $item->order->currency_id) {
                $rate = ExchangeRate::where('target_currency', $item->order->currency_id->code)->first()?->rate ?? 1;
                return $item->price / $rate;
            }
            return $item->price;
        });

        $count = $orderItems->count();
        $avgPrice = $count > 0 ? $total / $count : 0;
        $formatted = $avgPrice ? number_format($avgPrice, 2) : '0.00';

        return "Yesterday: €" . $formatted . " (" . $count . ")";
    }

    /**
     * Get last 7 days average price and count
     */
    private function getLastWeekAverage(int $variationId): string
    {
        $orderItems = Order_item_model::where('variation_id', $variationId)
            ->whereHas('order', function($q) {
                // Include ALL marketplaces (null, 1, 2, 3, 4, etc.) for parent total calculation
                $q->whereBetween('created_at', [now()->subDays(7)->startOfDay(), now()->yesterday()->endOfDay()])
                  ->where('order_type_id', 3);
                  // No marketplace filter - include all marketplaces
            })
            ->with('order.currency_id')
            ->get();

        // Calculate total first (same way as marketplace summaries), then calculate average
        // This ensures consistency between total and average calculations
        $total = $orderItems->sum(function($item) {
            if ($item->order && $item->order->currency != 4 && $item->order->currency_id) {
                $rate = ExchangeRate::where('target_currency', $item->order->currency_id->code)->first()?->rate ?? 1;
                return $item->price / $rate;
            }
            return $item->price;
        });

        $count = $orderItems->count();
        $avgPrice = $count > 0 ? $total / $count : 0;
        $formatted = $avgPrice ? number_format($avgPrice, 2) : '0.00';

        return "7 days: €" . $formatted . " (" . $count . ")";
    }

    /**
     * Get last 14 days average price and count
     */
    private function get2WeekAverage(int $variationId): string
    {
        $orderItems = Order_item_model::where('variation_id', $variationId)
            ->whereHas('order', function($q) {
                // Include ALL marketplaces (null, 1, 2, 3, 4, etc.) for parent total calculation
                $q->whereBetween('created_at', [now()->subDays(14)->startOfDay(), now()->yesterday()->endOfDay()])
                  ->where('order_type_id', 3);
                  // No marketplace filter - include all marketplaces
            })
            ->with('order.currency_id')
            ->get();

        // Calculate total first (same way as marketplace summaries), then calculate average
        // This ensures consistency between total and average calculations
        $total = $orderItems->sum(function($item) {
            if ($item->order && $item->order->currency != 4 && $item->order->currency_id) {
                $rate = ExchangeRate::where('target_currency', $item->order->currency_id->code)->first()?->rate ?? 1;
                return $item->price / $rate;
            }
            return $item->price;
        });

        $count = $orderItems->count();
        $avgPrice = $count > 0 ? $total / $count : 0;
        $formatted = $avgPrice ? number_format($avgPrice, 2) : '0.00';

        return "14 days: €" . $formatted . " (" . $count . ")";
    }

    /**
     * Get last 30 days average price and count
     */
    private function get30DaysAverage(int $variationId): string
    {
        $orderItems = Order_item_model::where('variation_id', $variationId)
            ->whereHas('order', function($q) {
                // Include ALL marketplaces (null, 1, 2, 3, 4, etc.) for parent total calculation
                $q->whereBetween('created_at', [now()->subDays(30)->startOfDay(), now()->yesterday()->endOfDay()])
                  ->where('order_type_id', 3);
                  // No marketplace filter - include all marketplaces
            })
            ->with('order.currency_id')
            ->get();

        // Calculate total first (same way as marketplace summaries), then calculate average
        // This ensures consistency between total and average calculations
        $total = $orderItems->sum(function($item) {
            if ($item->order && $item->order->currency != 4 && $item->order->currency_id) {
                $rate = ExchangeRate::where('target_currency', $item->order->currency_id->code)->first()?->rate ?? 1;
                return $item->price / $rate;
            }
            return $item->price;
        });

        $count = $orderItems->count();
        $avgPrice = $count > 0 ? $total / $count : 0;
        $formatted = $avgPrice ? number_format($avgPrice, 2) : '0.00';

        return "30 days: €" . $formatted . " (" . $count . ")";
    }
}

