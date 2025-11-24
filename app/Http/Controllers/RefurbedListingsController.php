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
            set_time_limit(300); // 5 minutes

            $updated = 0;
            $failed = 0;
            $errors = [];

            // Get ALL offers from Refurbed API (with automatic pagination), sorted by stock descending, stock > 0
            $filter = [
                'quantity' => ['greater_than' => 0]
            ];

            // Check if specific SKU is requested
            if (request()->has('sku')) {
                $filter['sku'] = ['equals' => request('sku')];
            }

            $sort = [
                'order_by' => 'quantity',
                'direction' => 'DESC'
            ];
            $response = $this->refurbed->getAllOffers($filter, $sort);
            $offers = $response['offers'] ?? [];

            if (empty($offers)) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No Refurbed offers found',
                    'updated' => 0,
                ]);
            }

            // Only apply aggressive rate limiting for bulk operations (more than 10 items)
            $isBulkOperation = count($offers) > 10;

            foreach ($offers as $index => $offer) {
                try {
                    $sku = $offer['sku'] ?? null;

                    if (!$sku) {
                        continue;
                    }

                    // Update offer quantity to 0 via Refurbed API
                    // Use only SKU (oneof field - cannot use both sku and id)
                    $identifier = ['sku' => $sku];
                    $updates = ['quantity' => 0];

                    $response = $this->refurbed->updateOffer($identifier, $updates);

                    $updated++;

                    // Add delay only for bulk operations to prevent rate limiting
                    if ($isBulkOperation) {
                        usleep(100000); // 1 second delay for bulk updates
                    }

                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = [
                        'sku' => $offer['sku'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ];

                    // Add longer delay after error (likely rate limit)
                    if ($isBulkOperation) {
                        usleep(200000); // 2 second delay after error
                    }
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
                'total' => count($offers),
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

            // Get ALL offers from Refurbed API (with automatic pagination), sorted by stock descending
            $sort = [
                'order_by' => 'quantity',
                'direction' => 'DESC'
            ];
            $response = $this->refurbed->getAllOffers([], $sort);
            $offers = $response['offers'] ?? [];

            if (empty($offers)) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No Refurbed offers found',
                    'updated' => 0,
                ]);
            }

            // Only apply aggressive rate limiting for bulk operations (more than 10 items)
            $isBulkOperation = count($offers) > 10;

            foreach ($offers as $index => $offer) {
                try {
                    $sku = $offer['sku'] ?? null;

                    if (!$sku) {
                        $skipped++;
                        continue;
                    }

                    // Find variation in local system by SKU
                    $variation = \App\Models\Variation_model::where('sku', $sku)->first();

                    if (!$variation) {
                        $skipped++;
                        continue;
                    }

                    $systemStock = (int) ($variation->listed_stock ?? 0);

                    // Update offer quantity to match system stock via Refurbed API
                    // Use only SKU (oneof field - cannot use both sku and id)
                    $identifier = ['sku' => $sku];
                    $updates = ['quantity' => $systemStock];

                    $this->refurbed->updateOffer($identifier, $updates);

                    $updated++;

                    // Add delay only for bulk operations to prevent rate limiting
                    if ($isBulkOperation) {
                        usleep(1000000); // 1 second delay for bulk updates
                    }

                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = [
                        'sku' => $offer['sku'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ];

                    // Add longer delay after error (likely rate limit)
                    if ($isBulkOperation) {
                        usleep(2000000); // 2 second delay after error
                    }
                }
            }

            // Log consolidated results
            Log::info('Refurbed: Stock update from system completed', [
                'updated' => $updated,
                'failed' => $failed,
                'skipped' => $skipped,
                'total' => count($offers),
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
                'total' => count($offers),
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
