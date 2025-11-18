<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use RuntimeException;

class RefurbedAPIController extends Controller
{
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

        $this->apiKey = (string) ($config['api_key'] ?? '');

        if ($this->apiKey === '') {
            throw new RuntimeException('Refurbed API key is missing. Set REFURBED_API_KEY in your environment.');
        }

        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.refurbed.com', '/');
        $this->authScheme = trim($config['auth_scheme'] ?? 'Bearer');
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

    public function getOrder(string $orderId): array
    {
        return $this->post('refb.merchant.v1.OrderService/GetOrder', ['id' => $orderId]);
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

    public function updateOrderItemState(string $orderItemId, string $state, array $attributes = []): array
    {
        return $this->post('refb.merchant.v1.OrderItemService/UpdateOrderItemState', $this->cleanPayload(array_merge([
            'id' => $orderItemId,
            'state' => $state,
        ], $attributes)));
    }

    public function listOffers(array $filter = [], array $pagination = [], array $sort = []): array
    {
        return $this->post('refb.merchant.v1.OfferService/ListOffers', $this->cleanPayload([
            'filter' => $filter,
            'pagination' => $pagination,
            'sort' => $sort,
        ]));
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

    public function listShippingProfiles(array $filter = [], array $pagination = [], array $sort = []): array
    {
        return $this->post('refb.merchant.v1.ShippingProfileService/ListShippingProfiles', $this->cleanPayload([
            'filter' => $filter,
            'pagination' => $pagination,
            'sort' => $sort,
        ]));
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

    protected function logger(): LoggerInterface
    {
        if ($this->logChannel) {
            return Log::channel($this->logChannel);
        }

        return Log::channel(config('logging.default'));
    }
}
