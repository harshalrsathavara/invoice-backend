<?php

namespace Tests\Unit;

use App\Services\Money;
use PHPUnit\Framework\TestCase;

class MoneyFormatTest extends TestCase
{
    public function test_groups_the_indian_way_not_the_western_way(): void
    {
        // After the first three digits the groups are pairs.
        $this->assertSame('25,00,000.00', Money::amount(2500000));
        $this->assertSame('1,25,461.20', Money::amount(125461.20));
        $this->assertSame('999.00', Money::amount(999));
        $this->assertSame('1,000.00', Money::amount(1000));
    }

    public function test_rupee_symbol(): void
    {
        $this->assertSame('₹1,18,000.00', Money::rupees(118000));
    }

    public function test_raw_is_ungrouped_for_anything_that_gets_parsed_again(): void
    {
        $this->assertSame('125461.20', Money::raw(125461.20));
    }

    public function test_compact_uses_indian_scale_words(): void
    {
        // Chart chrome only — lakh and crore, because the grouping is Indian.
        $this->assertSame('8.04L', Money::compact(803513.77));
        $this->assertSame('17.6L', Money::compact(1755166.80));
        $this->assertSame('1.76Cr', Money::compact(17551668.00));
        $this->assertSame('37.8K', Money::compact(37760.00));
        $this->assertSame('640', Money::compact(640.40));
        $this->assertSame('0', Money::compact(0));
        $this->assertSame('-8.04L', Money::compact(-803513.77));
    }

    public function test_negatives_keep_their_sign(): void
    {
        $this->assertSame('-1,25,461.20', Money::amount(-125461.20));
    }
}
