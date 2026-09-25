<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\SyncConflict;
use App\Models\SyncLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Two-way sync between a handset and the server.
 *
 * The handset stays the working store — it has to keep raising bills with no
 * signal — so this is a mirror, not a source of truth. Rows are identified by
 * the client-generated UUID, never by the server's auto-increment id.
 *
 * The conflict rule is last-write-wins on `updated_at`, which is the only rule
 * that can be applied without a human present. A losing change is written to
 * `sync_conflicts` rather than dropped, so the admin can see what was
 * overwritten and put it back.
 *
 * The pull cursor is deliberately inclusive (`updated_at >= since`). MySQL
 * timestamps are second-precision, so an exclusive cursor can skip a row
 * written in the same second as the last sync. Re-sending a handful of rows is
 * harmless because every apply is an idempotent upsert keyed on the UUID;
 * silently losing one is not.
 */
class SyncService
{
    /** Order matters: parents land before the rows that point at them. */
    private const PUSH_ORDER = ['businesses', 'customers', 'items', 'invoices', 'payments'];

    public function __construct(
        private InvoiceWriter $writer,
    ) {}

    /**
     * Applies a batch of client changes.
     *
     * @return array{counts: array<string,int>, conflicts: int, server_time: string}
     */
    public function push(User $user, ?Device $device, array $changes): array
    {
        $counts = [];
        $conflicts = [];

        DB::transaction(function () use ($user, $changes, &$counts, &$conflicts) {
            foreach (self::PUSH_ORDER as $table) {
                $rows = $changes[$table] ?? [];
                if (! is_array($rows) || $rows === []) {
                    continue;
                }

                $applied = 0;
                foreach ($rows as $row) {
                    if (! is_array($row) || empty($row['uuid'])) {
                        continue;
                    }

                    $result = match ($table) {
                        'businesses' => $this->applyBusiness($user, $row),
                        'customers' => $this->applyScoped($user, Customer::class, $row, [
                            'name', 'phone', 'address', 'gst_number',
                            'city', 'state', 'post_code', 'email', 'is_active',
                        ]),
                        'items' => $this->applyScoped($user, Item::class, $row, ['name', 'default_rate', 'hsn_code']),
                        'invoices' => $this->applyInvoice($user, $row),
                        'payments' => $this->applyPayment($user, $row),
                        default => null,
                    };

                    if ($result === 'applied') {
                        $applied++;
                    } elseif ($result instanceof SyncConflict) {
                        $conflicts[] = $result;
                    }
                }

                $counts[$table] = $applied;
            }
        });

        $log = SyncLog::create([
            'user_id' => $user->id,
            'device_id' => $device?->id,
            'direction' => 'push',
            'counts' => $counts,
            'conflicts' => count($conflicts),
        ]);

        foreach ($conflicts as $conflict) {
            $conflict->forceFill(['sync_log_id' => $log->id])->save();
        }

        $device?->forceFill(['last_pushed_at' => now(), 'last_seen_at' => now()])->saveQuietly();

        return [
            'counts' => $counts,
            'conflicts' => count($conflicts),
            'server_time' => now()->toIso8601String(),
        ];
    }

    /**
     * Everything in this owner's data that changed at or after the cursor,
     * soft-deleted rows included so a deletion propagates.
     */
    public function pull(User $user, ?Device $device, ?CarbonImmutable $since): array
    {
        $businessIds = $user->businesses()->withTrashed()->pluck('id');

        $payload = [
            'businesses' => $this->pullRows(
                Business::withTrashed()->where('user_id', $user->id), $since
            ),
            'customers' => $this->pullRows(
                Customer::withTrashed()->whereIn('business_id', $businessIds), $since
            ),
            'items' => $this->pullRows(
                Item::withTrashed()->whereIn('business_id', $businessIds), $since
            ),
            'invoices' => $this->pullInvoices($businessIds, $since),
            'payments' => $this->pullRows(
                Payment::withTrashed()->whereHas('invoice', fn ($q) => $q->whereIn('business_id', $businessIds)), $since
            ),
        ];

        SyncLog::create([
            'user_id' => $user->id,
            'device_id' => $device?->id,
            'direction' => 'pull',
            'counts' => array_map('count', $payload),
        ]);

        $device?->forceFill(['last_pulled_at' => now(), 'last_seen_at' => now()])->saveQuietly();

        return [
            'changes' => $payload,
            'server_time' => now()->toIso8601String(),
        ];
    }

    // ---------------- apply ----------------

