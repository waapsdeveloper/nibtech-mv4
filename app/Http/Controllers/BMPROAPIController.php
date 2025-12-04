<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceTokenResolver;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BMPROAPIController extends Controller
{
    private const ENV_PROD = 'prod';
    private const ENV_DEV = 'dev';

    private const BASE_URLS = [
        self::ENV_PROD => 'https://api.pro.backmarket.com/sellers-prod/2024-03',
        self::ENV_DEV => 'https://api.pro.backmarket.com/sellers-dev/2024-03',
    ];

    private string $fallbackAccessToken;
    private string $userAgent;
    private MarketplaceTokenResolver $tokenResolver;

    public function __construct(MarketplaceTokenResolver $tokenResolver)
    {
        $this->tokenResolver = $tokenResolver;
        $this->fallbackAccessToken = trim((string) env('BMPRO_API_TOKEN', ''));
        $this->userAgent = trim((string) env('BMPRO_API_USER_AGENT', 'BMPRO API Client/1.0'));
    }

    public function getOrder(int|string $orderId, string $environment = self::ENV_PROD, array $options = []): array
    {
        $orderId = trim((string) $orderId);

        if ($orderId === '') {
            return [
                'success' => false,
                'status' => 0,
                'error' => 'Order ID is required.',
            ];
        }

        return $this->requestGet('orders/' . $orderId, [], $environment, $options);
    }

    public function cancelOrder(
        int|string $orderId,
        ?string $reason = null,
        string $environment = self::ENV_PROD,
        array $options = []
    ): array {
        $orderId = trim((string) $orderId);

        if ($orderId === '') {
            return [
                'success' => false,
                'status' => 0,
                'error' => 'Order ID is required.',
            ];
        }

        $payload = [];

        if ($reason !== null && trim($reason) !== '') {
            $payload['reason'] = trim($reason);
        }

        return $this->requestPost('orders/' . $orderId . '/cancel', [], $environment, $payload ?: null, $options);
    }

    public function getOrders(array $filters = [], string $environment = self::ENV_PROD, bool $autoPaginate = false, array $options = []): array
    {
        $query = Arr::only($filters, ['fulfillment_status', 'financial_status', 'page-size', 'page']);

        if (! $autoPaginate) {
            return $this->requestGet('orders', $query, $environment, $options);
        }

        $page = isset($query['page']) ? max(1, (int) $query['page']) : 1;
        $aggregated = [];
        $lastResponse = null;

        do {
            $query['page'] = $page;
            $response = $this->requestGet('orders', $query, $environment, $options);
            $lastResponse = $response;

            if (! ($response['success'] ?? false)) {
                break;
            }

            $data = $response['data'] ?? [];
            $items = $this->extractItems($data);

            if (! empty($items)) {
                $aggregated = array_merge($aggregated, $items);
            }

            $hasNextLink = $this->hasNextLink($data);
            $page++;
        } while ($hasNextLink);

        if ($lastResponse === null) {
            return [
                'success' => false,
                'status' => 0,
                'error' => 'Unable to contact BMPRO API.',
            ];
        }

        if (! ($lastResponse['success'] ?? false)) {
            $lastResponse['aggregated'] = $aggregated;
            return $lastResponse;
        }

        return [
            'success' => true,
            'status' => $lastResponse['status'] ?? 200,
            'data' => $aggregated,
            'last_page' => $page - 1,
        ];
    }

    public function getListings(array $filters = [], string $environment = self::ENV_PROD, bool $autoPaginate = false, array $options = []): array
    {
        $query = Arr::only($filters, ['publication_state', 'page-size', 'page']);

        if (! $autoPaginate) {
            return $this->requestGet('listings', $query, $environment, $options);
        }

        $page = isset($query['page']) ? max(1, (int) $query['page']) : 1;
        $aggregated = [];
        $lastResponse = null;

        do {
            $query['page'] = $page;
            $response = $this->requestGet('listings', $query, $environment, $options);
            $lastResponse = $response;

            if (! ($response['success'] ?? false)) {
                break;
            }

            $data = $response['data'] ?? [];
            $items = $this->extractItems($data);

            if (! empty($items)) {
                $aggregated = array_merge($aggregated, $items);
            }

            $hasNextLink = $this->hasNextLink($data);
            $page++;
        } while ($hasNextLink);

        if ($lastResponse === null) {
            return [
                'success' => false,
                'status' => 0,
                'error' => 'Unable to contact BMPRO API.',
            ];
        }

        if (! ($lastResponse['success'] ?? false)) {
            $lastResponse['aggregated'] = $aggregated;
            return $lastResponse;
        }

        return [
            'success' => true,
            'status' => $lastResponse['status'] ?? 200,
            'data' => $aggregated,
            'last_page' => $page - 1,
        ];
    }

    public function getListing(int|string $listingId, string $environment = self::ENV_PROD, array $options = []): array
    {
        $listingId = trim((string) $listingId);

        if ($listingId === '') {
            return [
                'success' => false,
                'status' => 0,
                'error' => 'Listing ID is required.',
            ];
        }

        return $this->requestGet('listings/' . $listingId, [], $environment, $options);
    }

    public function createListing(array $payload, string $environment = self::ENV_PROD, array $query = [], array $options = []): array
    {
        return $this->requestPost('listings', $query, $environment, $payload, $options);
    }

    public function updateListing(int|string $listingId, array $payload, string $environment = self::ENV_PROD, array $query = [], array $options = []): array
    {
        $listingId = trim((string) $listingId);

        if ($listingId === '') {
            return [
                'success' => false,
                'status' => 0,
                'error' => 'Listing ID is required.',
            ];
        }

        return $this->requestPatch('listings/' . $listingId, $query, $environment, $payload, $options);
    }

    public function getStatus(string $environment = self::ENV_PROD, array $options = []): array
    {
        return $this->requestGet('status', [], $environment, $options);
    }

    private function requestGet(string $endpoint, array $query, string $environment, array $options = []): array
    {
        return $this->handleRequest('GET', $endpoint, $query, null, $environment, $options);
    }

    private function requestPost(string $endpoint, array $query, string $environment, array|string|null $payload = null, array $options = []): array
    {
        return $this->handleRequest('POST', $endpoint, $query, $payload, $environment, $options);
    }

    private function requestPatch(string $endpoint, array $query, string $environment, array|string|null $payload = null, array $options = []): array
    {
        return $this->handleRequest('PATCH', $endpoint, $query, $payload, $environment, $options);
    }

    private function handleRequest(string $method, string $endpoint, array $query, array|string|null $payload, string $environment, array $options = []): array
    {
        $token = $this->resolveAccessToken($options);

        $url = $this->resolveEndpoint($endpoint, $environment);

        try {
            $response = $this->sendRequest($method, $url, $query, $payload, $token);
        } catch (Exception $exception) {
            $requestContext = $this->buildRequestContext($method, $url, $query, $payload, $options);

            Log::warning('BMPRO API request failed.', [
                'request' => $requestContext,
                'message' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 0,
                'error' => $exception->getMessage(),
                'request' => $requestContext,
            ];
        }

        if ($response->successful()) {
            return [
                'success' => true,
                'status' => $response->status(),
                'data' => $response->json() ?? [],
                'headers' => $response->headers(),
            ];
        }

        $payload = $response->json();
        $errorBody = $payload ?? $response->body();

        $requestContext = $this->buildRequestContext($method, $url, $query, $payload, $options);

        Log::warning('BMPRO API returned error response.', [
            'request' => $requestContext,
            'status' => $response->status(),
            'body' => $errorBody,
        ]);

        return [
            'success' => false,
            'status' => $response->status(),
            'error' => $errorBody,
            'request' => $requestContext,
        ];
    }

    private function sendRequest(string $method, string $url, array $query, array|string|null $payload, string $token): Response
    {
        $request = Http::withHeaders($this->buildHeaders($token))
            ->timeout((int) env('BMPRO_API_TIMEOUT', 30))
            ->retry((int) env('BMPRO_API_RETRIES', 2), 200);

        $options = [];

        if (! empty($query)) {
            $options['query'] = $query;
        }

        if (is_array($payload)) {
            $options['json'] = $payload;
        } elseif (! is_null($payload)) {
            $options['body'] = $payload;
        }

        return $request->send(strtoupper($method), $url, $options);
    }

    private function resolveEndpoint(string $endpoint, string $environment): string
    {
        $environment = strtolower($environment);
        $baseUrl = self::BASE_URLS[$environment] ?? self::BASE_URLS[self::ENV_PROD];

        $endpoint = ltrim($endpoint, '/');

        return rtrim($baseUrl, '/') . '/' . $endpoint;
    }

    private function buildHeaders(string $token): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => $this->formatAuthorizationHeader($token),
            'User-Agent' => $this->userAgent,
        ];
    }

    private function formatAuthorizationHeader(string $token): string
    {
        $token = trim($token);

        if ($token === '') {
            throw new RuntimeException('BMPRO API token is not configured.');
        }

        if (stripos($token, 'bearer ') === 0 || stripos($token, 'basic ') === 0) {
            return $token;
        }

        return 'Bearer ' . $token;
    }

    private function resolveAccessToken(array $options = []): string
    {
        $marketplaceId = isset($options['marketplace_id']) ? (int) $options['marketplace_id'] : null;
        $currency = $options['currency'] ?? null;

        $token = $this->tokenResolver->resolve($marketplaceId, $currency);

        if ($token !== null && $token !== '') {
            return $token;
        }

        if ($this->fallbackAccessToken === '') {
            throw new RuntimeException('BMPRO API token is not configured.');
        }

        return $this->fallbackAccessToken;
    }

    private function buildRequestContext(string $method, string $url, array $query, array|string|null $payload, array $options = []): array
    {
        return [
            'method' => strtoupper($method),
            'url' => $url,
            'query' => $query,
            'payload' => is_scalar($payload) ? $payload : (is_array($payload) ? $payload : null),
            'options' => $options,
        ];
    }

    private function extractItems($data): array
    {
        if (is_array($data)) {
            if (isset($data['items']) && is_array($data['items'])) {
                return $data['items'];
            }
            if (isset($data['results']) && is_array($data['results'])) {
                return $data['results'];
            }
            if (isset($data['listings']) && is_array($data['listings'])) {
                return $data['listings'];
            }
        }

        return [];
    }

    private function hasNextLink($data): bool
    {
        if (! is_array($data)) {
            return false;
        }

        if (! empty($data['links']['next'])) {
            return true;
        }

        if (isset($data['pagination']['has_next']) && $data['pagination']['has_next']) {
            return true;
        }

        return false;
    }
}
