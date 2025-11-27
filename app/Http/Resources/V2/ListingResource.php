<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

class ListingResource extends JsonResource
{
    /**
     * Transform the resource into an array for listing display
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'country' => $this->country,
            'country_data' => [
                'id' => $this->country_id->id ?? null,
                'code' => $this->country_id->code ?? null,
                'title' => $this->country_id->title ?? null,
                'market_url' => $this->country_id->market_url ?? null,
                'market_code' => $this->country_id->market_code ?? null,
            ],
            'marketplace_id' => $this->marketplace_id,
            'marketplace' => [
                'id' => $this->marketplace->id ?? null,
                'name' => $this->marketplace->name ?? null,
            ],
            'currency' => [
                'id' => $this->currency->id ?? null,
                'code' => $this->currency->code ?? null,
                'sign' => $this->currency->sign ?? null,
            ],
            'min_price' => $this->min_price,
            'price' => $this->price,
            'min_price_limit' => $this->min_price_limit,
            'price_limit' => $this->price_limit,
            'buybox' => $this->buybox,
            'buybox_price' => $this->buybox_price,
            'target_price' => $this->target_price,
            'target_percentage' => $this->target_percentage,
            'handler_status' => $this->handler_status,
            'handler_status_label' => $this->getHandlerStatusLabel(),
            'reference_uuid' => $this->reference_uuid,
            'reference_uuid_2' => $this->reference_uuid_2,
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * Get handler status label
     */
    private function getHandlerStatusLabel(): string
    {
        return match ($this->handler_status) {
            1 => 'Active',
            2 => 'Error',
            3 => 'Inactive',
            default => 'Unknown',
        };
    }
}

