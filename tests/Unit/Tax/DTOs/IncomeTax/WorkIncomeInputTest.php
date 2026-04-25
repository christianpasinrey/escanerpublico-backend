<?php

namespace Tests\Unit\Tax\DTOs\IncomeTax;

use InvalidArgumentException;
use Modules\Tax\DTOs\IncomeTax\WorkIncomeInput;
use Modules\Tax\ValueObjects\Money;
use Tests\TestCase;

class WorkIncomeInputTest extends TestCase
{
    public function test_constructs_from_money_objects(): void
    {
        $w = new WorkIncomeInput(
            gross: Money::fromFloat(30000),
            socialSecurityPaid: Money::fromFloat(1944.99),
            irpfWithheld: Money::fromFloat(5000),
        );

        $this->assertSame('30000.00', $w->gross->amount);
        $this->assertSame('1944.99', $w->socialSecurityPaid->amount);
        $this->assertSame('5000.00', $w->irpfWithheld->amount);
    }

    public function test_rejects_negative_gross(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new WorkIncomeInput(
            gross: new Money('-100.00'),
            socialSecurityPaid: Money::zero(),
            irpfWithheld: Money::zero(),
        );
    }

    public function test_rejects_negative_social_security(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new WorkIncomeInput(
            gross: Money::fromFloat(30000),
            socialSecurityPaid: new Money('-1.00'),
            irpfWithheld: Money::zero(),
        );
    }

    public function test_rejects_negative_withholdings(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new WorkIncomeInput(
            gross: Money::fromFloat(30000),
            socialSecurityPaid: Money::zero(),
            irpfWithheld: new Money('-1.00'),
        );
    }
}
