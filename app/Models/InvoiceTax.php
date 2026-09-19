<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class InvoiceTax extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = ['uuid', 'label', 'percent'];

    protected function casts(): array
    {
        return ['percent' => 'float'];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * True when a bill carries CGST alongside SGST (or UTGST) at *different*
     * rates. Each is normally half the headline rate — 18% GST is CGST 9% +
     * SGST 9% — so a mismatch is nearly always a typo.
     *
     * A warning only: the person raising the bill decides what is correct, and
     * the API never rejects a document over it.
     */
    public static function hasMismatchedSplit(Collection $taxes): bool
    {
        $rateOf = function (string $label) use ($taxes): ?float {
            foreach ($taxes as $tax) {
                if (strtoupper($tax->label) === $label) {
                    return (float) $tax->percent;
                }
            }

            return null;
        };

        $cgst = $rateOf('CGST');
        if ($cgst === null) {
            return false;
        }

        foreach (['SGST', 'UTGST'] as $counterpart) {
            $other = $rateOf($counterpart);
            if ($other !== null && abs($other - $cgst) > 0.0001) {
                return true;
            }
        }

        return false;
    }
}
