<?php

namespace App\Http\Controllers;

use App\Models\Marketplace_model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class RefurbedAPIController extends Controller
{
    private const MAX_BATCH_SIZE = 50;
    protected string $baseUrl;

    protected string $apiKey;

    protected string $authScheme;

    protected string $userAgent;

    protected int $timeout;

    protected int $maxRetries;

    protected int $retryDelayMs;

    protected ?string $logChannel;

    protected ?string $sourceSystem;

    public function __construct()
    {
        $config = config('services.refurbed', []);

        // Try to get API key from marketplace table first, fallback to config
        try {
            $marketplaceToken = Marketplace_model::where('name', 'Refurbed')->first()?->api_key;
        } catch (Throwable $e) {
            $marketplaceToken = null;
            Log::warning('Refurbed: unable to read API key from marketplace table', ['error' => $e->getMessage()]);
        }

        $this->apiKey = (string) ($marketplaceToken ?? $config['api_key'] ?? '');

        if ($this->apiKey === '') {
            throw new RuntimeException('Refurbed API key is missing. Set api_key in marketplace table for "Refurbed" or REFURBED_API_KEY in your environment.');
        }

        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.refurbed.com', '/');
        $this->authScheme = trim($config['auth_scheme'] ?? 'Plain');
        $this->userAgent = $config['user_agent'] ?? config('app.name', 'nibritaintech') . '/RefurbedConnector';
        $this->timeout = (int) ($config['timeout'] ?? 30);
        $this->maxRetries = (int) ($config['max_retries'] ?? 3);
        $this->retryDelayMs = (int) ($config['retry_delay_ms'] ?? 250);
        $this->logChannel = $config['log_channel'] ?? null;
        $this->sourceSystem = $config['source_system'] ?? 'nibritaintech';
    }

    public function listOrders(array $filter = [], array $pagination = [], array $sort = []): array
    {
        return $this->post('refb.merchant.v1.OrderService/ListOrders', $this->cleanPayload([
            'filter' => $filter,
            'pagination' => $pagination,
            'sort' => $sort,
        ]));
    }

    /**
     * Get all orders with automatic pagination
     */
    public function getAllOrders(array $filter = [], array $sort = [], int $pageSize = 100): array
    {
        $allOrders = [];
        $pageToken = null;
        $pageCount = 0;
        $hasMore = true;

        while ($hasMore) {
            $pagination = array_filter([
                'page_size' => $pageSize,
                'page_token' => $pageToken,
            ]);

            $response = $this->listOrders($filter, $pagination, $sort);

            if (!empty($response['orders'])) {
                $allOrders = array_merge($allOrders, $response['orders']);

                $lastOrder = end($response['orders']);
                $pageToken = $lastOrder['id'] ?? null;
            }

            $hasMore = $response['has_more'] ?? false;
            $pageCount++;

            if ($pageCount > 100) {
                Log::warning("Refurbed: Reached page limit for orders", ['pages' => $pageCount]);
                break;
            }
        }

        return [
            'orders' => $allOrders,
            'total' => count($allOrders)
        ];
    }

    public function getOrder(string $orderId): array
    {
        return $this->post('refb.merchant.v1.OrderService/GetOrder', ['id' => $orderId]);
    }

    public function getOrderInvoice(string $orderId): array
    {
        return $this->post('refb.merchant.v1.OrderService/GetOrderInvoice', [
            'order_id' => $orderId,
        ]);
    }

    public function getOrderCommercialInvoice(string $orderId): array
    {
        return $this->post('refb.merchant.v1.OrderService/GetOrderCommercialInvoice', [
            'order_id' => $orderId,
        ]);
    }

    public function acceptOrder(string $orderId): array
    {
        return $this->post('refb.merchant.v1.OrderService/AcceptOrder', ['id' => $orderId]);
    }

    public function listOrderItems(string $orderId, array $filter = [], array $pagination = [], array $sort = []): array
    {
        return $this->post('refb.merchant.v1.OrderItemService/ListOrderItemsByOrder', $this->cleanPayload([
            'order_id' => $orderId,
            'filter' => $filter,
            'pagination' => $pagination,
            'sort' => $sort,
        ]));
    }

    /**
     * Get all order items with automatic pagination
     */
    public function getAllOrderItems(string $orderId, array $filter = [], array $sort = [], int $pageSize = 100): array
    {
        $allItems = [];
        $pageToken = null;
        $pageCount = 0;
        $hasMore = true;

        while ($hasMore) {
            $pagination = array_filter([
                'page_size' => $pageSize,
                'page_token' => $pageToken,
            ]);

            $response = $this->listOrderItems($orderId, $filter, $pagination, $sort);

            if (!empty($response['order_items'])) {
                $allItems = array_merge($allItems, $response['order_items']);

                $lastItem = end($response['order_items']);
                $pageToken = $lastItem['id'] ?? null;
            }

            $hasMore = $response['has_more'] ?? false;
            $pageCount++;

            if ($pageCount > 100) {
                Log::warning("Refurbed: Reached page limit for order items", ['pages' => $pageCount]);
                break;
            }
        }

        return [
            'order_items' => $allItems,
            'total' => count($allItems)
        ];
    }

    public function updateOrderItemState(string $orderItemId, string $state, array $attributes = []): array
    {
        return $this->post('refb.merchant.v1.OrderItemService/UpdateOrderItemState', $this->cleanPayload(array_merge([
            'id' => $orderItemId,
            'state' => $state,
        ], $attributes)));
    }

    /**
     * Batch update order items (tracking payload, carrier info, etc.).
     *
     * @param  array  $orderItemUpdates  Each entry mirrors the payload of the single-item UpdateOrderItem endpoint.
     * @param  array  $options  Optional keys: chunk_size (defaults to 50) and body (extra fields merged into every request).
     */
    public function batchUpdateOrderItems(array $orderItemUpdates, array $options = []): array
    {
        return $this->sendBatchedOrderItemUpdates(
            'refb.merchant.v1.OrderItemService/BatchUpdateOrderItems',
            'order_item_updates',
            $orderItemUpdates,
            $options
        );
    }

    /**
     * Batch update order item states (e.g. NEW -> SHIPPED) with optional tracking metadata per item.
     *
     * @param  array  $stateUpdates  Each entry must include at least an id and state field accepted by Refurbed.
     * @param  array  $options  Optional keys: chunk_size (defaults to 50) and body (extra fields merged into every request).
     */
    public function batchUpdateOrderItemsState(array $stateUpdates, array $options = []): array
    {
        return $this->sendBatchedOrderItemUpdates(
            'refb.merchant.v1.OrderItemService/BatchUpdateOrderItemsState',
            'order_item_state_updates',
            $stateUpdates,
            $options
        );
    }

    public function listOffers(array $filter = [], array $pagination = [], array $sort = []): array
    {
        return $this->post('refb.merchant.v1.OfferService/ListOffers', $this->cleanPayload([
            'filter' => $filter,
            'pagination' => $pagination,
            'sort' => $sort,
        ]));
    }

    /**
     * Get all offers with automatic pagination
     */
    public function getAllOffers(array $filter = [], array $sort = [], int $pageSize = 100): array
    {
        $allOffers = [];
        $pageToken = null;
        $pageCount = 0;
        $hasMore = true;

        while ($hasMore) {
            $pagination = array_filter([
                'page_size' => $pageSize,
                'page_token' => $pageToken,
            ]);

            // Add delay between requests to avoid rate limiting (except for first page)
            if ($pageCount > 0) {
                usleep(500000); // 500ms delay between pages
            }

            $response = $this->listOffers($filter, $pagination, $sort);

            if (!empty($response['offers'])) {
                $allOffers = array_merge($allOffers, $response['offers']);

                // Use the last offer's ID as the page token for the next request
                $lastOffer = end($response['offers']);
                $pageToken = $lastOffer['id'] ?? null;
            }

            // Check has_more flag to determine if there are more pages
            $hasMore = $response['has_more'] ?? false;

            $pageCount++;

            // Safety limit to prevent infinite loops
            if ($pageCount > 100) {
                Log::warning("Refurbed: Reached page limit", ['pages' => $pageCount]);
                break;
            }
        }

        Log::info("Refurbed: Fetched all offers", [
            'total_pages' => $pageCount,
            'total_offers' => count($allOffers)
        ]);

        return [
            'offers' => $allOffers,
            'total' => count($allOffers)
        ];
    }

    public function updateOffer(array $identifier, array $updates): array
    {
        if (empty($identifier)) {
            throw new RuntimeException('Offer identifier is required when updating offers.');
        }

        return $this->post('refb.merchant.v1.OfferService/UpdateOffer', $this->cleanPayload(array_merge([
            'identifier' => $identifier,
        ], $updates)));
    }

    public function getOffer(array $identifier): array
    {
        if (empty($identifier)) {
            throw new RuntimeException('Offer identifier is required when fetching offers.');
        }

        return $this->post('refb.merchant.v1.OfferService/GetOffer', $this->cleanPayload([
            'identifier' => $identifier,
        ]));
    }

    public function createShippingLabel(string $orderId, string $merchantAddressId, float $parcelWeight, ?string $carrier = null): array
    {
        return $this->post('refb.merchant.v1.OrderService/CreateShippingLabel', $this->cleanPayload([
            'order_id' => $orderId,
            'merchant_address_id' => $merchantAddressId,
            'parcel_weight' => $parcelWeight,
            'carrier' => $carrier,
        ]));
    }

    public function listShippingLabels(string $orderId): array
    {
        return $this->post('refb.merchant.v1.OrderService/ListShippingLabels', ['order_id' => $orderId]);
    }

    public function listMerchantAddresses(array $pagination = []): array
    {
        return $this->post('refb.merchant.v1.MerchantService/ListMerchantAddresses', $this->cleanPayload([
            'pagination' => $pagination,
        ]));
    }

    public function createMerchantAddress(array $payload): array
    {
        return $this->post('refb.merchant.v1.MerchantService/CreateMerchantAddress', $this->cleanPayload($payload));
    }

    public function listShippingProfiles(array $filter = [], array $pagination = [], array $sort = []): array
    {
        return $this->post('refb.merchant.v1.ShippingProfileService/ListShippingProfiles', $this->cleanPayload([
            'filter' => $filter,
            'pagination' => $pagination,
            'sort' => $sort,
        ]));
    }

    /**
     * Get all shipping profiles with automatic pagination
     */
    public function getAllShippingProfiles(array $filter = [], array $sort = [], int $pageSize = 100): array
    {
        $allProfiles = [];
        $pageToken = null;
        $pageCount = 0;
        $hasMore = true;

        while ($hasMore) {
            $pagination = array_filter([
                'page_size' => $pageSize,
                'page_token' => $pageToken,
            ]);

            $response = $this->listShippingProfiles($filter, $pagination, $sort);

            if (!empty($response['shipping_profiles'])) {
                $allProfiles = array_merge($allProfiles, $response['shipping_profiles']);

                $lastProfile = end($response['shipping_profiles']);
                $pageToken = $lastProfile['id'] ?? null;
            }

            $hasMore = $response['has_more'] ?? false;
            $pageCount++;

            if ($pageCount > 100) {
                Log::warning("Refurbed: Reached page limit for shipping profiles", ['pages' => $pageCount]);
                break;
            }
        }

        return [
            'shipping_profiles' => $allProfiles,
            'total' => count($allProfiles)
        ];
    }

    protected function post(string $path, array $body = []): array
    {
        $response = $this->http()->post($this->buildUrl($path), $body);

        return $this->handleResponse($response, $path);
    }

    protected function get(string $path, array $query = []): array
    {
        $response = $this->http()->get($this->buildUrl($path), $query);

        return $this->handleResponse($response, $path);
    }

    protected function http(): PendingRequest
    {
        $request = Http::withHeaders($this->defaultHeaders())
            ->timeout($this->timeout)
            ->acceptJson();

        if ($this->maxRetries > 0) {
            $request = $request->retry($this->maxRetries, $this->retryDelayMs, function ($exception, $request) {
                return $this->shouldRetry($exception);
            });
        }

        return $request;
    }

    protected function defaultHeaders(): array
    {
        return array_filter([
            'Authorization' => trim($this->authScheme . ' ' . $this->apiKey),
            'User-Agent' => $this->userAgent,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Source-System' => $this->sourceSystem,
        ]);
    }

    protected function handleResponse(Response $response, string $path): array
    {
        if ($response->failed()) {
            $this->logError('Refurbed API request failed', [
                'endpoint' => $path,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            $response->throw();
        }

        return $response->json() ?? [];
    }

    protected function shouldRetry($exception): bool
    {
        if (! $exception instanceof RequestException || ! $exception->response) {
            return false;
        }

        return in_array($exception->response->status(), [408, 425, 429, 500, 502, 503, 504], true);
    }

    protected function buildUrl(string $path): string
    {
        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    protected function cleanPayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->cleanPayload($value);
            }

            if ($payload[$key] === [] || $payload[$key] === null) {
                unset($payload[$key]);
            }
        }

        return $payload;
    }

    protected function logError(string $message, array $context = []): void
    {
        $this->logger()->error($message, $context);
    }

    /**
     * Send Refurbed OrderItem batch requests safely (enforcing the 50 item API limit).
     */
    protected function sendBatchedOrderItemUpdates(string $endpoint, string $payloadKey, array $updates, array $options = []): array
    {
        $updates = array_values(array_filter($updates, fn ($item) => ! empty($item)));

        if ($updates === []) {
            return [
                'batches' => [],
                'total' => 0,
            ];
        }

        $chunkSize = (int) ($options['chunk_size'] ?? self::MAX_BATCH_SIZE);
        $chunkSize = max(1, min($chunkSize, self::MAX_BATCH_SIZE));
        $additionalBody = $options['body'] ?? [];
        $responses = [];

        foreach (array_chunk($updates, $chunkSize) as $chunk) {
            $body = $this->cleanPayload(array_merge($additionalBody, [
                $payloadKey => $chunk,
            ]));

            $responses[] = $this->post($endpoint, $body);
        }

        return [
            'batches' => $responses,
            'total' => count($updates),
        ];
    }

    protected function logger(): LoggerInterface
    {
        if ($this->logChannel) {
            return Log::channel($this->logChannel);
        }

        return Log::channel(config('logging.default'));
    }
}
