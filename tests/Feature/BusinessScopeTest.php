<?php

namespace Tests\Feature;

use App\Http\Middleware\ScopeToBusiness;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The header switcher: which firm's books the panel is open on.
 *
 * The rule these all turn on is that the choice is made once and then holds
 * everywhere — a panel that filtered the bill list but not the ledger would
 * be worse than one that filtered nothing, because the two would disagree
 * without saying so.
 */
class BusinessScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Business $steel;

    private Business $timber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);

        $this->steel = Business::factory()->for($this->admin)->withSeries('ST')->create(['name' => 'Rajesh Steel Works']);
        $this->timber = Business::factory()->for($this->admin)->withSeries('TM')->create(['name' => 'Patel Timber Mart']);

        Invoice::factory()->for($this->steel)->worth(5000)->create(['customer_name' => 'Steel Customer']);
        Invoice::factory()->for($this->timber)->worth(7000)->create(['customer_name' => 'Timber Customer']);

        Customer::factory()->for($this->steel)->create(['name' => 'Steel Customer']);
        Customer::factory()->for($this->timber)->create(['name' => 'Timber Customer']);

        $this->actingAs($this->admin);
    }

    public function test_choosing_a_business_hides_every_other_ones_bills(): void
    {
        $this->post(route('admin.businesses.switch'), ['business' => $this->steel->uuid])
            ->assertRedirect();

        $this->assertSame($this->steel->uuid, session(ScopeToBusiness::KEY));

        $this->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertSee('Steel Customer')
            ->assertDontSee('Timber Customer');
    }

    public function test_the_choice_holds_across_the_rest_of_the_panel(): void
    {
        $this->post(route('admin.businesses.switch'), ['business' => $this->timber->uuid]);

        // The ledger, the catalogue and the reports each had a business
        // filter of their own; one choice now drives all of them.
        $this->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('Timber Customer')
            ->assertDontSee('Steel Customer');

        $this->get(route('admin.reports.overdue'))
            ->assertOk()
            ->assertSee('Timber Customer')
            ->assertDontSee('Steel Customer');
    }

    public function test_all_businesses_puts_everything_back(): void
    {
        $this->post(route('admin.businesses.switch'), ['business' => $this->steel->uuid]);
        $this->post(route('admin.businesses.switch'), ['business' => '']);

        $this->assertNull(session(ScopeToBusiness::KEY));

        $this->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertSee('Steel Customer')
            ->assertSee('Timber Customer');
    }

    public function test_a_business_filter_on_a_page_switches_the_whole_panel(): void
    {
        // Otherwise the page select and the header would disagree about which
        // books are open, and only one of them would be visible.
        $this->get(route('admin.invoices.index', ['business' => $this->steel->uuid]))->assertOk();

        $this->assertSame($this->steel->uuid, session(ScopeToBusiness::KEY));

        $this->get(route('admin.customers.index'))
            ->assertOk()
            ->assertDontSee('Timber Customer');
    }

    public function test_the_sidebar_offers_the_business_list_only_when_showing_all(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertOk()
            // The switcher itself, on every page of the panel.
            ->assertSee('All businesses')
            ->assertSee('Patel Timber Mart')
            ->assertSee('Your firms &amp; GST details', false);

        $this->post(route('admin.businesses.switch'), ['business' => $this->steel->uuid]);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Rajesh Steel Works')
            ->assertSee('Profile, GST &amp; numbering', false)
            ->assertDontSee('Your firms &amp; GST details', false);
    }

    public function test_the_dashboard_counts_only_the_chosen_business(): void
    {
        $this->post(route('admin.businesses.switch'), ['business' => $this->steel->uuid]);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Steel Customer')
            ->assertDontSee('Timber Customer');
    }

    public function test_a_selection_that_no_longer_exists_falls_back_to_all(): void
    {
        $this->post(route('admin.businesses.switch'), ['business' => $this->timber->uuid]);

        // Removed the way the panel removes one: the business and everything
        // under it are marked deleted together.
        $this->delete(route('admin.businesses.destroy', $this->timber))->assertRedirect();

        $this->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertSee('Steel Customer');

        $this->assertNull(session(ScopeToBusiness::KEY));
    }
}
