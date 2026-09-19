<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Gives a model a client-generatable UUID.
 *
 * The handset creates rows while offline and has no access to the server's
 * auto-increment ids, so the UUID — not the id — is the identity that travels
 * over sync. The server fills one in when a row is created here instead.
 */
trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeUuid($query, string $uuid)
    {
        return $query->where('uuid', $uuid);
    }

    /** Rows changed since a sync cursor, deletions included. */
    public function scopeChangedSince($query, ?\DateTimeInterface $since)
    {
        return $since ? $query->where('updated_at', '>', $since) : $query;
    }
}
