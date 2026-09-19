<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Models\Concerns\NotNullDefaults;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use HasFactory, HasUuid, NotNullDefaults, SoftDeletes;

    /** Columns the schema declares NOT NULL, and what a blank one stores. */
    protected array $notNullDefaults = [
        'tagline' => '', 'mobile' => '', 'jurisdiction_text' => '',
        'gst_number' => '', 'email' => '', 'upi_id' => '',
        'bill_prefix' => '', 'bill_fy' => '',
    ];

    protected $fillable = [
        'uuid', 'name', 'tagline', 'address', 'mobile', 'jurisdiction_text',
        'gst_number', 'email', 'bank_details', 'logo_path', 'signature_path',
        'upi_id', 'next_bill_no', 'next_quote_no', 'next_challan_no',
        'bill_prefix', 'fy_reset', 'bill_fy', 'terms_text',
    ];

    protected function casts(): array
    {
        return [
            'fy_reset' => 'boolean',
            'next_bill_no' => 'integer',
            'next_quote_no' => 'integer',
            'next_challan_no' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /** Real, uncancelled bills — the only documents that count as money owed. */
    public function bills()
    {
        return $this->hasMany(Invoice::class)
            ->where('doc_type', Invoice::TYPE_BILL)
            ->whereNull('voided_at');
    }

    /**
     * The financial year a date falls in, as '26-27'. April to March.
     */
    public static function financialYearOf(\DateTimeInterface $date): string
    {
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');
        $startYear = $month >= 4 ? $year : $year - 1;
        $end = ($startYear + 1) % 100;

        return sprintf('%02d-%02d', $startYear % 100, $end);
    }

    /**
     * How a number is shown and printed: 'RS/26-27/007', 'RS/QT/003', or a
     * plain '7' when nothing is configured.
     *
     * Composed once, when the document is saved, and stored on it — changing
     * these settings later must not renumber documents already issued.
     */
    public function formatBillNo(int $billNo, ?\DateTimeInterface $on = null, string $type = Invoice::TYPE_BILL): string
    {
        $marker = match ($type) {
            Invoice::TYPE_QUOTATION => 'QT',
            Invoice::TYPE_CHALLAN => 'DC',
            default => null,
        };

        $parts = [];
        if ($this->bill_prefix !== '' && $this->bill_prefix !== null) {
            $parts[] = $this->bill_prefix;
        }
        if ($marker !== null) {
            $parts[] = $marker;
        }
        if ($this->fy_reset) {
            $parts[] = static::financialYearOf($on ?? now());
        }
        $parts[] = str_pad((string) $billNo, 3, '0', STR_PAD_LEFT);

        return count($parts) === 1 ? (string) $billNo : implode('/', $parts);
    }

    /** Which counter column a document kind draws from. */
    public static function counterColumnFor(string $type): string
    {
        return match ($type) {
            Invoice::TYPE_QUOTATION => 'next_quote_no',
            Invoice::TYPE_CHALLAN => 'next_challan_no',
            default => 'next_bill_no',
        };
    }
}
