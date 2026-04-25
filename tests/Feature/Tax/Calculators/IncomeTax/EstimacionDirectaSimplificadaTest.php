<?php

namespace Tests\Feature\Tax\Calculators\IncomeTax;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Calculators\IncomeTax\EstimacionDirectaSimplificada;
use Modules\Tax\DTOs\IncomeTax\EconomicActivityInput;
use Modules\Tax\DTOs\IncomeTax\IncomeTaxInput;
use Modules\Tax\DTOs\IncomeTax\TaxpayerSituation;
use Modules\Tax\Services\IncomeTax\IncomeTaxDeductionsCalculator;
use Modules\Tax\Services\IncomeTax\PersonalMinimumCalculator;
use Modules\Tax\Services\IncomeTax\WorkIncomeReductionCalculator;
use Modules\Tax\Services\IrpfScaleResolver;
use Modules\Tax\Services\TaxParameterRepository;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegimeCode;
use Modules\Tax\ValueObjects\RegionCode;
use Tests\TestCase;
use Tests\Unit\Tax\Calculators\Payroll\SeedsTaxParameters;

/**
 * Tests del EstimacionDirectaSimplificada (EDS).
 *
 * Aplicable a autónomos cifra negocio ≤ 600.000 €/año.
 * Rendimiento neto = ingresos − gastos − reducción genérica art. 30 RIRPF.
 *
 * Reducción genérica:
 *   - 2023+: 7 % sobre rendimiento previo (Ley 31/2022 art. 64)
 *   - Tope: 2.000 € anual.
 *
 * Fuente: Ley 35/2006 art. 30 + RD 439/2007 art. 30.
 */
class EstimacionDirectaSimplificadaTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTaxParameters;

    private EstimacionDirectaSimplificada $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTaxParameters(2025);
        $repo = $this->app->make(TaxParameterRepository::class);
        $this->calc = new EstimacionDirectaSimplificada(
            new IrpfScaleResolver($repo),
            new PersonalMinimumCalculator($repo),
            new WorkIncomeReductionCalculator($repo),
            new IncomeTaxDeductionsCalculator($repo),
            $repo,
        );
    }

    /**
     * GOLDEN — Autónomo EDS Cataluña 2025 con reducción del 7 % capada al tope.
     *
     *  Ingresos: 40.000
     *  Gastos:    8.000
     *  Rendimiento previo: 32.000
     *  Reducción genérica: 7 % × 32.000 = 2.240 → CAPADA a 2.000 €.
     *  Rendimiento neto: 32.000 − 2.000 = 30.000
     *  Mínimo personal: 5.550
     *
     *  Estatal sobre 30.000:
     *    tramo 20.200-35.200 (15 %, fijo 2.112,75)
     *    = 2.112,75 + 9.800 × 0,15 = 2.112,75 + 1.470 = 3.582,75
     *  Estatal sobre 5.550: 527,25
     *  Cuota estatal: 3.055,50
     *
     *  CT sobre 30.000:
     *    tramo 21.000-33.007,20 (15 %, fijo 2.399,13)
     *    = 2.399,13 + (30.000 − 21.000) × 0,15
     *    = 2.399,13 + 1.350 = 3.749,13
     *  CT sobre 5.550:
     *    tramo 0-12.450 (10,50 %) = 5.550 × 0,105 = 582,75
     *  Cuota CT: 3.749,13 − 582,75 = 3.166,38
     *
     *  Cuota total: 3.055,50 + 3.166,38 = 6.221,88
     *  Cuota líquida: 6.221,88 (sin deducciones autonómicas)
     *
     *  Pagos fraccionados (modelo 130) ya pagados: 3.000
     *  Resultado: 6.221,88 − 3.000 = 3.221,88 (a ingresar)
     *
     *  Fuente: art. 30 RIRPF (RD 439/2007), Ley 5/2020 DOGC para escala CT.
     */
    public function test_golden_autonomo_eds_40k_cataluna_2025(): void
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
                quarterlyPaymentsAlreadyPaid: Money::fromFloat(3000),
            ),
        ));

        $this->assertSame('30000.00', $result->netIncome->amount);
        $this->assertSame('3055.50', $result->stateQuota->amount);
        $this->assertSame('3166.38', $result->regionalQuota->amount);
        $this->assertSame('6221.88', $result->totalGross->amount);
        $this->assertSame('3221.88', $result->result->amount);
    }

    /**
     * Caso pequeño: ingresos 15.000, gastos 5.000.
     *  Previo: 10.000. Reducción 7 % = 700 (no llega al tope 2.000).
     *  Neto: 10.000 − 700 = 9.300.
     */
    public function test_eds_small_income_uses_7_percent_no_cap(): void
    {
        $result = $this->calc->calculate(new IncomeTaxInput(
            regime: RegimeCode::fromString('EDS'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::state(),
            taxpayerSituation: TaxpayerSituation::single(),
            economicActivity: new EconomicActivityInput(
                activityCode: '843.5',
                grossRevenue: Money::fromFloat(15000),
                deductibleExpenses: Money::fromFloat(5000),
                quarterlyPaymentsAlreadyPaid: Money::zero(),
            ),
        ));

        $this->assertSame('9300.00', $result->netIncome->amount);
    }

    public function test_eds_negative_yield_does_not_apply_reduction(): void
    {
        $result = $this->calc->calculate(new IncomeTaxInput(
            regime: RegimeCode::fromString('EDS'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::state(),
            taxpayerSituation: TaxpayerSituation::single(),
            economicActivity: new EconomicActivityInput(
                activityCode: '843.5',
                grossRevenue: Money::fromFloat(10000),
                deductibleExpenses: Money::fromFloat(15000),
                quarterlyPaymentsAlreadyPaid: Money::zero(),
            ),
        ));

        // Rendimiento previo negativo: la línea de "reducción genérica" no debe aparecer.
        $hasGenericReductionLine = collect($result->breakdown->lines)
            ->contains(fn ($l) => str_contains($l->concept, 'genérica'));
        $this->assertFalse($hasGenericReductionLine);

        // Y el rendimiento neto se capa a 0 a efectos de base liquidable.
        $this->assertSame('0.00', $result->netIncome->amount);
    }

    public function test_eds_breakdown_includes_generic_reduction_line(): void
    {
        $result = $this->calc->calculate(new IncomeTaxInput(
            regime: RegimeCode::fromString('EDS'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::state(),
            taxpayerSituation: TaxpayerSituation::single(),
            economicActivity: new EconomicActivityInput(
                activityCode: '843.5',
                grossRevenue: Money::fromFloat(40000),
                deductibleExpenses: Money::fromFloat(8000),
                quarterlyPaymentsAlreadyPaid: Money::zero(),
            ),
        ));

        $hasGenericReductionLine = collect($result->breakdown->lines)
            ->contains(fn ($l) => str_contains($l->concept, 'genérica'));
        $this->assertTrue($hasGenericReductionLine);
    }
}
