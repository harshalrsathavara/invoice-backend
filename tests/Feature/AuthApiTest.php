<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_handset_signs_in_and_registers_itself(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-pass')]);
        $deviceUuid = (string) Str::uuid();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-pass',
            'device_name' => 'OnePlus Nord',
            'device_uuid' => $deviceUuid,
            'platform' => 'android',
            'app_version' => '1.0.0',
        ])->assertOk()->assertJsonStructure(['token', 'user', 'device', 'server_time']);

        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas('devices', ['uuid' => $deviceUuid, 'user_id' => $user->id, 'name' => 'OnePlus Nord']);
    }

    public function test_signing_in_again_on_the_same_handset_replaces_its_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-pass')]);
        $deviceUuid = (string) Str::uuid();

        $payload = [
            'email' => $user->email,
            'password' => 'secret-pass',
            'device_name' => 'OnePlus Nord',
            'device_uuid' => $deviceUuid,
        ];

        $first = $this->postJson('/api/v1/auth/login', $payload)->json('token');
        $second = $this->postJson('/api/v1/auth/login', $payload)->json('token');

        $this->assertNotSame($first, $second);
        // One device, one valid credential.
        $this->assertSame(1, $user->tokens()->count());
        $this->assertSame(1, $user->devices()->count());
    }

    public function test_bad_credentials_are_refused(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-pass')]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong',
            'device_name' => 'OnePlus Nord',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_the_token_reaches_protected_routes(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-pass')]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-pass',
            'device_name' => 'OnePlus Nord',
        ])->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_signing_out_invalidates_the_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-pass')]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-pass',
            'device_name' => 'OnePlus Nord',
        ])->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertSame(0, $user->tokens()->count());

        // The guard caches the resolved user for the lifetime of the test, so
        // it has to be dropped before the next request re-authenticates.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}
