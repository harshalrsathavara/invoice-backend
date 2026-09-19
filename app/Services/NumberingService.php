<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Hands out document numbers.
 *
 * Ported from the handset's `reserveNextBillNo`, and it has to behave
 * identically: each document kind counts separately, so raising a quotation
 * never burns a bill number, and the read and the increment happen inside one
 * locked transaction so two documents saved at the same moment cannot claim
 * the same number.
 */
class NumberingService
{
    /**
     * Reserves the next number for a document kind and returns both the raw
     * counter and the formatted reference to freeze onto the document.
     *
     * @return array{bill_no: int, bill_ref: string}
     */
    public function reserve(Business $business, string $type = Invoice::TYPE_BILL, ?\DateTimeInterface $on = null): array
    {
        $on ??= now();

        return DB::transaction(function () use ($business, $type, $on) {
            /** @var Business $locked */
            $locked = Business::query()
                ->whereKey($business->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $column = Business::counterColumnFor($type);
            $currentFy = Business::financialYearOf($on);

            // The reset fires once, on the first document of the new year:
            // an empty stored year means numbering has never rolled over.
            $startingNewYear = $locked->fy_reset
                && $locked->bill_fy !== ''
                && $locked->bill_fy !== $currentFy;

            $billNo = $startingNewYear ? 1 : (int) $locked->{$column};

            $update = [$column => $billNo + 1];
            if ($type === Invoice::TYPE_BILL) {
                $update['bill_fy'] = $currentFy;
            }
            $locked->forceFill($update)->saveQuietly();

            // Refresh the caller's copy so it doesn't hold a stale counter.
            $business->forceFill($update);

            return [
                'bill_no' => $billNo,
                'bill_ref' => $locked->formatBillNo($billNo, $on, $type),
            ];
        });
    }
}
