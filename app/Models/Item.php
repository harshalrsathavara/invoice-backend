<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Models\Concerns\NotNullDefaults;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, HasUuid, NotNullDefaults, SoftDeletes;

    /** Columns the schema declares NOT NULL, and what a blank one stores. */
    protected array $notNullDefaults = [
        'default_rate' => 0, 'hsn_code' => '',
    ];

    protected $fillable = ['uuid', 'name', 'default_rate', 'hsn_code'];

    protected function casts(): array
    {
        return ['default_rate' => 'float'];
    }

    /**
     * Deleting a business marks it and everything under it deleted together,
     * so a deleted row's parent is deleted too. The panel is the one place
     * those rows can still be read, and reading one with no business to name
     * it was an error page rather than a record.
     */
    public function business()
    {
        return $this->belongsTo(Business::class)->withTrashed();
    }
}
