<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

class VariationListResource extends JsonResource
{
    /**
     * Transform the resource into an array for initial list display
     * Only includes minimal data needed for the list view
     */
    public function toArray($request): array
    {
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

