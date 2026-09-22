<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OtpCodeMail;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Signing in with a phone number and a one-time code.
 *
 * The handset asks for a code, then exchanges it for the same Sanctum device
 * token that email sign-in returns — so sync, and every other authenticated
 * route, works identically whichever way the owner signed in.
 */
class OtpController extends Controller
{
    public function __construct(
        private OtpService $otp,
    ) {}

    /** Sends a code to the number, and in test mode hands it straight back. */
    public function request(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'min:6', 'max:20'],
        ]);

        $phone = $this->otp->normalise($data['phone']);
        $user = User::where('phone', $phone)->first();

        // Whether the number is known is not disclosed: replying the same way
        // either way stops this being used to find out who has an account.
        if (! $user && ! config('otp.allow_registration')) {
            return response()->json([
                'sent' => true,
                'expires_in_minutes' => (int) config('otp.ttl_minutes'),
            ]);
        }

        $issued = $this->otp->issue($phone);
        $sentTo = $this->deliver($user, $issued['code']);

        return response()->json([
            'sent' => true,
            'expires_in_minutes' => (int) config('otp.ttl_minutes'),

            // Where it went, masked, so the app can say "check h••••@gmail.com"
            // rather than leaving the owner guessing. Null when there was
            // nowhere to send it.
            'sent_to' => $sentTo,

            // Test mode only: lets the app prefill the field while delivery
            // is being set up.
            'debug_code' => config('otp.debug') ? $issued['code'] : null,
            'debug' => (bool) config('otp.debug'),
        ]);
    }

    /**
     * Sends the code to the account's email address.
     *
     * Failure is swallowed on purpose: a mail server that is down must not
     * turn into a 500 that tells an attacker the number exists, and in test
     * mode the code comes back in the response anyway. The failure is logged
     * so it can be found when someone says they never got it.
     *
     * A number registering for the first time has no account yet, and so
     * nowhere to send to — in that case the code only comes back in the
     * response, which is why registration is for test mode.
     *
     * @return string|null the masked address it went to
     */
    private function deliver(?User $user, string $code): ?string
    {
        if (! $user || config('otp.channel') !== 'email' || ! $user->email) {
            return null;
        }

        try {
            Mail::to($user->email)->send(new OtpCodeMail(
                code: $code,
                expiresInMinutes: (int) config('otp.ttl_minutes'),
                name: $user->name,
            ));
        } catch (\Throwable $e) {
            Log::error('OTP email failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return $this->mask($user->email);
    }

    /** h••••@gmail.com — enough to recognise, not enough to harvest. */
    private function mask(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($name, 0, 1);

        return $visible.str_repeat('•', max(mb_strlen($name) - 1, 1)).'@'.$domain;
    }

    /** Exchanges a valid code for a device token. */
    public function verify(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'min:6', 'max:20'],
            'code' => ['required', 'string', 'min:4', 'max:8'],
            'device_name' => ['required', 'string', 'max:120'],
            'device_uuid' => ['nullable', 'uuid'],
            'platform' => ['nullable', 'string', 'max:32'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ]);

        $phone = $this->otp->normalise($data['phone']);

        if (! $this->otp->verify($phone, $data['code'])) {
            throw ValidationException::withMessages([
                'code' => ['That code is wrong or has expired.'],
            ]);
        }

        $user = User::where('phone', $phone)->first();

        if (! $user) {
            if (! config('otp.allow_registration')) {
                throw ValidationException::withMessages([
                    'phone' => ['This number is not set up on the server.'],
                ]);
            }

            // A fresh owner with an empty set of books. The email is a
            // placeholder: the column is unique and required, and this
            // account signs in by phone.
            $user = User::create([
                'name' => 'Owner '.$phone,
                'email' => 'otp-'.Str::lower(Str::random(12)).User::PLACEHOLDER_EMAIL_DOMAIN,
                'phone' => $phone,
                'password' => Str::random(40),
            ]);
        }

        $device = $user->devices()->firstOrNew(['uuid' => $data['device_uuid'] ?? null]);
        $device->fill([
            'name' => $data['device_name'],
            'platform' => $data['platform'] ?? 'android',
            'app_version' => $data['app_version'] ?? null,
        ]);
        $device->last_seen_at = now();
        $user->devices()->save($device);

        // One token per device, as with email sign-in: signing in again
        // replaces the old token rather than leaving two that both work.
        $user->tokens()->where('name', 'device:'.$device->uuid)->delete();
        $token = $user->createToken('device:'.$device->uuid)->plainTextToken;

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return response()->json([
            'token' => $token,
            'user' => [
                'name' => $user->name,
                // Null rather than the stand-in address, so the handset never
                // offers it as the owner's email — it was being prefilled
                // into the business profile and printed on bills.
                'email' => $user->realEmail(),
                'phone' => $user->phone,
                'is_admin' => $user->is_admin,
            ],
            'device' => [
                'uuid' => $device->uuid,
                'name' => $device->name,
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
