<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'business_uuid' => $this->business->uuid,
            'name' => $this->name,
            'default_rate' => (float) $this->default_rate,
            'hsn_code' => $this->hsn_code,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
