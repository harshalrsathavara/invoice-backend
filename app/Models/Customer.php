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
    ];

    protected $fillable = ['uuid', 'name', 'phone', 'address', 'gst_number'];

    public function business()
    {
        return $this->belongsTo(Business::class);
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
