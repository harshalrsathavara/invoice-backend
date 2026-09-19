<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Invoice;
use App\Services\NumberingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberingTest extends TestCase
{
    use RefreshDatabase;

    private function numbering(): NumberingService
    {
        return app(NumberingService::class);
    }

    public function test_each_document_kind_counts_separately(): void
    {
        $business = Business::factory()->create();

        $this->assertSame(1, $this->numbering()->reserve($business, Invoice::TYPE_BILL)['bill_no']);
        $this->assertSame(2, $this->numbering()->reserve($business, Invoice::TYPE_BILL)['bill_no']);

        // Raising a quotation must not burn a bill number.
        $this->assertSame(1, $this->numbering()->reserve($business, Invoice::TYPE_QUOTATION)['bill_no']);
        $this->assertSame(3, $this->numbering()->reserve($business, Invoice::TYPE_BILL)['bill_no']);
    }

    public function test_plain_numbering_when_nothing_is_configured(): void
    {
        $business = Business::factory()->create();

        $this->assertSame('1', $this->numbering()->reserve($business)['bill_ref']);
    }

    public function test_series_reference_carries_prefix_and_financial_year(): void
    {
        $business = Business::factory()->withSeries('RS')->create();

        $reserved = $this->numbering()->reserve(
            $business, Invoice::TYPE_BILL, CarbonImmutable::parse('2026-09-15')
        );

        $this->assertSame('RS/26-27/001', $reserved['bill_ref']);
    }

    public function test_quotations_and_challans_carry_their_own_marker(): void
    {
        $business = Business::factory()->withSeries('RS')->create();
        $on = CarbonImmutable::parse('2026-09-15');

        $this->assertSame('RS/QT/26-27/001', $this->numbering()->reserve($business, Invoice::TYPE_QUOTATION, $on)['bill_ref']);
        $this->assertSame('RS/DC/26-27/001', $this->numbering()->reserve($business, Invoice::TYPE_CHALLAN, $on)['bill_ref']);
    }

    public function test_financial_year_runs_april_to_march(): void
    {
        $this->assertSame('26-27', Business::financialYearOf(CarbonImmutable::parse('2026-04-01')));
        $this->assertSame('25-26', Business::financialYearOf(CarbonImmutable::parse('2026-03-31')));
        $this->assertSame('26-27', Business::financialYearOf(CarbonImmutable::parse('2027-01-10')));
    }

    public function test_numbering_restarts_on_the_first_bill_of_the_new_year(): void
    {
        $business = Business::factory()->withSeries('RS')->create();

        // Three bills in 25-26.
        foreach (range(1, 3) as $_) {
            $this->numbering()->reserve($business, Invoice::TYPE_BILL, CarbonImmutable::parse('2026-02-10'));
        }
        $this->assertSame(4, $business->fresh()->next_bill_no);

        $first = $this->numbering()->reserve($business->fresh(), Invoice::TYPE_BILL, CarbonImmutable::parse('2026-04-02'));

        $this->assertSame(1, $first['bill_no']);
        $this->assertSame('RS/26-27/001', $first['bill_ref']);
        $this->assertSame('26-27', $business->fresh()->bill_fy);

        // And it only fires once.
        $second = $this->numbering()->reserve($business->fresh(), Invoice::TYPE_BILL, CarbonImmutable::parse('2026-04-03'));
        $this->assertSame(2, $second['bill_no']);
    }

    public function test_reset_is_skipped_when_no_year_has_been_recorded_yet(): void
    {
        // A business that has never issued a bill starts at 1 regardless.
        $business = Business::factory()->withSeries()->create(['next_bill_no' => 7, 'bill_fy' => '']);

        $this->assertSame(7, $this->numbering()->reserve($business, Invoice::TYPE_BILL)['bill_no']);
    }

    public function test_the_same_number_may_exist_in_two_financial_years(): void
    {
        // With the reset on, bill 1 recurs every year — so the stored rows
        // must be able to hold RS/25-26/001 and RS/26-27/001 side by side.
        $business = Business::factory()->withSeries('RS')->create();

        $lastYear = CarbonImmutable::parse('2026-02-10');
        $thisYear = CarbonImmutable::parse('2026-04-02');

        $first = $this->numbering()->reserve($business->fresh(), Invoice::TYPE_BILL, $lastYear);
        Invoice::factory()->for($business)->create([
            'bill_no' => $first['bill_no'], 'bill_ref' => $first['bill_ref'], 'date' => $lastYear,
        ]);

        $second = $this->numbering()->reserve($business->fresh(), Invoice::TYPE_BILL, $thisYear);
        Invoice::factory()->for($business)->create([
            'bill_no' => $second['bill_no'], 'bill_ref' => $second['bill_ref'], 'date' => $thisYear,
        ]);

        $this->assertSame(1, $first['bill_no']);
        $this->assertSame(1, $second['bill_no']);
        $this->assertSame('RS/25-26/001', $first['bill_ref']);
        $this->assertSame('RS/26-27/001', $second['bill_ref']);
        $this->assertSame(2, $business->invoices()->count());
    }

    public function test_numbers_are_never_handed_out_twice(): void
    {
        $business = Business::factory()->create();

        $issued = collect(range(1, 25))
            ->map(fn () => $this->numbering()->reserve($business->fresh())['bill_no']);

        $this->assertSame($issued->unique()->count(), $issued->count());
        $this->assertSame(range(1, 25), $issued->all());
    }
}
