<?php

namespace Tests\Feature\Tax\Calculators\IncomeTax;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Calculators\IncomeTax\RendimientosTrabajoSimulator;
use Modules\Tax\DTOs\IncomeTax\IncomeTaxInput;
use Modules\Tax\DTOs\IncomeTax\TaxpayerSituation;
use Modules\Tax\DTOs\IncomeTax\WorkIncomeInput;
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
 * Tests del RendimientosTrabajoSimulator (régimen ASALARIADO_GEN).
 *
 * Replica el modelo 100 anual para asalariado puro, con cálculo paso a paso
 * comparable contra la nómina M4 anualizada.
 */
class RendimientosTrabajoSimulatorTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTaxParameters;

    private RendimientosTrabajoSimulator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTaxParameters(2025);
        $repo = $this->app->make(TaxParameterRepository::class);
        $this->calc = new RendimientosTrabajoSimulator(
            new IrpfScaleResolver($repo),
            new PersonalMinimumCalculator($repo),
            new WorkIncomeReductionCalculator($repo),
            new IncomeTaxDeductionsCalculator($repo),
        );
    }

    /**
     * CASO GOLDEN — Soltero 30k Madrid 2025, retenciones 5.000 €.
     *
     * Cálculo paso a paso (Ley 35/2006 art. 17-67):
     *
     *  Bruto anual: 30.000
     *  Cotizaciones SS pagadas: 1.944,99 (calculado por nómina mensual M4)
     *  Rendimiento neto previo: 30.000 − 1.944,99 = 28.055,01
     *  Reducción art. 20 LIRPF: 28.055,01 > 19.747,50 → 0
     *  Rendimiento neto del trabajo: 28.055,01
     *  Mínimo personal: 5.550
     *
     *  Cuota estatal sobre 28.055,01:
     *    tramo 20.200-35.200 (15 %, fijo 2.112,75)
     *    = 2.112,75 + (28.055,01 − 20.200) × 0,15
     *    = 2.112,75 + 1.178,25 = 3.291,00
     *  Cuota estatal sobre 5.550:
     *    tramo 0-12.450 (9,5 %)
     *    = 5.550 × 0,095 = 527,25
     *  Cuota estatal: 3.291,00 − 527,25 = 2.763,75
     *
     *  Cuota MD sobre 28.055,01:
     *    tramo 19.764,81-36.842,71 (12,80 %, fijo 1.809,11)
     *    = 1.809,11 + (28.055,01 − 19.764,81) × 0,128
     *    = 1.809,11 + 1.061,14 = 2.870,25
     *  Cuota MD sobre 5.550:
     *    tramo 0-13.896,71 (8,50 %)
     *    = 5.550 × 0,085 = 471,75
     *  Cuota MD: 2.870,25 − 471,75 = 2.398,50
     *
     *  Cuota íntegra total: 2.763,75 + 2.398,50 = 5.162,25
     *  Sin deducciones autonómicas (sin descendientes < 3 años, sin familia
     *  numerosa).
     *  Cuota líquida: 5.162,25
     *
     *  Pagos a cuenta (retenciones): 5.000
     *  Resultado: 5.162,25 − 5.000 = 162,25 (a ingresar)
     *
     *  Fuente: Ley 35/2006 art. 56-67 (BOE-A-2006-20764),
     *          Ley CAM 1/2024 deflactación (BOCM-20240112-1).
     */
    public function test_golden_soltero_30k_madrid_2025(): void
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

        $this->assertSame('28055.01', $result->netIncome->amount);
        $this->assertSame('2763.75', $result->stateQuota->amount);
        $this->assertSame('2398.50', $result->regionalQuota->amount);
        $this->assertSame('5162.25', $result->totalGross->amount);
        $this->assertSame('5162.25', $result->liquidQuota->amount);
        $this->assertSame('162.25', $result->result->amount);
        $this->assertTrue($result->isToPay());
        $this->assertFalse($result->isToRefund());
    }

    /**
     * CASO GOLDEN — Casado con 2 hijos 60k Valencia 2025, retenciones 12.000.
     *
     *  Bruto: 60.000; SS pagada: 3.819,55 (capada por base máx); previous net = 56.180,45
     *  Reducción art. 20: 0 (>19.747,50)
     *  Neto trabajo: 56.180,45
     *  Mínimo personal+familiar: 5.550 + 2.400 + 2.700 = 10.650
     *
     *  Estatal sobre 56.180,45:
     *    tramo 35.200-60.000 (18,5 %, fijo 4.362,75)
     *    = 4.362,75 + (56.180,45 − 35.200) × 0,185
     *    = 4.362,75 + 3.881,38 = 8.244,13 (20.980,45 × 0,185 = 3.881,3832..., trunca)
     *  Estatal sobre 10.650:
     *    tramo 0-12.450 (9,5 %) = 10.650 × 0,095 = 1.011,75
     *  Cuota estatal: 8.244,13 − 1.011,75 = 7.232,38
     *
     *  VC sobre 56.180,45:
     *    tramo 52.000-65.000 (22,5 %, fijo 7.530)
     *    = 7.530 + 4.180,45 × 0,225
     *    = 7.530 + 940,60 = 8.470,60 (4180,45 × 0,225 = 940,60125 trunca 940,60)
     *  VC sobre 10.650:
     *    tramo 0-12.000 (9 %) = 10.650 × 0,09 = 958,50
     *  Cuota VC: 8.470,60 − 958,50 = 7.512,10
     *
     *  Cuota total: 7.232,38 + 7.512,10 = 14.744,48
     *  Sin deducciones autonómicas (no hay descendientes <3 años; sólo 2 → no fam. numerosa).
     *  Cuota líquida: 14.744,48
     *
     *  Pagos a cuenta: 12.000
     *  Resultado: 14.744,48 − 12.000 = 2.744,48 (a ingresar)
     */
    public function test_golden_casado_60k_valencia_2025_2_hijos(): void
    {
        $result = $this->calc->calculate(new IncomeTaxInput(
            regime: RegimeCode::fromString('ASALARIADO_GEN'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('VC'),
            taxpayerSituation: new TaxpayerSituation(
                married: true,
                spouseHasIncome: true,
                descendants: 2,
            ),
            workIncome: new WorkIncomeInput(
                gross: Money::fromFloat(60000),
                socialSecurityPaid: Money::fromFloat(3819.55),
                irpfWithheld: Money::fromFloat(12000),
            ),
        ));

        $this->assertSame('56180.45', $result->netIncome->amount);
        $this->assertSame('7232.38', $result->stateQuota->amount);
        $this->assertSame('7512.10', $result->regionalQuota->amount);
        $this->assertSame('14744.48', $result->totalGross->amount);
        $this->assertSame('14744.48', $result->liquidQuota->amount);
        $this->assertSame('2744.48', $result->result->amount);
    }

    /**
     * CASO EDGE — A devolver: retenciones excesivas.
     *
     * Bruto 25.000, SS 1.500, retenciones 5.500 (mucho más que la cuota líquida).
     * El resultado debe ser negativo (a devolver).
     */
    public function test_edge_resultado_a_devolver_cuando_retenciones_excesivas(): void
    {
        $result = $this->calc->calculate(new IncomeTaxInput(
            regime: RegimeCode::fromString('ASALARIADO_GEN'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('MD'),
            taxpayerSituation: TaxpayerSituation::single(),
            workIncome: new WorkIncomeInput(
                gross: Money::fromFloat(25000),
                socialSecurityPaid: Money::fromFloat(1500),
                irpfWithheld: Money::fromFloat(5500),
            ),
        ));

        $this->assertTrue($result->isToRefund());
        $this->assertFalse($result->isToPay());
        $this->assertStringStartsWith('-', $result->result->amount);
    }

    /**
     * Estatal: aplicar escala estatal sin autonómica si region=STATE.
     */
    public function test_state_only_skips_regional_quota(): void
    {
        $result = $this->calc->calculate(new IncomeTaxInput(
            regime: RegimeCode::fromString('ASALARIADO_GEN'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::state(),
            taxpayerSituation: TaxpayerSituation::single(),
            workIncome: new WorkIncomeInput(
                gross: Money::fromFloat(30000),
                socialSecurityPaid: Money::fromFloat(1944.99),
                irpfWithheld: Money::fromFloat(0),
            ),
        ));

        $this->assertSame('0.00', $result->regionalQuota->amount);
        $this->assertSame('2763.75', $result->stateQuota->amount);
    }

    public function test_breakdown_includes_disclaimer(): void
    {
        $result = $this->calc->calculate(new IncomeTaxInput(
            regime: RegimeCode::fromString('ASALARIADO_GEN'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('MD'),
            taxpayerSituation: TaxpayerSituation::single(),
            workIncome: new WorkIncomeInput(
                gross: Money::fromFloat(30000),
                socialSecurityPaid: Money::fromFloat(1944.99),
                irpfWithheld: Money::fromFloat(0),
            ),
        ));

        $this->assertArrayHasKey('disclaimer', $result->breakdown->meta);
        $this->assertStringContainsString('informativa', (string) $result->breakdown->meta['disclaimer']);
    }

    public function test_breakdown_lines_have_legal_references(): void
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

        $linesWithRef = collect($result->breakdown->lines)
            ->filter(fn ($l) => $l->legalReference !== null)
            ->count();

        $this->assertGreaterThan(5, $linesWithRef);
    }
}
