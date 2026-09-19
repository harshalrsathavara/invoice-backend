<?php

namespace App\Services;

/**
 * Converts a rupee amount into words using the Indian numbering system
 * (Thousand / Lakh / Crore), e.g. 3080 -> "Three Thousand Eighty Rupees Only".
 *
 * Ported from the handset so a document created through the API or the admin
 * panel carries the same "Rupees in words" line as one raised on the phone.
 */
class NumberToWords
{
    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight',
        'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen',
        'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const TENS = [
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy',
        'Eighty', 'Ninety',
    ];

    public static function convert(float $amount): string
    {
        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);

        // Rounding the paise can carry into the rupees: 99.999 is a hundred
        // rupees, not ninety-nine rupees and a hundred paise.
        if ($paise === 100) {
            $rupees++;
            $paise = 0;
        }

        if ($rupees === 0 && $paise === 0) {
            return 'Zero Rupees Only';
        }

        $parts = [];
        if ($rupees > 0) {
            $parts[] = self::convertInteger($rupees).' Rupees';
        }
        if ($paise > 0) {
            $parts[] = 'and '.self::convertInteger($paise).' Paise';
        }

        return implode(' ', $parts).' Only';
    }

    private static function convertInteger(int $n): string
    {
        if ($n === 0) {
            return 'Zero';
        }

        $crore = intdiv($n, 10000000);
        $n %= 10000000;
        $lakh = intdiv($n, 100000);
        $n %= 100000;
        $thousand = intdiv($n, 1000);
        $n %= 1000;
        $hundred = intdiv($n, 100);
        $rest = $n % 100;

        $segments = [];
        if ($crore > 0) {
            $segments[] = self::convertBelow1000($crore).' Crore';
        }
        if ($lakh > 0) {
            $segments[] = self::convertBelow1000($lakh).' Lakh';
        }
        if ($thousand > 0) {
            $segments[] = self::convertBelow1000($thousand).' Thousand';
        }
        if ($hundred > 0) {
            $segments[] = self::ONES[$hundred].' Hundred';
        }
        if ($rest > 0) {
            $segments[] = self::convertBelow100($rest);
        }

        return implode(' ', $segments);
    }

    private static function convertBelow1000(int $n): string
    {
        $hundred = intdiv($n, 100);
        $rest = $n % 100;

        $segments = [];
        if ($hundred > 0) {
            $segments[] = self::ONES[$hundred].' Hundred';
        }
        if ($rest > 0) {
            $segments[] = self::convertBelow100($rest);
        }

        return implode(' ', $segments);
    }

    private static function convertBelow100(int $n): string
    {
        if ($n < 20) {
            return self::ONES[$n];
        }

        $tens = intdiv($n, 10);
        $ones = $n % 10;

        return $ones > 0 ? self::TENS[$tens].' '.self::ONES[$ones] : self::TENS[$tens];
    }
}
