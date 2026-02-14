<?php

namespace App\Services\Repair;

use App\Models\PartBatch;
use App\Models\RepairPart;
use App\Models\RepairPartUsage;
use Illuminate\Support\Facades\DB;

class RepairPartService
{
    /**
     * Consume parts from inventory for a repair.
     * Uses batch_id if provided; otherwise FIFO (oldest batch with enough stock).
     * Deducts from part_batches.quantity_remaining and repair_parts.on_hand.
     *
     * @param  array  $attributes  Optional: batch_id, unit_cost, process_id, process_stock_id, stock_id, technician_id, notes
     */
    public function consumePart(int $partId, int $qty, array $attributes = []): RepairPartUsage
    {
        return DB::transaction(function () use ($partId, $qty, $attributes) {
            $part = RepairPart::lockForUpdate()->findOrFail($partId);

            if ($qty < 1) {
                $qty = 1;
            }

            $batch = null;
            $batchId = $attributes['batch_id'] ?? null;

            if ($batchId) {
                $batch = PartBatch::where('repair_part_id', $partId)
                    ->where('id', $batchId)
                    ->where('quantity_remaining', '>=', $qty)
                    ->lockForUpdate()
                    ->first();

                if (! $batch) {
                    throw new \InvalidArgumentException(
                        'Selected batch has insufficient quantity or does not exist for this part.'
                    );
                }
            } else {
                // FIFO: oldest batch with enough stock
                $batch = PartBatch::where('repair_part_id', $partId)
                    ->where('quantity_remaining', '>=', $qty)
                    ->orderBy('received_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();
            }

            // When not using a batch, ensure part has enough on_hand
            if (! $batch && ($part->on_hand ?? 0) < $qty) {
                throw new \InvalidArgumentException(
                    'Insufficient part quantity on hand (no batch with enough stock for FIFO).'
                );
            }

            $unitCost = $part->unit_cost;
            if ($batch) {
                $unitCost = $attributes['unit_cost'] ?? $batch->unit_cost;
            } else {
                $unitCost = $attributes['unit_cost'] ?? $part->unit_cost;
            }

            $totalCost = $unitCost * $qty;

            // Deduct from part.on_hand (keep legacy in sync)
            $part->on_hand = max(0, $part->on_hand - $qty);
            $part->save();

            // Deduct from batch if we used one
            if ($batch) {
                $batch->quantity_remaining = max(0, $batch->quantity_remaining - $qty);
                $batch->save();
            }

            $usage = new RepairPartUsage(array_merge($attributes, [
                'repair_part_id' => $part->id,
                'batch_id' => $batch?->id,
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
            ]));
            $usage->save();

            return $usage;
        });
    }

    /**
     * Restock part (legacy: adds to repair_parts.on_hand only).
     * For batch-based receiving use receiveBatch() instead.
     */
    public function restockPart(int $partId, int $qty): RepairPart
    {
        return DB::transaction(function () use ($partId, $qty) {
            $part = RepairPart::lockForUpdate()->findOrFail($partId);
            $part->on_hand += max(0, $qty);
            $part->save();

            return $part;
        });
    }

    /**
     * Record a new batch (bulk purchase). Creates part_batches row and adds to part.on_hand.
     *
     * @param  array  $attributes  Optional: received_at, purchase_date (Y-m-d or Carbon; default = received_at), supplier, notes
     */
    public function receiveBatch(
        int $partId,
        string $batchNumber,
        int $quantityReceived,
        float $unitCost,
        array $attributes = []
    ): PartBatch {
        return DB::transaction(function () use ($partId, $batchNumber, $quantityReceived, $unitCost, $attributes) {
            $part = RepairPart::lockForUpdate()->findOrFail($partId);

            $totalCost = round($quantityReceived * $unitCost, 2);
            $receivedAt = $attributes['received_at'] ?? now();
            if (is_string($receivedAt)) {
                $receivedAt = \Carbon\Carbon::parse($receivedAt);
            }
            $purchaseDate = $attributes['purchase_date'] ?? $receivedAt;
            if (is_string($purchaseDate)) {
                $purchaseDate = \Carbon\Carbon::parse($purchaseDate);
            }

            $batch = PartBatch::create([
                'repair_part_id' => $partId,
                'batch_number' => $batchNumber,
                'quantity_received' => $quantityReceived,
                'quantity_remaining' => $quantityReceived,
                'quantity_purchased' => $quantityReceived,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'received_at' => $receivedAt,
                'purchase_date' => $purchaseDate,
                'supplier' => $attributes['supplier'] ?? null,
                'notes' => $attributes['notes'] ?? null,
            ]);

            $part->on_hand = ($part->on_hand ?? 0) + $quantityReceived;
            $part->save();

            return $batch;
        });
    }

    /**
     * Get batches for a part that have stock (for dropdowns / selection).
     */
    public function getAvailableBatchesForPart(int $partId)
    {
        return PartBatch::where('repair_part_id', $partId)
            ->inStock()
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();
    }
}
