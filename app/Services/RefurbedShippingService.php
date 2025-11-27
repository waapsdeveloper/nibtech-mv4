<?php

namespace App\Services;

use App\Http\Controllers\RefurbedAPIController;
use App\Models\Order_item_model;
use App\Models\Order_model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RefurbedShippingService
{
    private const DEFAULT_CARRIER = 'DHL_EXPRESS';
    /**
     * Create a Refurbed shipping label and optionally mark the order as shipped.
     *
     * @param  array  $options  Supported keys: merchant_address_id, parcel_weight, carrier,
     *                          mark_shipped (bool), processed_by (int|null), skip_if_exists (bool),
     *                          sync_identifiers (bool), identifier_options (array)
     * @return object|string
     */
    public function createLabel(Order_model $order, RefurbedAPIController $refurbedApi, array $options = [])
    {
        $merchantAddressId = $this->resolveMerchantAddressId($order, $options);
        $parcelWeight = $this->resolveParcelWeight($order, $options);
        $carrier = $this->resolveCarrier($order, $options);
        $markShipped = (bool) ($options['mark_shipped'] ?? false);
        $skipIfExists = (bool) ($options['skip_if_exists'] ?? false);
        $syncIdentifiers = (bool) ($options['sync_identifiers'] ?? false);
        $identifierOptions = $options['identifier_options'] ?? [];

        if ($skipIfExists && $order->label_url && $order->tracking_number) {
            return (object) [
                'tracking_number' => $order->tracking_number,
                'label_url' => $order->label_url,
                'skipped' => true,
            ];
        }

        if ($merchantAddressId == null) {
            return 'Refurbed merchant address ID is required before dispatch.';
        }

        if ($carrier === null || $carrier === '') {
            return 'Refurbed carrier is required before dispatch.';
        }

        $parcelWeight = $parcelWeight !== null ? (float) $parcelWeight : 0.0;
        if ($parcelWeight <= 0) {
            return 'Parcel weight must be greater than zero for Refurbed shipments.';
        }

        try {
            $labelResponse = $refurbedApi->createShippingLabel(
                $order->reference_id,
                $merchantAddressId,
                $parcelWeight,
                $carrier ?: null
            );
        } catch (\Throwable $e) {
            Log::error('Refurbed: Failed to create shipping label', [
                'order_id' => $order->id,
                'order_reference' => $order->reference_id,
                'error' => $e->getMessage(),
            ]);

            return 'Failed to create Refurbed shipping label: ' . $e->getMessage();
        }

        $labelUrl = $this->persistLabel($order, $labelResponse);
        if ($labelUrl) {
            $order->label_url = $labelUrl;
        }

        $trackingNumber = $this->extractTrackingNumber($labelResponse);
        if ($trackingNumber) {
            $order->tracking_number = $trackingNumber;
        }

        if ($markShipped) {
            $order->status = 3;
            if (array_key_exists('processed_by', $options)) {
                $order->processed_by = $options['processed_by'];
            }
            $order->processed_at = now();
        }

        $order->save();

        if ($markShipped) {
            Order_item_model::where('order_id', $order->id)->update(['status' => 3]);
            $this->updateOrderItemsState($order, $refurbedApi, $trackingNumber, $carrier);
        }

        if ($syncIdentifiers) {
            $identifierOptions = array_filter($identifierOptions + [
                'tracking_number' => $order->tracking_number,
                'carrier' => $carrier,
            ]);

            $this->syncOrderItemIdentifiers($order, $refurbedApi, $identifierOptions);
        }

        return (object) array_filter([
            'tracking_number' => $trackingNumber,
            'label_url' => $order->label_url,
            'mark_shipped' => $markShipped,
        ]);
    }

    protected function resolveMerchantAddressId(Order_model $order, array $options): ?string
    {
        $fromOptions = data_get($options, 'merchant_address_id') ?? data_get($options, 'shipping_id');
        if (! empty($fromOptions)) {
            return trim($fromOptions);
        }

        $order->loadMissing('marketplace');
        $fromMarketplace = data_get($order->marketplace, 'shipping_id');
        if (! empty($fromMarketplace)) {
            return trim($fromMarketplace);
        }

        return null;
    }

    protected function resolveCarrier(Order_model $order, array $options): ?string
    {
        $fromOptions = data_get($options, 'carrier');
        if (! empty($fromOptions)) {
            return $this->normalizeCarrier($fromOptions);
        }

        $order->loadMissing('marketplace');
        $fromMarketplace = data_get($order->marketplace, 'default_shipping_carrier');

        if (! empty($fromMarketplace)) {
            return $this->normalizeCarrier($fromMarketplace);
        }

        return self::DEFAULT_CARRIER;
    }

    protected function resolveParcelWeight(Order_model $order, array $options): ?float
    {
        if (array_key_exists('parcel_weight', $options) && $options['parcel_weight'] !== null && $options['parcel_weight'] !== '') {
            return (float) $options['parcel_weight'];
        }

        return $this->extractCategoryWeightFromOrder($order);
    }

    protected function extractCategoryWeightFromOrder(Order_model $order): ?float
    {
        $order->loadMissing('order_items.variation.product.category_id');

        foreach ($order->order_items as $item) {
            $variation = $item->variation;
            $product = $variation ? $variation->product : null;
            $category = $product ? $product->category_id : null;
            $weight = $this->extractWeightFromCategory($category);
            if ($weight !== null) {
                return $weight;
            }
        }

        return null;
    }

    protected function normalizeCarrier(?string $carrier): ?string
    {
        if ($carrier === null) {
            return null;
        }

        $normalized = strtoupper(str_replace(' ', '_', trim($carrier)));

        if ($normalized === '' || $normalized === 'N/A') {
            return null;
        }

        if ($normalized === 'DHL-EXPRESS') {
            $normalized = 'DHL_EXPRESS';
        }

        return $normalized;
    }

    protected function extractWeightFromCategory($category): ?float
    {
        if (! $category) {
            return null;
        }

        $fields = [
            'default_shipping_weight',
            'default_weight',
            'shipping_weight',
            'weight',
        ];

        foreach ($fields as $field) {
            $value = data_get($category, $field);
            if ($value !== null && $value !== '' && is_numeric($value)) {
                $numericValue = (float) $value;
                if ($numericValue > 0) {
                    return $numericValue;
                }
            }
        }

        return null;
    }

    protected function persistLabel(Order_model $order, array $labelResponse): ?string
    {
        $downloadUrl = data_get($labelResponse, 'label.download_url')
            ?? data_get($labelResponse, 'download_url')
            ?? data_get($labelResponse, 'shipping_label.download_url')
            ?? data_get($labelResponse, 'labels.0.download_url');

        if ($downloadUrl) {
            return $downloadUrl;
        }

        $rawContent = data_get($labelResponse, 'label.content')
            ?? data_get($labelResponse, 'label_pdf')
            ?? data_get($labelResponse, 'shipping_label.content')
            ?? data_get($labelResponse, 'labels.0.content')
            ?? data_get($labelResponse, 'content');

        if ($rawContent === null) {
            return null;
        }

        $binary = base64_decode($rawContent, true);
        if ($binary === false) {
            $binary = $rawContent;
        }

        $fileName = 'refurbed-labels/' . $order->reference_id . '-' . now()->timestamp . '.pdf';

        try {
            Storage::disk('public')->put($fileName, $binary);
            return Storage::url($fileName);
        } catch (\Throwable $e) {
            Log::warning('Refurbed: Unable to persist label file', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function extractTrackingNumber(array $labelResponse): ?string
    {
        return data_get($labelResponse, 'label.tracking_number')
            ?? data_get($labelResponse, 'tracking_number')
            ?? data_get($labelResponse, 'shipping_label.tracking_number')
            ?? data_get($labelResponse, 'labels.0.tracking_number');
    }

    protected function updateOrderItemsState(Order_model $order, RefurbedAPIController $refurbedApi, ?string $trackingNumber, ?string $carrier): void
    {
        $order->loadMissing('order_items');

        $updates = [];
        foreach ($order->order_items as $item) {
            if ($item->reference_id == null) {
                continue;
            }

            $entry = [
                'id' => $item->reference_id,
                'state' => 'SHIPPED',
            ];

            if ($trackingNumber) {
                $entry['parcel_tracking_number'] = $trackingNumber;
            }

            if ($carrier) {
                $entry['carrier'] = $carrier;
            }

            $updates[] = $entry;
        }

        if ($updates === []) {
            return;
        }

        try {
            $refurbedApi->batchUpdateOrderItemsState($updates);
        } catch (\Throwable $e) {
            Log::error('Refurbed: Failed to update order item states during label creation', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function syncOrderItemIdentifiers(Order_model $order, RefurbedAPIController $refurbedApi, array $options = []): void
    {
        $order->loadMissing('order_items.stock');

        $trackingNumber = $options['tracking_number'] ?? null;
        $carrier = $options['carrier'] ?? null;

        $updates = [];

        foreach ($order->order_items as $item) {
            if (! $item->reference_id) {
                continue;
            }

            $stock = $item->stock;
            $imei = $stock->imei ?? null;
            $serialNumber = $stock->serial_number ?? null;

            $payload = ['id' => $item->reference_id];

            if ($imei) {
                $payload['imei'] = $imei;
            }

            if ($serialNumber) {
                $payload['serial_number'] = $serialNumber;
            }

            if ($trackingNumber) {
                $payload['parcel_tracking_number'] = $trackingNumber;
            }

            if ($carrier) {
                $payload['parcel_tracking_carrier'] = $carrier;
            }

            if (count($payload) === 1) {
                continue;
            }

            $updates[] = $payload;
        }

        if ($updates === []) {
            return;
        }

        try {
            $refurbedApi->batchUpdateOrderItems($updates);
        } catch (\Throwable $e) {
            Log::error('Refurbed: Failed to upload identifiers for order items', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
