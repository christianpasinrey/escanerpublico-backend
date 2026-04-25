<?php

namespace Tests\Feature\Tax\Calculators\IncomeTax;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Calculators\IncomeTax\IncomeTaxCalculator;
use Modules\Tax\DTOs\IncomeTax\EconomicActivityInput;
use Modules\Tax\DTOs\IncomeTax\IncomeTaxInput;
use Modules\Tax\DTOs\IncomeTax\TaxpayerSituation;
use Modules\Tax\DTOs\IncomeTax\WorkIncomeInput;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegimeCode;
use Modules\Tax\ValueObjects\RegionCode;
use RuntimeException;
use Tests\TestCase;
use Tests\Unit\Tax\Calculators\Payroll\SeedsTaxParameters;

/**
 * Tests del dispatcher IncomeTaxCalculator.
 */
class IncomeTaxCalculatorDispatcherTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTaxParameters;

    private IncomeTaxCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTaxParameters(2025);
        $this->calc = $this->app->make(IncomeTaxCalculator::class);
    }

    public function test_dispatch_to_asalariado(): void
    {
        $result = $this->calc->calculate(new IncomeTaxInput(
            regime: RegimeCode::fromString('ASALARIADO_GEN'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('MD'),
            taxpayerSituation: TaxpayerSituation::single(),
            workIncome: new WorkIncomeInput(
                gross: Money::fromFloat(30000),
                socialSecurityPaid: Money::fromFloat(1944.99),
                irpfWithheld: Money::fromFloat(5000),
            ),
        ));

        $this->assertSame('ASALARIADO_GEN', $result->breakdown->meta['regime'] ?? '');
    }

    public function test_dispatch_to_edn(): void
    {
        $result = $this->calc->calculate(new IncomeTaxInput(
            regime: RegimeCode::fromString('EDN'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('MD'),
            taxpayerSituation: TaxpayerSituation::single(),
            economicActivity: new EconomicActivityInput(
                activityCode: '849.7',
                grossRevenue: Money::fromFloat(50000),
                deductibleExpenses: Money::fromFloat(10000),
                quarterlyPaymentsAlreadyPaid: Money::zero(),
            ),
        ));

        $this->assertSame('EDN', $result->breakdown->meta['regime'] ?? '');
    }

    public function test_dispatch_to_eds(): void
    {
        $result = $this->calc->calculate(new IncomeTaxInput(
            regime: RegimeCode::fromString('EDS'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('CT'),
            taxpayerSituation: TaxpayerSituation::single(),
            economicActivity: new EconomicActivityInput(
                activityCode: '843.5',
                grossRevenue: Money::fromFloat(40000),
                deductibleExpenses: Money::fromFloat(8000),
                quarterlyPaymentsAlreadyPaid: Money::zero(),
            ),
        ));

        $this->assertSame('EDS', $result->breakdown->meta['regime'] ?? '');
    }

    public function test_dispatch_to_eo(): void
    {
        $result = $this->calc->calculate(new IncomeTaxInput(
            regime: RegimeCode::fromString('EO'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('AN'),
            taxpayerSituation: TaxpayerSituation::single(),
            economicActivity: new EconomicActivityInput(
                activityCode: '721.2',
                grossRevenue: Money::fromFloat(30000),
                deductibleExpenses: Money::zero(),
                quarterlyPaymentsAlreadyPaid: Money::zero(),
                eoModulesData: ['personal_no_asalariado' => 1],
            ),
        ));

        $this->assertSame('EO', $result->breakdown->meta['regime'] ?? '');
    }

    public function test_rejects_foral_region(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/foral/i');

        $this->calc->calculate(new IncomeTaxInput(
            regime: RegimeCode::fromString('ASALARIADO_GEN'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('PV'),
            taxpayerSituation: TaxpayerSituation::single(),
            workIncome: new WorkIncomeInput(
                gross: Money::fromFloat(30000),
                socialSecurityPaid: Money::fromFloat(1944.99),
                irpfWithheld: Money::zero(),
            ),
        ));
    }

    public function test_rejects_unsupported_region(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/MVP/i');

        $this->calc->calculate(new IncomeTaxInput(
            regime: RegimeCode::fromString('ASALARIADO_GEN'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('GA'),
            taxpayerSituation: TaxpayerSituation::single(),
            workIncome: new WorkIncomeInput(
                gross: Money::fromFloat(30000),
                socialSecurityPaid: Money::fromFloat(1944.99),
                irpfWithheld: Money::zero(),
            ),
        ));
    }

    /**
     * EDGE — La validación de "fuera de MVP" se hace en el FormRequest a nivel
     * de API; aquí dentro del dispatcher el match cubre los 4 regímenes
     * soportados y cualquier otro lanzaría InvalidArgumentException. Un test
     * directo no es factible porque IncomeTaxInput solo acepta regímenes
     * irpf y los soportados están todos cubiertos por sus calculators. Esta
     * casuística la cubre el test de validación API.
     */
    public function test_dispatch_state_only_skips_regional_quota(): void
    {
        $result = $this->calc->calculate(new IncomeTaxInput(
            regime: RegimeCode::fromString('ASALARIADO_GEN'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::state(),
            taxpayerSituation: TaxpayerSituation::single(),
            workIncome: new WorkIncomeInput(
                gross: Money::fromFloat(30000),
                socialSecurityPaid: Money::fromFloat(1944.99),
                irpfWithheld: Money::zero(),
            ),
        ));

        $this->assertSame('0.00', $result->regionalQuota->amount);
        $this->assertGreaterThan(0.0, (float) $result->stateQuota->amount);
    }
}
