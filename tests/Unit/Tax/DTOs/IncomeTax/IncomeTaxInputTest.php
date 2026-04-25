<?php

namespace Tests\Unit\Tax\DTOs\IncomeTax;

use InvalidArgumentException;
use Modules\Tax\DTOs\IncomeTax\EconomicActivityInput;
use Modules\Tax\DTOs\IncomeTax\IncomeTaxInput;
use Modules\Tax\DTOs\IncomeTax\TaxpayerSituation;
use Modules\Tax\DTOs\IncomeTax\WorkIncomeInput;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegimeCode;
use Modules\Tax\ValueObjects\RegionCode;
use Tests\TestCase;

class IncomeTaxInputTest extends TestCase
{
    public function test_asalariado_requires_work_income(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new IncomeTaxInput(
            regime: RegimeCode::fromString('ASALARIADO_GEN'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('MD'),
            taxpayerSituation: TaxpayerSituation::single(),
            workIncome: null,
        );
    }

    public function test_edn_requires_economic_activity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new IncomeTaxInput(
            regime: RegimeCode::fromString('EDN'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('MD'),
            taxpayerSituation: TaxpayerSituation::single(),
            economicActivity: null,
        );
    }

    public function test_eds_requires_economic_activity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new IncomeTaxInput(
            regime: RegimeCode::fromString('EDS'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('CT'),
            taxpayerSituation: TaxpayerSituation::single(),
        );
    }

    public function test_eo_requires_economic_activity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new IncomeTaxInput(
            regime: RegimeCode::fromString('EO'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('AN'),
            taxpayerSituation: TaxpayerSituation::single(),
        );
    }

    public function test_eo_rejects_deductible_expenses_above_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new IncomeTaxInput(
            regime: RegimeCode::fromString('EO'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('AN'),
            taxpayerSituation: TaxpayerSituation::single(),
            economicActivity: new EconomicActivityInput(
                activityCode: '673.2',
                grossRevenue: Money::fromFloat(50000),
                deductibleExpenses: Money::fromFloat(1000), // ← inválido en EO
                quarterlyPaymentsAlreadyPaid: Money::zero(),
            ),
        );
    }

    public function test_rejects_non_irpf_regime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new IncomeTaxInput(
            regime: RegimeCode::fromString('IVA_GEN'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('MD'),
            taxpayerSituation: TaxpayerSituation::single(),
        );
    }

    public function test_accepts_asalariado_with_work_income(): void
    {
        $input = new IncomeTaxInput(
            regime: RegimeCode::fromString('ASALARIADO_GEN'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('MD'),
            taxpayerSituation: TaxpayerSituation::single(),
            workIncome: new WorkIncomeInput(
                gross: Money::fromFloat(30000),
                socialSecurityPaid: Money::fromFloat(1944.99),
                irpfWithheld: Money::fromFloat(5000),
            ),
        );

        $this->assertSame('ASALARIADO_GEN', $input->regime->code);
        $this->assertNotNull($input->workIncome);
    }

    public function test_serializes_to_json(): void
    {
        $input = new IncomeTaxInput(
            regime: RegimeCode::fromString('ASALARIADO_GEN'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('MD'),
            taxpayerSituation: TaxpayerSituation::single(),
            workIncome: new WorkIncomeInput(
                gross: Money::fromFloat(30000),
                socialSecurityPaid: Money::fromFloat(1944.99),
                irpfWithheld: Money::fromFloat(5000),
            ),
        );

        $arr = $input->jsonSerialize();
        $this->assertSame(2025, $arr['year']);
        $this->assertSame('ASALARIADO_GEN', $arr['regime']->code);
    }
}
