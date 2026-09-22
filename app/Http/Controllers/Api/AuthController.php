<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Signs a handset in and returns a token scoped to that device.
     *
     * The device is registered on the way through, so the admin can see which
     * phones hold data and revoke one that has been lost.
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:120'],
            'device_uuid' => ['nullable', 'uuid'],
            'platform' => ['nullable', 'string', 'max:32'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
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

        // One token per device: signing in again on the same handset replaces
        // the old token rather than piling up valid credentials.
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

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'name' => $user->name,
                // An account that registered by phone has a stand-in address,
                // never a real one. Same rule as sign-in: it is not an email
                // and is not offered as one.
                'email' => $user->realEmail(),
                'phone' => $user->phone,
                'is_admin' => $user->is_admin,
            ],
            'businesses' => $user->businesses()->pluck('uuid'),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Signed out.']);
    }
}
