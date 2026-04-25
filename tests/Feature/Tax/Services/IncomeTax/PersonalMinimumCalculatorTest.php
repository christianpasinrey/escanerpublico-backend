<?php

namespace Tests\Feature\Tax\Services\IncomeTax;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\DTOs\IncomeTax\TaxpayerSituation;
use Modules\Tax\Services\IncomeTax\PersonalMinimumCalculator;
use Modules\Tax\Services\TaxParameterRepository;
use Modules\Tax\ValueObjects\FiscalYear;
use Tests\TestCase;
use Tests\Unit\Tax\Calculators\Payroll\SeedsTaxParameters;

/**
 * Tests del PersonalMinimumCalculator (variante anual del IRPF).
 *
 * Mínimos 2025 (art. 56-61 LIRPF):
 *  - Personal: 5.550 €
 *  - +65: +1.150 €
 *  - +75: +1.400 € adicional
 *  - 1º descendiente: 2.400 €
 *  - 2º: 2.700 €
 *  - 3º: 4.000 €
 *  - 4º+: 4.500 €
 *  - <3 años: +2.800 € por hijo
 *  - Ascendiente >65 conviviente: 1.150 €
 *  - Discapacidad <65 %: +3.000 €
 *  - Discapacidad ≥65 %: +9.000 €
 */
class PersonalMinimumCalculatorTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTaxParameters;

    private PersonalMinimumCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTaxParameters(2025);
        $this->calc = new PersonalMinimumCalculator(
            $this->app->make(TaxParameterRepository::class),
        );
    }

    public function test_single_taxpayer_returns_5550(): void
    {
        $result = $this->calc->calculate(
            FiscalYear::fromInt(2025),
            TaxpayerSituation::single(),
        );

        $this->assertSame('5550.00', $result->amount);
    }

    public function test_taxpayer_over_65_adds_1150(): void
    {
        $result = $this->calc->calculate(
            FiscalYear::fromInt(2025),
            new TaxpayerSituation(ageAtYearEnd: 70),
        );

        // 5550 + 1150 = 6700
        $this->assertSame('6700.00', $result->amount);
    }

    public function test_taxpayer_over_75_adds_1150_plus_1400(): void
    {
        $result = $this->calc->calculate(
            FiscalYear::fromInt(2025),
            new TaxpayerSituation(ageAtYearEnd: 80),
        );

        // 5550 + 1150 + 1400 = 8100
        $this->assertSame('8100.00', $result->amount);
    }

    public function test_two_descendants_minimum(): void
    {
        $result = $this->calc->calculate(
            FiscalYear::fromInt(2025),
            new TaxpayerSituation(descendants: 2),
        );

        // 5550 + 2400 + 2700 = 10650
        $this->assertSame('10650.00', $result->amount);
    }

    public function test_descendants_under_3_increment(): void
    {
        $result = $this->calc->calculate(
            FiscalYear::fromInt(2025),
            new TaxpayerSituation(descendants: 1, descendantsUnder3: 1),
        );

        // 5550 + 2400 + 2800 (under 3) = 10750
        $this->assertSame('10750.00', $result->amount);
    }

    public function test_one_ascendant_over_65(): void
    {
        $result = $this->calc->calculate(
            FiscalYear::fromInt(2025),
            new TaxpayerSituation(ascendantsOver65Living: 1),
        );

        // 5550 + 1150 = 6700
        $this->assertSame('6700.00', $result->amount);
    }

    public function test_disability_general(): void
    {
        $result = $this->calc->calculate(
            FiscalYear::fromInt(2025),
            new TaxpayerSituation(disabilityPercent: 35),
        );

        // 5550 + 3000 = 8550
        $this->assertSame('8550.00', $result->amount);
    }

    public function test_disability_grave(): void
    {
        $result = $this->calc->calculate(
            FiscalYear::fromInt(2025),
            new TaxpayerSituation(disabilityPercent: 70),
        );

        // 5550 + 9000 = 14550
        $this->assertSame('14550.00', $result->amount);
    }

    public function test_disability_below_threshold_does_not_add(): void
    {
        $result = $this->calc->calculate(
            FiscalYear::fromInt(2025),
            new TaxpayerSituation(disabilityPercent: 20),
        );

        $this->assertSame('5550.00', $result->amount);
    }

    public function test_complex_family(): void
    {
        // Casado, 3 hijos (1 < 3 años), 1 ascendiente >65, discapacidad 40%
        $result = $this->calc->calculate(
            FiscalYear::fromInt(2025),
            new TaxpayerSituation(
                married: true,
                descendants: 3,
                descendantsUnder3: 1,
                ascendantsOver65Living: 1,
                disabilityPercent: 40,
            ),
        );

        // 5550 (personal)
        // + 2400 + 2700 + 4000 (3 descendientes)
        // + 2800 (uno bajo 3 años)
        // + 1150 (ascendiente)
        // + 3000 (discapacidad general)
        // = 21600
        $this->assertSame('21600.00', $result->amount);
    }
}
