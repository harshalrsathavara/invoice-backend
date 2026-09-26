<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GST charged from the items' own rates, on the server.
 *
 * The handset does this arithmetic too, and the two have to agree to the
 * paisa: a bill raised offline and pushed up must not change value on the way.
 * These mirror `test/gst_test.dart` in the app, case for case.
 */
class GstTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private InvoiceWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['is_admin' => true]);
        $this->business = Business::factory()->for($user)->withSeries('RS')->create();
        $this->writer = app(InvoiceWriter::class);
    }

    private function bill(array $lines, array $extra = []): Invoice
    {
        return $this->writer->create($this->business, array_merge([
            'customer_name' => 'Mahesh Traders',
            'date' => now()->toDateString(),
            'lines' => $lines,
        ], $extra));
    }

    public function test_one_slab_inside_the_state_splits_into_cgst_and_sgst(): void
    {
        $invoice = $this->bill([
            ['particulars' => 'Grinding', 'quantity' => 10, 'rate' => 100, 'gst_rate' => 18],
        ]);

        $rows = $invoice->tax_rows;

        $this->assertCount(2, $rows);
        $this->assertSame('CGST', $rows[0]['label']);
        $this->assertEqualsWithDelta(9.0, $rows[0]['percent'], 0.001);
        $this->assertEqualsWithDelta(90.0, $rows[0]['amount'], 0.001);
        $this->assertSame('SGST', $rows[1]['label']);
        $this->assertEqualsWithDelta(90.0, $rows[1]['amount'], 0.001);
        $this->assertEqualsWithDelta(180.0, $invoice->total_tax, 0.001);
        $this->assertEqualsWithDelta(1180.0, $invoice->computed_total, 0.001);
    }

    public function test_outside_the_state_it_is_one_igst_row(): void
    {
        $invoice = $this->bill(
            [['particulars' => 'Grinding', 'quantity' => 10, 'rate' => 100, 'gst_rate' => 18]],
            ['is_inter_state' => true],
        );

        $rows = $invoice->tax_rows;

        $this->assertCount(1, $rows);
        $this->assertSame('IGST', $rows[0]['label']);
        $this->assertEqualsWithDelta(18.0, $rows[0]['percent'], 0.001);
        $this->assertEqualsWithDelta(180.0, $rows[0]['amount'], 0.001);
        // Same money either way — only the heads it is filed under differ.
        $this->assertEqualsWithDelta(1180.0, $invoice->computed_total, 0.001);
    }

    public function test_a_mixed_bill_is_charged_at_each_slab(): void
    {
        $invoice = $this->bill([
            ['particulars' => 'Timber', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 5],
            ['particulars' => 'Fitting', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18],
        ]);

        $slabs = $invoice->gst_slabs;

        $this->assertCount(2, $slabs);
        $this->assertEqualsWithDelta(5.0, $slabs[0]['rate'], 0.001);
        $this->assertEqualsWithDelta(50.0, $slabs[0]['tax'], 0.001);
        $this->assertEqualsWithDelta(18.0, $slabs[1]['rate'], 0.001);
        $this->assertEqualsWithDelta(180.0, $slabs[1]['tax'], 0.001);
        $this->assertEqualsWithDelta(230.0, $invoice->total_tax, 0.001);
        $this->assertEqualsWithDelta(2230.0, $invoice->computed_total, 0.001);
    }

    public function test_the_discount_is_shared_out_before_tax_in_proportion(): void
    {
        $invoice = $this->bill(
            [
                ['particulars' => 'Timber', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 5],
                ['particulars' => 'Fitting', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18],
            ],
            ['discount_type' => Invoice::DISCOUNT_PERCENT, 'discount_value' => 10],
        );

        $slabs = $invoice->gst_slabs;

        $this->assertEqualsWithDelta(200.0, $invoice->discount_amount, 0.001);
        $this->assertEqualsWithDelta(900.0, $slabs[0]['taxable_value'], 0.001);
        $this->assertEqualsWithDelta(45.0, $slabs[0]['tax'], 0.001);
        $this->assertEqualsWithDelta(162.0, $slabs[1]['tax'], 0.001);
        $this->assertEqualsWithDelta(2007.0, $invoice->computed_total, 0.001);
    }

    public function test_a_line_with_no_rate_is_billed_with_no_tax(): void
    {
        $invoice = $this->bill([
            ['particulars' => 'Taxed', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18],
            ['particulars' => 'Exempt', 'quantity' => 1, 'rate' => 500],
        ]);

        $this->assertEqualsWithDelta(1000.0, $invoice->gst_slabs[0]['taxable_value'], 0.001);
        $this->assertEqualsWithDelta(180.0, $invoice->total_tax, 0.001);
        $this->assertEqualsWithDelta(1680.0, $invoice->computed_total, 0.001);
    }

    public function test_the_two_halves_add_back_to_the_slab(): void
    {
        // 18% of 1,234.55 is 222.22, which does not halve cleanly.
        $invoice = $this->bill([
            ['particulars' => 'Odd', 'quantity' => 1, 'rate' => 1234.55, 'gst_rate' => 18],
        ]);

        $slab = $invoice->gst_slabs[0];
        $rows = $invoice->tax_rows;

        $this->assertEqualsWithDelta($slab['tax'], $rows[0]['amount'] + $rows[1]['amount'], 0.001);
    }

    public function test_a_bill_taxed_the_old_way_keeps_its_rows(): void
    {
        $invoice = $this->bill(
            [['particulars' => 'Job work', 'quantity' => 10, 'rate' => 100]],
            ['taxes' => [
                ['label' => 'CGST', 'percent' => 9],
                ['label' => 'SGST', 'percent' => 9],
            ]],
        );

        $this->assertFalse($invoice->uses_line_gst);
        $this->assertSame([], $invoice->gst_slabs);
        $this->assertCount(2, $invoice->tax_rows);
        $this->assertEqualsWithDelta(180.0, $invoice->total_tax, 0.001);
        $this->assertEqualsWithDelta(1180.0, $invoice->computed_total, 0.001);
    }

    public function test_the_cached_total_matches_what_was_computed(): void
    {
        // The dashboard and the ledger read the cached column, so it has to
        // carry the per-slab figure rather than a stale one.
        $invoice = $this->bill([
            ['particulars' => 'Timber', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 5],
            ['particulars' => 'Fitting', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18],
        ]);

        $this->assertEqualsWithDelta(2230.0, (float) $invoice->fresh()->total, 0.001);
    }

    public function test_the_gst_report_sees_a_bill_taxed_per_item(): void
    {
        // This is the one that files the return. While the report read the
        // stored tax rows, a bill taxed from its items — which has none —
        // was simply absent from it, and the figures filed would have been
        // short by however much of the quarter used the new way.
        $this->bill([
            ['particulars' => 'Timber', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 5],
            ['particulars' => 'Fitting', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18],
        ]);

        $reports = app(\App\Services\ReportsService::class);
        $bills = $this->business->invoices()->with(['lines', 'taxes'])->get();
        $gst = $reports->gstSummary($bills);

        // Two slabs, each split in half: CGST 2.5, CGST 9, SGST 2.5, SGST 9.
        $this->assertCount(4, $gst);
        $this->assertEqualsWithDelta(230.0, $gst->sum('tax_amount'), 0.001);

        // The taxable value is per row, counted once per bill across slabs.
        $this->assertEqualsWithDelta(2000.0, $reports->totalTaxableValue($bills), 0.001);
        $this->assertEqualsWithDelta(230.0, $reports->totalTax($bills), 0.001);
    }

    public function test_an_exempt_line_is_not_taxable_value(): void
    {
        $this->bill([
            ['particulars' => 'Taxed', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18],
            ['particulars' => 'Exempt', 'quantity' => 1, 'rate' => 500],
        ]);

        $reports = app(\App\Services\ReportsService::class);
        $bills = $this->business->invoices()->with(['lines', 'taxes'])->get();

        // 1,000, not 1,500: the exempt line is not value the tax was on.
        $this->assertEqualsWithDelta(1000.0, $reports->totalTaxableValue($bills), 0.001);
    }

    public function test_the_rate_is_stored_against_the_line(): void
    {
        // It has to survive the write, not just the sum: the bill is read
        // back from these rows every time it is shown, shared or re-printed.
        $invoice = $this->bill([
            ['particulars' => 'Grinding', 'quantity' => 1, 'rate' => 100, 'gst_rate' => 18],
            ['particulars' => 'Carriage', 'quantity' => 1, 'rate' => 50],
        ]);

        $this->assertDatabaseHas('invoice_lines', [
            'invoice_id' => $invoice->id,
            'particulars' => 'Grinding',
            'gst_rate' => 18.00,
        ]);
        $this->assertDatabaseHas('invoice_lines', [
            'invoice_id' => $invoice->id,
            'particulars' => 'Carriage',
            'gst_rate' => 0.00,
        ]);

        $this->assertEqualsWithDelta(18.0, (float) $invoice->fresh()->lines->first()->gst_rate, 0.001);
    }
}