    private function applyBusiness(User $user, array $row): string|SyncConflict|null
    {
        $existing = Business::withTrashed()->where('uuid', $row['uuid'])->first();

        if ($existing && $existing->user_id !== $user->id) {
            return null; // Not this owner's row; never cross tenants.
        }

        if ($existing && $this->serverWins($existing, $row)) {
            return $this->recordConflict($user, $existing, $row);
        }

        $business = $existing ?? new Business(['uuid' => $row['uuid']]);
        $business->user_id = $user->id;
        $business->fill($this->only($row, [
            'name', 'tagline', 'address', 'mobile', 'jurisdiction_text',
            'gst_number', 'email', 'bank_details', 'upi_id', 'bill_prefix',
            'fy_reset', 'bill_fy', 'terms_text',
        ]));

        // Counters only ever move forward. A handset that has issued bill 40
        // must never let the server hand out 12 again.
        foreach (['next_bill_no', 'next_quote_no', 'next_challan_no'] as $counter) {
            if (isset($row[$counter])) {
                $business->{$counter} = max((int) $business->{$counter}, (int) $row[$counter]);
            }
        }

        $this->applyTimestamps($business, $row);
        $business->save();
        $this->applyDeletion($business, $row);

        return 'applied';
    }

    /** Customers and items: same shape, different columns. */
    private function applyScoped(User $user, string $modelClass, array $row, array $fields): string|SyncConflict|null
    {
        $business = $this->businessFor($user, $row);
        if (! $business) {
            return null;
        }

        /** @var Model $existing */
        $existing = $modelClass::withTrashed()->where('uuid', $row['uuid'])->first();

        if ($existing && $existing->business_id !== $business->id) {
            return null;
        }

        if ($existing && $this->serverWins($existing, $row)) {
            return $this->recordConflict($user, $existing, $row);
        }

        $model = $existing ?? new $modelClass(['uuid' => $row['uuid']]);
        $model->business_id = $business->id;
        $model->fill($this->only($row, $fields));

        $this->applyTimestamps($model, $row);
        $model->save();
        $this->applyDeletion($model, $row);

        return 'applied';
    }

    /**
     * An invoice arrives as one unit — header, lines and taxes together — and
     * is replaced wholesale, because that is how the handset edits one.
     *
     * The number the handset issued is accepted as-is and never reassigned:
     * the bill has already been printed and handed over.
     */
    private function applyInvoice(User $user, array $row): string|SyncConflict|null
    {
        $business = $this->businessFor($user, $row);
        if (! $business) {
            return null;
        }

        $existing = Invoice::withTrashed()->where('uuid', $row['uuid'])->first();

        if ($existing && $existing->business_id !== $business->id) {
            return null;
        }

        if ($existing && $this->serverWins($existing, $row)) {
            return $this->recordConflict($user, $existing, $row);
        }

        $invoice = $existing ?? new Invoice(['uuid' => $row['uuid']]);
        $invoice->business_id = $business->id;
        $invoice->fill($this->only($row, [
            'doc_type', 'bill_no', 'bill_ref', 'customer_name', 'customer_uuid',
            'date', 'amount_in_words', 'discount_type', 'discount_value',
            'round_off', 'notes', 'void_reason', 'converted_from_uuid', 'photo_path',
        ]));
        // The column has a default, but a row that omits doc_type leaves the
        // attribute null in memory until the model is re-read — and the
        // counter bump below needs it now.
        $invoice->doc_type = $row['doc_type'] ?? $invoice->doc_type ?? Invoice::TYPE_BILL;
        $invoice->voided_at = ! empty($row['voided_at']) ? $this->parseTimestamp($row['voided_at']) : null;

        $this->applyTimestamps($invoice, $row);
        $invoice->save();

        if (array_key_exists('lines', $row)) {
            $invoice->lines()->delete();
            foreach (array_values($row['lines'] ?? []) as $position => $line) {
                $invoice->lines()->create([
                    'uuid' => $line['uuid'] ?? null,
                    'particulars' => $line['particulars'] ?? '',
                    'quantity' => $line['quantity'] ?? 0,
                    'rate' => $line['rate'] ?? 0,
                    'position' => $line['position'] ?? $position,
                ]);
            }
        }

        if (array_key_exists('taxes', $row)) {
            $invoice->taxes()->delete();
            foreach ($row['taxes'] ?? [] as $tax) {
                $invoice->taxes()->create([
                    'uuid' => $tax['uuid'] ?? null,
                    'label' => $tax['label'] ?? 'GST',
                    'percent' => $tax['percent'] ?? 0,
                ]);
            }
        }

        // Keep the business counter ahead of anything the handset has issued.
        $column = Business::counterColumnFor($invoice->doc_type);
        if ((int) $invoice->bill_no >= (int) $business->{$column}) {
            $business->forceFill([$column => (int) $invoice->bill_no + 1])->saveQuietly();
        }

        $invoice->load(['lines', 'taxes', 'payments']);
        $invoice->recalculateTotals();
        $this->applyDeletion($invoice, $row);

        return 'applied';
    }

