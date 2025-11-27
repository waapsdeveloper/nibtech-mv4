<?php

namespace App\Http\Controllers;

use App\Models\Order_model;
use Illuminate\Http\Request;

class RefurbedDispatchCheckController extends Controller
{
    private const REFURBED_MARKETPLACE_ID = 4;

    public function __invoke(Request $request)
    {
        $orderId = trim((string) $request->query('order_id'));
        $imei = trim((string) $request->query('imei')) ?: null;

        if ($orderId === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Query parameter "order_id" is required.',
            ], 422);
        }

        $localOrder = Order_model::with(['order_items.stock'])
            ->where('reference_id', $orderId)
            ->where('marketplace_id', self::REFURBED_MARKETPLACE_ID)
            ->first();

        $localSummary = $this->buildLocalSummary($localOrder, $imei);

        try {
            $refurbedApi = new RefurbedAPIController();
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Unable to initialize Refurbed API client: ' . $e->getMessage(),
                'local' => $localSummary,
            ], 500);
        }

        $remoteSummary = $this->buildRemoteSummary($refurbedApi, $orderId, $imei);

        return response()->json([
            'ok' => true,
            'refurbed_order_id' => $orderId,
            'imei' => $imei,
            'local' => $localSummary,
            'remote' => $remoteSummary,
        ]);
    }

    protected function buildLocalSummary(?Order_model $order, ?string $imei): array
    {
        if (!$order) {
            return ['found' => false];
        }

        $imeiMatches = false;
        $imeiList = [];

        if ($imei) {
            foreach ($order->order_items as $item) {
                $stock = $item->stock;
                if (! $stock) {
                    continue;
                }

                $stockImei = $stock->imei ?? $stock->serial_number;
                if ($stockImei) {
                    $imeiList[] = $stockImei;
                    if ($stockImei === $imei) {
                        $imeiMatches = true;
                    }
                }
            }
        }

        return array_filter([
            'found' => true,
            'order_id' => $order->id,
            'status' => $order->status,
            'tracking_number' => $order->tracking_number,
            'label_url' => $order->label_url,
            'delivery_note_url' => $order->delivery_note_url,
            'processed_at' => optional($order->processed_at)->toDateTimeString(),
            'imei_match' => $imei ? $imeiMatches : null,
            'imei_list' => $imeiList ?: null,
        ], fn ($value) => $value !== null && $value !== []);
    }

    protected function buildRemoteSummary(RefurbedAPIController $api, string $orderId, ?string $imei): array
    {
        $summary = [];

        try {
            $order = $api->getOrder($orderId);
            $summary['order'] = array_filter([
                'state' => data_get($order, 'state'),
                'tracking_number' => data_get($order, 'tracking_number'),
                'carrier' => data_get($order, 'carrier'),
                'updated_at' => data_get($order, 'updated_at') ?? data_get($order, 'modified_at'),
            ]);
        } catch (\Throwable $e) {
            $summary['order_error'] = $e->getMessage();
        }

        try {
            $labels = $api->listShippingLabels($orderId);
            $labelEntry = data_get($labels, 'shipping_labels.0')
                ?? data_get($labels, 'labels.0')
                ?? data_get($labels, 'label');

            if ($labelEntry) {
                $summary['label'] = array_filter([
                    'tracking_number' => data_get($labelEntry, 'tracking_number'),
                    'carrier' => data_get($labelEntry, 'carrier'),
                    'download_url' => data_get($labelEntry, 'download_url'),
                    'created_at' => data_get($labelEntry, 'created_at'),
                ]);
            }
        } catch (\Throwable $e) {
            $summary['label_error'] = $e->getMessage();
        }

        try {
            $items = $api->listOrderItems($orderId);
            $orderItems = data_get($items, 'order_items', []);
            $summary['items'] = collect($orderItems)->map(function ($item) {
                return array_filter([
                    'id' => data_get($item, 'id'),
                    'state' => data_get($item, 'state'),
                    'imei' => data_get($item, 'imei'),
                    'serial_number' => data_get($item, 'serial_number'),
                    'parcel_tracking_number' => data_get($item, 'parcel_tracking_number'),
                    'parcel_tracking_carrier' => data_get($item, 'parcel_tracking_carrier'),
                ]);
            })->all();

            if ($imei) {
                $summary['imei_match'] = collect($orderItems)->contains(function ($item) use ($imei) {
                    return data_get($item, 'imei') === $imei || data_get($item, 'serial_number') === $imei;
                });
            }
        } catch (\Throwable $e) {
            $summary['items_error'] = $e->getMessage();
        }

        return $summary;
    }
}
