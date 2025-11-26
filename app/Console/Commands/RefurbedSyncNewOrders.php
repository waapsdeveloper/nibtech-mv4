<?php

namespace App\Console\Commands;

use App\Http\Controllers\RefurbedAPIController;
use App\Models\Country_model;
use App\Models\Currency_model;
use App\Models\Order_model;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefurbedSyncNewOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refurbed:new
        {--state=* : Override the default Refurbed states (NEW,PENDING)}
        {--page-size=50 : Page size for each API request (max 200)}
        {--lookback-hours=48 : Re-sync Refurbed orders created within the last N hours}
        {--skip-items : Skip fetching order items for each order}';

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
            $states = ['NEW', 'PENDING'];
        }

        $refurbed = new RefurbedAPIController();
        $orderModel = new Order_model();
        $currencyCodes = $this->getCurrencyCodes();
        $countryCodes = $this->getCountryCodes();

        $filter = [];
        $pageSize = $this->sanitizePageSize((int) $this->option('page-size'));
        $sort = [
            'order_by' => 'CREATED_AT',
            'direction' => 'DESC',
        ];

        $syncStats = $this->syncOrders($refurbed, $orderModel, $filter, $sort, $pageSize, $currencyCodes, $countryCodes, $states);

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

    private function syncOrders(
        RefurbedAPIController $refurbed,
        Order_model $orderModel,
        array $filter,
        array $sort,
        int $pageSize,
        array $currencyCodes,
        array $countryCodes,
        array $stateWhitelist
    ): array {
        try {
            $response = $refurbed->getAllOrders($filter, $sort, $pageSize);
        } catch (\Throwable $e) {
            Log::error('Refurbed: failed to fetch new orders', [
                'error' => $e->getMessage(),
                'filter' => $filter,
            ]);

            $this->error('Refurbed new-order sync failed: ' . $e->getMessage());

            return ['processed' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $orders = $response['orders'] ?? [];
        $processed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($orders as $orderData) {
            $orderData = $this->adaptOrderPayload($orderData);
            $preAcceptanceState = strtoupper($orderData['state'] ?? '');
            $orderData = $this->acceptOrderIfNeeded($refurbed, $orderData);
            $orderId = $orderData['id'] ?? $orderData['order_number'] ?? null;

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

            $orderItems = $orderData['order_items'] ?? $orderData['items'] ?? null;

            if (! $orderItems && ! $this->option('skip-items')) {
                $orderItems = $this->fetchOrderItems($refurbed, $orderId);
            }

            try {
                $orderModel->storeRefurbedOrderInDB($orderData, $orderItems, $currencyCodes, $countryCodes);
                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Refurbed: failed to persist new order', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
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
            $orderId = $this->resolveRefurbedOrderId($orderRecord);

            if (! $orderId) {
                continue;
            }

            try {
                $orderResponse = $refurbed->getOrder($orderId);
            } catch (\Throwable $e) {
                Log::warning('Refurbed: failed to fetch order details during refresh', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            $orderPayload = $this->adaptOrderPayload($orderResponse['order'] ?? $orderResponse);
            $orderItems = $orderPayload['order_items'] ?? $orderPayload['items'] ?? null;

            if (! $orderItems && ! $this->option('skip-items')) {
                $orderItems = $this->fetchOrderItems($refurbed, $orderId);
            }

            try {
                $orderModel->storeRefurbedOrderInDB($orderPayload, $orderItems, $currencyCodes, $countryCodes);
                $refreshed++;
            } catch (\Throwable $e) {
                Log::error('Refurbed: failed to refresh order', [
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

    private function acceptOrderIfNeeded(RefurbedAPIController $refurbed, array $orderData): array
    {
        $orderId = $orderData['id'] ?? $orderData['order_number'] ?? null;
        $state = strtoupper($orderData['state'] ?? '');

        if (! $orderId || ! in_array($state, ['NEW', 'PENDING'], true)) {
            return $orderData;
        }

        try {
            $response = $refurbed->acceptOrder($orderId);
            Log::info('Refurbed: order accepted', ['order_id' => $orderId]);

            $updated = $response['order'] ?? $response;

            if (is_array($updated) && ! empty($updated)) {
                $merged = array_merge($orderData, $updated);

                return $this->adaptOrderPayload($merged);
            }

            $orderData['state'] = 'ACCEPTED';
        } catch (\Throwable $e) {
            Log::warning('Refurbed: unable to accept order', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }

        return $orderData;
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

        if (! isset($orderData['currency'])) {
            $orderData['currency'] = $orderData['currency_code'] ?? $orderData['settlement_currency_code'] ?? null;
        }

        if (! isset($orderData['total_amount'])) {
            $orderData['total_amount'] = $orderData['total_paid']
                ?? $orderData['total_charged']
                ?? $orderData['settlement_total_paid']
                ?? null;
        }

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

    private function resolveRefurbedOrderId($orderRecord): ?string
    {
        $preferred = $orderRecord->reference_id ?? null;

        if (! empty($preferred)) {
            return (string) $preferred;
        }

        $fallback = $orderRecord->reference ?? null;

        if ($fallback && $fallback !== Order_model::REFURBED_STOCK_SYNCED_REFERENCE) {
            return (string) $fallback;
        }

        return null;
    }
}
