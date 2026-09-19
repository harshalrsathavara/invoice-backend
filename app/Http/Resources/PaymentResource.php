<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'date' => $this->date?->toDateString(),
            'amount' => (float) $this->amount,
            'mode' => $this->mode,
            'note' => $this->note,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
