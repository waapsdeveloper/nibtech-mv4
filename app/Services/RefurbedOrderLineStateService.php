<?php

namespace App\Services;

use App\Http\Controllers\RefurbedAPIController;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RefurbedOrderLineStateService
{
    protected RefurbedAPIController $refurbed;

    public function __construct(RefurbedAPIController $refurbed)
    {
        $this->refurbed = $refurbed;
    }

    /**
     * Push Refurbed order items from ACCEPTED to SHIPPED state.
     */
    public function shipOrderLines(string $orderId, array $options = []): array
    {
        $requestedLineIds = collect($options['order_item_ids'] ?? [])
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        $filter = [];
        if ($requestedLineIds->isNotEmpty()) {
            $filter['id'] = ['any_of' => $requestedLineIds->all()];
        }

        try {
            $itemsResponse = $this->refurbed->getAllOrderItems($orderId, $filter);
        } catch (\Throwable $e) {
            Log::error('Refurbed: Unable to fetch order items for manual shipment', [
                'order_id' => $orderId,
                'filter' => $filter,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Unable to fetch order items from Refurbed.', 0, $e);
        }

        $items = collect($itemsResponse['order_items'] ?? [])
            ->filter(fn ($item) => ! empty($item['id']))
            ->values();

        if ($requestedLineIds->isNotEmpty()) {
            $items = $items->filter(fn ($item) => $requestedLineIds->contains((string) $item['id']))->values();
        }

        $missingIds = $requestedLineIds->isEmpty()
            ? collect()
            : $requestedLineIds->diff($items->pluck('id')->map(fn ($id) => (string) $id));

        $force = (bool) ($options['force'] ?? false);

        $eligibleItems = $force
            ? $items
            : $items->filter(fn ($item) => strtoupper($item['state'] ?? '') === 'ACCEPTED')->values();

        $skippedDueToState = $force
            ? collect()
            : $items->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->diff($eligibleItems->pluck('id')->map(fn ($id) => (string) $id));

        $skipped = $skippedDueToState->merge($missingIds)->unique()->values();

        if ($eligibleItems->isEmpty()) {
            return [
                'status' => 'noop',
                'message' => 'No eligible order lines found to update.',
                'order_id' => $orderId,
                'updated' => 0,
                'skipped' => $skipped->all(),
                'result' => ['batches' => [], 'total' => 0],
            ];
        }

        $trackingNumber = $options['tracking_number'] ?? null;
        $carrier = $options['carrier'] ?? null;

        $updates = $eligibleItems->map(function ($item) use ($trackingNumber, $carrier) {
            $payload = [
                'id' => $item['id'],
                'state' => 'SHIPPED',
            ];

            if ($trackingNumber) {
                $payload['parcel_tracking_number'] = $trackingNumber;
            }

            if ($carrier) {
                $payload['parcel_tracking_carrier'] = $carrier;
            }

            return $payload;
        })->values()->all();

        try {
            $result = $this->refurbed->batchUpdateOrderItemsState($updates);
        } catch (\Throwable $e) {
            Log::error('Refurbed: Failed to update order item states', [
                'order_id' => $orderId,
                'item_ids' => array_column($updates, 'id'),
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Failed to update order item states: ' . $e->getMessage(), 0, $e);
        }

        $summary = [
            'batches' => $result['batches'] ?? [],
            'total' => $result['total'] ?? count($updates),
        ];

        return [
            'status' => 'success',
            'message' => 'Order lines updated.',
            'order_id' => $orderId,
            'updated' => count($updates),
            'skipped' => $skipped->all(),
            'result' => $summary,
        ];
    }
}
