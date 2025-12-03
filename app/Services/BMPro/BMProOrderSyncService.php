<?php

namespace App\Services\BMPro;

use App\Http\Controllers\BMPROAPIController;
use App\Models\Order_model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class BMProOrderSyncService
{
    private const DEFAULT_TARGETS = [
        ['currency' => 'EUR', 'marketplace_id' => 2],
        ['currency' => 'GBP', 'marketplace_id' => 3],
    ];

    public function __construct(
        private BMPROAPIController $bmpro,
        private Order_model $orders
    ) {
    }

    /**
     * Synchronize orders from Back Market Pro.
     *
     * @param  array{fulfillment_status?: string, financial_status?: string, page-size?: int, page?: int}  $filters
     * @param  array{
     *     currencies?: array<int,string>,
     *     currency?: array<int,string>|string|null,
     *     marketplace_ids?: array<int,int>,
     *     marketplace_id?: array<int,int>|int|null,
     *     environment?: string,
     *     auto_paginate?: bool
     * }  $options
     */
    public function sync(array $filters = [], array $options = []): array
    {
        $environment = $options['environment'] ?? 'prod';
        $autoPaginate = array_key_exists('auto_paginate', $options)
            ? (bool) $options['auto_paginate']
            : true;

        $targets = $this->resolveTargets($options);
        $filters = $this->sanitizeFilters($filters);

        $summaries = [];

        foreach ($targets as $target) {
            $requestOptions = [
                'marketplace_id' => $target['marketplace_id'],
                'currency' => $target['currency'],
            ];

            $response = $this->bmpro->getOrders($filters, $environment, $autoPaginate, $requestOptions);

            if (! ($response['success'] ?? false)) {
                $summaries[] = [
                    'currency' => $target['currency'],
                    'marketplace_id' => $target['marketplace_id'],
                    'processed' => 0,
                    'failed' => 0,
                    'success' => false,
                    'message' => $response['error'] ?? 'Unknown BMPRO API error.',
                ];

                continue;
            }

            $orders = $this->extractOrders($response['data'] ?? []);

            $processed = 0;
            $failed = 0;

            foreach ($orders as $orderPayload) {
                try {
                    $items = $this->extractItems($orderPayload);
                    $this->orders->storeBMProOrderInDB(
                        $orderPayload,
                        $items,
                        [],
                        [],
                        $target['marketplace_id'],
                        $target['currency']
                    );
                    $processed++;
                } catch (\Throwable $exception) {
                    $failed++;
                    Log::error('BMPRO: failed to persist order', [
                        'marketplace_id' => $target['marketplace_id'],
                        'currency' => $target['currency'],
                        'order_id' => $orderPayload['id'] ?? ($orderPayload['order_number'] ?? null),
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            $summaries[] = [
                'currency' => $target['currency'],
                'marketplace_id' => $target['marketplace_id'],
                'processed' => $processed,
                'failed' => $failed,
                'success' => $failed === 0,
            ];
        }

        return $summaries;
    }

    private function sanitizeFilters(array $filters): array
    {
        $allowed = ['fulfillment_status', 'financial_status', 'page-size', 'page'];
        $filtered = Arr::only($filters, $allowed);

        return array_filter($filtered, fn ($value) => $value !== null && $value !== '');
    }

    private function resolveTargets(array $options): array
    {
        $marketplaceOptions = $this->normalizeList(
            $options['marketplace_ids'] ?? $options['marketplace_id'] ?? []
        );
        $currencyOptions = $this->normalizeList(
            $options['currencies'] ?? $options['currency'] ?? []
        );

        $targets = [];

        if (! empty($marketplaceOptions)) {
            foreach ($marketplaceOptions as $marketplaceId) {
                $targets[] = [
                    'marketplace_id' => $marketplaceId,
                    'currency' => $this->mapMarketplaceToCurrency($marketplaceId),
                ];
            }
        } elseif (! empty($currencyOptions)) {
            foreach ($currencyOptions as $currency) {
                $targets[] = [
                    'currency' => $currency,
                    'marketplace_id' => $this->mapCurrencyToMarketplace($currency),
                ];
            }
        } else {
            $targets = self::DEFAULT_TARGETS;
        }

        return array_map(function (array $target) {
            $currency = strtoupper($target['currency'] ?? 'EUR');
            $marketplaceId = $target['marketplace_id'] ?? $this->mapCurrencyToMarketplace($currency);

            return [
                'currency' => $currency,
                'marketplace_id' => $marketplaceId,
            ];
        }, $targets);
    }

    private function normalizeList($value): array
    {
        $value = Arr::wrap($value);

        $value = array_map(static function ($entry) {
            if ($entry === null) {
                return null;
            }

            if (is_numeric($entry)) {
                return (int) $entry;
            }

            return is_string($entry) ? trim($entry) : null;
        }, $value);

        return array_values(array_filter($value, fn ($entry) => $entry !== null && $entry !== ''));
    }

    private function extractOrders($data): array
    {
        if (isset($data['items']) && is_array($data['items'])) {
            return $data['items'];
        }

        if (isset($data['orders']) && is_array($data['orders'])) {
            return $data['orders'];
        }

        if (is_array($data) && ! Arr::isAssoc($data)) {
            return $data;
        }

        return [];
    }

    private function extractItems($orderPayload): ?array
    {
        if (isset($orderPayload['items']) && is_array($orderPayload['items'])) {
            return $orderPayload['items'];
        }

        if (isset($orderPayload['order_lines']) && is_array($orderPayload['order_lines'])) {
            return $orderPayload['order_lines'];
        }

        if (isset($orderPayload['order_items']) && is_array($orderPayload['order_items'])) {
            return $orderPayload['order_items'];
        }

        return null;
    }

    private function mapCurrencyToMarketplace(string $currency): int
    {
        return match (strtoupper($currency)) {
            'GBP' => 3,
            default => 2,
        };
    }

    private function mapMarketplaceToCurrency(int $marketplaceId): string
    {
        return match ($marketplaceId) {
            3 => 'GBP',
            default => 'EUR',
        };
    }
}
