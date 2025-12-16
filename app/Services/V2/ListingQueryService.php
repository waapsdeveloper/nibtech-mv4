<?php

namespace App\Services\V2;

use App\Models\Variation_model;
use App\Models\Products_model;
use App\Models\Storage_model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ListingQueryService
{
    /**
     * Build optimized query for listing variations
     * Returns query builder with minimal eager loading for initial list
     */
    public function buildVariationQuery(Request $request)
    {
        list($productSearch, $storageSearch) = $this->resolveProductAndStorageSearch($request->input('product_name'));

        // Eager load ALL relationships like original listing page for faster performance
        // This matches the original approach which loads everything in one query
        $query = Variation_model::with([
            'listings',
            'listings.country_id',
            'listings.currency',
            'listings.marketplace',
            'product',
            'available_stocks',
            'pending_orders',
            'pending_bm_orders',
            'storage_id',
            'color_id',
            'grade_id',
        ]);

        // Apply filters
        $query = $this->applyFilters($query, $request, $productSearch, $storageSearch);
        
        // Apply sorting
        $query = $this->applySorting($query, $request);

        return $query;
    }

    /**
     * Apply all filters to the query
     * Uses subqueries for related table filters to avoid conflicts with sorting joins
     */
    private function applyFilters($query, Request $request, $productSearch, $storageSearch)
    {
        return $query
            ->when($request->filled('reference_id'), function ($q) use ($request) {
                return $q->where('reference_id', $request->input('reference_id'));
            })
            ->when($request->filled('variation_id'), function ($q) use ($request) {
                return $q->where('id', $request->input('variation_id'));
            })
            ->when($request->filled('category'), function ($q) use ($request) {
                // Use whereHas to match original implementation
                return $q->whereHas('product', function ($productQuery) use ($request) {
                    $productQuery->where('category', $request->input('category'));
                });
            })
            ->when($request->filled('brand'), function ($q) use ($request) {
                // Use whereHas to match original implementation
                return $q->whereHas('product', function ($productQuery) use ($request) {
                    $productQuery->where('brand', $request->input('brand'));
                });
            })
            ->when($request->filled('marketplace'), function ($q) use ($request) {
                // Use whereHas - it's more efficient and works fine before joins
                return $q->whereHas('listings', function ($q) use ($request) {
                    $q->where('marketplace_id', $request->input('marketplace'));
                });
            })
            ->when($request->filled('product'), function ($q) use ($request) {
                return $q->where('product_id', $request->input('product'));
            })
            ->when($productSearch->count() > 0, function ($q) use ($productSearch) {
                return $q->whereIn('product_id', $productSearch);
            })
            ->when($storageSearch->count() > 0, function ($q) use ($storageSearch) {
                return $q->whereIn('storage', $storageSearch);
            })
            ->when($request->filled('sku'), function ($q) use ($request) {
                return $q->where('sku', $request->input('sku'));
            })
            ->when($request->filled('color'), function ($q) use ($request) {
                return $q->where('color', $request->input('color'));
            })
            ->when($request->filled('storage'), function ($q) use ($request) {
                return $q->where('storage', $request->input('storage'));
            })
            ->when($request->filled('grade'), function ($q) use ($request) {
                return $q->whereIn('grade', (array) $request->input('grade'));
            })
            ->when($request->filled('topup'), function ($q) use ($request) {
                // Use whereHas - it's more efficient
                return $q->whereHas('listed_stock_verifications', function ($verificationQuery) use ($request) {
                    $verificationQuery->where('process_id', $request->input('topup'));
                });
            })
            ->when($request->filled('listed_stock'), function ($q) use ($request) {
                if ((int) $request->input('listed_stock') === 1) {
                    return $q->where('listed_stock', '>', 0);
                }
                if ((int) $request->input('listed_stock') === 2) {
                    return $q->where('listed_stock', '<=', 0);
                }
            })
            ->when($request->filled('available_stock'), function ($q) use ($request) {
                if ((int) $request->input('available_stock') === 1) {
                    // Use whereHas for available_stocks (relationship handles complex logic)
                    // This works because it's applied before joins
                    return $q->whereHas('available_stocks')
                        ->withCount(['available_stocks', 'pending_orders'])
                        ->havingRaw('(available_stocks_count - pending_orders_count) > 0');
                }
                if ((int) $request->input('available_stock') === 2) {
                    return $q->whereDoesntHave('available_stocks');
                }
            })
            ->when($request->filled('process_id') && $request->input('special') === 'show_only', function ($q) use ($request) {
                // Use whereHas - it's more efficient
                return $q->whereHas('process_stocks', function ($processStockQuery) use ($request) {
                    $processStockQuery->where('process_id', $request->input('process_id'));
                });
            });
        
        // Apply state filter - matches original order (before sale_40, handler_status, whereNotNull)
        $state = $request->input('state');
        if ($state === null || $state === '') {
            $query->whereIn('state', [2, 3]);
        } elseif ((int) $state !== 10) {
            $query->where('state', $state);
        }
        
        // Apply sale_40 filter - matches original order
        $query->when($request->filled('sale_40'), function ($q) {
            return $q->withCount('today_orders as today_orders_count')
                ->having('today_orders_count', '<', DB::raw('listed_stock * 0.05'));
        })
        // Apply handler_status filters - matches original order
        ->when((int) $request->input('handler_status') === 2, function ($q) use ($request) {
            return $q->whereHas('listings', function ($listingQuery) use ($request) {
                $listingQuery->where('handler_status', $request->input('handler_status'))
                    ->whereIn('country', [73, 199]);
            });
        })
        ->when(in_array((int) $request->input('handler_status'), [1, 3], true), function ($q) use ($request) {
            return $q->whereHas('listings', function ($listingQuery) use ($request) {
                $listingQuery->where('handler_status', $request->input('handler_status'));
            });
        })
        // Apply whereNotNull('sku') at the end, before sorting - matches original
        ->whereNotNull('sku');
        
        return $query;
    }

    /**
     * Apply sorting to the query
     * Matches original ListingController sorting logic exactly
     */
    private function applySorting($query, Request $request)
    {
        $sort = $request->input('sort', 1);

        return match ((int) $sort) {
            4 => $query->join('products', 'variation.product_id', '=', 'products.id')
                ->orderBy('products.model', 'asc')
                ->orderBy('variation.storage', 'asc')
                ->orderBy('variation.color', 'asc')
                ->orderBy('variation.grade', 'asc')
                ->select('variation.*'),
            3 => $query->join('products', 'variation.product_id', '=', 'products.id')
                ->orderBy('products.model', 'desc')
                ->orderBy('variation.storage', 'asc')
                ->orderBy('variation.color', 'asc')
                ->orderBy('variation.grade', 'asc')
                ->select('variation.*'),
            2 => $query->orderBy('listed_stock', 'asc')
                ->orderBy('variation.storage', 'asc')
                ->orderBy('variation.color', 'asc')
                ->orderBy('variation.grade', 'asc'),
            default => $query->orderBy('listed_stock', 'desc')
                ->orderBy('variation.storage', 'asc')
                ->orderBy('variation.color', 'asc')
                ->orderBy('variation.grade', 'asc'),
        };
    }

    /**
     * Resolve product and storage search from product name
     */
    private function resolveProductAndStorageSearch(?string $productName): array
    {
        if (empty($productName)) {
            return [collect(), collect()];
        }

        $searchTerm = trim($productName);
        $parts = explode(' ', $searchTerm);
        $lastSegment = end($parts);

        $storageSearch = Storage_model::where('name', 'like', $lastSegment . '%')->pluck('id');

        if ($storageSearch->count() > 0) {
            array_pop($parts);
            $searchTerm = trim(implode(' ', $parts));
        } else {
            $storageSearch = collect();
        }

        $productSearch = Products_model::where('model', 'like', '%' . $searchTerm . '%')->pluck('id');

        return [$productSearch, $storageSearch];
    }
}

