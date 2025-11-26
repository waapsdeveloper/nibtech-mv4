<?php

namespace App\Console\Commands;

use App\Http\Controllers\RefurbedAPIController;
use App\Models\Country_model;
use App\Models\Currency_model;
use App\Models\Order_model;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;

class RefurbedSyncOrders extends Command
{
    private static bool $acceptOrderUnavailable = false;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refurbed:orders
        {--state=* : Filter Refurbed orders by state}
        {--fulfillment=* : Filter Refurbed orders by fulfillment state}
        {--page-size=100 : Page size for each API request (max 200)}
        {--skip-items : Skip fetching order items for each order}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Refurbed orders from the API and persist them locally.';

    public function handle(): int
    {
        $refurbed = new RefurbedAPIController();
        $orderModel = new Order_model();

        $currencyCodes = $this->getCurrencyCodes();
        $countryCodes = $this->getCountryCodes();

        $stateWhitelist = $this->normalizeList($this->option('state'));
        $filter = $this->buildFilter();
        $pageSize = $this->sanitizePageSize((int) $this->option('page-size'));
        $sort = [
            'order_by' => 'CREATED_AT',
            'direction' => 'DESC',
        ];

        try {
            $response = $refurbed->getAllOrders($filter, $sort, $pageSize);
        } catch (\Throwable $e) {
            Log::error('Refurbed: failed to fetch orders', [
                'error' => $e->getMessage(),
                'filter' => $filter,
            ]);

            $this->error('Refurbed order sync failed: ' . $e->getMessage());

            return self::FAILURE;
        }
        $orders = $response['orders'] ?? [];

        if (empty($orders)) {
            $this->info('Refurbed: no orders returned for current filters.');

            return self::SUCCESS;
        }

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($orders as $orderData) {
            $orderData = $this->adaptOrderPayload($orderData);
            $preAcceptanceState = strtoupper($orderData['state'] ?? '');
            $orderData = $this->acceptOrderIfNeeded($refurbed, $orderData);
            $orderState = $preAcceptanceState ?: strtoupper($orderData['state'] ?? '');
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
                try {
                    $itemsResponse = $refurbed->getAllOrderItems($orderId);
                    $orderItems = $itemsResponse['order_items'] ?? null;
                } catch (\Throwable $e) {
                    Log::warning('Refurbed: unable to fetch order items', [
                        'order_id' => $orderId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            try {
                $orderModel->storeRefurbedOrderInDB($orderData, $orderItems, $currencyCodes, $countryCodes);
                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Refurbed: failed to persist order', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info(sprintf('Refurbed orders synced. processed=%d skipped=%d failed=%d', $processed, $skipped, $failed));

        return self::SUCCESS;
    }

    private function buildFilter(): array
    {
        $filter = [];

        $fulfillmentStates = $this->normalizeList($this->option('fulfillment'));
        if (! empty($fulfillmentStates)) {
            $filter['fulfillment_state'] = ['any_of' => $fulfillmentStates];
        }

        return $filter;
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

    private function acceptOrderIfNeeded(RefurbedAPIController $refurbed, array $orderData): array
    {
        $orderId = $orderData['id'] ?? $orderData['order_number'] ?? null;
        $state = strtoupper($orderData['state'] ?? '');

        if (! $orderId || ! in_array($state, ['NEW', 'PENDING'], true) || self::$acceptOrderUnavailable) {
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
        } catch (RequestException $e) {
            $response = $e->response;
            $acceptEndpointMissing = $response
                && $response->status() === 404
                && str_contains($response->body() ?? '', 'AcceptOrder');

            if ($acceptEndpointMissing) {
                self::$acceptOrderUnavailable = true;
                Log::notice('Refurbed: AcceptOrder endpoint unavailable, skipping future acceptance attempts.');
            } else {
                Log::warning('Refurbed: unable to accept order', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Refurbed: unable to accept order', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }

        return $orderData;
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
}
