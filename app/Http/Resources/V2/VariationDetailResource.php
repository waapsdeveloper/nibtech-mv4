<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

class VariationDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array for detailed variation view
     * Includes all relationships and calculated data
     */
    public function toArray($request): array
    {
        $availableStocksCount = $this->available_stocks ? $this->available_stocks->count() : 0;
        $pendingOrdersCount = $this->pending_orders ? $this->pending_orders->sum('quantity') : 0;
        $stockDifference = $availableStocksCount - $pendingOrdersCount;

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'reference_id' => $this->reference_id,
            'listed_stock' => $this->listed_stock ?? 0,
            'state' => $this->state,
            'state_label' => $this->getStateLabel(),
            'product' => [
                'id' => $this->product->id ?? null,
                'model' => $this->product->model ?? null,
                'brand' => $this->product->brand ?? null,
                'category' => $this->product->category ?? null,
            ],
            'storage' => [
                'id' => $this->storage_id->id ?? null,
                'name' => $this->storage_id->name ?? null,
            ],
            'color' => [
                'id' => $this->color_id->id ?? null,
                'name' => $this->color_id->name ?? null,
                'code' => $this->color_id->code ?? null,
            ],
            'grade' => [
                'id' => $this->grade_id->id ?? null,
                'name' => $this->grade_id->name ?? null,
            ],
            'stats' => [
                'available_stocks' => $availableStocksCount,
                'pending_orders' => $pendingOrdersCount,
                'stock_difference' => $stockDifference,
                'has_stock_issue' => $stockDifference < 0,
            ],
            'listings' => ListingResource::collection($this->whenLoaded('listings')),
            'listings_count' => $this->whenLoaded('listings', fn() => $this->listings->count()),
        ];
    }

    /**
     * Get state label
     */
    private function getStateLabel(): string
    {
        return match ($this->state) {
            0 => 'Missing price/comment',
            1 => 'Pending validation',
            2 => 'Online',
            3 => 'Offline',
            4 => 'Deactivated',
            default => 'Unknown',
        };
    }
}

