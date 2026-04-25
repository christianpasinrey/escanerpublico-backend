<?php

namespace Tests\Feature\Tax\Calculators\IncomeTax;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Calculators\IncomeTax\EstimacionDirectaNormal;
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
 * Tests del EstimacionDirectaNormal (EDN).
 *
 * Aplicable a autónomos con cifra de negocio > 600.000 €/año o que renuncien
 * a EDS. Rendimiento neto = ingresos − gastos íntegramente deducibles.
 *
 * Fuente: Ley 35/2006 art. 27-30 (BOE-A-2006-20764).
 */
class EstimacionDirectaNormalTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTaxParameters;

    private EstimacionDirectaNormal $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTaxParameters(2025);
        $repo = $this->app->make(TaxParameterRepository::class);
        $this->calc = new EstimacionDirectaNormal(
            new IrpfScaleResolver($repo),
            new PersonalMinimumCalculator($repo),
            new WorkIncomeReductionCalculator($repo),
            new IncomeTaxDeductionsCalculator($repo),
        );
    }

    /**
     * GOLDEN — Autónomo profesional EDN Madrid 2025.
     *
     *  Ingresos: 50.000
     *  Gastos:   15.000
     *  Rendimiento neto: 35.000 (no aplica reducción 5/7 % en EDN)
     *  Mínimo personal: 5.550
     *
     *  Estatal sobre 35.000:
     *    tramo 20.200-35.200 (15 %, fijo 2.112,75)
     *    = 2.112,75 + (35.000 − 20.200) × 0,15
     *    = 2.112,75 + 2.220,00 = 4.332,75
     *  Estatal sobre 5.550:
     *    tramo 0-12.450 (9,5 %) = 527,25
     *  Cuota estatal: 4.332,75 − 527,25 = 3.805,50
     *
     *  MD sobre 35.000:
     *    tramo 19.764,81-36.842,71 (12,80 %, fijo 1.809,11)
     *    = 1.809,11 + (35.000 − 19.764,81) × 0,128
     *    = 1.809,11 + 1.950,10 = 3.759,21 (15235.19 × 0.128 = 1950.10432, trunca 1950.10)
     *  MD sobre 5.550:
     *    tramo 0-13.896,71 (8,5 %) = 471,75
     *  Cuota MD: 3.759,21 − 471,75 = 3.287,46
     *
     *  Cuota total: 3.805,50 + 3.287,46 = 7.092,96
     *  Cuota líquida: 7.092,96 (sin deducciones autonómicas; sin descendientes)
     *  Pagos fraccionados (130) ya pagados: 4.000
     *  Resultado: 7.092,96 − 4.000 = 3.092,96 (a ingresar)
     *
     *  Fuente: Ley 35/2006 art. 28-29 + Ley 1/2024 CAM (deflactación).
     */
    public function test_golden_autonomo_edn_50k_madrid_2025(): void
    {
        $result = $this->calc->calculate(new IncomeTaxInput(
            regime: RegimeCode::fromString('EDN'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('MD'),
            taxpayerSituation: TaxpayerSituation::single(),
            economicActivity: new EconomicActivityInput(
                activityCode: '849.7',
                grossRevenue: Money::fromFloat(50000),
                deductibleExpenses: Money::fromFloat(15000),
                quarterlyPaymentsAlreadyPaid: Money::fromFloat(4000),
            ),
        ));

        $this->assertSame('35000.00', $result->netIncome->amount);
        $this->assertSame('3805.50', $result->stateQuota->amount);
        $this->assertSame('3287.46', $result->regionalQuota->amount);
        $this->assertSame('7092.96', $result->totalGross->amount);
        $this->assertSame('7092.96', $result->liquidQuota->amount);
        $this->assertSame('3092.96', $result->result->amount);
    }

    /**
     * EDGE — Rendimiento negativo (gastos > ingresos): se capa a 0 a efectos
     * de base liquidable. El resultado debe ser todo a devolver (los pagos
     * fraccionados se devuelven íntegros).
     */
    public function test_edge_rendimiento_negativo_devuelve_pagos_fraccionados(): void
    {
        $result = $this->calc->calculate(new IncomeTaxInput(
            regime: RegimeCode::fromString('EDN'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('MD'),
            taxpayerSituation: TaxpayerSituation::single(),
            economicActivity: new EconomicActivityInput(
                activityCode: '849.7',
                grossRevenue: Money::fromFloat(20000),
                deductibleExpenses: Money::fromFloat(30000),
                quarterlyPaymentsAlreadyPaid: Money::fromFloat(2000),
            ),
        ));

        // Base = 0 → cuota líquida = 0 → resultado = 0 − 2.000 = −2.000.
        $this->assertSame('0.00', $result->netIncome->amount);
        $this->assertSame('0.00', $result->liquidQuota->amount);
        $this->assertSame('-2000.00', $result->result->amount);
        $this->assertTrue($result->isToRefund());
    }

    public function test_edn_does_not_apply_5_percent_generic_reduction(): void
    {
        $result = $this->calc->calculate(new IncomeTaxInput(
            regime: RegimeCode::fromString('EDN'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('MD'),
            taxpayerSituation: TaxpayerSituation::single(),
            economicActivity: new EconomicActivityInput(
                activityCode: '849.7',
                grossRevenue: Money::fromFloat(50000),
                deductibleExpenses: Money::fromFloat(15000),
                quarterlyPaymentsAlreadyPaid: Money::zero(),
            ),
        ));

        // Sin reducción 5 % en EDN.
        // Buscamos en el breakdown la ausencia de la línea de reducción 5 %.
        $hasGenericReductionLine = collect($result->breakdown->lines)
            ->contains(fn ($l) => str_contains($l->concept, 'genérica'));

        $this->assertFalse($hasGenericReductionLine);
        // Y el rendimiento neto coincide con ingresos − gastos (35.000).
        $this->assertSame('35000.00', $result->netIncome->amount);
    }

    public function test_edn_andalucia_state_fallback_for_regional_scale(): void
    {
        // Caso AN: comprobamos que la cuota autonómica AN aplica correctamente.
        // Ingresos 30.000 - gastos 5.000 = 25.000.
        // Mínimo personal: 5.550.
        //
        // Estatal sobre 25.000:
        //   tramo 20.200-35.200 (15 %, 2.112,75)
        //   = 2.112,75 + (25.000 − 20.200) × 0,15
        //   = 2.112,75 + 720 = 2.832,75
        // Estatal sobre 5.550 = 527,25
        // Cuota estatal: 2.305,50
        //
        // AN sobre 25.000:
        //   tramo 21.000-35.200 (15 %, 2.195)
        //   = 2.195 + (25.000 − 21.000) × 0,15
        //   = 2.195 + 600 = 2.795
        // AN sobre 5.550:
        //   tramo 0-13.000 (9,5 %) = 527,25
        // Cuota AN: 2.267,75
        $result = $this->calc->calculate(new IncomeTaxInput(
            regime: RegimeCode::fromString('EDN'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('AN'),
            taxpayerSituation: TaxpayerSituation::single(),
            economicActivity: new EconomicActivityInput(
                activityCode: '849.7',
                grossRevenue: Money::fromFloat(30000),
                deductibleExpenses: Money::fromFloat(5000),
                quarterlyPaymentsAlreadyPaid: Money::zero(),
            ),
        ));

        $this->assertSame('25000.00', $result->netIncome->amount);
        $this->assertSame('2305.50', $result->stateQuota->amount);
        $this->assertSame('2267.75', $result->regionalQuota->amount);
    }
}
