<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Domain of the stand-in address given to an account that registered from
     * a handset. The users table needs an email, and needs it unique, but a
     * phone number signing up has never given one — so the server invents an
     * address that nobody can write to.
     *
     * It is a placeholder, not an email, and must never be offered as one:
     * prefilled into a form, printed on a bill, or shown as the way to reach
     * the owner.
     */
    public const PLACEHOLDER_EMAIL_DOMAIN = '@invoice.local';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /** True when this account has no email of its own and signs in by phone. */
    public function hasPlaceholderEmail(): bool
    {
        return str_ends_with(mb_strtolower((string) $this->email), self::PLACEHOLDER_EMAIL_DOMAIN);
    }

    /** The address worth showing or offering, or null when there is only a stand-in. */
    public function realEmail(): ?string
    {
        return $this->hasPlaceholderEmail() ? null : $this->email;
    }

    public function businesses()
    {
        return $this->hasMany(Business::class);
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function syncLogs()
    {
        return $this->hasMany(SyncLog::class);
    }

    public function syncConflicts()
    {
        return $this->hasMany(SyncConflict::class);
    }

    /** Every invoice belonging to any of this owner's businesses. */
    public function invoices()
    {
        return $this->hasManyThrough(Invoice::class, Business::class);
    }
}
