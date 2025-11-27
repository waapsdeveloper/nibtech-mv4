<?php

namespace App\Console\Commands;

use App\Http\Controllers\RefurbedAPIController;
use App\Models\Order_model;
use App\Services\RefurbedShippingService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RefurbedCreateLabels extends Command
{
    protected $signature = 'refurbed:create-labels
        {--order_ids=* : Specific local order IDs to process}
        {--refurbed_order_ids=* : Specific Refurbed order IDs (reference_id) to process}
        {--limit=25 : Maximum unlabeled orders to pick when no IDs are provided}
        {--carrier= : Override carrier slug}
        {--parcel-weight= : Override parcel weight in KG}
        {--merchant-address-id= : Override merchant address ID}
        {--mark-shipped : Mark orders as shipped after label creation}
        {--no-mark-shipped : Do not mark orders as shipped or sync states}
        {--sync-identifiers : Push IMEI/serial identifiers after label creation}
        {--no-skip-existing : Process even if tracking/label already exists}
    ';

    protected $description = 'Create Refurbed shipping labels for a batch of orders (cron-friendly).';

    private const REFURBED_MARKETPLACE_ID = 4;

    public function handle(): int
    {
        $orders = $this->resolveOrders();

        if ($orders->isEmpty()) {
            $this->info('No Refurbed orders matched the selection criteria.');
            return self::SUCCESS;
        }

        try {
            $refurbedApi = new RefurbedAPIController();
        } catch (\Throwable $e) {
            $this->error('Unable to initialize Refurbed API client: ' . $e->getMessage());
            return self::FAILURE;
        }

        /** @var RefurbedShippingService $service */
        $service = app(RefurbedShippingService::class);
        $options = $this->buildOptions();

        $successCount = 0;

        foreach ($orders as $order) {
            $this->line(sprintf('Processing order #%s (ID %d)...', $order->reference_id, $order->id));

            try {
                $result = $service->createLabel($order, $refurbedApi, $options);
            } catch (\Throwable $e) {
                $this->error(sprintf('  ✖ Unexpected error: %s', $e->getMessage()));
                continue;
            }

            if (is_string($result)) {
                $this->warn(sprintf('  ⚠ %s', $result));
                continue;
            }

            $order->refresh();

            $rawResponse = $result->response ?? null;

            if (empty($order->label_url)) {
                $this->warn('  ⚠ Refurbed API responded but no label URL was returned. Order left in queue.');
                $this->dumpRefurbedResponse($rawResponse);
                continue;
            }

            $successCount++;

            $this->info(sprintf('  ✓ Tracking: %s | Label: %s', $order->tracking_number ?? '-', $order->label_url ?? '-'));
        }

        $this->info(sprintf('Finished. %d/%d orders received labels.', $successCount, $orders->count()));

        return self::SUCCESS;
    }

    protected function resolveOrders(): Collection
    {
        $orderIds = array_filter((array) $this->option('order_ids'), fn ($value) => $value !== null && $value !== '');
        $refurbedIds = array_filter((array) $this->option('refurbed_order_ids'), fn ($value) => $value !== null && $value !== '');

        $query = Order_model::query()->where('marketplace_id', self::REFURBED_MARKETPLACE_ID);

        if ($orderIds !== []) {
            $query->whereIn('id', $orderIds);
        }

        if ($refurbedIds !== []) {
            $query->whereIn('reference_id', $refurbedIds);
        }

        if ($orderIds === [] && $refurbedIds === []) {
            $query->whereNull('label_url');
        }

        $limit = (int) $this->option('limit') ?: 25;

        return $query->orderBy('reference_id')->limit($limit)->get();
    }

    protected function buildOptions(): array
    {
        $markShipped = true;

        if ($this->option('no-mark-shipped')) {
            $markShipped = false;
        } elseif ($this->option('mark-shipped')) {
            $markShipped = true;
        }

        $options = [
            'mark_shipped' => $markShipped,
            'skip_if_exists' => ! (bool) $this->option('no-skip-existing'),
            'sync_identifiers' => (bool) $this->option('sync-identifiers'),
            'processed_by' => null,
        ];

        $optionalMap = [
            'merchant_address_id' => 'merchant-address-id',
            'carrier' => 'carrier',
            'parcel_weight' => 'parcel-weight',
        ];

        foreach ($optionalMap as $key => $optionName) {
            $value = $this->option($optionName);
            if ($value !== null && $value !== '') {
                $options[$key] = $value;
            }
        }

        return $options;
    }

    protected function dumpRefurbedResponse($response): void
    {
        if ($response === null) {
            return;
        }

        $encoded = json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $encoded = print_r($response, true);
        }

        $this->line('      ↳ Refurbed response payload:');
        foreach (explode("\n", $encoded) as $line) {
            $this->line('        ' . $line);
        }
    }
}
