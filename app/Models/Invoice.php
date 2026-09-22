<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Models\Concerns\NotNullDefaults;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A bill, quotation or delivery challan.
 *
 * All three share this table because on paper they are the same thing — a
 * customer, a list of work and a total. Only bills are money owed: quotations
 * and challans are excluded from every total, ledger and report.
 *
 * All money arithmetic lives here as derived accessors, never in SQL and never
 * re-derived in a controller, so the server agrees with the handset to the
 * paisa. `total` and `paid_amount` are cached columns recomputed on write.
 */
class Invoice extends Model
{
    use HasFactory, HasUuid, NotNullDefaults, SoftDeletes;

    public const TYPE_BILL = 'bill';
    public const TYPE_QUOTATION = 'quotation';
    public const TYPE_CHALLAN = 'challan';

    public const DISCOUNT_NONE = 'none';
    public const DISCOUNT_PERCENT = 'percent';
    public const DISCOUNT_AMOUNT = 'amount';

    /** Anything at or below this is treated as settled, as on the handset. */
    public const EPSILON = 0.001;

    /** Columns the schema declares NOT NULL, and what a blank one stores. */
    protected array $notNullDefaults = [
        'bill_ref' => '', 'amount_in_words' => '', 'void_reason' => '',
        'doc_type' => self::TYPE_BILL, 'discount_type' => self::DISCOUNT_NONE,
        'discount_value' => 0, 'round_off' => 0,
    ];

    protected $fillable = [
        'uuid', 'doc_type', 'bill_no', 'bill_ref', 'customer_name',
        'customer_uuid', 'date', 'amount_in_words', 'discount_type',
        'discount_value', 'round_off', 'notes', 'voided_at', 'void_reason',
        'converted_from_uuid', 'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'voided_at' => 'datetime',
            'discount_value' => 'float',
            'round_off' => 'float',
            'total' => 'float',
            'paid_amount' => 'float',
            'bill_no' => 'integer',
        ];
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

    public function lines()
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('position');
    }

    public function taxes()
    {
        return $this->hasMany(InvoiceTax::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class)->orderBy('date');
    }

    // ---------------- scopes ----------------

    /** A real, uncancelled bill — the only kind that counts as money owed. */
    public function scopeLiveBills($query)
    {
        return $query->where('doc_type', self::TYPE_BILL)->whereNull('voided_at');
    }

    public function scopeBetween($query, ?\DateTimeInterface $from, ?\DateTimeInterface $to)
    {
        if ($from) {
            $query->whereDate('date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('date', '<=', $to);
        }

        return $query;
    }

    // ---------------- money ----------------

    /** Sum of the line items, before discount and tax. */
    public function getSubtotalAttribute(): float
    {
        return round($this->lines->sum(fn (InvoiceLine $l) => $l->amount), 2);
    }

    /**
     * Never exceeds the subtotal, so a mistyped discount can't make the bill
     * negative.
     */
    public function getDiscountAmountAttribute(): float
    {
        $subtotal = $this->subtotal;

        $raw = match ($this->discount_type) {
            self::DISCOUNT_PERCENT => $subtotal * (float) $this->discount_value / 100,
            self::DISCOUNT_AMOUNT => (float) $this->discount_value,
            default => 0.0,
        };

        return round(max(0.0, min($raw, $subtotal)), 2);
    }

    /**
     * The amount tax is charged on: subtotal less any discount. Discount
     * before tax is the standard order on an Indian invoice.
     */
    public function getTaxableAmountAttribute(): float
    {
        return round($this->subtotal - $this->discount_amount, 2);
    }

    public function taxAmountFor(InvoiceTax $tax): float
    {
        return round($this->taxable_amount * (float) $tax->percent / 100, 2);
    }

    public function getTotalTaxAttribute(): float
    {
        return round($this->taxes->sum(fn (InvoiceTax $t) => $this->taxAmountFor($t)), 2);
    }

    /** Total before the round-off adjustment. */
    public function getTotalBeforeRoundingAttribute(): float
    {
        return round($this->taxable_amount + $this->total_tax, 2);
    }

    /** Grand total actually payable. */
    public function getComputedTotalAttribute(): float
    {
        return round($this->total_before_rounding + (float) $this->round_off, 2);
    }

    public function getBalanceAttribute(): float
    {
        return round((float) $this->total - (float) $this->paid_amount, 2);
    }

    public function getIsVoidedAttribute(): bool
    {
        return $this->voided_at !== null;
    }

    /** What to show wherever the document is named. */
    public function getDisplayNoAttribute(): string
    {
        return $this->bill_ref !== '' && $this->bill_ref !== null
            ? $this->bill_ref
            : (string) $this->bill_no;
    }

    public function getStatusAttribute(): string
    {
        if ($this->is_voided) {
            return 'Cancelled';
        }
        if ($this->balance <= self::EPSILON) {
            return 'Paid';
        }
        if ((float) $this->paid_amount > 0) {
            return 'Partial';
        }

        return 'Unpaid';
    }

    /** The adjustment that would land the total on a whole rupee. */
    public static function roundingDeltaFor(float $amount): float
    {
        return round(round($amount) - $amount, 2);
    }

    /**
     * Rewrites the cached `total` and `paid_amount` columns from the rows that
     * actually determine them.
     *
     * Every list, ledger and report reads those two columns; recomputing here
     * keeps them correct without rewriting each query to join against lines,
     * taxes and payments.
     */
    public function recalculateTotals(): void
    {
        $this->loadMissing(['lines', 'taxes', 'payments']);

        // Deliberately does not touch `updated_at`. These two columns are
        // derived, not edited: the rows that determine them — lines, taxes,
        // payments — carry their own timestamps and sync on their own, and the
        // handset recomputes its copy from those rows exactly as this does.
        //
        // Bumping the invoice here would also make it look edited at the
        // moment a payment arrived, so a genuine content edit queued earlier on
        // the phone would then lose last-write-wins and be filed as a conflict.
        $timestamps = $this->timestamps;
        $this->timestamps = false;

        $this->forceFill([
            'total' => $this->computed_total,
            'paid_amount' => round($this->payments->sum('amount'), 2),
        ])->saveQuietly();

        $this->timestamps = $timestamps;
    }
}
