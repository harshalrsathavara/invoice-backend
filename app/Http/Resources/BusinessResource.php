<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BusinessResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'address' => $this->address,
            'mobile' => $this->mobile,
            'jurisdiction_text' => $this->jurisdiction_text,
            'gst_number' => $this->gst_number,
            'email' => $this->email,
            'bank_details' => $this->bank_details,
            'logo_path' => $this->logo_path,
            'signature_path' => $this->signature_path,
            // Where to fetch the bytes. The path alone is a server-side
            // location the handset has no way to resolve.
            'logo_url' => $this->logo_path
                ? url("/api/v1/businesses/{$this->uuid}/image/logo")
                : null,
            'signature_url' => $this->signature_path
                ? url("/api/v1/businesses/{$this->uuid}/image/signature")
                : null,
            'upi_id' => $this->upi_id,
            'numbering' => [
                'next_bill_no' => $this->next_bill_no,
                'next_quote_no' => $this->next_quote_no,
                'next_challan_no' => $this->next_challan_no,
                'bill_prefix' => $this->bill_prefix,
                'fy_reset' => $this->fy_reset,
                'bill_fy' => $this->bill_fy,
            ],
            'terms_text' => $this->terms_text,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
