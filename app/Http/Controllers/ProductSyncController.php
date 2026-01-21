<?php

namespace App\Http\Controllers;

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
                'variations' => function ($query) {
                    $query->where('state', '!=', 4); // Exclude archived
                },
                'variations.stocks' => function ($query) {
                    $query->where('status', 1); // Only available stocks
                }
            ])
            ->paginate($perPage, ['*'], 'page', $page);

            $data = $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'model' => $product->model,
                    'brand' => $product->brand,
                    'category' => $product->category,
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
                return [
                    'id' => $product->id,
                    'model' => $product->model,
                    'brand' => $product->brand,
                    'category' => $product->category,
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
