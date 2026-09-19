<?php

namespace Tests\Unit;

use App\Services\NumberToWords;
use PHPUnit\Framework\TestCase;

class NumberToWordsTest extends TestCase
{
    public function test_uses_the_indian_numbering_system(): void
    {
        $this->assertSame('Three Thousand Eighty Rupees Only', NumberToWords::convert(3080));
        $this->assertSame('One Lakh Twenty Thousand Rupees Only', NumberToWords::convert(120000));
        $this->assertSame('One Crore Rupees Only', NumberToWords::convert(10000000));
    }

    public function test_handles_paise(): void
    {
        $this->assertSame('Ten Rupees and Fifty Paise Only', NumberToWords::convert(10.50));
    }

    public function test_zero(): void
    {
        $this->assertSame('Zero Rupees Only', NumberToWords::convert(0));
    }

    public function test_rounding_paise_carries_into_the_rupees(): void
    {
        // 99.999 is a hundred rupees, not ninety-nine and a hundred paise.
        $this->assertSame('One Hundred Rupees Only', NumberToWords::convert(99.999));
    }
}
