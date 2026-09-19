<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->business = Business::factory()->for($this->user)->withSeries('RS')->create();
        Sanctum::actingAs($this->user);
    }

    private function url(string $path = ''): string
    {
        return "/api/v1/businesses/{$this->business->uuid}".$path;
    }

    public function test_raising_a_bill_reserves_a_number_and_computes_the_total(): void
    {
        $response = $this->postJson($this->url('/invoices'), [
            'customer_name' => 'Mahesh Traders',
            'date' => '2026-09-15',
            'lines' => [
                ['particulars' => 'Lathe Job Work', 'quantity' => 2, 'rate' => 500],
                ['particulars' => 'Welding', 'quantity' => 1, 'rate' => 1000],
            ],
            'taxes' => [
                ['label' => 'CGST', 'percent' => 9],
                ['label' => 'SGST', 'percent' => 9],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.bill_ref', 'RS/26-27/001')
            ->assertJsonPath('data.display_no', 'RS/26-27/001')
            ->assertJsonPath('data.totals.subtotal', 2000)
            ->assertJsonPath('data.totals.total_tax', 360)
            ->assertJsonPath('data.totals.total', 2360)
            ->assertJsonPath('data.status', 'Unpaid')
            ->assertJsonPath('data.amount_in_words', 'Two Thousand Three Hundred Sixty Rupees Only');
    }

    public function test_raising_a_bill_grows_the_customer_and_item_catalogues(): void
    {
        $this->postJson($this->url('/invoices'), [
            'customer_name' => 'New Customer',
            'lines' => [['particulars' => 'Boring Work', 'quantity' => 1, 'rate' => 750]],
        ])->assertCreated();

        $this->assertDatabaseHas('customers', ['business_id' => $this->business->id, 'name' => 'New Customer']);
        $this->assertDatabaseHas('items', ['business_id' => $this->business->id, 'name' => 'Boring Work', 'default_rate' => 750]);
    }

    public function test_a_bill_needs_at_least_one_line(): void
    {
        $this->postJson($this->url('/invoices'), [
            'customer_name' => 'Mahesh Traders',
            'lines' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('lines');
    }

    public function test_editing_keeps_the_number_and_the_payments(): void
    {
        $created = $this->postJson($this->url('/invoices'), [
            'customer_name' => 'Mahesh Traders',
            'lines' => [['particulars' => 'Lathe Job Work', 'quantity' => 1, 'rate' => 20000]],
        ])->json('data');

        $uuid = $created['uuid'];

        $this->postJson($this->url("/invoices/{$uuid}/payments"), [
            'amount' => 8000, 'mode' => 'upi', 'note' => 'GPay ref 4471',
        ])->assertOk()->assertJsonPath('data.status', 'Partial');

        $updated = $this->putJson($this->url("/invoices/{$uuid}"), [
            'customer_name' => 'Mahesh Traders',
            'lines' => [['particulars' => 'Lathe Job Work', 'quantity' => 1, 'rate' => 25000]],
        ])->assertOk()->json('data');

        $this->assertSame($created['bill_ref'], $updated['bill_ref']);
        // assertEquals, not assertSame: JSON renders 25000.0 as 25000, so the
        // decoded value is an int even though the column is a decimal.
        $this->assertEquals(25000, $updated['totals']['total']);
        $this->assertEquals(8000, $updated['totals']['paid_amount']);
        $this->assertEquals(17000, $updated['totals']['balance']);
    }

    public function test_cancelling_keeps_the_document_and_its_number(): void
    {
        $uuid = $this->postJson($this->url('/invoices'), [
            'customer_name' => 'Mahesh Traders',
            'lines' => [['particulars' => 'Work', 'quantity' => 1, 'rate' => 1000]],
        ])->json('data.uuid');

        $this->postJson($this->url("/invoices/{$uuid}/void"), ['reason' => 'Duplicate'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Cancelled')
            ->assertJsonPath('data.void_reason', 'Duplicate');

        $this->assertDatabaseHas('invoices', ['uuid' => $uuid, 'void_reason' => 'Duplicate']);

        // A cancelled document is not editable until it is reinstated.
        $this->putJson($this->url("/invoices/{$uuid}"), [
            'lines' => [['particulars' => 'Work', 'quantity' => 1, 'rate' => 2000]],
        ])->assertStatus(422);

        $this->postJson($this->url("/invoices/{$uuid}/unvoid"))
            ->assertOk()
            ->assertJsonPath('data.status', 'Unpaid');
    }

    public function test_a_quotation_does_not_burn_a_bill_number(): void
    {
        $this->postJson($this->url('/invoices'), [
            'doc_type' => 'quotation',
            'customer_name' => 'Mahesh Traders',
            'date' => '2026-09-15',
            'lines' => [['particulars' => 'Work', 'quantity' => 1, 'rate' => 1000]],
        ])->assertCreated()->assertJsonPath('data.bill_ref', 'RS/QT/26-27/001');

        $this->postJson($this->url('/invoices'), [
            'customer_name' => 'Mahesh Traders',
            'date' => '2026-09-15',
            'lines' => [['particulars' => 'Work', 'quantity' => 1, 'rate' => 1000]],
        ])->assertCreated()->assertJsonPath('data.bill_ref', 'RS/26-27/001');
    }

    public function test_mismatched_gst_split_warns_but_does_not_reject(): void
    {
        $this->postJson($this->url('/invoices'), [
            'customer_name' => 'Mahesh Traders',
            'lines' => [['particulars' => 'Work', 'quantity' => 1, 'rate' => 1000]],
            'taxes' => [['label' => 'CGST', 'percent' => 9], ['label' => 'SGST', 'percent' => 6]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.tax_warning', 'CGST and SGST are set to different rates.');
    }

    public function test_another_owners_business_is_not_reachable(): void
    {
        $stranger = Business::factory()->create();

        $this->getJson("/api/v1/businesses/{$stranger->uuid}/invoices")->assertForbidden();
    }

    public function test_the_endpoint_requires_a_token(): void
    {
        app()['auth']->forgetGuards();

        $this->getJson($this->url('/invoices'))->assertUnauthorized();
    }
}
