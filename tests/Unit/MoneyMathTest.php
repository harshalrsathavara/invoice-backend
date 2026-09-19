<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The arithmetic has to agree with the handset to the paisa, because both
 * sides compute it independently and a bill that disagrees with its own PDF is
 * worse than no server at all.
 */
class MoneyMathTest extends TestCase
{
    use RefreshDatabase;

    private function bill(array $lines, array $taxes = [], array $attributes = []): Invoice
    {
        $business = Business::factory()->create();
        $invoice = Invoice::factory()->for($business)->create($attributes);

        foreach ($lines as $i => [$qty, $rate]) {
            $invoice->lines()->create([
                'particulars' => 'Work', 'quantity' => $qty, 'rate' => $rate, 'position' => $i,
            ]);
        }
        foreach ($taxes as [$label, $percent]) {
            $invoice->taxes()->create(['label' => $label, 'percent' => $percent]);
        }

        return $invoice->fresh(['lines', 'taxes', 'payments']);
    }

    public function test_subtotal_is_the_sum_of_the_lines(): void
    {
        $bill = $this->bill([[2, 500], [3, 1000]]);

        $this->assertSame(4000.0, $bill->subtotal);
    }

    public function test_percentage_discount_comes_off_before_tax(): void
    {
        $bill = $this->bill(
            [[1, 120000]],
            [['CGST', 9], ['SGST', 9]],
            ['discount_type' => Invoice::DISCOUNT_PERCENT, 'discount_value' => 20],
        );

        $this->assertSame(120000.0, $bill->subtotal);
        $this->assertSame(24000.0, $bill->discount_amount);
        $this->assertSame(96000.0, $bill->taxable_amount);
        // Both halves are charged on the same taxable value.
        $this->assertSame(17280.0, $bill->total_tax);
        $this->assertSame(113280.0, $bill->computed_total);
    }

    public function test_a_discount_larger_than_the_bill_is_clamped(): void
    {
        $bill = $this->bill(
            [[1, 5000]],
            [],
            ['discount_type' => Invoice::DISCOUNT_AMOUNT, 'discount_value' => 9999],
        );

        $this->assertSame(5000.0, $bill->discount_amount);
        $this->assertSame(0.0, $bill->taxable_amount);
        $this->assertSame(0.0, $bill->computed_total);
    }

    public function test_round_off_is_applied_after_tax(): void
    {
        $bill = $this->bill([[1, 1000.40]], [], ['round_off' => -0.40]);

        $this->assertSame(1000.40, $bill->total_before_rounding);
        $this->assertSame(1000.0, $bill->computed_total);
    }

    public function test_rounding_delta_lands_on_a_whole_rupee(): void
    {
        $this->assertSame(-0.40, Invoice::roundingDeltaFor(1000.40));
        $this->assertSame(0.25, Invoice::roundingDeltaFor(999.75));
    }

    public function test_status_follows_the_balance(): void
    {
        $bill = $this->bill([[1, 20000]]);
        $bill->recalculateTotals();
        $this->assertSame('Unpaid', $bill->fresh()->status);

        $bill->payments()->create(['date' => now(), 'amount' => 8000, 'mode' => 'cash']);
        $bill->load('payments')->recalculateTotals();
        $this->assertSame('Partial', $bill->fresh()->status);

        $bill->payments()->create(['date' => now(), 'amount' => 12000, 'mode' => 'upi']);
        $bill->load('payments')->recalculateTotals();
        $this->assertSame('Paid', $bill->fresh()->status);
        $this->assertSame(0.0, $bill->fresh()->balance);
    }

    public function test_a_cancelled_bill_reads_as_cancelled_whatever_is_owed(): void
    {
        $bill = $this->bill([[1, 20000]], [], ['voided_at' => now(), 'void_reason' => 'Duplicate']);
        $bill->recalculateTotals();

        $this->assertSame('Cancelled', $bill->fresh()->status);
    }

    public function test_paid_total_is_recomputed_from_the_payment_rows(): void
    {
        $bill = $this->bill([[1, 10000]]);
        $bill->payments()->create(['date' => now(), 'amount' => 3000, 'mode' => 'cash']);
        $bill->payments()->create(['date' => now(), 'amount' => 2000, 'mode' => 'cheque']);

        $bill->load('payments')->recalculateTotals();

        $this->assertSame(5000.0, (float) $bill->fresh()->paid_amount);
        $this->assertSame(5000.0, $bill->fresh()->balance);
    }
}
