<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SyncConflict;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Business $business;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->business = Business::factory()->for($this->user)->create();
        $this->device = Device::factory()->for($this->user)->create();
        Sanctum::actingAs($this->user);
    }

    private function push(array $changes)
    {
        return $this->postJson('/api/v1/sync/push', [
            'device_uuid' => $this->device->uuid,
            'changes' => $changes,
        ]);
    }

    public function test_a_handset_can_push_a_bill_it_raised_offline(): void
    {
        $invoiceUuid = (string) Str::uuid();

        $this->push([
            'customers' => [[
                'uuid' => (string) Str::uuid(),
                'business_uuid' => $this->business->uuid,
                'name' => 'Mahesh Traders',
                'phone' => '9876543210',
                'updated_at' => '2026-09-14T10:00:00+05:30',
            ]],
            'invoices' => [[
                'uuid' => $invoiceUuid,
                'business_uuid' => $this->business->uuid,
                'doc_type' => 'bill',
                'bill_no' => 7,
                'bill_ref' => 'RS/26-27/007',
                'customer_name' => 'Mahesh Traders',
                'date' => '2026-09-14',
                'discount_type' => 'none',
                'discount_value' => 0,
                'round_off' => 0,
                'updated_at' => '2026-09-14T10:05:00+05:30',
                'lines' => [
                    ['uuid' => (string) Str::uuid(), 'particulars' => 'Lathe Job Work', 'quantity' => 2, 'rate' => 500],
                ],
                'taxes' => [
                    ['uuid' => (string) Str::uuid(), 'label' => 'CGST', 'percent' => 9],
                    ['uuid' => (string) Str::uuid(), 'label' => 'SGST', 'percent' => 9],
                ],
            ]],
        ])->assertOk()->assertJsonPath('counts.invoices', 1);

        $invoice = Invoice::where('uuid', $invoiceUuid)->firstOrFail();

        $this->assertSame(7, $invoice->bill_no);
        $this->assertSame('RS/26-27/007', $invoice->bill_ref);
        $this->assertEquals(1180, $invoice->total);
        $this->assertCount(2, $invoice->taxes);
    }

    public function test_the_number_the_handset_issued_is_never_reassigned(): void
    {
        $this->push(['invoices' => [[
            'uuid' => (string) Str::uuid(),
            'business_uuid' => $this->business->uuid,
            'bill_no' => 40,
            'bill_ref' => '40',
            'customer_name' => 'Mahesh Traders',
            'date' => '2026-09-14',
            'updated_at' => '2026-09-14T10:00:00+05:30',
            'lines' => [['uuid' => (string) Str::uuid(), 'particulars' => 'Work', 'quantity' => 1, 'rate' => 100]],
        ]]])->assertOk();

        // And the server's own counter is dragged past it, so a bill raised
        // here can never collide with one the phone has already printed.
        $this->assertSame(41, $this->business->fresh()->next_bill_no);
    }

    public function test_pushing_the_same_batch_twice_changes_nothing(): void
    {
        $payload = ['customers' => [[
            'uuid' => (string) Str::uuid(),
            'business_uuid' => $this->business->uuid,
            'name' => 'Mahesh Traders',
            'updated_at' => '2026-09-14T10:00:00+05:30',
        ]]];

        $this->push($payload)->assertOk();
        $this->push($payload)->assertOk();

        $this->assertSame(1, Customer::count());
    }

    public function test_a_stale_change_loses_and_is_kept_for_review(): void
    {
        $customer = Customer::factory()->for($this->business)->create(['name' => 'Server Name']);
        $customer->forceFill(['updated_at' => CarbonImmutable::parse('2026-09-15 12:00:00')])->saveQuietly();

        // The handset edited the same row, but earlier — and sends IST, so the
        // offset has to be honoured for the comparison to mean anything.
        $this->push(['customers' => [[
            'uuid' => $customer->uuid,
            'business_uuid' => $this->business->uuid,
            'name' => 'Stale Phone Name',
            'updated_at' => '2026-09-15T09:00:00+05:30',
        ]]])->assertOk()->assertJsonPath('conflicts', 1);

        $this->assertSame('Server Name', $customer->fresh()->name);

        $conflict = SyncConflict::firstOrFail();
        $this->assertSame('Customer', $conflict->model_type);
        $this->assertSame('Stale Phone Name', $conflict->incoming['name']);
        $this->assertFalse($conflict->reviewed);
    }

    public function test_a_newer_change_from_the_handset_wins(): void
    {
        $customer = Customer::factory()->for($this->business)->create(['name' => 'Server Name']);
        $customer->forceFill(['updated_at' => CarbonImmutable::parse('2026-09-15 12:00:00')])->saveQuietly();

        // 20:00 IST is 14:30 UTC — later than the stored 12:00, so it wins.
        $this->push(['customers' => [[
            'uuid' => $customer->uuid,
            'business_uuid' => $this->business->uuid,
            'name' => 'Newer Phone Name',
            'updated_at' => '2026-09-15T20:00:00+05:30',
        ]]])->assertOk()->assertJsonPath('conflicts', 0);

        $this->assertSame('Newer Phone Name', $customer->fresh()->name);
    }

    public function test_a_deletion_on_the_handset_propagates(): void
    {
        $customer = Customer::factory()->for($this->business)->create();

        $this->push(['customers' => [[
            'uuid' => $customer->uuid,
            'business_uuid' => $this->business->uuid,
            'name' => $customer->name,
            'updated_at' => now()->addMinute()->toIso8601String(),
            'deleted_at' => now()->addMinute()->toIso8601String(),
        ]]])->assertOk();

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    /**
     * The other half of a soft delete: an admin putting a row back has to
     * reach the handset that deleted it, or the panel's Restore button would
     * only ever be true on the server.
     *
     * Time is moved forward rather than timestamps being written by hand,
     * because what is under test is whether `restore()` bumps `updated_at`
     * past the handset's cursor on its own.
     */
    public function test_restoring_a_deleted_row_in_the_panel_reaches_the_handset(): void
    {
        $customer = Customer::factory()->for($this->business)->create(['name' => 'Mahesh Traders']);

        $this->travel(1)->minutes();

        $this->push(['customers' => [[
            'uuid' => $customer->uuid,
            'business_uuid' => $this->business->uuid,
            'name' => $customer->name,
            'updated_at' => now()->toIso8601String(),
            'deleted_at' => now()->toIso8601String(),
        ]]])->assertOk();

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);

        // Where the handset's cursor sits: it has seen the deletion.
        $cursor = now();

        $this->travel(1)->minutes();
        $customer->fresh()->restore();

        $row = collect(
            $this->getJson('/api/v1/sync/pull?since='.urlencode($cursor->toIso8601String()).'&device_uuid='.$this->device->uuid)
                ->assertOk()
                ->json('changes.customers')
        )->firstWhere('uuid', $customer->uuid);

        $this->assertNotNull($row, 'A restored customer should come back down on the next pull.');
        $this->assertNull($row['deleted_at'] ?? null, 'It should arrive alive, not still tombstoned.');

        $this->travelBack();
    }

    public function test_a_payment_lands_against_its_bill_and_moves_the_balance(): void
    {
        $invoice = Invoice::factory()->for($this->business)->worth(20000)->create();

        $this->push(['payments' => [[
            'uuid' => (string) Str::uuid(),
            'invoice_uuid' => $invoice->uuid,
            'date' => '2026-09-14',
            'amount' => 8000,
            'mode' => 'upi',
            'note' => 'GPay ref 4471',
            'updated_at' => '2026-09-14T10:00:00+05:30',
        ]]])->assertOk()->assertJsonPath('counts.payments', 1);

        $this->assertEquals(8000, $invoice->fresh()->paid_amount);
        $this->assertSame('Partial', $invoice->fresh()->status);
    }

    public function test_a_payment_does_not_make_its_bill_look_edited(): void
    {
        $invoice = Invoice::factory()->for($this->business)->worth(20000)->create();
        $invoice->forceFill(['updated_at' => CarbonImmutable::parse('2026-09-10 10:00:00')])->saveQuietly();

        $this->push(['payments' => [[
            'uuid' => (string) Str::uuid(),
            'invoice_uuid' => $invoice->uuid,
            'date' => '2026-09-14',
            'amount' => 8000,
            'mode' => 'cash',
            'updated_at' => '2026-09-14T10:00:00+05:30',
        ]]])->assertOk();

        // The cached total moved, but the bill itself was not edited — and if
        // it looked edited, a content change queued on the phone before now
        // would lose last-write-wins and be filed as a conflict.
        $this->assertEquals(8000, $invoice->fresh()->paid_amount);
        $this->assertSame(
            '2026-09-10 10:00:00',
            $invoice->fresh()->updated_at->format('Y-m-d H:i:s')
        );
    }

    public function test_an_edit_queued_before_a_payment_still_wins(): void
    {
        $invoice = Invoice::factory()->for($this->business)->worth(20000)->create();
        $invoice->forceFill(['updated_at' => CarbonImmutable::parse('2026-09-10 10:00:00')])->saveQuietly();

        // The payment arrives first, then the older-but-still-newer-than-stored
        // edit the phone had been holding.
        $this->push(['payments' => [[
            'uuid' => (string) Str::uuid(),
            'invoice_uuid' => $invoice->uuid,
            'date' => '2026-09-14', 'amount' => 8000, 'mode' => 'cash',
            'updated_at' => '2026-09-14T10:00:00+05:30',
        ]]])->assertOk();

        $this->push(['invoices' => [[
            'uuid' => $invoice->uuid,
            'business_uuid' => $this->business->uuid,
            'customer_name' => 'Corrected Name',
            'date' => '2026-09-10',
            'bill_no' => $invoice->bill_no,
            'updated_at' => '2026-09-12T10:00:00+05:30',
            'lines' => [['uuid' => (string) Str::uuid(), 'particulars' => 'Work', 'quantity' => 1, 'rate' => 20000]],
        ]]])->assertOk()->assertJsonPath('conflicts', 0);

        $this->assertSame('Corrected Name', $invoice->fresh()->customer_name);
        // And the payment survives the edit.
        $this->assertEquals(8000, $invoice->fresh()->paid_amount);
    }

    public function test_another_owners_business_uuid_is_ignored(): void
    {
        $stranger = Business::factory()->create();

        $this->push(['customers' => [[
            'uuid' => (string) Str::uuid(),
            'business_uuid' => $stranger->uuid,
            'name' => 'Should Not Land',
            'updated_at' => now()->toIso8601String(),
        ]]])->assertOk()->assertJsonPath('counts.customers', 0);

        $this->assertDatabaseMissing('customers', ['name' => 'Should Not Land']);
    }

    public function test_pull_returns_only_what_changed_since_the_cursor(): void
    {
        $old = Customer::factory()->for($this->business)->create(['name' => 'Already Synced']);
        $old->forceFill(['updated_at' => CarbonImmutable::parse('2026-09-10 10:00:00')])->saveQuietly();

        $cursor = CarbonImmutable::parse('2026-09-12 00:00:00');

        $fresh = Customer::factory()->for($this->business)->create(['name' => 'Added Later']);
        $fresh->forceFill(['updated_at' => CarbonImmutable::parse('2026-09-14 10:00:00')])->saveQuietly();

        $response = $this->getJson('/api/v1/sync/pull?since='.urlencode($cursor->toIso8601String()).'&device_uuid='.$this->device->uuid)
            ->assertOk();

        $names = collect($response->json('changes.customers'))->pluck('name');

        $this->assertTrue($names->contains('Added Later'));
        $this->assertFalse($names->contains('Already Synced'));
    }

    public function test_pull_carries_the_business_uuid_not_the_server_id(): void
    {
        Customer::factory()->for($this->business)->create();

        $row = $this->getJson('/api/v1/sync/pull')->assertOk()->json('changes.customers.0');

        $this->assertSame($this->business->uuid, $row['business_uuid']);
        $this->assertArrayNotHasKey('business_id', $row);
        $this->assertArrayNotHasKey('id', $row);
    }

    public function test_a_sync_is_recorded_against_the_device(): void
    {
        $this->push(['customers' => [[
            'uuid' => (string) Str::uuid(),
            'business_uuid' => $this->business->uuid,
            'name' => 'Mahesh Traders',
            'updated_at' => now()->toIso8601String(),
        ]]])->assertOk();

        $this->assertDatabaseHas('sync_logs', ['device_id' => $this->device->id, 'direction' => 'push']);
        $this->assertNotNull($this->device->fresh()->last_pushed_at);
    }
}
