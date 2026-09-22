<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Models\Concerns\NotNullDefaults;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, HasUuid, NotNullDefaults, SoftDeletes;

    /** Columns the schema declares NOT NULL, and what a blank one stores. */
    protected array $notNullDefaults = [
        'phone' => '', 'gst_number' => '',
        'city' => '', 'state' => '', 'post_code' => '', 'email' => '',
    ];

    protected $fillable = [
        'uuid', 'name', 'phone', 'address', 'gst_number',
        'city', 'state', 'post_code', 'email',
    ];

    /**
     * The whole postal address on one line, skipping whatever is blank.
     *
     * `address` holds only the street line since the address was split, so
     * anywhere that wants the lot — the panel, a statement — joins it back up
     * here rather than each doing its own version.
     */
    public function getFullAddressAttribute(): string
    {
        return collect([$this->address, $this->city, $this->state, $this->post_code])
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->implode(', ');
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

    /**
     * Invoices are matched by name, not foreign key — the app stores the
     * customer as text on the bill so a bill keeps the name it was raised
     * under. Renames are applied across both in one transaction.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'business_id', 'business_id')
            ->whereColumn('invoices.customer_name', 'customers.name');
    }
}
