<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A change that lost a last-write-wins comparison.
 *
 * Last write wins is the rule, but a discarded edit is never thrown away
 * silently — the losing payload is kept here so the admin can see exactly what
 * was overwritten, and put it back by hand if it mattered.
 */
class SyncConflict extends Model
{
    use HasFactory;

    protected $fillable = [
        'sync_log_id', 'user_id', 'model_type', 'uuid',
        'incoming', 'existing', 'resolution', 'reviewed',
    ];

    protected function casts(): array
    {
        return [
            'incoming' => 'array',
            'existing' => 'array',
            'reviewed' => 'boolean',
        ];
    }

    public function syncLog()
    {
        return $this->belongsTo(SyncLog::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnreviewed($query)
    {
        return $query->where('reviewed', false);
    }
}
