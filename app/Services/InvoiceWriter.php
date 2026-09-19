<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * The single write path for documents.
 *
 * Creating or editing a bill touches four tables and two cached columns, so it
 * all happens here, in one transaction, rather than being spread across
 * controllers. Anything that changes what a bill is worth ends by recomputing
 * its totals.
 */
class InvoiceWriter
{
    public function __construct(
        private NumberingService $numbering,
    ) {}

    /**
     * Raises a new document and freezes its number onto it.
     *
     * @param  array{doc_type?: string, customer_name: string, date?: mixed, lines?: array, taxes?: array, discount_type?: string, discount_value?: float, round_off?: float, notes?: string, uuid?: string, photo_path?: string, converted_from_uuid?: string}  $data
     */
    public function create(Business $business, array $data): Invoice
    {
        return DB::transaction(function () use ($business, $data) {
            $type = $data['doc_type'] ?? Invoice::TYPE_BILL;
            $date = isset($data['date']) ? \Carbon\CarbonImmutable::parse($data['date']) : \Carbon\CarbonImmutable::now();

            $reserved = $this->numbering->reserve($business, $type, $date);

            $invoice = $business->invoices()->create([
                'uuid' => $data['uuid'] ?? null,
                'doc_type' => $type,
                'bill_no' => $reserved['bill_no'],
                'bill_ref' => $reserved['bill_ref'],
                'customer_name' => trim($data['customer_name']),
                'customer_uuid' => $data['customer_uuid'] ?? null,
                'date' => $date->toDateString(),
                'discount_type' => $data['discount_type'] ?? Invoice::DISCOUNT_NONE,
                'discount_value' => $data['discount_value'] ?? 0,
                'round_off' => $data['round_off'] ?? 0,
                'notes' => $data['notes'] ?? '',
                'photo_path' => $data['photo_path'] ?? null,
                'converted_from_uuid' => $data['converted_from_uuid'] ?? null,
            ]);

            $this->writeChildren($invoice, $data);
            $this->syncCatalogues($business, $invoice, $data);
            $this->finalise($invoice);

            return $invoice->fresh(['lines', 'taxes', 'payments']);
        });
    }

    /**
     * Rewrites an existing document: header fields, plus lines and taxes
     * replaced wholesale.
     *
     * The number and the recorded payments are kept — editing a bill must not
     * renumber it or forget what has been paid.
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $invoice->fill(array_filter([
                'customer_name' => isset($data['customer_name']) ? trim($data['customer_name']) : null,
                'customer_uuid' => $data['customer_uuid'] ?? null,
                'date' => $data['date'] ?? null,
                'discount_type' => $data['discount_type'] ?? null,
                'notes' => $data['notes'] ?? null,
                'photo_path' => $data['photo_path'] ?? null,
            ], fn ($v) => $v !== null));

            // These two legitimately take zero, so they can't go through
            // array_filter with the rest.
            if (array_key_exists('discount_value', $data)) {
                $invoice->discount_value = $data['discount_value'];
            }
            if (array_key_exists('round_off', $data)) {
                $invoice->round_off = $data['round_off'];
            }

            $invoice->save();

            if (array_key_exists('lines', $data)) {
                $invoice->lines()->delete();
            }
            if (array_key_exists('taxes', $data)) {
                $invoice->taxes()->delete();
            }
            $this->writeChildren($invoice, $data);
            $this->syncCatalogues($invoice->business, $invoice, $data);
            $this->finalise($invoice);

            return $invoice->fresh(['lines', 'taxes', 'payments']);
        });
    }

    /**
     * Cancels a document without deleting it: the number stays used and the
     * row stays visible, which is what keeps a numbered series honest.
     */
    public function void(Invoice $invoice, string $reason): Invoice
    {
        $invoice->forceFill([
            'voided_at' => now(),
            'void_reason' => $reason,
        ])->save();

        return $invoice;
    }

    public function unvoid(Invoice $invoice): Invoice
    {
        $invoice->forceFill([
            'voided_at' => null,
            'void_reason' => '',
        ])->save();

        return $invoice;
    }

    public function addPayment(Invoice $invoice, array $data): Payment
    {
        return DB::transaction(function () use ($invoice, $data) {
            $payment = $invoice->payments()->create([
                'uuid' => $data['uuid'] ?? null,
                'date' => $data['date'] ?? now()->toDateString(),
                'amount' => $data['amount'],
                'mode' => $data['mode'] ?? 'cash',
                'note' => $data['note'] ?? '',
            ]);

            $invoice->load('payments');
            $invoice->recalculateTotals();

            return $payment;
        });
    }

    public function deletePayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $invoice = $payment->invoice;
            $payment->delete();

            $invoice->load('payments');
            $invoice->recalculateTotals();
        });
    }

    // ---------------- internals ----------------

    private function writeChildren(Invoice $invoice, array $data): void
    {
        foreach (array_values($data['lines'] ?? []) as $position => $line) {
            $invoice->lines()->create([
                'uuid' => $line['uuid'] ?? null,
                'particulars' => $line['particulars'],
                'quantity' => $line['quantity'] ?? 0,
                'rate' => $line['rate'] ?? 0,
                'position' => $line['position'] ?? $position,
            ]);
        }

        foreach ($data['taxes'] ?? [] as $tax) {
            $invoice->taxes()->create([
                'uuid' => $tax['uuid'] ?? null,
                'label' => $tax['label'],
                'percent' => $tax['percent'] ?? 0,
            ]);
        }
    }

    /**
     * Free-text entry on a bill grows the catalogues, exactly as typing a new
     * name into the form does on the handset — that is what turns it into a
     * suggestion next time.
     */
    private function syncCatalogues(Business $business, Invoice $invoice, array $data): void
    {
        $this->upsertCustomerIfNew($business, $invoice->customer_name);

        foreach ($data['lines'] ?? [] as $line) {
            $this->upsertItemIfNew($business, $line['particulars'] ?? '', (float) ($line['rate'] ?? 0));
        }
    }

    public function upsertCustomerIfNew(Business $business, string $name): ?Customer
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $existing = $business->customers()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        return $existing ?? $business->customers()->create(['name' => $name]);
    }

    public function upsertItemIfNew(Business $business, string $name, float $rate): ?Item
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $existing = $business->items()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        return $existing ?? $business->items()->create([
            'name' => $name,
            'default_rate' => $rate,
        ]);
    }

    /**
     * Recomputes the cached totals, then writes the words line from the total
     * the server actually arrived at — never from a figure the client sent, so
     * the words can't disagree with the number beside them.
     */
    private function finalise(Invoice $invoice): void
    {
        $invoice->load(['lines', 'taxes', 'payments']);
        $invoice->recalculateTotals();

        $invoice->forceFill([
            'amount_in_words' => NumberToWords::convert($invoice->computed_total),
        ])->saveQuietly();
    }
}
