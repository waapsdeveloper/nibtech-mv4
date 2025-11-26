<?php

namespace App\Console\Commands;

use App\Http\Controllers\RefurbedAPIController;
use App\Models\Country_model;
use App\Models\Currency_model;
use App\Models\Order_model;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefurbedSyncOrders extends Command
{
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

        foreach ($orders as $orderData) {
            $orderId = $orderData['id'] ?? $orderData['order_number'] ?? null;

            if (! $orderId) {
                $skipped++;
                Log::warning('Refurbed: order payload missing identifier', ['payload' => $orderData]);
                continue;
            }

            $orderItems = null;

            if (! $this->option('skip-items')) {
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
                Log::error('Refurbed: failed to persist order', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info(sprintf('Refurbed orders synced. processed=%d skipped=%d', $processed, $skipped));

        return self::SUCCESS;
    }

    private function buildFilter(): array
    {
        $filter = [];

        $states = $this->normalizeList($this->option('state'));
        if (! empty($states)) {
            $filter['state'] = ['any_of' => $states];
        }

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
}
