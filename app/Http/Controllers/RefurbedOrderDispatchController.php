<?php

namespace App\Http\Controllers;

use App\Models\Order_model;
use App\Services\RefurbedShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefurbedOrderDispatchController extends Controller
{
    private const REFURBED_MARKETPLACE_ID = 4;

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['nullable', 'integer'],
            'refurbed_order_id' => ['nullable', 'string'],
            'reference_id' => ['nullable', 'string'],
            'carrier' => ['nullable', 'string'],
            'parcel_weight' => ['nullable', 'numeric'],
            'merchant_address_id' => ['nullable', 'string'],
            'skip_if_exists' => ['sometimes', 'boolean'],
            'mark_shipped' => ['sometimes', 'boolean'],
            'sync_identifiers' => ['sometimes', 'boolean'],
        ]);

        $order = $this->findRefurbedOrder($validated);

        if (! $order) {
            return response()->json([
                'ok' => false,
                'message' => 'Refurbed order not found locally. Include order_id or refurbed_order_id.',
            ], 404);
        }

        try {
            $refurbedApi = new RefurbedAPIController();
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Unable to initialize Refurbed API client: ' . $e->getMessage(),
            ], 500);
        }

        $options = $this->buildDispatchOptions($validated, $request->user()?->id);

        /** @var RefurbedShippingService $service */
        $service = app(RefurbedShippingService::class);
        $result = $service->createLabel($order, $refurbedApi, $options);

        if (is_string($result)) {
            return response()->json([
                'ok' => false,
                'message' => $result,
            ], 422);
        }

        $order->refresh();

        return response()->json([
            'ok' => true,
            'message' => 'Refurbed order dispatched successfully.',
            'order' => [
                'id' => $order->id,
                'reference_id' => $order->reference_id,
                'status' => $order->status,
                'tracking_number' => $order->tracking_number,
                'label_url' => $order->label_url,
                'delivery_note_url' => $order->delivery_note_url,
                'processed_at' => optional($order->processed_at)->toDateTimeString(),
            ],
            'result' => $result,
        ]);
    }

    protected function findRefurbedOrder(array $validated): ?Order_model
    {
        $query = Order_model::query()->where('marketplace_id', self::REFURBED_MARKETPLACE_ID);

        if (! empty($validated['order_id'])) {
            return $query->where('id', (int) $validated['order_id'])->first();
        }

        $referenceId = $validated['refurbed_order_id']
            ?? $validated['reference_id']
            ?? null;

        if ($referenceId !== null && $referenceId !== '') {
            return $query->where('reference_id', $referenceId)->first();
        }

        return null;
    }

    protected function buildDispatchOptions(array $validated, ?int $processedBy): array
    {
        $options = [
            'mark_shipped' => array_key_exists('mark_shipped', $validated)
                ? (bool) $validated['mark_shipped']
                : true,
            'skip_if_exists' => (bool) ($validated['skip_if_exists'] ?? false),
            'sync_identifiers' => array_key_exists('sync_identifiers', $validated)
                ? (bool) $validated['sync_identifiers']
                : true,
            'processed_by' => $processedBy,
        ];

        foreach (['merchant_address_id', 'carrier', 'parcel_weight'] as $optionalKey) {
            if (isset($validated[$optionalKey]) && $validated[$optionalKey] !== '') {
                $options[$optionalKey] = $validated[$optionalKey];
            }
        }

        return $options;
    }
}
