<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Owner accounts. The rules worth holding are the ones that lock someone out or
 * erase books: an account deletes for real and the businesses under it cascade
 * with it at the database level, and an administrator can demote themselves out
 * of the only door into the panel.
 */
class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true, 'name' => 'The Admin']);
        $this->actingAs($this->admin);
    }

    public function test_the_list_and_the_form_render(): void
    {
        $this->get(route('admin.users.index'))->assertOk()->assertSee('The Admin');
        $this->get(route('admin.users.create'))->assertOk();

        $owner = User::factory()->create();
        Business::factory()->for($owner)->create();

        // The edit page counts what the account holds, to say whether it can go.
        $this->get(route('admin.users.edit', $owner))->assertOk()->assertSee('still holds');
    }

    public function test_adding_an_owner_who_signs_in_from_the_app_only(): void
    {
        $this->post(route('admin.users.store'), [
            'name' => 'Rajesh Patel',
            'email' => 'rajesh@example.com',
            'phone' => '9825044551',
            'password' => 'phone-secret',
        ])->assertRedirect(route('admin.users.index'));

        $owner = User::where('email', 'rajesh@example.com')->sole();

        $this->assertFalse($owner->is_admin);
        $this->assertTrue(Hash::check('phone-secret', $owner->password));

        // An ordinary owner is exactly who a new business can be filed under.
        $this->post(route('admin.businesses.store'), [
            'user_id' => $owner->id,
            'name' => 'Patel Engineering',
        ])->assertSessionHasNoErrors();

        $this->assertSame($owner->id, Business::where('name', 'Patel Engineering')->sole()->user_id);
    }

    public function test_an_owner_without_panel_access_cannot_open_the_panel(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);

        $this->actingAs($owner)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_a_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post(route('admin.users.store'), [
            'name' => 'Someone Else',
            'email' => 'taken@example.com',
            'password' => 'long-enough',
        ])->assertSessionHasErrors('email');
    }

    public function test_a_short_password_is_rejected(): void
    {
        $this->post(route('admin.users.store'), [
            'name' => 'Rajesh Patel',
            'email' => 'short@example.com',
            'password' => 'abc',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'short@example.com']);
    }

    public function test_a_blank_password_box_leaves_the_password_alone(): void
    {
        $owner = User::factory()->create(['password' => Hash::make('original-secret')]);

        $this->put(route('admin.users.update', $owner), [
            'name' => 'Renamed Owner',
            'email' => $owner->email,
            'password' => '',
        ])->assertSessionHasNoErrors();

        $owner->refresh();

        $this->assertSame('Renamed Owner', $owner->name);
        $this->assertTrue(Hash::check('original-secret', $owner->password));
    }

    public function test_setting_a_new_password_signs_their_handsets_out(): void
    {
        $owner = User::factory()->create();
        $owner->createToken('device:'.fake()->uuid());

        $this->assertSame(1, $owner->tokens()->count());

        $this->put(route('admin.users.update', $owner), [
            'name' => $owner->name,
            'email' => $owner->email,
            'password' => 'a-new-secret',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('a-new-secret', $owner->fresh()->password));
        $this->assertSame(0, $owner->tokens()->count());
    }

    public function test_an_admin_cannot_remove_their_own_panel_access(): void
    {
        // Unticking the box on your own account shuts the door you came in by,
        // and with one administrator it shuts it on everybody.
        $this->put(route('admin.users.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
        ])->assertSessionHasErrors('is_admin');

        $this->assertTrue($this->admin->fresh()->is_admin);
    }

    public function test_editing_your_own_account_still_works(): void
    {
        // The form disables the box on your own account and posts it back as a
        // hidden field, so an ordinary self-edit must go through untouched.
        $this->get(route('admin.users.edit', $this->admin))
            ->assertOk()
            ->assertSee('cannot take this off your own account', false);

        $this->put(route('admin.users.update', $this->admin), [
            'name' => 'The Admin',
            'email' => $this->admin->email,
            'phone' => '9033894343',
            'is_admin' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('9033894343', $this->admin->fresh()->phone);
        $this->assertTrue($this->admin->fresh()->is_admin);
    }

    public function test_promoting_and_demoting_somebody_else_works(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);

        $this->put(route('admin.users.update', $owner), [
            'name' => $owner->name,
            'email' => $owner->email,
            'is_admin' => '1',
        ]);
        $this->assertTrue($owner->fresh()->is_admin);

        $this->put(route('admin.users.update', $owner), [
            'name' => $owner->name,
            'email' => $owner->email,
        ]);
        $this->assertFalse($owner->fresh()->is_admin);
    }

    /**
     * businesses.user_id cascades on delete, so this is not a tidiness rule:
     * without it the database erases every business, customer, bill and receipt
     * under the account outright, ignoring soft deletes entirely.
     */
    public function test_an_owner_who_still_holds_books_cannot_be_deleted(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->for($owner)->create();
        $customer = Customer::factory()->for($business)->create();
        $invoice = Invoice::factory()->for($business)->create();

        $this->delete(route('admin.users.destroy', $owner))->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->assertDatabaseHas('businesses', ['id' => $business->id]);
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }

    public function test_a_business_removed_from_the_panel_still_counts_as_held(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->for($owner)->create();

        // Soft-deleted is still a row the cascade would take with it.
        $business->delete();

        $this->delete(route('admin.users.destroy', $owner))->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    public function test_an_empty_account_can_be_removed(): void
    {
        $owner = User::factory()->create();
        $owner->createToken('device:'.fake()->uuid());

        $this->delete(route('admin.users.destroy', $owner))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        $this->assertSame(0, \Laravel\Sanctum\PersonalAccessToken::count());
    }

    public function test_you_cannot_delete_the_account_you_are_signed_in_as(): void
    {
        $this->delete(route('admin.users.destroy', $this->admin))->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }
}
