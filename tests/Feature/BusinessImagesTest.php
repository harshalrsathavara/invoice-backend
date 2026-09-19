<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The logo and the signature that print on a bill.
 *
 * Both columns shipped in the first migration and both are published by
 * BusinessResource, but nothing could fill them except a phone backup, and
 * nothing served the files once they were there.
 */
class BusinessImagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->business = Business::factory()->for($this->admin)->create();
        $this->actingAs($this->admin);
    }

    public function test_uploading_a_logo_and_a_signature_from_the_panel(): void
    {
        $this->put(route('admin.businesses.update', $this->business), [
            'name' => $this->business->name,
            'logo' => UploadedFile::fake()->image('logo.png', 400, 200),
            'signature' => UploadedFile::fake()->image('sign.jpg', 300, 120),
        ])->assertSessionHasNoErrors();

        $business = $this->business->fresh();

        // Same convention the importer uses, so a backup image and an uploaded
        // one are indistinguishable afterwards.
        $this->assertStringStartsWith('business_images/', $business->logo_path);
        $this->assertStringStartsWith('business_images/', $business->signature_path);
        Storage::disk('public')->assertExists($business->logo_path);
        Storage::disk('public')->assertExists($business->signature_path);
    }

    public function test_images_go_to_whichever_disk_is_configured(): void
    {
        // Production points this at R2 so images survive redeploys.
        config(['filesystems.business_images_disk' => 'r2']);
        Storage::fake('r2');

        $this->put(route('admin.businesses.update', $this->business), [
            'name' => $this->business->name,
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertSessionHasNoErrors();

        $path = $this->business->fresh()->logo_path;
        Storage::disk('r2')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_new_business_can_be_added_with_its_logo_in_one_go(): void
    {
        $this->post(route('admin.businesses.store'), [
            'user_id' => $this->admin->id,
            'name' => 'Gayatri Engineering',
            'logo' => UploadedFile::fake()->image('logo.webp'),
        ])->assertSessionHasNoErrors();

        Storage::disk('public')->assertExists(
            Business::where('name', 'Gayatri Engineering')->sole()->logo_path
        );
    }

    public function test_replacing_an_image_removes_the_file_it_replaced(): void
    {
        $this->put(route('admin.businesses.update', $this->business), [
            'name' => $this->business->name,
            'logo' => UploadedFile::fake()->image('first.png'),
        ]);

        $first = $this->business->fresh()->logo_path;

        $this->put(route('admin.businesses.update', $this->business), [
            'name' => $this->business->name,
            'logo' => UploadedFile::fake()->image('second.png'),
        ]);

        $second = $this->business->fresh()->logo_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_an_untouched_file_input_keeps_the_image_it_already_has(): void
    {
        $this->put(route('admin.businesses.update', $this->business), [
            'name' => $this->business->name,
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $path = $this->business->fresh()->logo_path;

        // A file input submits nothing when nobody touches it, and editing the
        // phone number must not silently drop the logo.
        $this->put(route('admin.businesses.update', $this->business), [
            'name' => $this->business->name,
            'mobile' => '9825044551',
        ])->assertSessionHasNoErrors();

        $this->assertSame($path, $this->business->fresh()->logo_path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_ticking_remove_clears_the_image_and_deletes_the_file(): void
    {
        $this->put(route('admin.businesses.update', $this->business), [
            'name' => $this->business->name,
            'signature' => UploadedFile::fake()->image('sign.png'),
        ]);

        $path = $this->business->fresh()->signature_path;

        $this->put(route('admin.businesses.update', $this->business), [
            'name' => $this->business->name,
            'remove_signature' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertNull($this->business->fresh()->signature_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_file_that_is_not_an_image_is_rejected(): void
    {
        $this->put(route('admin.businesses.update', $this->business), [
            'name' => $this->business->name,
            'logo' => UploadedFile::fake()->create('books.pdf', 40, 'application/pdf'),
        ])->assertSessionHasErrors('logo');

        $this->assertNull($this->business->fresh()->logo_path);
    }

    public function test_an_image_over_the_size_limit_is_rejected(): void
    {
        $this->put(route('admin.businesses.update', $this->business), [
            'name' => $this->business->name,
            'logo' => UploadedFile::fake()->image('huge.png')->size(3000),
        ])->assertSessionHasErrors('logo');
    }

    public function test_the_panel_serves_the_image_and_404s_when_there_is_none(): void
    {
        $this->get(route('admin.businesses.image', [$this->business, 'logo']))->assertNotFound();

        $this->put(route('admin.businesses.update', $this->business), [
            'name' => $this->business->name,
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $this->get(route('admin.businesses.image', [$this->business, 'logo']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_the_handset_can_fetch_it_and_is_told_where(): void
    {
        $this->uploadLogo();

        Sanctum::actingAs($this->admin);

        $this->getJson("/api/v1/businesses/{$this->business->uuid}")
            ->assertOk()
            ->assertJsonPath('data.logo_url', url("/api/v1/businesses/{$this->business->uuid}/image/logo"))
            ->assertJsonPath('data.signature_url', null);

        $this->get("/api/v1/businesses/{$this->business->uuid}/image/logo")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_another_owners_image_is_not_served(): void
    {
        $this->uploadLogo();

        // A signature is the owner's real signature, so these are served to a
        // token that owns the business and to nobody else.
        Sanctum::actingAs(User::factory()->create(['is_admin' => false]));

        $this->get("/api/v1/businesses/{$this->business->uuid}/image/logo")
            ->assertForbidden();
    }

    public function test_the_business_page_shows_the_logo_once_it_has_one(): void
    {
        $url = route('admin.businesses.image', [$this->business, 'logo']);

        $this->get(route('admin.businesses.show', $this->business))->assertOk()->assertDontSee($url);

        $this->uploadLogo();

        $this->get(route('admin.businesses.show', $this->business))->assertOk()->assertSee($url);
    }

    private function uploadLogo(): void
    {
        $this->put(route('admin.businesses.update', $this->business), [
            'name' => $this->business->name,
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertSessionHasNoErrors();
    }

    public function test_a_soft_deleted_business_keeps_its_files(): void
    {
        $this->put(route('admin.businesses.update', $this->business), [
            'name' => $this->business->name,
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $path = $this->business->fresh()->logo_path;

        $this->delete(route('admin.businesses.destroy', $this->business));

        // The row is recoverable, so the file it points at has to still be there.
        Storage::disk('public')->assertExists($path);
    }
}
