<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'doc_type' => Invoice::TYPE_BILL,
            'bill_no' => 1,
            'bill_ref' => '',
            'customer_name' => 'Mahesh Traders',
            'date' => now()->toDateString(),
            'amount_in_words' => '',
            'discount_type' => Invoice::DISCOUNT_NONE,
            'discount_value' => 0,
            'round_off' => 0,
            'notes' => '',
            'total' => 0,
            'paid_amount' => 0,
        ];
    }

    public function quotation(): static
    {
        return $this->state(fn () => ['doc_type' => Invoice::TYPE_QUOTATION]);
    }

    public function challan(): static
    {
        return $this->state(fn () => ['doc_type' => Invoice::TYPE_CHALLAN]);
    }

    public function voided(string $reason = 'Duplicate'): static
    {
        return $this->state(fn () => ['voided_at' => now(), 'void_reason' => $reason]);
    }

    /**
     * Gives the bill a single line worth the amount asked for, so a test can
     * say "a bill for ₹20,000" without spelling out quantity and rate.
     */
    public function worth(float $amount): static
    {
        return $this->afterCreating(function (Invoice $invoice) use ($amount) {
            $invoice->lines()->create([
                'particulars' => 'Lathe Job Work',
                'quantity' => 1,
                'rate' => $amount,
                'position' => 0,
            ]);
            $invoice->load(['lines', 'taxes', 'payments']);
            $invoice->recalculateTotals();
        });
    }
}
