<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    use HasFactory;

    protected $fillable = ['device_id', 'user_id', 'direction', 'counts', 'conflicts', 'notes'];

    protected function casts(): array
    {
        return [
            'counts' => 'array',
            'conflicts' => 'integer',
        ];
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function conflictRows()
    {
        return $this->hasMany(SyncConflict::class);
    }

    /** Total rows moved, for the one-line summary in the admin list. */
    public function getRowCountAttribute(): int
    {
        return (int) collect($this->counts ?? [])->sum();
    }
}
