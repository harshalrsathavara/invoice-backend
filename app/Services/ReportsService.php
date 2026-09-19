<?php

namespace App\Services;

use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Turns saved bills into the figures the reports show.
 *
 * A direct port of the handset's `ReportsService`, and deliberately pure for
 * the same reason: every method takes bills and returns numbers, so the
 * arithmetic is testable without a database and the money maths stays on the
 * Invoice model rather than being re-derived in SQL.
 */
class ReportsService
{
    /**
     * Quotations, challans and cancelled bills are documents, not income.
     * Every report starts by dropping them.
     */
    public function liveBills(Collection $all): Collection
    {
        return $all->filter(
            fn (Invoice $i) => $i->doc_type === Invoice::TYPE_BILL && ! $i->is_voided
        )->values();
    }

    public function totalBilled(Collection $bills): float
    {
        return round($bills->sum(fn (Invoice $i) => (float) $i->total), 2);
    }

    public function totalCollected(Collection $bills): float
    {
        return round($bills->sum(fn (Invoice $i) => (float) $i->paid_amount), 2);
    }

    public function totalOutstanding(Collection $bills): float
    {
        return round($this->totalBilled($bills) - $this->totalCollected($bills), 2);
    }

    /**
     * GST charged in the period, one row per label and rate.
     *
     * The taxable value is repeated per row on purpose: on a bill carrying
     * CGST 9% and SGST 9%, both are charged on the same ₹1,00,000, and a
     * return asks for it that way. Summing the taxable column across rows
     * would double count, which is why totalTaxableValue is separate.
     */
    public function gstSummary(Collection $bills): Collection
    {
        $byKey = [];

        foreach ($this->liveBills($bills) as $bill) {
            foreach ($bill->taxes as $tax) {
                $key = strtoupper($tax->label).'|'.(float) $tax->percent;
                $existing = $byKey[$key] ?? ['taxable' => 0.0, 'tax' => 0.0, 'count' => 0];

                $byKey[$key] = [
                    'taxable' => $existing['taxable'] + $bill->taxable_amount,
                    'tax' => $existing['tax'] + $bill->taxAmountFor($tax),
                    'count' => $existing['count'] + 1,
                ];
            }
        }

        return collect($byKey)
            ->map(function (array $value, string $key) {
                [$label, $percent] = explode('|', $key);

                return [
                    'label' => $label,
                    'percent' => (float) $percent,
                    'taxable_amount' => round($value['taxable'], 2),
                    'tax_amount' => round($value['tax'], 2),
                    'bill_count' => $value['count'],
                ];
            })
            ->sortBy([['label', 'asc'], ['percent', 'asc']])
            ->values();
    }

    /** Value the tax was charged on, counting each taxed bill once. */
    public function totalTaxableValue(Collection $bills): float
    {
        return round(
            $this->liveBills($bills)
                ->filter(fn (Invoice $b) => $b->taxes->isNotEmpty())
                ->sum(fn (Invoice $b) => $b->taxable_amount),
            2
        );
    }

    public function totalTax(Collection $bills): float
    {
        return round($this->liveBills($bills)->sum(fn (Invoice $b) => $b->total_tax), 2);
    }

    /**
     * Outstanding money split by how old the bill is.
     *
     * Measured from the bill date: there are no payment terms in this system,
     * so "how long has this been owed" is the honest question it can answer.
     *
     * @return array{up_to_30: float, from_31_to_60: float, over_60: float, total: float}
     */
    public function ageing(Collection $bills, ?\DateTimeInterface $asOf = null): array
    {
        $today = CarbonImmutable::instance($asOf ? CarbonImmutable::instance($asOf) : now())->startOfDay();

        $upTo30 = 0.0;
        $from31To60 = 0.0;
        $over60 = 0.0;

        foreach ($this->liveBills($bills) as $bill) {
            $owed = $bill->balance;
            if ($owed <= Invoice::EPSILON) {
                continue;
            }

            $days = CarbonImmutable::instance($bill->date)->startOfDay()->diffInDays($today, false);

            if ($days <= 30) {
                $upTo30 += $owed;
            } elseif ($days <= 60) {
                $from31To60 += $owed;
            } else {
                $over60 += $owed;
            }
        }

        return [
            'up_to_30' => round($upTo30, 2),
            'from_31_to_60' => round($from31To60, 2),
            'over_60' => round($over60, 2),
            'total' => round($upTo30 + $from31To60 + $over60, 2),
        ];
    }

    /**
     * Billing per month for the months ending with the one containing
     * $endingAt. Months with no bills are included as zero, so a chart keeps
     * even spacing and a quiet month reads as a gap rather than vanishing.
     */
    public function monthlyBilling(Collection $bills, int $months = 6, ?\DateTimeInterface $endingAt = null): Collection
    {
        $end = CarbonImmutable::instance($endingAt ? CarbonImmutable::instance($endingAt) : now());
        $live = $this->liveBills($bills);

        return collect(range(0, $months - 1))->map(function (int $i) use ($end, $months, $live) {
            $month = $end->startOfMonth()->subMonths($months - 1 - $i);
            $next = $month->addMonth();

            $inMonth = $live->filter(function (Invoice $b) use ($month, $next) {
                $date = CarbonImmutable::instance($b->date);

                return $date->gte($month) && $date->lt($next);
            });

            return [
                'month' => $month->format('Y-m'),
                'label' => $month->format('M Y'),
                'billed' => round($inMonth->sum(fn (Invoice $b) => (float) $b->total), 2),
                'bill_count' => $inMonth->count(),
            ];
        });
    }

    public function topCustomers(Collection $bills, int $limit = 5): Collection
    {
        $byName = [];

        foreach ($this->liveBills($bills) as $bill) {
            $key = mb_strtolower($bill->customer_name);
            $existing = $byName[$key] ?? ['display' => $bill->customer_name, 'amount' => 0.0, 'count' => 0];

            $byName[$key] = [
                'display' => $existing['display'],
                'amount' => $existing['amount'] + (float) $bill->total,
                'count' => $existing['count'] + 1,
            ];
        }

        return $this->rank($byName, $limit);
    }

    /** Which kind of work earns most, by line item description. */
    public function topItems(Collection $bills, int $limit = 5): Collection
    {
        $byName = [];

        foreach ($this->liveBills($bills) as $bill) {
            foreach ($bill->lines as $line) {
                $key = trim(mb_strtolower($line->particulars));
                if ($key === '') {
                    continue;
                }

                $existing = $byName[$key] ?? ['display' => $line->particulars, 'amount' => 0.0, 'count' => 0];

                $byName[$key] = [
                    'display' => $existing['display'],
                    'amount' => $existing['amount'] + $line->amount,
                    'count' => $existing['count'] + 1,
                ];
            }
        }

        return $this->rank($byName, $limit);
    }

    private function rank(array $byName, int $limit): Collection
    {
        return collect($byName)
            ->map(fn (array $v) => [
                'name' => $v['display'],
                'amount' => round($v['amount'], 2),
                'count' => $v['count'],
            ])
            ->sortByDesc('amount')
            ->take($limit)
            ->values();
    }

    /** Everything the dashboard shows, in one pass. */
    public function summary(Collection $bills): array
    {
        $live = $this->liveBills($bills);

        return [
            'bill_count' => $live->count(),
            'total_billed' => $this->totalBilled($live),
            'total_collected' => $this->totalCollected($live),
            'total_outstanding' => $this->totalOutstanding($live),
            'total_taxable_value' => $this->totalTaxableValue($live),
            'total_tax' => $this->totalTax($live),
        ];
    }
}
