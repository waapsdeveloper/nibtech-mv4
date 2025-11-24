<?php

namespace App\Http\Controllers;

use App\Console\Commands\FunctionsThirty;
use App\Models\Listing_model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RefurbedListingsController extends Controller
{
    protected RefurbedAPIController $refurbed;

    public function __construct(RefurbedAPIController $refurbed)
    {
        $this->refurbed = $refurbed;
    }

    public function test(Request $request): JsonResponse
    {
        $perPage = $this->clampPageSize((int) $request->input('per_page', 50));

        // Get all listings without filtering by state for testing
        $filter = [];

        // Allow optional state filtering if provided
        if ($request->has('state')) {
            $states = $this->normalizeList($request->input('state'));
            if (!empty($states)) {
                $filter['state'] = ['any_of' => $states];
            }
        }

        $pagination = $this->buildPagination($perPage, $request->input('page_token'));
        $sort = $this->buildSort(
            $request->input('sort_by'),
            $request->input('sort_direction', 'ASC')
        );

        $payload = $this->refurbed->listOffers($filter, $pagination, $sort);

        return response()->json([
            'request' => [
                'filter' => $filter,
                'pagination' => $pagination,
                'sort' => $sort,
            ],
            'response' => $payload,
        ]);
    }

    public function active(Request $request): JsonResponse
    {
        $perPage = $this->clampPageSize((int) $request->input('per_page', 50));
        // Note: Refurbed uses different state enum values (OFFER_STATE_ACTIVE, etc.)
        // For now, fetch all offers without state filter
        $states = $this->normalizeList($request->input('state'), []);

        $filter = [];
        if (!empty($states)) {
            $filter['state'] = ['any_of' => $states];
        }

        $pagination = $this->buildPagination($perPage, $request->input('page_token'));
        $sort = $this->buildSort(
            $request->input('sort_by'),
            $request->input('sort_direction', 'ASC')
        );

        $payload = $this->refurbed->listOffers($filter, $pagination, $sort);

        return response()->json([
            'filters' => [
                'states' => $states,
                'page_size' => $pagination['page_size'] ?? $perPage,
            ],
            'data' => $payload,
        ]);
    }

    private function normalizeList(mixed $value, array $default = []): array
    {
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }

        if (! is_array($value)) {
            $value = [];
        }

        $value = array_values(array_filter($value));

        return empty($value) ? $default : $value;
    }

    private function clampPageSize(int $perPage): int
    {
        return max(1, min($perPage, 200));
    }

    private function buildPagination(int $perPage, ?string $pageToken): array
    {
        return array_filter([
            'page_size' => $perPage,
            'page_token' => $pageToken,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function buildSort(?string $field, string $direction): array
    {
        if (! $field) {
            return [];
        }

        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        return [
            'order_by' => $field,
            'direction' => $direction,
        ];
    }

    /**
     * Test endpoint to zero out all Refurbed listed stock
     */
    public function zeroStock(): JsonResponse
    {
        try {
            $updated = 0;
            $failed = 0;
            $errors = [];

            // Get all Refurbed listings (marketplace_id = 4)
            $listings = Listing_model::where('marketplace_id', 4)
                ->with('variation')
                ->get();

            if ($listings->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No Refurbed listings found',
                    'updated' => 0,
                ]);
            }

            foreach ($listings as $listing) {
                try {
                    $variation = $listing->variation;

                    if (!$variation) {
                        continue;
                    }

                    $sku = $variation->sku;

                    // Update offer quantity to 0 via Refurbed API
                    $identifier = ['sku' => $sku];
                    $updates = ['quantity' => 0];

                    $this->refurbed->updateOffer($identifier, $updates);

                    // Update local database
                    $variation->listed_stock = 0;
                    $variation->save();

                    $updated++;

                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = [
                        'sku' => $variation->sku ?? 'unknown',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // Log consolidated errors if any
            if (!empty($errors)) {
                Log::error('Refurbed: Zero stock operation had failures', [
                    'total_failed' => $failed,
                    'errors' => $errors,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => "Stock zeroed for {$updated} listings",
                'updated' => $updated,
                'failed' => $failed,
                'total' => $listings->count(),
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            Log::error('Refurbed: Zero stock operation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to zero stock: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manually trigger Refurbed listing sync
     */
    public function syncListings(): JsonResponse
    {
        try {
            set_time_limit(300); // 5 minutes

            $command = new FunctionsThirty();

            // Capture output
            ob_start();
            $command->get_refurbed_listings();
            $output = ob_get_clean();

            return response()->json([
                'status' => 'success',
                'message' => 'Refurbed listing sync completed',
                'output' => $output,
            ]);

        } catch (\Exception $e) {
            Log::error('Refurbed: Manual sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update all Refurbed stock quantities based on system variation->listed_stock
     */
    public function updateStockFromSystem(): JsonResponse
    {
        try {
            set_time_limit(300); // 5 minutes

            $updated = 0;
            $failed = 0;
            $skipped = 0;
            $errors = [];

            // Get all Refurbed listings (marketplace_id = 4) with their variations
            $listings = Listing_model::where('marketplace_id', 4)
                ->with('variation')
                ->get();

            if ($listings->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No Refurbed listings found',
                    'updated' => 0,
                ]);
            }

            foreach ($listings as $listing) {
                try {
                    $variation = $listing->variation;

                    if (!$variation) {
                        $skipped++;
                        continue;
                    }

                    $sku = $variation->sku;
                    $systemStock = (int) ($variation->listed_stock ?? 0);

                    // Update offer quantity to match system stock via Refurbed API
                    $identifier = ['sku' => $sku];
                    $updates = ['quantity' => $systemStock];

                    $this->refurbed->updateOffer($identifier, $updates);

                    $updated++;

                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = [
                        'sku' => $variation->sku ?? 'unknown',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // Log consolidated results
            Log::info('Refurbed: Stock update from system completed', [
                'updated' => $updated,
                'failed' => $failed,
                'skipped' => $skipped,
                'total' => $listings->count(),
            ]);

            // Log errors separately if any
            if (!empty($errors)) {
                Log::error('Refurbed: Stock update had failures', [
                    'total_failed' => $failed,
                    'errors' => $errors,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => "Stock updated for {$updated} listings from system variation->listed_stock",
                'updated' => $updated,
                'failed' => $failed,
                'skipped' => $skipped,
                'total' => $listings->count(),
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            Log::error('Refurbed: Update stock from system failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update stock: ' . $e->getMessage(),
            ], 500);
        }
    }
}
