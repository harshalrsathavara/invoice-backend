<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
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

        return response()->json([
            'sent' => true,
            'expires_in_minutes' => (int) config('otp.ttl_minutes'),

            // Test mode only: lets the app prefill the field while there is
            // no SMS to read it from.
            'debug_code' => config('otp.debug') ? $issued['code'] : null,
            'debug' => (bool) config('otp.debug'),
        ]);
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
                'email' => 'otp-'.Str::lower(Str::random(12)).'@invoice.local',
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
                'email' => $user->email,
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