    private function applyPayment(User $user, array $row): string|SyncConflict|null
    {
        if (empty($row['invoice_uuid'])) {
            return null;
        }

        $businessIds = $user->businesses()->withTrashed()->pluck('id');
        $invoice = Invoice::withTrashed()
            ->where('uuid', $row['invoice_uuid'])
            ->whereIn('business_id', $businessIds)
            ->first();

        if (! $invoice) {
            return null;
        }

        $existing = Payment::withTrashed()->where('uuid', $row['uuid'])->first();

        if ($existing && $this->serverWins($existing, $row)) {
            return $this->recordConflict($user, $existing, $row);
        }

        $payment = $existing ?? new Payment(['uuid' => $row['uuid']]);
        $payment->invoice_id = $invoice->id;
        $payment->fill($this->only($row, ['date', 'amount', 'mode', 'note']));

        $this->applyTimestamps($payment, $row);
        $payment->save();
        $this->applyDeletion($payment, $row);

        $invoice->load('payments');
        $invoice->recalculateTotals();

        return 'applied';
    }

    // ---------------- pull ----------------

    private function pullRows($query, ?CarbonImmutable $since)
    {
        if ($since) {
            $query->where('updated_at', '>=', $since);
        }

        return $query->orderBy('updated_at')->get()->map(fn (Model $m) => $this->serialise($m))->all();
    }

    private function pullInvoices($businessIds, ?CarbonImmutable $since): array
    {
        $query = Invoice::withTrashed()
            ->with(['lines', 'taxes'])
            ->whereIn('business_id', $businessIds);

        if ($since) {
            $query->where('updated_at', '>=', $since);
        }

        return $query->orderBy('updated_at')->get()->map(function (Invoice $invoice) {
            $row = $this->serialise($invoice);
            $row['lines'] = $invoice->lines->map(fn ($l) => [
                'uuid' => $l->uuid,
                'particulars' => $l->particulars,
                'quantity' => (float) $l->quantity,
                'rate' => (float) $l->rate,
                'position' => (int) $l->position,
            ])->all();
            $row['taxes'] = $invoice->taxes->map(fn ($t) => [
                'uuid' => $t->uuid,
                'label' => $t->label,
                'percent' => (float) $t->percent,
            ])->all();

            return $row;
        })->all();
    }

    /**
     * The wire shape. Business and invoice UUIDs travel instead of foreign
     * keys, because the handset has never seen a server id.
     */
    private function serialise(Model $model): array
    {
        $row = $model->attributesToArray();
        unset($row['id'], $row['business_id'], $row['invoice_id'], $row['user_id']);

        if ($model instanceof Customer || $model instanceof Item || $model instanceof Invoice) {
            $row['business_uuid'] = $model->business?->uuid ?? Business::withTrashed()->find($model->business_id)?->uuid;
        }
        if ($model instanceof Payment) {
            $row['invoice_uuid'] = Invoice::withTrashed()->find($model->invoice_id)?->uuid;
        }

        return $row;
    }

    // ---------------- helpers ----------------

    private function businessFor(User $user, array $row): ?Business
    {
        if (empty($row['business_uuid'])) {
            return null;
        }

        return Business::withTrashed()
            ->where('uuid', $row['business_uuid'])
            ->where('user_id', $user->id)
            ->first();
    }

    private function only(array $row, array $keys): array
    {
        return array_intersect_key($row, array_flip($keys));
    }

    /**
     * Normalises a client timestamp into the application timezone.
     *
     * The handset sends IST with a +05:30 offset. Eloquent stores a Carbon by
     * formatting its local time, so an offset-bearing value would be written
     * as its IST wall-clock reading and then read back as UTC — five and a
     * half hours adrift, which is enough to make a stale edit look like the
     * newest one and win every conflict.
     */
    private function parseTimestamp(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value)->setTimezone(config('app.timezone'));
    }

    /** True when the stored row is newer than the one being pushed. */
    private function serverWins(Model $existing, array $row): bool
    {
        if (empty($row['updated_at'])) {
            return false;
        }

        return $existing->updated_at
            && $existing->updated_at->gt($this->parseTimestamp($row['updated_at']));
    }

    /**
     * Honours the client's own timestamp so a row written offline three days
     * ago doesn't arrive looking like it was just edited — which would make it
     * win every later comparison.
     */
    private function applyTimestamps(Model $model, array $row): void
    {
        if (! empty($row['updated_at'])) {
            $model->timestamps = false;
            $model->updated_at = $this->parseTimestamp($row['updated_at']);
            $model->created_at ??= $this->parseTimestamp($row['created_at'] ?? $row['updated_at']);
        }
    }

    private function applyDeletion(Model $model, array $row): void
    {
        $incomingDelete = ! empty($row['deleted_at']);

        if ($incomingDelete && ! $model->trashed()) {
            $model->deleted_at = $this->parseTimestamp($row['deleted_at']);
            $model->saveQuietly();
        } elseif (! $incomingDelete && $model->trashed()) {
            $model->restore();
        }
    }

    private function recordConflict(User $user, Model $existing, array $row): SyncConflict
    {
        return SyncConflict::create([
            'user_id' => $user->id,
            'model_type' => class_basename($existing),
            'uuid' => $row['uuid'],
            'incoming' => $row,
            'existing' => $existing->attributesToArray(),
            'resolution' => 'server_kept',
        ]);
    }
}
