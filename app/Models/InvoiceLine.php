<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceLine extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = ['uuid', 'particulars', 'quantity', 'rate', 'gst_rate', 'position'];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'rate' => 'float',
            'gst_rate' => 'float',
            'position' => 'integer',
        ];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /** Derived, never stored — exactly as on the handset. */
    public function getAmountAttribute(): float
    {
        return round((float) $this->quantity * (float) $this->rate, 2);
    }
}
