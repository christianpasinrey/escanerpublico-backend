<?php

namespace Tests\Feature\Tax\Services\IncomeTax;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\DTOs\IncomeTax\TaxpayerSituation;
use Modules\Tax\Services\IncomeTax\IncomeTaxDeductionsCalculator;
use Modules\Tax\Services\TaxParameterRepository;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegionCode;
use Tests\TestCase;
use Tests\Unit\Tax\Calculators\Payroll\SeedsTaxParameters;

class IncomeTaxDeductionsCalculatorTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTaxParameters;

    private IncomeTaxDeductionsCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTaxParameters(2025);
        $this->calc = new IncomeTaxDeductionsCalculator(
            $this->app->make(TaxParameterRepository::class),
        );
    }

    public function test_donativos_below_250_uses_80_percent(): void
    {
        // Donado 200 € → 80 % = 160 €
        $deduction = $this->calc->donativosDeduction(
            Money::fromFloat(200),
            Money::fromFloat(30000),
        );

        $this->assertSame('160.00', $deduction->amount);
    }

    public function test_donativos_above_250_combines_brackets(): void
    {
        // Donado 1.000 €:
        //   Primeros 250 al 80 % = 200
        //   Exceso 750 al 40 % = 300
        //   Total = 500
        $deduction = $this->calc->donativosDeduction(
            Money::fromFloat(1000),
            Money::fromFloat(30000),
        );

        $this->assertSame('500.00', $deduction->amount);
    }

    public function test_donativos_capped_at_10_percent_of_taxable_base(): void
    {
        // Donado 5.000 €, base liquidable 10.000 €:
        //   80 % primeros 250 = 200
        //   40 % de 4.750 = 1.900
        //   Total bruto = 2.100
        //   Tope = 10 % × 10.000 = 1.000 → cap.
        $deduction = $this->calc->donativosDeduction(
            Money::fromFloat(5000),
            Money::fromFloat(10000),
        );

        $this->assertSame('1000.00', $deduction->amount);
    }

    public function test_donativos_zero_when_no_donations(): void
    {
        $deduction = $this->calc->donativosDeduction(
            Money::zero(),
            Money::fromFloat(30000),
        );

        $this->assertSame('0.00', $deduction->amount);
    }

    public function test_vivienda_historica_zero_when_not_eligible(): void
    {
        $deduction = $this->calc->viviendaHistoricaDeduction(
            Money::fromFloat(8000),
            false,
        );

        $this->assertSame('0.00', $deduction->amount);
    }

    public function test_vivienda_historica_15_percent_below_cap(): void
    {
        // Pagado 5.000 € → 15 % = 750
        $deduction = $this->calc->viviendaHistoricaDeduction(
            Money::fromFloat(5000),
            true,
        );

        $this->assertSame('750.00', $deduction->amount);
    }

    public function test_vivienda_historica_caps_at_9040(): void
    {
        // Pagado 12.000 € → cap 9.040 → 15 % × 9.040 = 1.356
        $deduction = $this->calc->viviendaHistoricaDeduction(
            Money::fromFloat(12000),
            true,
        );

        $this->assertSame('1356.00', $deduction->amount);
    }

    public function test_autonomica_nacimiento_madrid(): void
    {
        // Madrid 600 € por hijo. 1 hijo < 3 años → 600
        $deduction = $this->calc->autonomicaNacimientoAdopcion(
            FiscalYear::fromInt(2025),
            RegionCode::fromCode('MD'),
            new TaxpayerSituation(descendants: 1, descendantsUnder3: 1),
        );

        $this->assertSame('600.00', $deduction->amount);
    }

    public function test_autonomica_nacimiento_zero_when_no_children_under_3(): void
    {
        $deduction = $this->calc->autonomicaNacimientoAdopcion(
            FiscalYear::fromInt(2025),
            RegionCode::fromCode('MD'),
            new TaxpayerSituation(descendants: 2, descendantsUnder3: 0),
        );

        $this->assertSame('0.00', $deduction->amount);
    }

    public function test_autonomica_familia_numerosa_madrid_with_3_descendants(): void
    {
        // Madrid 1.200 € familia numerosa.
        $deduction = $this->calc->autonomicaFamiliaNumerosa(
            FiscalYear::fromInt(2025),
            RegionCode::fromCode('MD'),
            new TaxpayerSituation(descendants: 3),
        );

        $this->assertSame('1200.00', $deduction->amount);
    }

    public function test_autonomica_familia_numerosa_zero_below_3(): void
    {
        $deduction = $this->calc->autonomicaFamiliaNumerosa(
            FiscalYear::fromInt(2025),
            RegionCode::fromCode('MD'),
            new TaxpayerSituation(descendants: 2),
        );

        $this->assertSame('0.00', $deduction->amount);
    }

    public function test_autonomica_zero_when_state(): void
    {
        $deduction = $this->calc->autonomicaFamiliaNumerosa(
            FiscalYear::fromInt(2025),
            RegionCode::state(),
            new TaxpayerSituation(descendants: 5),
        );

        $this->assertSame('0.00', $deduction->amount);
    }

    public function test_apply_all_aggregates_lines_and_total(): void
    {
        $bundle = $this->calc->applyAll(
            FiscalYear::fromInt(2025),
            RegionCode::fromCode('MD'),
            new TaxpayerSituation(descendants: 3, descendantsUnder3: 1),
        );

        $this->assertNotEmpty($bundle['lines']);
        $this->assertGreaterThan(0, (float) $bundle['total']->amount);
        // 600 (nacimiento) + 1200 (familia numerosa) = 1800
        $this->assertSame('1800.00', $bundle['total']->amount);
    }
}
