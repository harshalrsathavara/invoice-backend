<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One sign-in code sent to one phone number.
 *
 * Rows are kept rather than deleted on use, so a code cannot be replayed and
 * so a burst of requests for the same number is visible.
 */
class OtpCode extends Model
{
    protected $fillable = ['phone', 'code_hash', 'expires_at', 'attempts', 'consumed_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null
            && $this->expires_at->isFuture()
            && $this->attempts < config('otp.max_attempts');
    }
}
