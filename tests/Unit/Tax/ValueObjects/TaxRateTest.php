<?php

namespace Tests\Unit\Tax\ValueObjects;

use InvalidArgumentException;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\TaxRate;
use Tests\TestCase;

class TaxRateTest extends TestCase
{
    public function test_constructs_from_percentage_int(): void
    {
        $r = TaxRate::fromPercentage(21);
        $this->assertSame('21.0000', $r->percentage);
    }

    public function test_constructs_from_percentage_float(): void
    {
        $r = TaxRate::fromPercentage(7.5);
        $this->assertSame('7.5000', $r->percentage);
    }

    public function test_rejects_invalid_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TaxRate('abc');
    }

    public function test_as_decimal_returns_factor(): void
    {
        $this->assertSame('0.21000000', TaxRate::fromPercentage(21)->asDecimal());
        $this->assertSame('0.04000000', TaxRate::fromPercentage(4)->asDecimal());
    }

    public function test_zero_helper(): void
    {
        $this->assertTrue(TaxRate::zero()->isZero());
    }

    public function test_add_rates(): void
    {
        $a = TaxRate::fromPercentage(15);
        $b = TaxRate::fromPercentage(7);

        $this->assertSame('22.0000', $a->add($b)->percentage);
    }

    public function test_money_apply_rate_uses_decimal_factor(): void
    {
        $base = new Money('1000.00');
        $rate = TaxRate::fromPercentage(7);

        $this->assertSame('70.00', $base->applyRate($rate)->amount);
    }
}
