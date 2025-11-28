<?php

namespace App\Services;

use App\Http\Controllers\RefurbedAPIController;
use App\Models\Order_model;
use Illuminate\Support\Collection;
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
        $orderId = trim($orderId);
        if ($orderId === '') {
            throw new RuntimeException('Refurbed order ID is required.');
        }

        $requestedLineIds = collect($options['order_item_ids'] ?? [])
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        $force = (bool) ($options['force'] ?? false);

        $items = $this->loadOrderItemsForShipment($orderId, $requestedLineIds);

        if ($requestedLineIds->isNotEmpty()) {
            $items = $items->filter(fn ($item) => $requestedLineIds->contains((string) $item['id']))->values();
        }

        $missingIds = $requestedLineIds->isEmpty()
            ? collect()
            : $requestedLineIds->diff($items->pluck('id')->map(fn ($id) => (string) $id));

        if ($force) {
            $eligibleItems = $items;
            $skippedDueToState = collect();
        } else {
            [$eligibleItems, $skippedDueToState] = $this->partitionItemsByState($items, ['ACCEPTED']);
        }

        $skipped = $skippedDueToState->merge($missingIds)->unique()->values();

        if ($eligibleItems->isEmpty()) {
            return [
                'status' => 'noop',
                'message' => 'No eligible order lines found to update.',
                'order_id' => $orderId,
                'updated' => 0,
                'skipped' => $skipped->all(),
                'result' => ['batches' => [], 'total' => 0],
                'request_payload' => null,
                'raw_response' => null,
            ];
        }

        $localOrder = $this->findLocalOrder($orderId);

        $trackingNumber = $options['tracking_number'] ?? $localOrder?->tracking_number;
        $carrier = $options['carrier'] ?? null;

        $updates = $this->buildStateUpdates(
            $eligibleItems,
            'SHIPPED',
            array_filter([
                'parcel_tracking_number' => $trackingNumber,
                'parcel_tracking_carrier' => $carrier,
            ], fn ($value) => $value !== null && $value !== '')
        );

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
            'request_payload' => $updates,
            'raw_response' => $result,
        ];
    }

    /**
     * Mirrors the RefurbedSyncOrders acceptance workflow for ACCEPTED -> SHIPPED transitions.
     */
    private function loadOrderItemsForShipment(string $orderId, Collection $requestedLineIds): Collection
    {
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

        return collect($itemsResponse['order_items'] ?? [])
            ->filter(fn ($item) => ! empty($item['id']))
            ->values();
    }

    private function partitionItemsByState(Collection $items, array $allowedStates): array
    {
        $allowedStates = array_map('strtoupper', $allowedStates);
        $eligible = collect();
        $skipped = collect();

        foreach ($items as $item) {
            $state = strtoupper($item['state'] ?? '');
            if (in_array($state, $allowedStates, true)) {
                $eligible->push($item);
            } else {
                $skipped->push((string) ($item['id'] ?? ''));
            }
        }

        return [$eligible->filter(fn ($item) => ! empty($item['id']))->values(), $skipped->filter()->values()];
    }

    private function buildStateUpdates(Collection $items, string $targetState, array $extraAttributes = []): array
    {
        return $items->map(function ($item) use ($targetState, $extraAttributes) {
            $payload = array_merge([
                'id' => $item['id'],
                'state' => $targetState,
            ], $extraAttributes);

            return array_filter($payload, fn ($value) => $value !== null && $value !== '');
        })->values()->all();
    }

    protected function findLocalOrder(string $orderId): ?Order_model
    {
        if ($orderId === '') {
            return null;
        }

        $query = Order_model::query()->where('reference_id', $orderId);

        if (ctype_digit($orderId)) {
            $query->orWhere('id', (int) $orderId);
        }

        return $query->first();
    }
}
