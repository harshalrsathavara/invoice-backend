<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Models\Concerns\NotNullDefaults;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One payment received against one bill.
 *
 * The invoice keeps `paid_amount` as a running total, but it is derived from
 * these rows rather than typed in directly — so the app can say not just how
 * much came in but when it arrived and how.
 */
class Payment extends Model
{
    use HasFactory, HasUuid, NotNullDefaults, SoftDeletes;

    public const MODES = ['cash', 'upi', 'cheque', 'bank', 'other'];

    /** Columns the schema declares NOT NULL, and what a blank one stores. */
    protected array $notNullDefaults = [
        'mode' => 'cash', 'note' => '',
    ];

    protected $fillable = ['uuid', 'date', 'amount', 'mode', 'note'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'float',
        ];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
