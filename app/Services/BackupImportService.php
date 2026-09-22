<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Invoice;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackupImportException extends \RuntimeException {}

/**
 * Seeds the server from the app's own backup file.
 *
 * This is the bridge for data that already exists on a handset. The backup
 * carries SQLite integer ids and no UUIDs, so every row is given one here and
 * the id-to-UUID map is handed back — the handset adopts those UUIDs once, and
 * from then on the two sides share an identity and can sync normally.
 */
class BackupImportService
{
    private const MAGIC = 'invoice_app_backup';

    private const FORMAT_VERSION = 1;

    public function __construct(
        private InvoiceWriter $writer,
    ) {}

    /**
     * Reads a backup without applying it, so the caller can be told what they
     * are about to import.
     */
    public function inspect(array $payload): array
    {
        if (($payload['magic'] ?? null) !== self::MAGIC) {
            throw new BackupImportException('This file is not an Invoice Generator backup.');
        }

        $version = $payload['format_version'] ?? null;
        if (! is_int($version) || $version > self::FORMAT_VERSION) {
            throw new BackupImportException('This backup was made by a newer version of the app. Update the server first.');
        }

        $tables = $payload['tables'] ?? null;
        if (! is_array($tables)) {
            throw new BackupImportException('This backup is missing its data.');
        }

        return [
            'created_at' => $payload['created_at'] ?? null,
            'businesses' => count($tables['businesses'] ?? []),
            'customers' => count($tables['customers'] ?? []),
            'items' => count($tables['items'] ?? []),
            'invoices' => count($tables['invoices'] ?? []),
            'payments' => count($tables['payments'] ?? []),
            'images' => count($payload['images'] ?? []),
        ];
    }

