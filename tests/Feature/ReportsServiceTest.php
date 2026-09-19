<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Invoice;
use App\Services\ReportsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsServiceTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private ReportsService $reports;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create();
        $this->reports = app(ReportsService::class);
    }

    private function bills(): \Illuminate\Support\Collection
    {
        return $this->business->invoices()->with(['lines', 'taxes'])->get();
    }

    private function bill(float $amount, array $overrides = [], array $taxes = []): Invoice
    {
        $invoice = Invoice::factory()->for($this->business)->create(array_merge([
            'bill_no' => $this->business->invoices()->count() + 1,
        ], $overrides));

        $invoice->lines()->create(['particulars' => 'Lathe Job Work', 'quantity' => 1, 'rate' => $amount, 'position' => 0]);
        foreach ($taxes as [$label, $percent]) {
            $invoice->taxes()->create(['label' => $label, 'percent' => $percent]);
        }

        $invoice->load(['lines', 'taxes', 'payments']);
        $invoice->recalculateTotals();

        return $invoice->fresh(['lines', 'taxes', 'payments']);
    }

    public function test_quotations_challans_and_cancelled_bills_are_not_income(): void
    {
        $this->bill(10000);
        $this->bill(5000, ['doc_type' => Invoice::TYPE_QUOTATION]);
        $this->bill(3000, ['doc_type' => Invoice::TYPE_CHALLAN]);
        $this->bill(7000, ['voided_at' => now()]);

        $live = $this->reports->liveBills($this->bills());

        $this->assertCount(1, $live);
        $this->assertSame(10000.0, $this->reports->totalBilled($live));
    }

    public function test_gst_summary_repeats_the_taxable_value_per_row(): void
    {
        // One bill of ₹1,00,000 carrying CGST 9% and SGST 9%.
        $this->bill(100000, [], [['CGST', 9], ['SGST', 9]]);

        $gst = $this->reports->gstSummary($this->bills());

        $this->assertCount(2, $gst);
        $this->assertSame('CGST', $gst[0]['label']);
        $this->assertSame(100000.0, $gst[0]['taxable_amount']);
        $this->assertSame(9000.0, $gst[0]['tax_amount']);
        $this->assertSame('SGST', $gst[1]['label']);
        $this->assertSame(9000.0, $gst[1]['tax_amount']);

        // Summing the taxable column would double count, so the honest total
        // counts each taxed bill once.
        $this->assertSame(100000.0, $this->reports->totalTaxableValue($this->bills()));
        $this->assertSame(18000.0, $this->reports->totalTax($this->bills()));
    }

    public function test_the_same_rate_under_two_labels_stays_apart(): void
    {
        $this->bill(100000, [], [['CGST', 9], ['SGST', 9]]);
        $this->bill(50000, [], [['IGST', 18]]);

        $gst = $this->reports->gstSummary($this->bills());

        $this->assertSame(['CGST', 'IGST', 'SGST'], $gst->pluck('label')->all());
    }

    public function test_ageing_splits_what_is_owed_by_how_long(): void
    {
        $asOf = CarbonImmutable::parse('2026-09-15');

        $this->bill(1000, ['date' => $asOf->subDays(10)->toDateString()]);
        $this->bill(2000, ['date' => $asOf->subDays(45)->toDateString()]);
        $this->bill(4000, ['date' => $asOf->subDays(90)->toDateString()]);

        $ageing = $this->reports->ageing($this->bills(), $asOf);

        $this->assertSame(1000.0, $ageing['up_to_30']);
        $this->assertSame(2000.0, $ageing['from_31_to_60']);
        $this->assertSame(4000.0, $ageing['over_60']);
        $this->assertSame(7000.0, $ageing['total']);
    }

    public function test_a_settled_bill_drops_out_of_ageing(): void
    {
        $asOf = CarbonImmutable::parse('2026-09-15');
        $bill = $this->bill(1000, ['date' => $asOf->subDays(10)->toDateString()]);

        $bill->payments()->create(['date' => $asOf, 'amount' => 1000, 'mode' => 'cash']);
        $bill->load('payments')->recalculateTotals();

        $this->assertSame(0.0, $this->reports->ageing($this->bills(), $asOf)['total']);
    }

    public function test_monthly_billing_includes_quiet_months_as_zero(): void
    {
        $end = CarbonImmutable::parse('2026-09-15');
        $this->bill(5000, ['date' => $end->toDateString()]);
        $this->bill(3000, ['date' => $end->subMonths(2)->toDateString()]);

        $monthly = $this->reports->monthlyBilling($this->bills(), 6, $end);

        $this->assertCount(6, $monthly);
        $this->assertSame('2026-09', $monthly->last()['month']);
        $this->assertSame(5000.0, $monthly->last()['billed']);
        // The month between the two bills is present, at zero.
        $this->assertSame(0.0, $monthly[4]['billed']);
        $this->assertSame(3000.0, $monthly[3]['billed']);
    }

    public function test_top_customers_are_ranked_by_value_and_merged_case_insensitively(): void
    {
        $this->bill(5000, ['customer_name' => 'Mahesh Traders']);
        $this->bill(3000, ['customer_name' => 'mahesh traders']);
        $this->bill(6000, ['customer_name' => 'Kiran Engineering']);

        $top = $this->reports->topCustomers($this->bills());

        $this->assertSame('Mahesh Traders', $top[0]['name']);
        $this->assertSame(8000.0, $top[0]['amount']);
        $this->assertSame(2, $top[0]['count']);
        $this->assertSame('Kiran Engineering', $top[1]['name']);
    }

    public function test_top_work_ranks_by_line_description(): void
    {
        $bill = $this->bill(1000);
        $bill->lines()->create(['particulars' => 'Welding', 'quantity' => 2, 'rate' => 2000, 'position' => 1]);
        $bill->load('lines')->recalculateTotals();

        $top = $this->reports->topItems($this->bills());

        $this->assertSame('Welding', $top[0]['name']);
        $this->assertSame(4000.0, $top[0]['amount']);
    }
}
