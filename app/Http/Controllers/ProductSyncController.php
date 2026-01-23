<?php

namespace App\Http\Controllers;

use App\Models\Brand_model;
use App\Models\Category_model;
use App\Models\Marketplace_model;
use App\Models\Products_model;
use App\Models\Variation_model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductSyncController extends Controller
{
    /**
     * Get all products with their variations and stock information
     */
    public function syncProducts(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 100);
            
            $products = Products_model::with([
                'brand_id',
                'category_id',
                'variations' => function ($query) {
                    $query->where('state', '!=', 4); // Exclude archived
                },
                'variations.stocks' => function ($query) {
                    $query->where('status', 1); // Only available stocks
                }
            ])
            ->paginate($perPage, ['*'], 'page', $page);

            $data = $products->map(function ($product) {
                // Get brand data
                $brandData = null;
                if ($product->brand) {
                    // Access relationship (method is brand_id() but accessed as property)
                    $brand = $product->brand_id;
                    if ($brand && $brand instanceof Brand_model) {
                        $brandData = [
                            'id' => $brand->id,
                            'name' => $brand->getAttribute('name') ?? null,
                            'slug' => $brand->getAttribute('slug') ?? null,
                            'description' => $brand->getAttribute('description') ?? null,
                        ];
                    } else {
                        // Fallback: return just the ID
                        $brandData = ['id' => $product->brand];
                    }
                }

                // Get category data
                $categoryData = null;
                if ($product->category) {
                    // Access relationship (method is category_id() but accessed as property)
                    $category = $product->category_id;
                    if ($category && $category instanceof Category_model) {
                        $categoryData = [
                            'id' => $category->id,
                            'name' => $category->getAttribute('name') ?? null,
                            'slug' => $category->getAttribute('slug') ?? null,
                            'description' => $category->getAttribute('description') ?? null,
                            'parent_id' => $category->getAttribute('parent_id') ?? null,
                        ];
                    } else {
                        // Fallback: return just the ID
                        $categoryData = ['id' => $product->category];
                    }
                }

                return [
                    'id' => $product->id,
                    'model' => $product->model,
                    'brand' => $brandData,
                    'category' => $categoryData,
                    'variations' => $product->variations->map(function ($variation) {
                        $availableStock = $variation->available_stocks()->count();
                        
                        return [
                            'id' => $variation->id,
                            'product_id' => $variation->product_id,
                            'reference_id' => $variation->reference_id,
                            'reference_uuid' => $variation->reference_uuid,
                            'sku' => $variation->sku,
                            'color' => $variation->color,
                            'storage' => $variation->storage,
                            'grade' => $variation->grade,
                            'sub_grade' => $variation->sub_grade,
                            'state' => $variation->state,
                            'listed_stock' => $variation->listed_stock ?? 0,
                            'available_stock' => $availableStock,
                            'default_stock_formula' => $variation->default_stock_formula,
                            'default_min_threshold' => $variation->default_min_threshold,
                            'default_max_threshold' => $variation->default_max_threshold,
                            'default_min_stock_required' => $variation->default_min_stock_required,
                        ];
                    }),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error syncing products: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get products updated since a specific timestamp
     */
    public function syncUpdatedProducts(Request $request)
    {
        try {
            $since = $request->get('since');
            if (!$since) {
                return response()->json([
                    'success' => false,
                    'message' => 'since parameter is required',
                ], 400);
            }

            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 100);

            $products = Products_model::whereHas('variations', function ($query) use ($since) {
                $query->where('updated_at', '>=', $since)
                    ->orWhere('created_at', '>=', $since);
            })
            ->orWhere('updated_at', '>=', $since)
            ->orWhere('created_at', '>=', $since)
            ->with([
                'brand_id',
                'category_id',
                'variations' => function ($query) use ($since) {
                    $query->where(function ($q) use ($since) {
                        $q->where('updated_at', '>=', $since)
                          ->orWhere('created_at', '>=', $since);
                    })
                    ->where('state', '!=', 4);
                },
                'variations.stocks' => function ($query) {
                    $query->where('status', 1);
                }
            ])
            ->paginate($perPage, ['*'], 'page', $page);

            $data = $products->map(function ($product) {
                // Get brand data
                $brandData = null;
                if ($product->brand) {
                    // Access relationship (method is brand_id() but accessed as property)
                    $brand = $product->brand_id;
                    if ($brand && $brand instanceof Brand_model) {
                        $brandData = [
                            'id' => $brand->id,
                            'name' => $brand->getAttribute('name') ?? null,
                            'slug' => $brand->getAttribute('slug') ?? null,
                            'description' => $brand->getAttribute('description') ?? null,
                        ];
                    } else {
                        // Fallback: return just the ID
                        $brandData = ['id' => $product->brand];
                    }
                }

                // Get category data
                $categoryData = null;
                if ($product->category) {
                    // Access relationship (method is category_id() but accessed as property)
                    $category = $product->category_id;
                    if ($category && $category instanceof Category_model) {
                        $categoryData = [
                            'id' => $category->id,
                            'name' => $category->getAttribute('name') ?? null,
                            'slug' => $category->getAttribute('slug') ?? null,
                            'description' => $category->getAttribute('description') ?? null,
                            'parent_id' => $category->getAttribute('parent_id') ?? null,
                        ];
                    } else {
                        // Fallback: return just the ID
                        $categoryData = ['id' => $product->category];
                    }
                }

                return [
                    'id' => $product->id,
                    'model' => $product->model,
                    'brand' => $brandData,
                    'category' => $categoryData,
                    'variations' => $product->variations->map(function ($variation) {
                        $availableStock = $variation->available_stocks()->count();
                        
                        return [
                            'id' => $variation->id,
                            'product_id' => $variation->product_id,
                            'reference_id' => $variation->reference_id,
                            'reference_uuid' => $variation->reference_uuid,
                            'sku' => $variation->sku,
                            'color' => $variation->color,
                            'storage' => $variation->storage,
                            'grade' => $variation->grade,
                            'sub_grade' => $variation->sub_grade,
                            'state' => $variation->state,
                            'listed_stock' => $variation->listed_stock ?? 0,
                            'available_stock' => $availableStock,
                            'default_stock_formula' => $variation->default_stock_formula,
                            'default_min_threshold' => $variation->default_min_threshold,
                            'default_max_threshold' => $variation->default_max_threshold,
                            'default_min_stock_required' => $variation->default_min_stock_required,
                        ];
                    }),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error syncing updated products: ' . $e->getMessage(),
            ], 500);
        }
    }
}
