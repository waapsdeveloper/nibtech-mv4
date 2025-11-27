<?php

namespace App\Console\Commands;

use App\Http\Controllers\RefurbedAPIController;
use App\Models\Country_model;
use App\Models\Currency_model;
use App\Models\Order_model;
use App\Models\Variation_model;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class RefurbedSyncNewOrders extends Command
{
    private static bool $orderLineAcceptanceUnavailable = false;
    private ?string $debugOrderId = null;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refurbed:new
        {--state=* : Override the default Refurbed states (NEW,PENDING)}
        {--page-size=50 : Page size for each API request (max 200)}
        {--max-pages=0 : Maximum number of order pages to fetch per run (0 = all)}
        {--lookback-hours=48 : Re-sync Refurbed orders created within the last N hours}
        {--skip-items : Skip fetching order items for each order}
        {--debug-order= : Limit processing to a single Refurbed order id and print API responses}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch the newest Refurbed orders and refresh recent ones that may still need shipping data.';

    public function handle(): int
    {
        $states = $this->normalizeList($this->option('state'));
        if (empty($states)) {
            $states = ['NEW'];
        }

        $refurbed = new RefurbedAPIController();
        $orderModel = new Order_model();
        $currencyCodes = $this->getCurrencyCodes();
        $countryCodes = $this->getCountryCodes();

        $filter = $this->buildStateFilter($states);

        $this->debugOrderId = $this->normalizeRefurbedOrderId($this->option('debug-order'));
        if ($this->debugOrderId) {
            return $this->debugSingleOrder(
                $refurbed,
                $orderModel,
                $currencyCodes,
                $countryCodes,
                $this->debugOrderId
            );
        }

        $pageSize = $this->sanitizePageSize((int) $this->option('page-size'));
        $sort = [
            'order_by' => 'CREATED_AT',
            'direction' => 'DESC',
        ];

        $maxPages = max(0, (int) $this->option('max-pages'));

        $syncStats = $this->syncOrders(
            $refurbed,
            $orderModel,
            $filter,
            $sort,
            $pageSize,
            $maxPages,
            $currencyCodes,
            $countryCodes,
            $states
        );

        $lookbackHours = max(0, (int) $this->option('lookback-hours'));
        $refreshed = 0;

        if ($lookbackHours > 0) {
            $refreshed = $this->refreshRecentOrders($refurbed, $orderModel, $currencyCodes, $countryCodes, $lookbackHours);
        }

        $this->info(sprintf(
            'Refurbed new-order sync complete. processed=%d skipped=%d failed=%d refreshed=%d',
            $syncStats['processed'],
            $syncStats['skipped'],
            $syncStats['failed'],
            $refreshed
        ));

        return self::SUCCESS;
    }

    private function buildStateFilter(array $states): array
    {
        $states = array_values(array_filter(array_map(static function ($state) {
            return strtoupper((string) $state);
        }, $states)));

        if (empty($states)) {
            return [];
        }

        return [
            'state' => [
                'any_of' => $states,
            ],
        ];
    }

    private function shouldProcessLocalOrder(?Order_model $order): bool
    {
        if (! $order) {
            return false;
        }

        $state = $order->state ?? null;
        if ($state === null && isset($order->status)) {
            $state = $order->status;
        }

        return (int) $state === 1;
    }

    private function syncOrders(
        RefurbedAPIController $refurbed,
        Order_model $orderModel,
        array $filter,
        array $sort,
        int $pageSize,
        int $maxPages,
        array $currencyCodes,
        array $countryCodes,
        array $stateWhitelist
    ): array {
        $processed = 0;
        $skipped = 0;
        $failed = 0;
        $pageToken = null;
        $pageCount = 0;
        $hasMore = true;

        while ($hasMore && ($maxPages === 0 || $pageCount < $maxPages)) {
            $pageCount++;
            $pagination = array_filter([
                'page_size' => $pageSize,
                'page_token' => $pageToken,
            ]);

            try {
                $response = $refurbed->listOrders($filter, $pagination, $sort);
            } catch (\Throwable $e) {
                Log::error('Refurbed: failed to fetch new orders', [
                    'error' => $e->getMessage(),
                    'filter' => $filter,
                    'page' => $pageCount,
                ]);

                $this->error('Refurbed new-order sync failed while fetching page ' . $pageCount . ': ' . $e->getMessage());

                break;
            }

            $orders = $response['orders'] ?? [];

            if (empty($orders)) {
                $hasMore = false;
                break;
            }

            foreach ($orders as $orderData) {
                $orderData = $this->adaptOrderPayload($orderData);
                $orderId = $orderData['id'] ?? $orderData['order_number'] ?? null;
                $orderItems = $orderData['order_items'] ?? $orderData['items'] ?? null;

                if (! $orderItems && ! $this->option('skip-items') && $orderId) {
                    $orderItems = $this->fetchOrderItems($refurbed, $orderId);
                }

                $preAcceptanceState = strtoupper($orderData['state'] ?? '');

                if (! $orderId) {
                    $skipped++;
                    Log::warning('Refurbed: order payload missing identifier', ['payload' => $orderData]);
                    continue;
                }

                $orderState = $preAcceptanceState ?: strtoupper($orderData['state'] ?? '');

                if (! empty($stateWhitelist) && $orderState !== '' && ! in_array($orderState, $stateWhitelist, true)) {
                    $skipped++;
                    continue;
                }

                $savedOrder = null;

                try {
                    $savedOrder = $orderModel->storeRefurbedOrderInDB($orderData, $orderItems, $currencyCodes, $countryCodes);
                    $processed++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::error('Refurbed: failed to persist new order', [
                        'order_id' => $orderId,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }

                if (! $this->shouldProcessLocalOrder($savedOrder)) {
                    continue;
                }

                $orderData = $this->acceptOrderLinesIfNeeded(
                    $refurbed,
                    $orderData,
                    $orderItems,
                    ! $this->option('skip-items'),
                    $this->isDebugOrder($orderId)
                );

                $postAcceptanceState = strtoupper($orderData['state'] ?? '');

                if ($postAcceptanceState === $preAcceptanceState) {
                    continue;
                }

                try {
                    $orderModel->storeRefurbedOrderInDB($orderData, $orderItems, $currencyCodes, $countryCodes);
                } catch (\Throwable $e) {
                    Log::warning('Refurbed: failed to persist order after acceptance', [
                        'order_id' => $orderId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $pageToken = $response['next_page_token'] ?? null;
            $hasMore = $pageToken && ($response['has_more'] ?? false);
        }

        if ($hasMore && $maxPages > 0) {
            $this->warn(sprintf(
                'Stopped after %d Refurbed order pages (pass --max-pages=%d to fetch more).',
                $pageCount,
                $maxPages + 1
            ));
        }

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }

    private function refreshRecentOrders(
        RefurbedAPIController $refurbed,
        Order_model $orderModel,
        array $currencyCodes,
        array $countryCodes,
        int $lookbackHours
    ): int {
        $threshold = Carbon::now()->subHours($lookbackHours);

        $recentOrders = Order_model::query()
            ->where('marketplace_id', 4)
            ->where(function ($query) {
                $query->whereNull('delivery_note_url')
                    ->orWhereNull('label_url');
            })
            ->where('created_at', '>=', $threshold)
            ->get(['id', 'reference', 'reference_id']);

        $refreshed = 0;

        foreach ($recentOrders as $orderRecord) {
            [$primaryOrderId, $fallbackOrderId] = $this->resolveRefurbedOrderIdentifiers($orderRecord);

            if (! $primaryOrderId) {
                continue;
            }

            $orderFetch = $this->fetchRefurbedOrderDetails($refurbed, $primaryOrderId, $fallbackOrderId);

            if (! $orderFetch) {
                continue;
            }

            $orderId = $orderFetch['id'];
            $orderResponse = $orderFetch['payload'];
            $orderPayload = $this->adaptOrderPayload($orderResponse['order'] ?? $orderResponse);
            $orderItems = $orderPayload['order_items'] ?? $orderPayload['items'] ?? null;

            if (! $orderItems && ! $this->option('skip-items')) {
                $orderItems = $this->fetchOrderItems($refurbed, $orderId);
            }

            $savedOrder = null;

            try {
                $savedOrder = $orderModel->storeRefurbedOrderInDB($orderPayload, $orderItems, $currencyCodes, $countryCodes);
                $refreshed++;
            } catch (\Throwable $e) {
                Log::error('Refurbed: failed to refresh order', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if (! $this->shouldProcessLocalOrder($savedOrder)) {
                continue;
            }

            $preAcceptanceState = strtoupper($orderPayload['state'] ?? '');

            $orderPayload = $this->acceptOrderLinesIfNeeded(
                $refurbed,
                $orderPayload,
                $orderItems,
                ! $this->option('skip-items'),
                $this->isDebugOrder($orderId)
            );

            $postAcceptanceState = strtoupper($orderPayload['state'] ?? '');

            if ($postAcceptanceState === $preAcceptanceState) {
                continue;
            }

            try {
                $orderModel->storeRefurbedOrderInDB($orderPayload, $orderItems, $currencyCodes, $countryCodes);
            } catch (\Throwable $e) {
                Log::warning('Refurbed: failed to persist order after acceptance during refresh', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $refreshed;
    }

    private function fetchOrderItems(RefurbedAPIController $refurbed, string $orderId): ?array
    {
        try {
            $itemsResponse = $refurbed->getAllOrderItems($orderId);

            return $itemsResponse['order_items'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('Refurbed: unable to fetch order items', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function acceptOrderLinesIfNeeded(RefurbedAPIController $refurbed, array $orderData, ?array &$orderItems, bool $canFetchItems, bool $logApiResponse = false): array
    {
        $orderId = $orderData['id'] ?? $orderData['order_number'] ?? null;
        $state = strtoupper($orderData['state'] ?? '');

        if (! $orderId || $state !== 'NEW' || self::$orderLineAcceptanceUnavailable) {
            return $orderData;
        }

        if ((! is_array($orderItems) || empty($orderItems)) && $canFetchItems) {
            $orderItems = $this->fetchOrderItems($refurbed, $orderId) ?? [];
        }

        if (empty($orderItems)) {
            return $orderData;
        }

        $acceptedItems = 0;
        $attemptedItems = 0;

        foreach ($orderItems as &$item) {
            $itemState = strtoupper($item['state'] ?? '');
            if (empty($item['id']) || $itemState !== 'NEW') {
                continue;
            }

            $attemptedItems++;
            $accepted = $this->attemptSingleItemAcceptance($refurbed, $item, $orderId, $logApiResponse);

            if ($accepted) {
                $item['state'] = 'ACCEPTED';
                $acceptedItems++;
                $this->applyListingQuantityChange($item, $orderId);
            }
        }
        unset($item);

        if ($acceptedItems > 0) {
            $orderData['state'] = 'ACCEPTED';
        }

        if ($logApiResponse && $attemptedItems > 0) {
            $this->info(sprintf(
                'Refurbed: attempted %d single-item updates, accepted=%d, failed=%d',
                $attemptedItems,
                $acceptedItems,
                $attemptedItems - $acceptedItems
            ));

            $latestItems = $this->fetchOrderItems($refurbed, $orderId) ?? [];
            if (! empty($latestItems)) {
                $orderItems = $latestItems;
            }

            $this->info('Latest Refurbed order item states for order ' . $orderId . ':');
            $this->line(json_encode(array_map(function ($item) {
                return array_filter([
                    'id' => $item['id'] ?? null,
                    'state' => $item['state'] ?? null,
                    'sku' => $item['sku'] ?? null,
                    'parcel_tracking_number' => $item['parcel_tracking_number'] ?? null,
                ]);
            }, $latestItems), JSON_PRETTY_PRINT));
        }

        return $orderData;
    }

    private function isDebugOrder(?string $orderId): bool
    {
        if (! $orderId || ! $this->debugOrderId) {
            return false;
        }

        return (string) $orderId === $this->debugOrderId;
    }

    private function debugSingleOrder(
        RefurbedAPIController $refurbed,
        Order_model $orderModel,
        array $currencyCodes,
        array $countryCodes,
        string $orderId
    ): int {
        $this->info('Debugging Refurbed order '.$orderId.'...');

        try {
            $orderResponse = $refurbed->getOrder($orderId);
        } catch (\Throwable $e) {
            $this->error('Failed to fetch order '.$orderId.': '.$e->getMessage());
            Log::error('Refurbed: debug fetch failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);

            return self::FAILURE;
        }

        $orderPayload = $this->adaptOrderPayload($orderResponse['order'] ?? $orderResponse);
        $orderItems = $orderPayload['order_items'] ?? $orderPayload['items'] ?? null;

        if (! $orderItems) {
            $orderItems = $this->fetchOrderItems($refurbed, $orderId);
        }

        $savedOrder = null;

        try {
            $savedOrder = $orderModel->storeRefurbedOrderInDB($orderPayload, $orderItems, $currencyCodes, $countryCodes);
            $this->info('Order '.$orderId.' persisted locally after debug run.');
        } catch (\Throwable $e) {
            $this->error('Failed to persist order '.$orderId.': '.$e->getMessage());
            Log::error('Refurbed: debug persist failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);

            return self::FAILURE;
        }

        if (! $this->shouldProcessLocalOrder($savedOrder)) {
            $this->warn('Skipping acceptance attempt because local order state is not 1.');

            return self::SUCCESS;
        }

        $preAcceptanceState = strtoupper($orderPayload['state'] ?? '');

        $orderPayload = $this->acceptOrderLinesIfNeeded($refurbed, $orderPayload, $orderItems, true, true);

        $postAcceptanceState = strtoupper($orderPayload['state'] ?? '');

        if ($postAcceptanceState === $preAcceptanceState) {
            return self::SUCCESS;
        }

        try {
            $orderModel->storeRefurbedOrderInDB($orderPayload, $orderItems, $currencyCodes, $countryCodes);
        } catch (\Throwable $e) {
            $this->error('Failed to persist post-acceptance order '.$orderId.': '.$e->getMessage());
            Log::error('Refurbed: debug persist failed after acceptance', ['order_id' => $orderId, 'error' => $e->getMessage()]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function normalizeList($values): array
    {
        $values = array_map(function ($value) {
            return strtoupper(trim((string) $value));
        }, (array) $values);

        $values = array_filter($values);

        return array_values(array_unique($values));
    }

    private function sanitizePageSize(int $pageSize): int
    {
        if ($pageSize <= 0) {
            return 50;
        }

        return min($pageSize, 200);
    }

    private function getCurrencyCodes(): array
    {
        return Currency_model::pluck('id', 'code')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    private function getCountryCodes(): array
    {
        return Country_model::pluck('id', 'code')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    /**
     * Map Refurbed API fields (per sample payload) to what Order_model expects.
     */
    private function adaptOrderPayload(array $orderData): array
    {
        if (! isset($orderData['order_number'])) {
            $orderData['order_number'] = $orderData['id'] ?? null;
        }

        $orderData['currency'] = $orderData['settlement_currency_code']
            ?? $orderData['currency']
            ?? $orderData['currency_code']
            ?? null;

        $orderData['total_amount'] = $orderData['settlement_total_paid']
            ?? $orderData['total_amount']
            ?? $orderData['total_paid']
            ?? $orderData['total_charged']
            ?? null;

        if (! isset($orderData['created_at']) && isset($orderData['released_at'])) {
            $orderData['created_at'] = $orderData['released_at'];
        }

        if (! isset($orderData['updated_at']) && isset($orderData['released_at'])) {
            $orderData['updated_at'] = $orderData['released_at'];
        }

        $shippingRaw = $orderData['shipping_address'] ?? null;
        $billingRaw = $orderData['invoice_address'] ?? null;
        $shippingLookup = is_array($shippingRaw) ? $shippingRaw : [];

        if (! isset($orderData['country'])) {
            $orderData['country'] = $shippingLookup['country_code']
                ?? $shippingLookup['country']
                ?? ($billingRaw['country_code'] ?? null);
        }

        if (! isset($orderData['billing_address']) && $billingRaw) {
            $orderData['billing_address'] = $this->mapRefurbedAddress($billingRaw);
        }

        if ($shippingRaw) {
            $orderData['shipping_address'] = $this->mapRefurbedAddress($shippingRaw);
        }

        if (! isset($orderData['customer'])) {
            $orderData['customer'] = [
                'email' => $orderData['customer_email'] ?? null,
                'first_name' => $shippingLookup['first_name'] ?? $shippingLookup['given_name'] ?? null,
                'last_name' => $shippingLookup['family_name'] ?? $shippingLookup['last_name'] ?? null,
                'phone' => $shippingLookup['phone_number'] ?? $shippingLookup['phone'] ?? null,
            ];
        }

        if (isset($orderData['items']) && ! isset($orderData['order_items'])) {
            $orderData['order_items'] = $orderData['items'];
        }

        return $orderData;
    }

    private function mapRefurbedAddress(array $address): array
    {
        $streetLine = trim(($address['street_name'] ?? '') . ' ' . ($address['house_no'] ?? ''));

        return [
            'company' => $address['company'] ?? null,
            'first_name' => $address['first_name'] ?? $address['given_name'] ?? null,
            'last_name' => $address['last_name'] ?? $address['family_name'] ?? null,
            'street' => $streetLine ?: ($address['street'] ?? ''),
            'street2' => $address['street2'] ?? $address['street_line2'] ?? '',
            'postal_code' => $address['postal_code'] ?? $address['post_code'] ?? '',
            'country' => $address['country'] ?? $address['country_code'] ?? '',
            'city' => $address['city'] ?? $address['town'] ?? '',
            'phone' => $address['phone'] ?? $address['phone_number'] ?? '',
            'email' => $address['email'] ?? null,
        ];
    }

    private function resolveRefurbedOrderIdentifiers($orderRecord): array
    {
        $primary = $this->normalizeRefurbedOrderId($orderRecord->reference_id ?? null);
        $fallback = null;

        $reference = $orderRecord->reference ?? null;

        if (! empty($reference) && $reference !== Order_model::REFURBED_STOCK_SYNCED_REFERENCE) {
            $normalizedReference = $this->normalizeRefurbedOrderId($reference);

            if ($normalizedReference) {
                if (empty($primary)) {
                    $primary = $normalizedReference;
                } elseif ($normalizedReference !== $primary) {
                    $fallback = $normalizedReference;
                }
            }
        }

        return [$primary, $fallback];
    }

    private function normalizeRefurbedOrderId($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        return preg_match('/^\d+$/', $value) ? $value : null;
    }

    private function fetchRefurbedOrderDetails(
        RefurbedAPIController $refurbed,
        string $primaryOrderId,
        ?string $fallbackOrderId = null
    ): ?array {
        try {
            return [
                'id' => $primaryOrderId,
                'payload' => $refurbed->getOrder($primaryOrderId),
            ];
        } catch (RequestException $primaryException) {
            if ($this->isOrderNotFound($primaryException) && $fallbackOrderId) {
                try {
                    return [
                        'id' => $fallbackOrderId,
                        'payload' => $refurbed->getOrder($fallbackOrderId),
                    ];
                } catch (\Throwable $fallbackException) {
                    $this->logOrderRefreshFailure($fallbackOrderId, $fallbackException);
                    return null;
                }
            }

            $this->logOrderRefreshFailure($primaryOrderId, $primaryException);
        } catch (\Throwable $e) {
            $this->logOrderRefreshFailure($primaryOrderId, $e);
        }

        return null;
    }

    private function isOrderNotFound(RequestException $exception): bool
    {
        $response = $exception->response;

        return $response && $response->status() === 404;
    }

    private function logOrderRefreshFailure(string $orderId, \Throwable $exception): void
    {
        Log::warning('Refurbed: failed to fetch order details during refresh', [
            'order_id' => $orderId,
            'error' => $exception->getMessage(),
        ]);
    }

    private function attemptSingleItemAcceptance(RefurbedAPIController $refurbed, array $item, string $orderId, bool $logOutput = false): bool
    {
        $itemId = $item['id'] ?? null;

        if (! $itemId) {
            return false;
        }

        if ($logOutput) {
            $this->info('Calling single-item UpdateOrderItemState for item ' . $itemId . ' (order ' . $orderId . ')...');
        }

        try {
            $response = $refurbed->updateOrderItemState($itemId, 'ACCEPTED');
            if ($logOutput) {
                $this->line(json_encode($response, JSON_PRETTY_PRINT));
            }

            return true;
        } catch (RequestException $e) {
            if ($logOutput) {
                $this->error(sprintf('Single-item state update failed for %s: %s', $itemId, $e->getMessage()));
            }
            Log::warning('Refurbed: single-item acceptance failed', [
                'order_id' => $orderId,
                'order_item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            if ($logOutput) {
                $this->error(sprintf('Single-item state update failed for %s: %s', $itemId, $e->getMessage()));
            }
            Log::warning('Refurbed: single-item acceptance failed', [
                'order_id' => $orderId,
                'order_item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    private function applyListingQuantityChange(array $item, string $orderId): void
    {
        $sku = $item['sku'] ?? $item['merchant_sku'] ?? null;
        if (! $sku) {
            return;
        }

        $quantity = (int) ($item['quantity'] ?? 1);
        if ($quantity <= 0) {
            $quantity = 1;
        }

        $variation = Variation_model::where('sku', $sku)->first();

        if (! $variation) {
            Log::notice('Refurbed: unable to adjust listing quantity, variation missing', [
                'order_id' => $orderId,
                'sku' => $sku,
                'quantity' => $quantity,
            ]);

            return;
        }

        $originalStock = (int) ($variation->listed_stock ?? 0);
        $newStock = max(0, $originalStock - $quantity);

        if ($newStock === $originalStock) {
            return;
        }

        $variation->listed_stock = $newStock;
        $variation->save();

        Log::info('Refurbed: listing quantity adjusted after acceptance', [
            'order_id' => $orderId,
            'sku' => $sku,
            'variation_id' => $variation->id,
            'from' => $originalStock,
            'to' => $newStock,
            'delta' => -$quantity,
        ]);
    }

}
