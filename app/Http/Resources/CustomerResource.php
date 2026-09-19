<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'business_uuid' => $this->business->uuid,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'gst_number' => $this->gst_number,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
