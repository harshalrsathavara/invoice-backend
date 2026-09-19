<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A handset that syncs.
 *
 * Registered on first login so the admin can see which phones hold data, when
 * each last synced, and revoke one that has been lost.
 */
class Device extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = ['uuid', 'name', 'platform', 'app_version'];

    protected function casts(): array
    {
        return [
            'last_pulled_at' => 'datetime',
            'last_pushed_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function syncLogs()
    {
        return $this->hasMany(SyncLog::class);
    }

    public function touchSeen(): void
    {
        $this->forceFill(['last_seen_at' => now()])->saveQuietly();
    }

    /** Never synced, or not for a fortnight — worth the admin's attention. */
    public function getIsStaleAttribute(): bool
    {
        return $this->last_pushed_at === null
            || $this->last_pushed_at->lt(now()->subDays(14));
    }
}
