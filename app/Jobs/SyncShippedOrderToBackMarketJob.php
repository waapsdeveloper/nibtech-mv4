<?php

namespace App\Jobs;

use App\Http\Controllers\BackMarketAPIController;
use App\Models\Order_model;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncShippedOrderToBackMarketJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $orderId
    ) {}

    public function handle(): void
    {
        $order = Order_model::with(['order_items.variation', 'order_items.stock'])
            ->where('id', $this->orderId)
            ->where('order_type_id', 3)
            ->first();

        if (!$order || !$order->reference_id || !$order->tracking_number) {
            return;
        }

        if (!in_array((int) $order->status, [3, 6], true)) {
            return;
        }

        $bm = new BackMarketAPIController();
        $referenceId = $order->reference_id;
        $trackingNumber = $order->tracking_number;

        foreach ($order->order_items as $item) {
            if (!$item->stock_id || !$item->variation) {
                continue;
            }
            $stock = $item->stock;
            $sku = $item->variation->sku ?? null;
            if (!$sku) {
                continue;
            }
            $imei = $stock && !empty(trim((string) $stock->imei)) ? trim($stock->imei) : false;
            $serial = $stock && !empty(trim((string) $stock->serial_number)) ? trim($stock->serial_number) : null;

            try {
                $bm->shippingOrderlines($referenceId, $sku, $imei, $trackingNumber, $serial);
            } catch (\Throwable $e) {
                Log::warning('SyncShippedOrderToBackMarketJob: failed to post shipped state to Back Market', [
                    'order_id' => $order->id,
                    'reference_id' => $referenceId,
                    'sku' => $sku,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
