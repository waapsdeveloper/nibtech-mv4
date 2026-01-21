<?php

namespace App\Services\Repair;

use App\Models\RepairPart;
use App\Models\RepairPartUsage;
use Illuminate\Support\Facades\DB;

class RepairPartService
{
    public function consumePart(int $partId, int $qty, array $attributes = []): RepairPartUsage
    {
        return DB::transaction(function () use ($partId, $qty, $attributes) {
            $part = RepairPart::lockForUpdate()->findOrFail($partId);

            if ($qty < 1) {
                $qty = 1;
            }

            $part->on_hand = max(0, $part->on_hand - $qty);
            $part->save();

            $unitCost = $attributes['unit_cost'] ?? $part->unit_cost;

            $usage = new RepairPartUsage(array_merge($attributes, [
                'repair_part_id' => $part->id,
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'total_cost' => $unitCost * $qty,
            ]));

            $usage->save();

            return $usage;
        });
    }

    public function restockPart(int $partId, int $qty): RepairPart
    {
        return DB::transaction(function () use ($partId, $qty) {
            $part = RepairPart::lockForUpdate()->findOrFail($partId);
            $part->on_hand += max(0, $qty);
            $part->save();

            return $part;
        });
    }
}
