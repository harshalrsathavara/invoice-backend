<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The open seeding link.
 *
 * It takes no login, so what matters is that it cannot do damage: a reload
 * must not double the data, and it must not exist at all once the switch is
 * off.
 */
class DemoSeedRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('demo.enabled', true);
    }

    public function test_it_seeds_on_the_first_visit(): void
    {
        $this->get('/demo-seed')
            ->assertOk()
            ->assertSee('Seeded.')
            ->assertSee('Rajesh Steel Works')
            ->assertSee('Patel Timber Mart')
            ->assertSee('Shakti Electricals');

        $this->assertSame(3, Business::count());
    }

    public function test_reloading_it_creates_nothing_further(): void
    {
        $this->get('/demo-seed')->assertOk();

        $businesses = Business::count();
        $documents = Invoice::count();

        $this->get('/demo-seed')
            ->assertOk()
            ->assertSee('Already seeded.')
            ->assertSee('Reloading this page is safe.');

        $this->assertSame($businesses, Business::count());
        $this->assertSame($documents, Invoice::count());
    }

    public function test_it_does_not_exist_when_the_switch_is_off(): void
    {
        config()->set('demo.enabled', false);

        $this->get('/demo-seed')->assertNotFound();

        // And nothing was written on the way to the 404.
        $this->assertSame(0, Business::count());
    }

    public function test_it_leaves_a_real_business_alone(): void
    {
        // The nightmare this guards: somebody opens the link on an instance
        // that has real books on it.
        $admin = User::factory()->create(['is_admin' => true]);
        $real = Business::factory()->for($admin)->create([
            'bill_prefix' => 'RS',
            'name' => 'The Actual Firm',
        ]);
        Invoice::factory()->for($real)->worth(5000)->create(['customer_name' => 'A Real Customer']);

        $this->get('/demo-seed')->assertOk();

        $real->refresh();
        $this->assertSame('The Actual Firm', $real->name);
        $this->assertSame(1, $real->invoices()->count());
    }
}
