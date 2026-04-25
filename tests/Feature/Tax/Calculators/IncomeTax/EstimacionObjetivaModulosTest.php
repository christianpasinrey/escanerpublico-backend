<?php

namespace Tests\Feature\Tax\Calculators\IncomeTax;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Tax\Calculators\IncomeTax\EstimacionObjetivaModulos;
use Modules\Tax\DTOs\IncomeTax\EconomicActivityInput;
use Modules\Tax\DTOs\IncomeTax\IncomeTaxInput;
use Modules\Tax\DTOs\IncomeTax\TaxpayerSituation;
use Modules\Tax\Services\IncomeTax\EoModulesCalculator;
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
 * Tests del EstimacionObjetivaModulos (EO).
 *
 * Aplicable a las actividades del Anexo II Orden HFP anual (top-20 en MVP).
 * Rendimiento neto = Σ (signo × valor unidad) − reducción 5 % (DT 32ª LIRPF).
 *
 * Fuente: Ley 35/2006 art. 32 + Orden HFP/1397/2024 (BOE-A-2024-26896).
 */
class EstimacionObjetivaModulosTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTaxParameters;

    private EstimacionObjetivaModulos $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTaxParameters(2025);
        $repo = $this->app->make(TaxParameterRepository::class);
        $this->calc = new EstimacionObjetivaModulos(
            new IrpfScaleResolver($repo),
            new PersonalMinimumCalculator($repo),
            new WorkIncomeReductionCalculator($repo),
            new IncomeTaxDeductionsCalculator($repo),
            new EoModulesCalculator,
        );
    }

    /**
     * GOLDEN — Taxi (epígrafe IAE 721.2) Andalucía 2025, 1 titular + 30k km.
     *
     *  Módulos 2025 (Orden HFP/1397/2024):
     *    personal_no_asalariado: 4.076,60 €
     *    distancia_km (cada 1.000 km): 24,06 €
     *
     *  Rendimiento previo: 4.076,60 + 30 × 24,06 = 4.076,60 + 721,80 = 4.798,40
     *  Reducción 5 % (DT 32ª): 4.798,40 × 0,05 = 239,92
     *  Rendimiento neto: 4.798,40 − 239,92 = 4.558,48
     *
     *  Mínimo personal: 5.550
     *
     *  Estatal sobre 4.558,48:
     *    tramo 0-12.450 (9,5 %) = 4.558,48 × 0,095 = 433,05 (433.0556 trunca 433.05)
     *  Estatal sobre 5.550:
     *    tramo 0-12.450 (9,5 %) = 5.550 × 0,095 = 527,25
     *  Cuota estatal: 433,05 − 527,25 = NEGATIVO → cap a 0.
     *
     *  AN sobre 4.558,48:
     *    tramo 0-13.000 (9,5 %) = 4.558,48 × 0,095 = 433,05
     *  AN sobre 5.550:
     *    tramo 0-13.000 (9,5 %) = 527,25
     *  Cuota AN: 0.
     *
     *  Cuota total: 0
     *  Cuota líquida: 0
     *
     *  Pagos fraccionados (modelo 131): 200
     *  Resultado: 0 − 200 = −200 (a devolver).
     *
     *  Fuente: Anexo II Orden HFP/1397/2024 (BOE-A-2024-26896).
     */
    public function test_golden_taxi_721_2_andalucia_2025(): void
    {
        $result = $this->calc->calculate(new IncomeTaxInput(
            regime: RegimeCode::fromString('EO'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('AN'),
            taxpayerSituation: TaxpayerSituation::single(),
            economicActivity: new EconomicActivityInput(
                activityCode: '721.2',
                grossRevenue: Money::fromFloat(40000), // sólo informativo
                deductibleExpenses: Money::zero(),
                quarterlyPaymentsAlreadyPaid: Money::fromFloat(200),
                eoModulesData: [
                    'personal_no_asalariado' => 1,
                    'distancia_km' => 30,
                ],
            ),
        ));

        $this->assertSame('4558.48', $result->netIncome->amount);
        $this->assertSame('0.00', $result->stateQuota->amount);
        $this->assertSame('0.00', $result->regionalQuota->amount);
        $this->assertSame('0.00', $result->liquidQuota->amount);
        $this->assertSame('-200.00', $result->result->amount);
        $this->assertTrue($result->isToRefund());
    }

    /**
     * GOLDEN — Bar (epígrafe 673.2) Madrid 2025, autónomo titular + 1 empleado.
     *
     *  Módulos 2025:
     *    personal_no_asalariado: 9.551,05
     *    personal_asalariado:    1.136,08
     *    kw_potencia (5 kW):     5 × 173,65 = 868,25
     *    mesas (4 mesas):        4 × 91,96 = 367,84
     *
     *  Rendimiento previo: 9.551,05 + 1.136,08 + 868,25 + 367,84 = 11.923,22
     *  Reducción 5 %: 11.923,22 × 0,05 = 596,16 (596,161 trunca 596,16)
     *  Rendimiento neto: 11.923,22 − 596,16 = 11.327,06
     *
     *  Mínimo personal: 5.550
     *
     *  Estatal sobre 11.327,06:
     *    tramo 0-12.450 (9,5 %) = 11.327,06 × 0,095 = 1.076,07 (1076,07070 trunca)
     *  Estatal sobre 5.550: 527,25
     *  Cuota estatal: 1.076,07 − 527,25 = 548,82
     *
     *  MD sobre 11.327,06:
     *    tramo 0-13.896,71 (8,5 %) = 11.327,06 × 0,085 = 962,79 (962,80001 → bcmath trunca a 962,80)
     *
     *  Espera realmente 962.79 o 962.80? bcmath trunca. 11327.06 * 0.085:
     *    = 962.80001
     *    Pero la operación es applyRate: multiply por rate.asDecimal()
     *    rate.asDecimal con escala 8 = 0.08500000
     *    bcmul('11327.06', '0.08500000', 2) = ?
     *    11327.06 × 0.085 = 962.80001 → trunca a 962.80
     *
     *  MD sobre 5.550:
     *    tramo 0-13.896,71 (8,5 %) = 5.550 × 0,085 = 471,75
     *  Cuota MD: 962,80 − 471,75 = 491,05
     *
     *  Cuota total: 548,82 + 491,05 = 1.039,87
     *  Cuota líquida: 1.039,87 (sin deducciones autonómicas)
     *
     *  Pagos fraccionados: 0
     *  Resultado: 1.039,87 (a ingresar).
     */
    public function test_golden_bar_673_2_madrid_2025(): void
    {
        $result = $this->calc->calculate(new IncomeTaxInput(
            regime: RegimeCode::fromString('EO'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('MD'),
            taxpayerSituation: TaxpayerSituation::single(),
            economicActivity: new EconomicActivityInput(
                activityCode: '673.2',
                grossRevenue: Money::fromFloat(60000),
                deductibleExpenses: Money::zero(),
                quarterlyPaymentsAlreadyPaid: Money::zero(),
                eoModulesData: [
                    'personal_no_asalariado' => 1,
                    'personal_asalariado' => 1,
                    'kw_potencia' => 5,
                    'mesas' => 4,
                ],
            ),
        ));

        $this->assertSame('11327.06', $result->netIncome->amount);
        $this->assertSame('548.82', $result->stateQuota->amount);
        $this->assertSame('491.05', $result->regionalQuota->amount);
        $this->assertSame('1039.87', $result->liquidQuota->amount);
        $this->assertSame('1039.87', $result->result->amount);
    }

    public function test_eo_rejects_unsupported_activity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no está cubierto en MVP/');

        $this->calc->calculate(new IncomeTaxInput(
            regime: RegimeCode::fromString('EO'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('AN'),
            taxpayerSituation: TaxpayerSituation::single(),
            economicActivity: new EconomicActivityInput(
                activityCode: '999.999',
                grossRevenue: Money::fromFloat(30000),
                deductibleExpenses: Money::zero(),
                quarterlyPaymentsAlreadyPaid: Money::zero(),
                eoModulesData: ['personal_no_asalariado' => 1],
            ),
        ));
    }

    public function test_eo_breakdown_includes_module_lines(): void
    {
        $result = $this->calc->calculate(new IncomeTaxInput(
            regime: RegimeCode::fromString('EO'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('AN'),
            taxpayerSituation: TaxpayerSituation::single(),
            economicActivity: new EconomicActivityInput(
                activityCode: '721.2',
                grossRevenue: Money::fromFloat(40000),
                deductibleExpenses: Money::zero(),
                quarterlyPaymentsAlreadyPaid: Money::zero(),
                eoModulesData: [
                    'personal_no_asalariado' => 1,
                    'distancia_km' => 30,
                ],
            ),
        ));

        $modulesLines = collect($result->breakdown->lines)
            ->filter(fn ($l) => str_starts_with($l->concept, 'Módulo:'))
            ->count();

        $this->assertGreaterThanOrEqual(2, $modulesLines);
    }

    public function test_eo_breakdown_includes_5_percent_reduction(): void
    {
        $result = $this->calc->calculate(new IncomeTaxInput(
            regime: RegimeCode::fromString('EO'),
            year: FiscalYear::fromInt(2025),
            region: RegionCode::fromCode('AN'),
            taxpayerSituation: TaxpayerSituation::single(),
            economicActivity: new EconomicActivityInput(
                activityCode: '673.2',
                grossRevenue: Money::fromFloat(50000),
                deductibleExpenses: Money::zero(),
                quarterlyPaymentsAlreadyPaid: Money::zero(),
                eoModulesData: ['personal_no_asalariado' => 1, 'kw_potencia' => 3],
            ),
        ));

        $hasReductionLine = collect($result->breakdown->lines)
            ->contains(fn ($l) => str_contains($l->concept, '5 %'));
        $this->assertTrue($hasReductionLine);
    }
}
