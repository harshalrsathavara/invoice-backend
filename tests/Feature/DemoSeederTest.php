<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The seeder runs on every deployment, so the thing worth testing is not
 * that it writes data — it is that running it again writes nothing.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_three_businesses_with_books(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(3, Business::count());

        foreach (Business::all() as $business) {
            $this->assertTrue(
                $business->invoices()->withTrashed()->exists(),
                "{$business->name} was seeded without any documents.",
            );
        }

        // Every state the panel can show: settled, part settled, untouched,
        // cancelled, and the two documents that are not bills.
        $bills = Invoice::withTrashed()->with('payments')->get();
        $this->assertTrue($bills->contains(fn (Invoice $i) => $i->status === 'Paid'));
        $this->assertTrue($bills->contains(fn (Invoice $i) => $i->status === 'Partial'));
        $this->assertTrue($bills->contains(fn (Invoice $i) => $i->status === 'Unpaid'));
        $this->assertTrue($bills->contains(fn (Invoice $i) => $i->is_voided));
        $this->assertTrue($bills->contains(fn (Invoice $i) => $i->doc_type === Invoice::TYPE_QUOTATION));
        $this->assertTrue($bills->contains(fn (Invoice $i) => $i->doc_type === Invoice::TYPE_CHALLAN));
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $this->seed(DemoSeeder::class);

        $businesses = Business::count();
        $documents = Invoice::withTrashed()->count();
        $payments = \App\Models\Payment::count();

        // What a redeploy does.
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame($businesses, Business::count());
        $this->assertSame($documents, Invoice::withTrashed()->count());
        $this->assertSame($payments, \App\Models\Payment::count());
    }

    public function test_it_hangs_the_data_off_the_real_administrator(): void
    {
        // The deployment makes the admin from env vars before seeding, and a
        // second owner account nobody can sign in as would be worse than no
        // demo data at all.
        $admin = User::factory()->create(['is_admin' => true, 'email' => 'owner@example.com']);

        $this->seed(DemoSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertSame(3, $admin->businesses()->count());
    }

    public function test_it_leaves_a_business_that_already_has_bills_alone(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $steel = Business::factory()->for($admin)->create(['bill_prefix' => 'RS', 'name' => 'Renamed By Hand']);
        Invoice::factory()->for($steel)->worth(1000)->create(['customer_name' => 'Real Customer']);

        $this->seed(DemoSeeder::class);

        $steel->refresh();
        $this->assertSame('Renamed By Hand', $steel->name, 'A live business must not be rewritten by the seeder.');
        $this->assertSame(1, $steel->invoices()->withTrashed()->count());

        // The other two are still seeded around it.
        $this->assertSame(3, Business::count());
    }
}
