<?php

namespace App\Console\Commands;

use App\Services\RefurbedOrderLineStateService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use RuntimeException;

class RefurbedShipLines extends Command
{
    protected $signature = 'refurbed:ship-lines
        {order_id : Refurbed order reference/ID (as seen on the marketplace)}
        {--order-item-id=* : Optional Refurbed order item IDs to restrict the update}
        {--tracking-number= : Tracking number to send with the shipment}
        {--carrier= : Carrier slug (e.g. DHL_EXPRESS)}
        {--force : Ship provided lines even if they are not ACCEPTED}
    ';

    protected $description = 'Push Refurbed order lines from ACCEPTED to SHIPPED via the marketplace API.';

    public function __construct(protected RefurbedOrderLineStateService $orderLineService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $orderId = (string) $this->argument('order_id');

        $options = [
            'order_item_ids' => array_values(array_filter((array) $this->option('order-item-id'), fn ($value) => $value !== null && $value !== '')),
            'tracking_number' => $this->option('tracking-number') ?: null,
            'carrier' => $this->option('carrier') ?: null,
            'force' => (bool) $this->option('force'),
        ];

        $this->line(sprintf('Refurbed order: %s', $orderId));
        $this->line('Options: ' . json_encode(Arr::only($options, ['tracking_number', 'carrier', 'force'])));

        try {
            $result = $this->orderLineService->shipOrderLines($orderId, $options);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($result['status'] === 'noop') {
            $this->warn($result['message']);
            if (! empty($result['skipped'])) {
                $this->warn('Skipped IDs: ' . implode(', ', $result['skipped']));
            }

            return self::SUCCESS;
        }

        $this->info(sprintf('Updated %d order line(s).', $result['updated']));

        if (! empty($result['skipped'])) {
            $this->warn('Skipped IDs: ' . implode(', ', $result['skipped']));
        }

        $summary = $result['result'] ?? [];
        if (($summary['total'] ?? 0) > 0) {
            $this->line(sprintf('API batches sent: %d | Items acknowledged: %d', count($summary['batches'] ?? []), $summary['total']));
        }

        $requestPayload = $result['request_payload'] ?? null;
        if ($requestPayload !== null) {
            $this->line('Refurbed request payload:');
            $this->line(json_encode($requestPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $rawResponse = $result['raw_response'] ?? null;
        if ($rawResponse !== null) {
            $this->line('Refurbed response:');
            $this->line(json_encode($rawResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }
}
