<?php

namespace Tests\Unit\Tax\DTOs\IncomeTax;

use InvalidArgumentException;
use Modules\Tax\DTOs\IncomeTax\EconomicActivityInput;
use Modules\Tax\ValueObjects\Money;
use Tests\TestCase;

class EconomicActivityInputTest extends TestCase
{
    public function test_constructs_with_required_fields(): void
    {
        $a = new EconomicActivityInput(
            activityCode: '673.2',
            grossRevenue: Money::fromFloat(40000),
            deductibleExpenses: Money::fromFloat(8000),
            quarterlyPaymentsAlreadyPaid: Money::fromFloat(3000),
        );

        $this->assertSame('673.2', $a->activityCode);
        $this->assertSame('40000.00', $a->grossRevenue->amount);
        $this->assertSame('8000.00', $a->deductibleExpenses->amount);
        $this->assertNull($a->eoModulesData);
    }

    public function test_accepts_eo_modules_data(): void
    {
        $a = new EconomicActivityInput(
            activityCode: '721.2',
            grossRevenue: Money::fromFloat(35000),
            deductibleExpenses: Money::zero(),
            quarterlyPaymentsAlreadyPaid: Money::zero(),
            eoModulesData: [
                'personal_no_asalariado' => 1,
                'distancia_km' => 30,
            ],
        );

        $this->assertCount(2, $a->eoModulesData ?? []);
        $this->assertSame(1, $a->eoModulesData['personal_no_asalariado']);
    }

    public function test_rejects_empty_activity_code(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EconomicActivityInput(
            activityCode: '',
            grossRevenue: Money::fromFloat(1),
            deductibleExpenses: Money::zero(),
            quarterlyPaymentsAlreadyPaid: Money::zero(),
        );
    }

    public function test_rejects_negative_gross_revenue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EconomicActivityInput(
            activityCode: '673.2',
            grossRevenue: new Money('-1.00'),
            deductibleExpenses: Money::zero(),
            quarterlyPaymentsAlreadyPaid: Money::zero(),
        );
    }

    public function test_rejects_negative_eo_module_units(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EconomicActivityInput(
            activityCode: '721.2',
            grossRevenue: Money::fromFloat(10000),
            deductibleExpenses: Money::zero(),
            quarterlyPaymentsAlreadyPaid: Money::zero(),
            eoModulesData: ['m2_local' => -50],
        );
    }

    public function test_rejects_non_numeric_eo_module_units(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EconomicActivityInput(
            activityCode: '721.2',
            grossRevenue: Money::fromFloat(10000),
            deductibleExpenses: Money::zero(),
            quarterlyPaymentsAlreadyPaid: Money::zero(),
            // @phpstan-ignore-next-line — testing runtime guard
            eoModulesData: ['m2_local' => 'cincuenta'],
        );
    }
}
