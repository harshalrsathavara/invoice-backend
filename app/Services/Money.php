<?php

namespace App\Services;

/**
 * Rupee formatting, Indian style.
 *
 * Indian grouping is not the western thousands pattern: after the first three
 * digits the groups are pairs, so 2,500,000 is written 25,00,000. PHP's
 * number_format cannot express that, so the grouping is applied by hand.
 */
class Money
{
    /** "1,25,461.20" — grouped, no symbol. */
    public static function amount(float $value): string
    {
        $negative = $value < 0;
        $value = abs($value);

        $whole = (string) (int) floor($value);
        $paise = str_pad((string) (int) round(($value - floor($value)) * 100), 2, '0', STR_PAD_LEFT);

        // Last three digits stay together; everything before them pairs up.
        if (strlen($whole) > 3) {
            $head = substr($whole, 0, -3);
            $tail = substr($whole, -3);
            $head = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $head);
            $whole = $head.','.$tail;
        }

        return ($negative ? '-' : '').$whole.'.'.$paise;
    }

    /** "₹1,25,461.20" — for anything a person reads as money. */
    public static function rupees(float $value): string
    {
        return '₹'.self::amount($value);
    }

    /**
     * "8.04L" — short enough for an axis tick or a chart label.
     *
     * Indian scale words, because the grouping is Indian: a lakh is 1,00,000
     * and a crore is 1,00,00,000. Precision is deliberately lost here, so this
     * is only for chart chrome — never for a figure someone reconciles against.
     */
    public static function compact(float $value): string
    {
        $negative = $value < 0;
        $value = abs($value);

        [$scaled, $suffix] = match (true) {
            $value >= 10000000 => [$value / 10000000, 'Cr'],
            $value >= 100000 => [$value / 100000, 'L'],
            $value >= 1000 => [$value / 1000, 'K'],
            default => [$value, ''],
        };

        // Two significant-ish digits: 8.04L, 47.2L, 1.76Cr, 640.
        $decimals = $suffix === '' ? 0 : ($scaled < 10 ? 2 : 1);
        $out = number_format($scaled, $decimals, '.', '');

        // Trim only the decimal tail — "640" must not become "64".
        if ($decimals > 0) {
            $out = rtrim(rtrim($out, '0'), '.');
        }

        return ($negative ? '-' : '').$out.$suffix;
    }

    /** Machine-readable, ungrouped. CSV and anything that will be parsed again. */
    public static function raw(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
