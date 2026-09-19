<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\SyncConflict;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renders every admin screen. A Blade mistake only shows itself at render
 * time, so these walk the panel rather than asserting on data.
 */
class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Business $business;

    private Invoice $invoice;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true, 'name' => 'Harshal']);
        $this->business = Business::factory()->for($this->admin)->withSeries('RS')->create();

        $this->customer = Customer::factory()->for($this->business)->create(['name' => 'Mahesh Traders']);

        $this->invoice = Invoice::factory()->for($this->business)->worth(20000)->create([
            'customer_name' => 'Mahesh Traders',
            'bill_ref' => 'RS/26-27/001',
            'amount_in_words' => 'Twenty Thousand Rupees Only',
        ]);
        $this->invoice->taxes()->create(['label' => 'CGST', 'percent' => 9]);
        $this->invoice->taxes()->create(['label' => 'SGST', 'percent' => 9]);
        $this->invoice->payments()->create(['date' => now(), 'amount' => 8000, 'mode' => 'upi', 'note' => 'GPay 4471']);
        $this->invoice->load(['lines', 'taxes', 'payments'])->recalculateTotals();

        $device = Device::factory()->for($this->admin)->create();
        $log = SyncLog::create([
            'user_id' => $this->admin->id,
            'device_id' => $device->id,
            'direction' => 'push',
            'counts' => ['invoices' => 1, 'payments' => 1],
            'conflicts' => 1,
        ]);
        SyncConflict::create([
            'sync_log_id' => $log->id,
            'user_id' => $this->admin->id,
            'model_type' => 'Customer',
            'uuid' => $this->customer->uuid,
            'incoming' => ['name' => 'Stale Phone Name'],
            'existing' => ['name' => 'Mahesh Traders'],
            'resolution' => 'server_kept',
        ]);
    }

    public function test_the_panel_is_closed_to_visitors(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_a_non_admin_cannot_get_in(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_signing_in_and_out(): void
    {
        $user = User::factory()->create(['is_admin' => true, 'password' => bcrypt('secret-pass')]);

        $this->post(route('admin.login'), ['email' => $user->email, 'password' => 'secret-pass'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_wrong_details_are_rejected(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->from(route('admin.login'))
            ->post(route('admin.login'), ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_every_screen_renders(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Outstanding')
            ->assertSee('Rajesh Steel Works');

        $this->get(route('admin.businesses.index'))->assertOk()->assertSee('Rajesh Steel Works');
        $this->get(route('admin.businesses.show', $this->business))->assertOk()->assertSee('GST charged');

        $this->get(route('admin.invoices.index'))->assertOk()->assertSee('RS/26-27/001');
        $this->get(route('admin.invoices.show', $this->invoice))
            ->assertOk()
            ->assertSee('Twenty Thousand Rupees Only')
            ->assertSee('GPay 4471');

        $this->get(route('admin.customers.index'))->assertOk()->assertSee('Mahesh Traders');
        $this->get(route('admin.customers.show', $this->customer))->assertOk()->assertSee('Their documents');

        $this->get(route('admin.devices.index'))->assertOk()->assertSee('OnePlus Nord');
        $this->get(route('admin.conflicts.index'))->assertOk()->assertSee('Stale Phone Name');
    }

    public function test_the_invoice_list_filters(): void
    {
        $this->actingAs($this->admin);

        Invoice::factory()->for($this->business)->quotation()->worth(5000)->create([
            'customer_name' => 'Kiran Engineering', 'bill_no' => 2, 'bill_ref' => 'RS/QT/26-27/001',
        ]);

        $this->get(route('admin.invoices.index', ['doc_type' => 'quotation']))
            ->assertOk()
            ->assertSee('Kiran Engineering')
            ->assertDontSee('RS/26-27/001');

        $this->get(route('admin.invoices.index', ['search' => 'Mahesh']))
            ->assertOk()
            ->assertSee('Mahesh Traders')
            ->assertDontSee('Kiran Engineering');

        $this->get(route('admin.invoices.index', ['status' => 'Partial']))
            ->assertOk()
            ->assertSee('RS/26-27/001');
    }

    public function test_a_conflict_can_be_marked_reviewed(): void
    {
        $this->actingAs($this->admin);
        $conflict = SyncConflict::firstOrFail();

        $this->post(route('admin.conflicts.review', $conflict))->assertRedirect();

        $this->assertTrue($conflict->fresh()->reviewed);
    }

    public function test_revoking_a_device_removes_its_token(): void
    {
        $this->actingAs($this->admin);
        $device = Device::firstOrFail();
        $this->admin->createToken('device:'.$device->uuid);

        $this->assertSame(1, $this->admin->tokens()->count());

        $this->post(route('admin.devices.revoke', $device))->assertRedirect();

        $this->assertSame(0, $this->admin->fresh()->tokens()->count());
    }
}