    /**
     * Applies the backup under the given owner.
     *
     * @return array{counts: array<string,int>, uuid_map: array<string,array<int,string>>}
     */
    public function import(User $user, array $payload, bool $replaceExisting = false): array
    {
        $summary = $this->inspect($payload);
        $tables = $payload['tables'];

        $pathMap = $this->restoreImages($payload['images'] ?? []);

        $uuidMap = ['businesses' => [], 'customers' => [], 'items' => [], 'invoices' => [], 'payments' => []];
        $counts = [];

        DB::transaction(function () use ($user, $tables, $pathMap, $replaceExisting, &$uuidMap, &$counts) {
            if ($replaceExisting) {
                // Soft delete, never hard: an import that turns out to be the
                // wrong file must be recoverable.
                $user->businesses()->get()->each->delete();
            }

            $counts['businesses'] = 0;
            foreach ($tables['businesses'] ?? [] as $row) {
                $business = $user->businesses()->create([
                    'name' => $row['name'] ?? 'Untitled',
                    'tagline' => $row['tagline'] ?? '',
                    'address' => $row['address'] ?? '',
                    'mobile' => $row['mobile'] ?? '',
                    'jurisdiction_text' => $row['jurisdiction_text'] ?? '',
                    'gst_number' => $row['gst_number'] ?? '',
                    'email' => $row['email'] ?? '',
                    'bank_details' => $row['bank_details'] ?? '',
                    'upi_id' => $row['upi_id'] ?? '',
                    'logo_path' => $pathMap[$row['logo_path'] ?? ''] ?? null,
                    'signature_path' => $pathMap[$row['signature_path'] ?? ''] ?? null,
                    'next_bill_no' => (int) ($row['next_bill_no'] ?? 1),
                    'next_quote_no' => (int) ($row['next_quote_no'] ?? 1),
                    'next_challan_no' => (int) ($row['next_challan_no'] ?? 1),
                    'bill_prefix' => $row['bill_prefix'] ?? '',
                    'fy_reset' => (int) ($row['fy_reset'] ?? 0) === 1,
                    'bill_fy' => $row['bill_fy'] ?? '',
                    'terms_text' => $row['terms_text'] ?? '',
                ]);

                $uuidMap['businesses'][(int) $row['id']] = $business->uuid;
                $counts['businesses']++;
            }

            $businessByOldId = $user->businesses()
                ->whereIn('uuid', array_values($uuidMap['businesses']))
                ->get()
                ->keyBy('uuid');

            $resolveBusiness = function ($oldId) use ($uuidMap, $businessByOldId): ?Business {
                $uuid = $uuidMap['businesses'][(int) $oldId] ?? null;

                return $uuid ? $businessByOldId->get($uuid) : null;
            };

            $counts['customers'] = 0;
            foreach ($tables['customers'] ?? [] as $row) {
                $business = $resolveBusiness($row['business_id'] ?? null);
                if (! $business) {
                    continue;
                }
                $customer = $business->customers()->create([
                    'name' => $row['name'] ?? '',
                    'phone' => $row['phone'] ?? '',
                    'address' => $row['address'] ?? '',
                    'gst_number' => $row['gst_number'] ?? '',
                    'city' => $row['city'] ?? '',
                    'state' => $row['state'] ?? '',
                    'post_code' => $row['post_code'] ?? '',
                    'email' => $row['email'] ?? '',
                ]);
                $uuidMap['customers'][(int) $row['id']] = $customer->uuid;
                $counts['customers']++;
            }

            $counts['items'] = 0;
            foreach ($tables['items'] ?? [] as $row) {
                $business = $resolveBusiness($row['business_id'] ?? null);
                if (! $business) {
                    continue;
                }
                $item = $business->items()->create([
                    'name' => $row['name'] ?? '',
                    'default_rate' => (float) ($row['default_rate'] ?? 0),
                    'hsn_code' => $row['hsn_code'] ?? '',
                ]);
                $uuidMap['items'][(int) $row['id']] = $item->uuid;
                $counts['items']++;
            }

            // Children are grouped by their parent id up front so the invoice
            // loop doesn't re-scan the whole array per bill.
            $linesByInvoice = collect($tables['invoice_lines'] ?? [])->groupBy('invoice_id');
            $taxesByInvoice = collect($tables['invoice_taxes'] ?? [])->groupBy('invoice_id');
            $paymentsByInvoice = collect($tables['payments'] ?? [])->groupBy('invoice_id');

            $counts['invoices'] = 0;
            $counts['payments'] = 0;

            foreach ($tables['invoices'] ?? [] as $row) {
                $business = $resolveBusiness($row['business_id'] ?? null);
                if (! $business) {
                    continue;
                }

                $oldId = (int) $row['id'];

                $invoice = $business->invoices()->create([
                    'doc_type' => $row['doc_type'] ?? Invoice::TYPE_BILL,
                    'bill_no' => (int) ($row['bill_no'] ?? 0),
                    'bill_ref' => $row['bill_ref'] ?? '',
                    'customer_name' => $row['customer_name'] ?? '',
                    'date' => $this->date($row['date'] ?? null),
                    'amount_in_words' => $row['amount_in_words'] ?? '',
                    'discount_type' => $row['discount_type'] ?? Invoice::DISCOUNT_NONE,
                    'discount_value' => (float) ($row['discount_value'] ?? 0),
                    'round_off' => (float) ($row['round_off'] ?? 0),
                    'notes' => $row['notes'] ?? '',
                    'void_reason' => $row['void_reason'] ?? '',
                    'photo_path' => $pathMap[$row['photo_path'] ?? ''] ?? null,
                ]);

                if (! empty($row['voided_at'])) {
                    $invoice->forceFill(['voided_at' => $this->date($row['voided_at'])])->saveQuietly();
                }

                foreach (($linesByInvoice[$oldId] ?? collect())->values() as $position => $line) {
                    $invoice->lines()->create([
                        'particulars' => $line['particulars'] ?? '',
                        'quantity' => (float) ($line['quantity'] ?? 0),
                        'rate' => (float) ($line['rate'] ?? 0),
                        'position' => $position,
                    ]);
                }

                foreach ($taxesByInvoice[$oldId] ?? [] as $tax) {
                    $invoice->taxes()->create([
                        'label' => $tax['label'] ?? 'GST',
                        'percent' => (float) ($tax['percent'] ?? 0),
                    ]);
                }

                foreach ($paymentsByInvoice[$oldId] ?? [] as $payment) {
                    $created = $invoice->payments()->create([
                        'date' => $this->date($payment['date'] ?? null),
                        'amount' => (float) ($payment['amount'] ?? 0),
                        'mode' => $payment['mode'] ?? 'other',
                        'note' => $payment['note'] ?? '',
                    ]);
                    $uuidMap['payments'][(int) $payment['id']] = $created->uuid;
                    $counts['payments']++;
                }

                $invoice->load(['lines', 'taxes', 'payments']);
                $invoice->recalculateTotals();

                $uuidMap['invoices'][$oldId] = $invoice->uuid;
                $counts['invoices']++;
            }

            // The imported bills may run past the counters the backup carried.
            foreach ($user->businesses()->get() as $business) {
                foreach ([Invoice::TYPE_BILL, Invoice::TYPE_QUOTATION, Invoice::TYPE_CHALLAN] as $type) {
                    $column = Business::counterColumnFor($type);
                    $highest = (int) $business->invoices()->where('doc_type', $type)->max('bill_no');
                    if ($highest >= (int) $business->{$column}) {
                        $business->forceFill([$column => $highest + 1])->saveQuietly();
                    }
                }
            }
        });

        SyncLog::create([
            'user_id' => $user->id,
            'direction' => 'import',
            'counts' => $counts,
            'notes' => 'Backup dated '.($summary['created_at'] ?? 'unknown'),
        ]);

        return ['counts' => $counts, 'uuid_map' => $uuidMap];
    }

    /**
     * Writes the embedded images to disk and returns a map from the path the
     * handset recorded to the path they now live at.
     */
    private function restoreImages(array $images): array
    {
        $map = [];
        $disk = Storage::disk(config('filesystems.business_images_disk'));

        foreach ($images as $oldPath => $base64) {
            $binary = base64_decode((string) $base64, true);
            if ($binary === false) {
                continue;
            }

            $extension = str_contains((string) $oldPath, '.')
                ? Str::afterLast((string) $oldPath, '.')
                : 'jpg';

            $name = 'business_images/'.Str::uuid().'.'.$extension;
            $disk->put($name, $binary);
            $map[$oldPath] = $name;
        }

        return $map;
    }

    private function date(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        try {
            return \Carbon\CarbonImmutable::parse($raw)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
}
