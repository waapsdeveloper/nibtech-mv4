<?php

namespace App\Services\Support;

use App\Http\Controllers\BMPROAPIController;
use App\Http\Controllers\RefurbedAPIController;
use App\Models\SupportThread;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MarketplaceOrderActionService
{
    public function buildMarketplaceOrderUrl(?SupportThread $thread): ?string
    {
        if (! $thread) {
            return null;
        }

        $metadataUrl = data_get($thread->metadata, 'order_url');
        if ($metadataUrl && filter_var($metadataUrl, FILTER_VALIDATE_URL)) {
            return $metadataUrl;
        }

        $reference = $this->resolveOrderReference($thread);
        if (! $reference) {
            return null;
        }

        return match ($this->resolveMarketplace($thread)) {
            'refurbed' => sprintf('https://merchants.refurbed.com/orders/%s', rawurlencode($reference)),
            'bmpro', 'backmarket' => sprintf(
                'https://backmarket.fr/bo-seller/orders/all?orderId=%s#order-details=%s',
                rawurlencode($reference),
                rawurlencode($reference)
            ),
            default => null,
        };
    }

    public function supportsCancellation(?SupportThread $thread): bool
    {
        if (! $thread) {
            return false;
        }

        return in_array($this->resolveMarketplace($thread), ['refurbed', 'bmpro', 'backmarket'], true);
    }

    public function cancelOrder(SupportThread $thread, ?string $reason = null): array
    {
        $reference = $this->resolveOrderReference($thread);

        if (! $reference) {
            return $this->failure('Order reference is missing for this ticket.');
        }

        $marketplace = $this->resolveMarketplace($thread);

        if (! $marketplace) {
            return $this->failure('Marketplace is not linked to this ticket.');
        }

        if (! $this->supportsCancellation($thread)) {
            return $this->failure('Marketplace cancellations are not supported for this ticket.');
        }

        $reason = trim($reason ?? 'Cancelled via Support Hub');

        try {
            if ($marketplace === 'refurbed') {
                $client = app(RefurbedAPIController::class);
                $payload = $client->cancelOrder($reference, $reason);

                return $this->success('Refurbed order cancelled on marketplace.', $payload);
            }

            if (in_array($marketplace, ['bmpro', 'backmarket'], true)) {
                $client = app(BMPROAPIController::class);
                $response = $client->cancelOrder(
                    $reference,
                    $reason,
                    $this->resolveEnvironment($thread),
                    $this->buildBmproOptions($thread)
                );

                if (! ($response['success'] ?? false)) {
                    $message = is_string($response['error'] ?? null)
                        ? $response['error']
                        : 'Back Market API rejected the cancellation.';

                    return $this->failure($message, $response);
                }

                return $this->success('Back Market order cancelled on marketplace.', $response);
            }
        } catch (Throwable $exception) {
            Log::error('MarketplaceOrderActionService: cancel failed', [
                'thread_id' => $thread->id,
                'marketplace' => $marketplace,
                'reference' => $reference,
                'error' => $exception->getMessage(),
            ]);

            return $this->failure($exception->getMessage());
        }

        return $this->failure('Marketplace cancellations are not implemented for this channel.');
    }

    protected function resolveOrderReference(SupportThread $thread): ?string
    {
        $candidates = [
            $thread->order_reference,
            optional($thread->order)->reference_id,
            optional($thread->order)->reference,
            data_get($thread->metadata, 'order_reference'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    protected function resolveMarketplace(?SupportThread $thread): ?string
    {
        if (! $thread) {
            return null;
        }

        $orderMarketplaceId = optional($thread->order)->marketplace_id;
        $threadMarketplaceId = $thread->marketplace_id;
        $marketplaceName = optional($thread->marketplace)->name;
        $source = strtolower((string) $thread->marketplace_source);

        if ($orderMarketplaceId) {
            if ((int) $orderMarketplaceId === 4) {
                return 'refurbed';
            }

            if (in_array((int) $orderMarketplaceId, [1, 2, 3], true)) {
                return 'bmpro';
            }
        }

        if ($threadMarketplaceId) {
            if ((int) $threadMarketplaceId === 4) {
                return 'refurbed';
            }

            if (in_array((int) $threadMarketplaceId, [1, 2, 3], true)) {
                return 'bmpro';
            }
        }

        $name = strtolower((string) $marketplaceName);

        if ($name !== '') {
            if (Str::contains($name, 'refurbed')) {
                return 'refurbed';
            }

            if (Str::contains($name, ['back', 'bmpro'])) {
                return 'bmpro';
            }
        }

        if ($source !== '') {
            if (str_contains($source, 'refurbed')) {
                return 'refurbed';
            }

            if (str_contains($source, 'back')) {
                return 'bmpro';
            }
        }

        $metadataSource = strtolower((string) data_get($thread->metadata, 'marketplace'));
        if ($metadataSource !== '') {
            if (str_contains($metadataSource, 'refurbed')) {
                return 'refurbed';
            }

            if (str_contains($metadataSource, 'back')) {
                return 'bmpro';
            }
        }

        return null;
    }

    protected function resolveEnvironment(SupportThread $thread): string
    {
        $env = strtolower((string) data_get($thread->metadata, 'bm_environment', 'prod'));

        return $env === 'dev' ? 'dev' : 'prod';
    }

    protected function buildBmproOptions(SupportThread $thread): array
    {
        $options = [];
        $marketplaceId = optional($thread->order)->marketplace_id ?? $thread->marketplace_id;

        if ($marketplaceId) {
            $options['marketplace_id'] = (int) $marketplaceId;
        }

        $currency = optional($thread->order)->currency ?? data_get($thread->metadata, 'currency');
        if ($currency && ! isset($options['marketplace_id'])) {
            $options['currency'] = $currency;
        }

        return $options;
    }

    protected function success(string $message, $payload = null): array
    {
        return [
            'success' => true,
            'message' => $message,
            'payload' => $payload,
        ];
    }

    protected function failure(string $message, $payload = null): array
    {
        return [
            'success' => false,
            'message' => $message,
            'payload' => $payload,
        ];
    }
}
