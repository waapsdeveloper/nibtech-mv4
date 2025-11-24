<?php

namespace App\Services;

use App\Models\Marketplace_model;
use Illuminate\Support\Facades\Log;
use Throwable;

class MarketplaceTokenResolver
{
    private const CURRENCY_TO_MARKETPLACE = [
        'EUR' => 2,
        'GBP' => 3,
    ];

    /**
     * Cache marketplace tokens during a single request lifecycle.
     *
     * @var array<int, string|null>
     */
    protected array $cache = [];

    public function resolve(?int $marketplaceId = null, ?string $currency = null): ?string
    {
        $marketplaceId = $marketplaceId ?? $this->mapCurrency($currency);

        if (! $marketplaceId) {
            return null;
        }

        if (array_key_exists($marketplaceId, $this->cache)) {
            return $this->cache[$marketplaceId];
        }

        $token = null;

        try {
            $record = Marketplace_model::query()->find($marketplaceId);
            $token = $record?->api_key;
        } catch (Throwable $exception) {
            Log::warning('Unable to fetch marketplace API token.', [
                'marketplace_id' => $marketplaceId,
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->cache[$marketplaceId] = $token ? trim($token) : null;
    }

    protected function mapCurrency(?string $currency): ?int
    {
        if (! $currency) {
            return null;
        }

        $currency = strtoupper($currency);

        return self::CURRENCY_TO_MARKETPLACE[$currency] ?? null;
    }
}
