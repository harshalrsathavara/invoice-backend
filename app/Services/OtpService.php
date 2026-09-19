<?php

namespace App\Services;

use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Sign-in codes for a phone number.
 *
 * There is no SMS provider yet. In test mode the code is fixed and returned
 * to the caller so the app can prefill it; in real mode a random code is
 * generated and logged, and sending it is the piece still to be built.
 */
class OtpService
{
    /**
     * Issues a code for [$phone], replacing any code still outstanding.
     *
     * @return array{code: string, expires_at: \Illuminate\Support\Carbon}
     */
    public function issue(string $phone): array
    {
        // An earlier code must stop working the moment a new one is asked
        // for, or two live codes double the guessing surface.
        OtpCode::where('phone', $phone)->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = $this->generateCode();

        $record = OtpCode::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(config('otp.ttl_minutes')),
        ]);

        if (! config('otp.debug')) {
            // Until delivery exists, the log is the only way the code gets
            // out. Never logged in test mode, where it is in the response.
            Log::info('OTP issued', ['phone' => $phone, 'expires_at' => $record->expires_at]);
        }

        return ['code' => $code, 'expires_at' => $record->expires_at];
    }

    /**
     * True when [$code] is the live code for [$phone]. A wrong guess is
     * counted, and the code is burnt once it is used.
     */
    public function verify(string $phone, string $code): bool
    {
        $record = OtpCode::where('phone', $phone)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $record || ! $record->isUsable()) {
            return false;
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            return false;
        }

        $record->forceFill(['consumed_at' => now()])->save();

        return true;
    }

    /**
     * Digits only, and never trimmed of a leading zero — the number is stored
     * exactly as it is matched later.
     */
    public function normalise(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9+]/', '', $phone) ?? '';

        return str_starts_with($cleaned, '+')
            ? '+'.preg_replace('/[^0-9]/', '', substr($cleaned, 1))
            : $cleaned;
    }

    private function generateCode(): string
    {
        if (config('otp.debug')) {
            return config('otp.debug_code');
        }

        $length = (int) config('otp.length');

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}
