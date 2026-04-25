<?php

namespace Tests\Unit\Tax\ValueObjects;

use InvalidArgumentException;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\TaxRate;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_constructs_from_string_amount(): void
    {
        $m = new Money('1234.56');
        $this->assertSame('1234.56', $m->amount);
        $this->assertSame('EUR', $m->currency);
    }

    public function test_rejects_invalid_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Money('abc');
    }

    public function test_zero_factory(): void
    {
        $this->assertSame('0.00', Money::zero()->amount);
    }

    public function test_from_float_normalizes_to_two_decimals(): void
    {
        $this->assertSame('1234.57', Money::fromFloat(1234.567)->amount);
        $this->assertSame('100.00', Money::fromFloat(100)->amount);
    }

    public function test_add_subtract_multiply_divide_are_exact(): void
    {
        $a = new Money('100.00');
        $b = new Money('33.33');

        $this->assertSame('133.33', $a->add($b)->amount);
        $this->assertSame('66.67', $a->subtract($b)->amount);
        $this->assertSame('21.00', $a->multiply('0.21')->amount);
        $this->assertSame('33.33', $a->divide(3)->amount);
    }

    public function test_apply_rate(): void
    {
        $base = new Money('1000.00');
        $vat = TaxRate::fromPercentage(21);

        $this->assertSame('210.00', $base->applyRate($vat)->amount);
    }

    public function test_currency_mismatch_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Money('100.00', 'EUR'))->add(new Money('100.00', 'USD'));
    }

    public function test_division_by_zero_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Money('100.00'))->divide(0);
    }

    public function test_compare_and_zero_negative_helpers(): void
    {
        $a = new Money('100.00');
        $b = new Money('200.00');

        $this->assertSame(-1, $a->compare($b));
        $this->assertSame(1, $b->compare($a));
        $this->assertSame(0, $a->compare(new Money('100.00')));

        $this->assertTrue(Money::zero()->isZero());
        $this->assertTrue((new Money('-5.00'))->isNegative());
        $this->assertFalse((new Money('5.00'))->isNegative());
    }

    public function test_serializes_to_json(): void
    {
        $m = new Money('123.45');
        $this->assertSame(['amount' => '123.45', 'currency' => 'EUR'], $m->jsonSerialize());
    }
}
