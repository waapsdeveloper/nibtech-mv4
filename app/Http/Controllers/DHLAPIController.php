<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use RuntimeException;

class DHLAPIController extends Controller
{
    protected string $baseUrl;

    protected string $authUrl;

    protected string $clientId;

    protected string $clientSecret;

    protected ?string $accountNumber;

    protected int $timeout;

    protected int $maxRetries;

    protected int $retryDelayMs;

    protected int $tokenTtl;

    protected ?string $logChannel;

    protected ?string $preferredLanguage;

    protected ?string $cacheStore;

    public function __construct()
    {
        $config = config('services.dhl', []);

        $this->clientId = (string) ($config['client_id'] ?? '');
        $this->clientSecret = (string) ($config['client_secret'] ?? '');

        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new RuntimeException('DHL API client credentials are missing. Set DHL_CLIENT_ID and DHL_CLIENT_SECRET in your environment.');
        }

        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.dhl.com/mydhlapi/test', '/');
        $this->authUrl = $config['auth_url'] ?? 'https://api.dhl.com/mydhlapi/oauth/token';
        $this->accountNumber = $config['account_number'] ?? null;
        $this->timeout = (int) ($config['timeout'] ?? 30);
        $this->maxRetries = (int) ($config['max_retries'] ?? 3);
        $this->retryDelayMs = (int) ($config['retry_delay_ms'] ?? 250);
        $this->tokenTtl = (int) ($config['token_ttl'] ?? 3500);
        $this->logChannel = $config['log_channel'] ?? null;
        $this->preferredLanguage = $config['preferred_language'] ?? null;
        $this->cacheStore = $config['cache_store'] ?? null;
    }

    public function createShipment(array $shipment): array
    {
        if ($this->accountNumber && empty($shipment['accounts'])) {
            $shipment['accounts'] = [[
                'number' => $this->accountNumber,
                'typeCode' => 'shipper',
            ]];
        }

        return $this->post('/shipments', $this->cleanPayload($shipment));
    }

    public function getShipment(string $shipmentTrackingNumber): array
    {
        return $this->get("/shipments/{$shipmentTrackingNumber}");
    }

    public function downloadShipmentLabel(string $shipmentTrackingNumber, string $format = 'PDF'): array
    {
        return $this->get("/shipments/{$shipmentTrackingNumber}/labels", [
            'format' => $format,
        ]);
    }

    public function trackShipment(string $trackingNumber, ?string $originCountryCode = null, ?string $destinationCountryCode = null): array
    {
        return $this->get('/tracking', array_filter([
            'trackingNumber' => $trackingNumber,
            'originCountryCode' => $originCountryCode,
            'destinationCountryCode' => $destinationCountryCode,
        ]));
    }

    public function getRates(array $rateRequest): array
    {
        return $this->post('/rates', $this->cleanPayload($rateRequest));
    }

    public function createShippingLabel(array $labelRequest): array
    {
        return $this->post('/shipments/labels', $this->cleanPayload($labelRequest));
    }

    protected function post(string $path, array $body = []): array
    {
        $response = $this->send($path, function (PendingRequest $request, string $url) use ($body) {
            return $request->post($url, $body);
        });

        return $this->handleResponse($response, $path);
    }

    protected function get(string $path, array $query = []): array
    {
        $response = $this->send($path, function (PendingRequest $request, string $url) use ($query) {
            return $request->get($url, $query);
        });

        return $this->handleResponse($response, $path);
    }

    /**
     * @param  callable(PendingRequest,string):Response  $callback
     */
    protected function send(string $path, callable $callback): Response
    {
        $url = $this->buildUrl($path);

        $response = $callback($this->http($this->accessToken()), $url);

        if ($response->status() === 401) {
            $this->forgetToken();
            $response = $callback($this->http($this->accessToken()), $url);
        }

        return $response;
    }

    protected function http(string $token): PendingRequest
    {
        $request = Http::withHeaders($this->defaultHeaders($token))
            ->timeout($this->timeout)
            ->acceptJson();

        if ($this->maxRetries > 0) {
            $request = $request->retry($this->maxRetries, $this->retryDelayMs, function ($exception) {
                return $this->shouldRetry($exception);
            });
        }

        return $request;
    }

    protected function defaultHeaders(string $token): array
    {
        return array_filter([
            'Authorization' => 'Bearer ' . $token,
            'DHL-API-Key' => $this->clientId,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Accept-Language' => $this->preferredLanguage,
        ]);
    }

    protected function handleResponse(Response $response, string $path): array
    {
        if ($response->failed()) {
            $this->logError('DHL API request failed', [
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

    protected function accessToken(): string
    {
        $ttlSeconds = max(60, $this->tokenTtl - 60);

        return $this->cache()->remember($this->tokenCacheKey(), now()->addSeconds($ttlSeconds), function () {
            $response = Http::asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->acceptJson()
                ->timeout($this->timeout)
                ->post($this->authUrl, [
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->failed()) {
                $this->logError('DHL auth request failed', [
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);

                $response->throw();
            }

            $token = $response->json()['access_token'] ?? null;

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('DHL auth response did not include an access token.');
            }

            return $token;
        });
    }

    protected function forgetToken(): void
    {
        $this->cache()->forget($this->tokenCacheKey());
    }

    protected function cache(): CacheRepository
    {
        return $this->cacheStore
            ? Cache::store($this->cacheStore)
            : Cache::store(config('cache.default'));
    }

    protected function tokenCacheKey(): string
    {
        return 'dhl-api-token-' . md5($this->clientId . $this->baseUrl);
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
