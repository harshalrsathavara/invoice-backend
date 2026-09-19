<?php

namespace App\Http\Resources;

use App\Models\InvoiceTax;
use App\Services\Money;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'business_uuid' => $this->business->uuid,
            'doc_type' => $this->doc_type,
            'bill_no' => $this->bill_no,
            'bill_ref' => $this->bill_ref,
            'display_no' => $this->display_no,
            'customer_name' => $this->customer_name,
            'customer_uuid' => $this->customer_uuid,
            'date' => $this->date?->toDateString(),
            'notes' => $this->notes,
            'photo_path' => $this->photo_path,
            'converted_from_uuid' => $this->converted_from_uuid,

            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'uuid' => $line->uuid,
                'particulars' => $line->particulars,
                'quantity' => (float) $line->quantity,
                'rate' => (float) $line->rate,
                'amount' => $line->amount,
                'position' => $line->position,
            ])),

            'taxes' => $this->whenLoaded('taxes', fn () => $this->taxes->map(fn ($tax) => [
                'uuid' => $tax->uuid,
                'label' => $tax->label,
                'percent' => (float) $tax->percent,
                'amount' => $this->taxAmountFor($tax),
            ])),

            'payments' => PaymentResource::collection($this->whenLoaded('payments')),

            'totals' => $this->when($this->relationLoaded('lines'), fn () => [
                'subtotal' => $this->subtotal,
                'discount_type' => $this->discount_type,
                'discount_value' => (float) $this->discount_value,
                'discount_amount' => $this->discount_amount,
                'taxable_amount' => $this->taxable_amount,
                'total_tax' => $this->total_tax,
                'round_off' => (float) $this->round_off,
                'total' => (float) $this->total,
                'paid_amount' => (float) $this->paid_amount,
                'balance' => $this->balance,
                'formatted_total' => Money::rupees((float) $this->total),
            ]),

            'amount_in_words' => $this->amount_in_words,
            'status' => $this->status,
            'is_voided' => $this->is_voided,
            'voided_at' => $this->voided_at?->toIso8601String(),
            'void_reason' => $this->void_reason,

            // A warning, never a rejection: the person raising the bill decides
            // what belongs on it.
            'tax_warning' => $this->when(
                $this->relationLoaded('taxes') && InvoiceTax::hasMismatchedSplit($this->taxes),
                'CGST and SGST are set to different rates.'
            ),

            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
