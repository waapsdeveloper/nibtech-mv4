<?php

namespace App\Http\Controllers;

use App\Models\Order_model;
use App\Services\RefurbedShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RefurbedBulkLabelController extends Controller
{
    private const REFURBED_MARKETPLACE_ID = 4;

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_ids' => ['nullable', 'array'],
            'order_ids.*' => ['integer'],
            'refurbed_order_ids' => ['nullable', 'array'],
            'refurbed_order_ids.*' => ['string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'skip_if_exists' => ['sometimes', 'boolean'],
            'mark_shipped' => ['sometimes', 'boolean'],
            'sync_identifiers' => ['sometimes', 'boolean'],
            'carrier' => ['nullable', 'string'],
            'parcel_weight' => ['nullable', 'numeric'],
            'merchant_address_id' => ['nullable', 'string'],
        ]);

        $orders = $this->resolveOrders($validated);

        if ($orders->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'No Refurbed orders matched the provided criteria.',
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

        $options = $this->buildOptions($validated, $request->user()?->id);

        /** @var RefurbedShippingService $service */
        $service = app(RefurbedShippingService::class);

        $results = [];

        foreach ($orders as $order) {
            try {
                $result = $service->createLabel($order, $refurbedApi, $options);
            } catch (\Throwable $e) {
                $results[] = [
                    'order_id' => $order->id,
                    'reference_id' => $order->reference_id,
                    'success' => false,
                    'message' => 'Unexpected error: ' . $e->getMessage(),
                ];
                continue;
            }

            if (is_string($result)) {
                $results[] = [
                    'order_id' => $order->id,
                    'reference_id' => $order->reference_id,
                    'success' => false,
                    'message' => $result,
                ];
                continue;
            }

            $order->refresh();

            $results[] = [
                'order_id' => $order->id,
                'reference_id' => $order->reference_id,
                'success' => true,
                'tracking_number' => $order->tracking_number,
                'label_url' => $order->label_url,
                'result' => $result,
            ];
        }

        return response()->json([
            'ok' => true,
            'processed' => count($results),
            'results' => $results,
        ]);
    }

    protected function resolveOrders(array $validated): Collection
    {
        $query = Order_model::query()->where('marketplace_id', self::REFURBED_MARKETPLACE_ID);

        if (! empty($validated['order_ids'])) {
            $query->whereIn('id', $validated['order_ids']);
        }

        if (! empty($validated['refurbed_order_ids'])) {
            $query->whereIn('reference_id', $validated['refurbed_order_ids']);
        }

        if (empty($validated['order_ids']) && empty($validated['refurbed_order_ids'])) {
            $query->whereNull('label_url');
        }

        $limit = $validated['limit'] ?? 25;

        return $query->orderBy('reference_id')->limit($limit)->get();
    }

    protected function buildOptions(array $validated, ?int $processedBy): array
    {
        $options = [
            'mark_shipped' => array_key_exists('mark_shipped', $validated)
                ? (bool) $validated['mark_shipped']
                : false,
            'skip_if_exists' => (bool) ($validated['skip_if_exists'] ?? true),
            'sync_identifiers' => array_key_exists('sync_identifiers', $validated)
                ? (bool) $validated['sync_identifiers']
                : false,
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
