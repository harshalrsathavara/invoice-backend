<?php

namespace Tests\Feature;

use App\Mail\OtpCodeMail;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Signing in with a phone number and a one-time code.
 *
 * The handset has no SMS to read while this is being built, so test mode
 * fixes the code and returns it. These tests pin both that behaviour and the
 * rules that must survive it being switched off.
 */
class OtpAuthTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+919876543210';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('otp.debug', true);
        config()->set('otp.debug_code', '123456');
        config()->set('otp.allow_registration', false);
        config()->set('otp.channel', 'email');
    }

    private function owner(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'phone' => self::PHONE,
            'password' => Hash::make('secret-password'),
            'is_admin' => true,
        ], $attributes));
    }

    public function test_requesting_a_code_returns_it_in_test_mode(): void
    {
        $this->owner();

        $this->postJson('/api/v1/auth/otp/request', ['phone' => self::PHONE])
            ->assertOk()
            ->assertJson(['sent' => true, 'debug' => true, 'debug_code' => '123456']);

        $this->assertDatabaseCount('otp_codes', 1);
    }

    public function test_a_code_signs_the_handset_in_and_returns_a_working_token(): void
    {
        $user = $this->owner();

        $this->postJson('/api/v1/auth/otp/request', ['phone' => self::PHONE]);

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => '123456',
            'device_name' => 'Nord',
        ])->assertOk();

        $token = $response->json('token');
        $this->assertNotEmpty($token);
        $this->assertSame($user->id, $user->fresh()->id);

        // The token must work on the routes the app actually uses.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.phone', self::PHONE);
    }

    public function test_a_number_typed_with_spaces_still_finds_its_account(): void
    {
        $this->owner();

        $this->postJson('/api/v1/auth/otp/request', ['phone' => '+91 98765 43210'])
            ->assertOk();

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+91 98765 43210',
            'code' => '123456',
            'device_name' => 'Nord',
        ])->assertOk();
    }

    public function test_a_wrong_code_is_refused_and_counted(): void
    {
        $this->owner();
        $this->postJson('/api/v1/auth/otp/request', ['phone' => self::PHONE]);

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => '000000',
            'device_name' => 'Nord',
        ])->assertStatus(422);

        $this->assertSame(1, OtpCode::first()->attempts);
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        $this->owner();
        $this->postJson('/api/v1/auth/otp/request', ['phone' => self::PHONE]);

        $payload = [
            'phone' => self::PHONE,
            'code' => '123456',
            'device_name' => 'Nord',
        ];

        $this->postJson('/api/v1/auth/otp/verify', $payload)->assertOk();
        $this->postJson('/api/v1/auth/otp/verify', $payload)->assertStatus(422);
    }

    public function test_an_expired_code_is_refused(): void
    {
        $this->owner();
        $this->postJson('/api/v1/auth/otp/request', ['phone' => self::PHONE]);

        OtpCode::first()->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => '123456',
            'device_name' => 'Nord',
        ])->assertStatus(422);
    }

    public function test_requesting_a_new_code_kills_the_previous_one(): void
    {
        $this->owner();

        $this->postJson('/api/v1/auth/otp/request', ['phone' => self::PHONE]);
        $first = OtpCode::first();

        $this->postJson('/api/v1/auth/otp/request', ['phone' => self::PHONE]);

        $this->assertNotNull($first->fresh()->consumed_at);
        $this->assertDatabaseCount('otp_codes', 2);
    }

    public function test_an_unknown_number_gets_no_code_and_cannot_sign_in(): void
    {
        // The reply looks the same as for a known number, so the endpoint
        // cannot be used to find out who has an account.
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '+919000000000'])
            ->assertOk()
            ->assertJson(['sent' => true])
            ->assertJsonMissing(['debug_code' => '123456']);

        $this->assertDatabaseCount('otp_codes', 0);

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+919000000000',
            'code' => '123456',
            'device_name' => 'Nord',
        ])->assertStatus(422);
    }

    public function test_an_unknown_number_may_register_when_that_is_allowed(): void
    {
        config()->set('otp.allow_registration', true);

        $this->postJson('/api/v1/auth/otp/request', ['phone' => '+919000000000'])
            ->assertOk();

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+919000000000',
            'code' => '123456',
            'device_name' => 'Nord',
        ])->assertOk();

        $this->assertDatabaseHas('users', ['phone' => '+919000000000']);
    }

    public function test_the_code_is_emailed_to_the_account(): void
    {
        Mail::fake();
        $user = $this->owner();

        $response = $this->postJson('/api/v1/auth/otp/request', ['phone' => self::PHONE])
            ->assertOk();

        Mail::assertSent(OtpCodeMail::class, function (OtpCodeMail $mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->code === '123456';
        });

        // Masked, so the owner can tell which address to check without the
        // endpoint handing out a full address to anyone who asks.
        $this->assertSame('o••••@example.com', $response->json('sent_to'));
    }

    public function test_an_unknown_number_is_sent_nothing(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/otp/request', ['phone' => '+919000000000'])
            ->assertOk()
            ->assertJsonPath('sent_to', null);

        Mail::assertNothingSent();
    }

    public function test_a_mail_failure_does_not_break_sign_in(): void
    {
        // The code is still issued and still works; only the delivery fails.
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));
        $this->owner();

        $this->postJson('/api/v1/auth/otp/request', ['phone' => self::PHONE])
            ->assertOk()
            ->assertJsonPath('sent_to', null)
            ->assertJsonPath('debug_code', '123456');

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => '123456',
            'device_name' => 'Nord',
        ])->assertOk();
    }

    public function test_test_mode_lets_any_number_sign_in(): void
    {
        // What the default is, not what the tests set: while the code is
        // fixed, any number typed into the app should get in.
        config()->set('otp.allow_registration', (bool) config('otp.debug'));

        $this->postJson('/api/v1/auth/otp/request', ['phone' => '+919111111111'])
            ->assertOk()
            ->assertJsonPath('debug_code', '123456');

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+919111111111',
            'code' => '123456',
            'device_name' => 'Any handset',
        ])->assertOk();
    }

    public function test_test_mode_off_closes_registration_too(): void
    {
        config()->set('otp.debug', false);
        config()->set('otp.allow_registration', (bool) config('otp.debug'));

        $this->postJson('/api/v1/auth/otp/request', ['phone' => '+919111111111'])
            ->assertOk();

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+919111111111',
            'code' => '123456',
            'device_name' => 'Any handset',
        ])->assertStatus(422);
    }

    public function test_the_code_is_not_returned_when_test_mode_is_off(): void
    {
        config()->set('otp.debug', false);
        $this->owner();

        $response = $this->postJson('/api/v1/auth/otp/request', ['phone' => self::PHONE])
            ->assertOk()
            ->assertJson(['sent' => true, 'debug' => false]);

        $this->assertNull($response->json('debug_code'));

        // And the fixed test code must not open the door.
        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => '123456',
            'device_name' => 'Nord',
        ])->assertStatus(422);
    }
}
