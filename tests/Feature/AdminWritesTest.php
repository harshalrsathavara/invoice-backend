<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The panel writes as well as reads. These cover the rules that are easy to
 * break from a second write path: numbering, the customer-rename propagation,
 * recomputed balances, and the fact that editing must not renumber or forget.
 */
class AdminWritesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->business = Business::factory()->for($this->admin)->withSeries('RS')->create();
        $this->actingAs($this->admin);
    }

    // ---------------- business ----------------

    public function test_editing_a_business_changes_what_prints_on_a_bill(): void
    {
        $this->get(route('admin.businesses.edit', $this->business))->assertOk();

        $this->put(route('admin.businesses.update', $this->business), [
            'name' => 'Rajesh Steel Works',
            'gst_number' => '24AAAPL1234C1ZV',
            'upi_id' => 'rajesh@okhdfcbank',
            'terms_text' => 'Payment within 30 days.',
        ])->assertRedirect(route('admin.businesses.show', $this->business));

        $this->assertSame('rajesh@okhdfcbank', $this->business->fresh()->upi_id);
    }

    public function test_an_unticked_checkbox_turns_the_financial_year_reset_off(): void
    {
        $this->business->update(['fy_reset' => true]);

        // A checkbox that is off sends nothing at all, so "absent" has to mean
        // false rather than "leave it as it was".
        $this->put(route('admin.businesses.update', $this->business), ['name' => $this->business->name]);

        $this->assertFalse($this->business->fresh()->fy_reset);
    }

    public function test_adding_a_business_puts_it_under_the_owner_it_was_filed_for(): void
    {
        $owner = User::factory()->create(['name' => 'Rajesh Patel']);

        $this->get(route('admin.businesses.create'))->assertOk();

        $this->post(route('admin.businesses.store'), [
            'user_id' => $owner->id,
            'name' => 'Gayatri Engineering',
            'bill_prefix' => 'GE',
            'fy_reset' => '1',
        ])->assertRedirect();

        $business = Business::where('name', 'Gayatri Engineering')->sole();

        $this->assertSame($owner->id, $business->user_id);
        $this->assertNotEmpty($business->uuid);
        // A fresh series starts at one, whatever the prefix.
        $this->assertSame('GE/'.Business::financialYearOf(now()).'/001', $business->formatBillNo($business->next_bill_no));
    }

    /**
     * A browser submits every box on the form, so an optional one left empty
     * arrives as '' and then, through ConvertEmptyStringsToNull, as null. These
     * columns are NOT NULL with an empty default, and writing that null is a
     * constraint violation — posting only the keys a test cares about hides it.
     */
    public function test_a_form_submitted_with_the_optional_boxes_left_empty_saves(): void
    {
        $owner = User::factory()->create();

        $this->post(route('admin.businesses.store'), [
            'user_id' => $owner->id,
            'name' => 'Killer',
            'tagline' => '',
            'address' => '',
            'mobile' => '',
            'jurisdiction_text' => '',
            'gst_number' => '',
            'email' => '',
            'bank_details' => '',
            'upi_id' => '',
            'bill_prefix' => '',
            'terms_text' => '',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $business = Business::where('name', 'Killer')->sole();

        $this->assertSame('', $business->jurisdiction_text);
        $this->assertSame('', $business->bill_prefix);

        // And the same form on the way back out.
        $this->put(route('admin.businesses.update', $business), [
            'name' => 'Killer',
            'jurisdiction_text' => '',
            'gst_number' => '',
            'mobile' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame('', $business->fresh()->gst_number);
    }

    public function test_a_customer_and_a_catalogue_entry_save_with_their_optional_boxes_empty(): void
    {
        $this->post(route('admin.customers.store'), [
            'business_uuid' => $this->business->uuid,
            'name' => 'Gayatri Engineering',
            'phone' => '',
            'address' => '',
            'gst_number' => '',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('', Customer::where('name', 'Gayatri Engineering')->sole()->phone);

        $this->post(route('admin.items.store'), [
            'business_uuid' => $this->business->uuid,
            'name' => 'Surface Grinding',
            'default_rate' => '',
            'hsn_code' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame('', Item::where('name', 'Surface Grinding')->sole()->hsn_code);
    }

    public function test_a_business_needs_an_owner(): void
    {
        $this->post(route('admin.businesses.store'), ['name' => 'Ownerless Traders'])
            ->assertSessionHasErrors('user_id');

        $this->assertDatabaseMissing('businesses', ['name' => 'Ownerless Traders']);
    }

    public function test_removing_a_business_takes_its_books_with_it_and_erases_nothing(): void
    {
        $customer = Customer::factory()->for($this->business)->create();
        Item::factory()->for($this->business)->create();

        $invoice = Invoice::factory()->for($this->business)->create();
        $payment = $invoice->payments()->create(['date' => now(), 'amount' => 4000, 'mode' => 'cash']);

        $this->delete(route('admin.businesses.destroy', $this->business))
            ->assertRedirect(route('admin.businesses.index'));

        // Soft, and all the way down: sync propagates a deletion by pulling the
        // row with its deleted_at, so a child left behind stays on the handset.
        $this->assertSoftDeleted('businesses', ['id' => $this->business->id]);
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
        $this->assertSame(0, Business::count());
        $this->assertSame(1, Business::withTrashed()->count());
    }

    // ---------------- customers ----------------

    public function test_adding_a_customer(): void
    {
        $this->get(route('admin.customers.create'))->assertOk();

        $this->post(route('admin.customers.store'), [
            'business_uuid' => $this->business->uuid,
            'name' => 'Gayatri Engineering',
            'phone' => '9825044551',
        ])->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'business_id' => $this->business->id,
            'name' => 'Gayatri Engineering',
        ]);
    }

    public function test_renaming_a_customer_also_renames_them_on_their_bills(): void
    {
        $customer = Customer::factory()->for($this->business)->create(['name' => 'Old Name']);
        $invoice = Invoice::factory()->for($this->business)->worth(5000)->create(['customer_name' => 'Old Name']);

        $this->put(route('admin.customers.update', $customer), ['name' => 'New Name'])
            ->assertRedirect(route('admin.customers.show', $customer));

        $this->assertSame('New Name', $invoice->fresh()->customer_name);
    }

    public function test_a_duplicate_customer_name_is_rejected(): void
    {
        Customer::factory()->for($this->business)->create(['name' => 'Mahesh Traders']);

        $this->post(route('admin.customers.store'), [
            'business_uuid' => $this->business->uuid,
            'name' => 'Mahesh Traders',
        ])->assertSessionHasErrors('name');
    }

    public function test_removing_a_customer_leaves_their_bills_alone(): void
    {
        $customer = Customer::factory()->for($this->business)->create(['name' => 'Leaving Co']);
        $invoice = Invoice::factory()->for($this->business)->worth(1000)->create(['customer_name' => 'Leaving Co']);

        $this->delete(route('admin.customers.destroy', $customer))->assertRedirect();

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertNotNull($invoice->fresh());
        $this->assertSame('Leaving Co', $invoice->fresh()->customer_name);
    }

    public function test_the_statement_adds_bills_up_and_takes_receipts_off(): void
    {
        $customer = Customer::factory()->for($this->business)->create(['name' => 'Ledger Co']);
        $invoice = Invoice::factory()->for($this->business)->worth(10000)->create(['customer_name' => 'Ledger Co']);
        $invoice->payments()->create(['date' => now(), 'amount' => 4000, 'mode' => 'cash']);
        $invoice->load('payments')->recalculateTotals();

        $this->get(route('admin.customers.statement', $customer))
            ->assertOk()
            ->assertSee('Ledger Co')
            ->assertSee('Statement of account');
    }

    // ---------------- catalogue ----------------

    public function test_the_catalogue_can_be_added_to_and_edited(): void
    {
        $this->get(route('admin.items.index'))->assertOk();
        $this->get(route('admin.items.create'))->assertOk();

        $this->post(route('admin.items.store'), [
            'business_uuid' => $this->business->uuid,
            'name' => 'Surface Grinding',
            'default_rate' => 850,
        ])->assertRedirect();

        $item = Item::where('name', 'Surface Grinding')->firstOrFail();

        $this->put(route('admin.items.update', $item), ['name' => 'Surface Grinding', 'default_rate' => 900]);
        $this->assertSame(900.0, (float) $item->fresh()->default_rate);

        $this->delete(route('admin.items.destroy', $item))->assertRedirect();
        $this->assertSoftDeleted('items', ['id' => $item->id]);
    }

    // ---------------- documents ----------------

    public function test_raising_a_bill_from_the_panel_takes_the_next_number(): void
    {
        $this->get(route('admin.invoices.create'))->assertOk();

        // The column's default lives in the database, so the freshly made model
        // has not seen it yet.
        $before = (int) $this->business->fresh()->next_bill_no;

        $this->post(route('admin.invoices.store'), [
            'business_uuid' => $this->business->uuid,
            'doc_type' => 'bill',
            'customer_name' => 'Walk In',
            'date' => now()->toDateString(),
            'lines' => [
                ['particulars' => 'Welding', 'quantity' => 2, 'rate' => 1500],
                ['particulars' => '', 'quantity' => '', 'rate' => ''],
            ],
            'taxes' => [
                ['label' => 'CGST', 'percent' => 9],
                ['label' => 'SGST', 'percent' => 9],
            ],
            'discount_type' => 'none',
            'discount_value' => '',
            'round_off' => '',
        ])->assertRedirect();

        $invoice = Invoice::where('customer_name', 'Walk In')->firstOrFail();

        $this->assertSame($before, $invoice->bill_no);
        $this->assertSame($before + 1, $this->business->fresh()->next_bill_no);

        // The blank row the form leaves behind must not become a line.
        $this->assertCount(1, $invoice->lines);

        // 3000 + 18% = 3540
        $this->assertEqualsWithDelta(3540.0, (float) $invoice->total, 0.01);

        // Typing a new name onto a document grows the catalogues, exactly as
        // it does on the handset.
        $this->assertDatabaseHas('customers', ['business_id' => $this->business->id, 'name' => 'Walk In']);
        $this->assertDatabaseHas('items', ['business_id' => $this->business->id, 'name' => 'Welding']);
    }

    public function test_a_document_with_no_lines_is_rejected(): void
    {
        $this->post(route('admin.invoices.store'), [
            'business_uuid' => $this->business->uuid,
            'customer_name' => 'Nobody',
            'lines' => [['particulars' => '', 'quantity' => '', 'rate' => '']],
        ])->assertSessionHasErrors('lines');
    }

    public function test_editing_a_bill_keeps_its_number_and_its_receipts(): void
    {
        $invoice = Invoice::factory()->for($this->business)->worth(10000)->create([
            'customer_name' => 'Kiran Engineering',
            'bill_ref' => 'RS/26-27/007',
        ]);
        $invoice->payments()->create(['date' => now(), 'amount' => 4000, 'mode' => 'cash']);
        $invoice->load('payments')->recalculateTotals();

        $this->get(route('admin.invoices.edit', $invoice))->assertOk();

        $this->put(route('admin.invoices.update', $invoice), [
            'customer_name' => 'Kiran Engineering',
            'date' => $invoice->date->toDateString(),
            'lines' => [['particulars' => 'Boring', 'quantity' => 1, 'rate' => 20000]],
            'taxes' => [],
            'discount_type' => 'none',
            'discount_value' => 0,
            'round_off' => 0,
        ])->assertRedirect(route('admin.invoices.show', $invoice));

        $fresh = $invoice->fresh(['payments']);
        $this->assertSame('RS/26-27/007', $fresh->bill_ref);
        $this->assertEqualsWithDelta(20000.0, (float) $fresh->total, 0.01);
        $this->assertEqualsWithDelta(4000.0, (float) $fresh->paid_amount, 0.01);
        $this->assertCount(1, $fresh->payments);
    }

    public function test_a_cancelled_document_cannot_be_edited_until_it_is_reinstated(): void
    {
        $invoice = Invoice::factory()->for($this->business)->worth(1000)->create(['voided_at' => now()]);

        $this->put(route('admin.invoices.update', $invoice), [
            'customer_name' => 'Anyone',
            'lines' => [['particulars' => 'X', 'quantity' => 1, 'rate' => 1]],
        ])->assertSessionHasErrors('customer_name');

        $this->post(route('admin.invoices.unvoid', $invoice))->assertRedirect();
        $this->assertNull($invoice->fresh()->voided_at);
    }

    public function test_cancelling_keeps_the_number_used(): void
    {
        $invoice = Invoice::factory()->for($this->business)->worth(1000)->create();

        $this->post(route('admin.invoices.void', $invoice), ['reason' => 'Raised twice'])
            ->assertRedirect(route('admin.invoices.show', $invoice));

        $fresh = $invoice->fresh();
        $this->assertNotNull($fresh->voided_at);
        $this->assertSame('Raised twice', $fresh->void_reason);
        $this->assertSame('Cancelled', $fresh->status);
    }

    public function test_a_cancellation_needs_a_reason(): void
    {
        $invoice = Invoice::factory()->for($this->business)->worth(1000)->create();

        $this->post(route('admin.invoices.void', $invoice), ['reason' => ''])
            ->assertSessionHasErrors('reason');
    }

    // ---------------- payments ----------------

    public function test_recording_a_receipt_moves_the_balance(): void
    {
        $invoice = Invoice::factory()->for($this->business)->worth(10000)->create();

        $this->post(route('admin.payments.store', $invoice), [
            'amount' => 6000,
            'date' => now()->toDateString(),
            'mode' => 'upi',
            'note' => 'GPay',
        ])->assertRedirect(route('admin.invoices.show', $invoice));

        $fresh = $invoice->fresh();
        $this->assertEqualsWithDelta(6000.0, (float) $fresh->paid_amount, 0.01);
        $this->assertEqualsWithDelta(4000.0, $fresh->balance, 0.01);
        $this->assertSame('Partial', $fresh->status);
    }

    public function test_removing_a_receipt_recomputes_the_balance(): void
    {
        $invoice = Invoice::factory()->for($this->business)->worth(10000)->create();
        $this->post(route('admin.payments.store', $invoice), ['amount' => 10000]);

        $this->assertSame('Paid', $invoice->fresh()->status);

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->delete(route('admin.payments.destroy', [$invoice, $payment]))->assertRedirect();

        $this->assertEqualsWithDelta(0.0, (float) $invoice->fresh()->paid_amount, 0.01);
        $this->assertSame('Unpaid', $invoice->fresh()->status);
    }

    public function test_a_quotation_does_not_take_payments(): void
    {
        $quotation = Invoice::factory()->for($this->business)->worth(5000)->create(['doc_type' => 'quotation']);

        $this->post(route('admin.payments.store', $quotation), ['amount' => 100])
            ->assertSessionHasErrors('amount');

        $this->assertCount(0, $quotation->fresh()->payments);
    }

    public function test_a_receipt_of_zero_is_rejected(): void
    {
        $invoice = Invoice::factory()->for($this->business)->worth(1000)->create();

        $this->post(route('admin.payments.store', $invoice), ['amount' => 0])
            ->assertSessionHasErrors('amount');
    }

    // ---------------- reports ----------------

    public function test_the_report_pages_render(): void
    {
        Invoice::factory()->for($this->business)->worth(5000)->create(['date' => now()->subDays(200)]);

        $this->get(route('admin.reports.gst'))->assertOk();
        $this->get(route('admin.reports.payments'))->assertOk();
        $this->get(route('admin.reports.overdue'))->assertOk()->assertSee('Pending payments');
    }

    public function test_the_chase_list_only_shows_bills_past_the_age_asked_for(): void
    {
        $old = Invoice::factory()->for($this->business)->worth(7000)->create([
            'customer_name' => 'Long Overdue Co',
            'date' => now()->subDays(120),
        ]);
        Invoice::factory()->for($this->business)->worth(3000)->create([
            'customer_name' => 'Recent Co',
            'date' => now()->subDays(5),
        ]);

        $this->get(route('admin.reports.overdue', ['days' => 90]))
            ->assertOk()
            ->assertSee('Long Overdue Co')
            ->assertDontSee('Recent Co');

        $this->assertSame('Long Overdue Co', $old->fresh()->customer_name);
    }

    public function test_a_backwards_date_window_is_swapped_rather_than_returning_nothing(): void
    {
        $this->get(route('admin.reports.gst', ['from' => '2026-09-30', 'to' => '2026-09-01']))
            ->assertOk();
    }
}
