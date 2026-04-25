<?php

namespace Tests\Unit\Tax\Services\IncomeTax;

use Database\Seeders\Modules\Tax\IncomeTax\EoModulesDataProvider;
use InvalidArgumentException;
use Modules\Tax\Services\IncomeTax\EoModulesCalculator;
use Modules\Tax\ValueObjects\FiscalYear;
use Tests\TestCase;

class EoModulesCalculatorTest extends TestCase
{
    private EoModulesCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new EoModulesCalculator;
    }

    public function test_calculates_yield_for_taxi_with_standard_modules(): void
    {
        // Taxi (721.2): 1 personal_no_asalariado (titular) + 30 unidades (30k km).
        // Valor 2025 (BOE-A-2024-26896):
        //   personal_no_asalariado: 4076,60 €/persona
        //   distancia_km: 24,06 €/1.000 km
        // → 4076,60 + 30 × 24,06 = 4076,60 + 721,80 = 4798,40 €
        $yield = $this->calc->calculatePreviousYield(
            FiscalYear::fromInt(2025),
            '721.2',
            [
                'personal_no_asalariado' => 1,
                'distancia_km' => 30,
            ],
        );

        $this->assertSame('4798.40', $yield->amount);
    }

    public function test_calculates_yield_for_bar_673_2(): void
    {
        // Bar (673.2) en 2025:
        //   personal_no_asalariado: 9551,05
        //   personal_asalariado: 1136,08
        //   kw_potencia: 173,65
        //   mesas: 91,96
        //
        // Bar pequeño: 1 titular + 1 empleado + 5 kW + 4 mesas
        //   = 9551,05 + 1136,08 + 5 × 173,65 + 4 × 91,96
        //   = 9551,05 + 1136,08 + 868,25 + 367,84
        //   = 11.923,22
        $yield = $this->calc->calculatePreviousYield(
            FiscalYear::fromInt(2025),
            '673.2',
            [
                'personal_no_asalariado' => 1,
                'personal_asalariado' => 1,
                'kw_potencia' => 5,
                'mesas' => 4,
            ],
        );

        $this->assertSame('11923.22', $yield->amount);
    }

    public function test_calculates_yield_for_peluqueria(): void
    {
        // Peluquería (972.1) 2025:
        //   personal_no_asalariado: 9706,83
        //   personal_asalariado: 3110,42
        //   kw_potencia: 117,63
        //   m2_local: 38,57
        //
        // 1 titular + 0 empleado + 6 kW + 30 m²:
        //   = 9706,83 + 0 + 705,78 + 1157,10
        //   = 11.569,71
        $yield = $this->calc->calculatePreviousYield(
            FiscalYear::fromInt(2025),
            '972.1',
            [
                'personal_no_asalariado' => 1,
                'kw_potencia' => 6,
                'm2_local' => 30,
            ],
        );

        $this->assertSame('11569.71', $yield->amount);
    }

    public function test_returns_zero_when_modules_data_empty(): void
    {
        $yield = $this->calc->calculatePreviousYield(
            FiscalYear::fromInt(2025),
            '673.2',
            [],
        );

        $this->assertSame('0.00', $yield->amount);
    }

    public function test_returns_zero_when_modules_data_null(): void
    {
        $yield = $this->calc->calculatePreviousYield(
            FiscalYear::fromInt(2025),
            '673.2',
            null,
        );

        $this->assertSame('0.00', $yield->amount);
    }

    public function test_throws_when_activity_not_supported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no está cubierto en MVP/');

        $this->calc->calculatePreviousYield(
            FiscalYear::fromInt(2025),
            '999.999',
            null,
        );
    }

    public function test_module_lines_returns_breakdown(): void
    {
        $lines = $this->calc->moduleLines(
            FiscalYear::fromInt(2025),
            '721.2',
            [
                'personal_no_asalariado' => 1,
                'distancia_km' => 30,
            ],
        );

        $this->assertNotEmpty($lines);
        $titular = collect($lines)->firstWhere('key', 'personal_no_asalariado');
        $this->assertNotNull($titular);
        $this->assertSame(1.0, $titular['units']);
        $this->assertSame('4076.60', $titular['amount']->amount);
    }

    public function test_legal_reference_returns_boe_url(): void
    {
        $ref = $this->calc->legalReference(FiscalYear::fromInt(2025), '721.2');

        $this->assertStringStartsWith('https://www.boe.es', $ref);
    }

    public function test_legal_reference_falls_back_when_not_supported(): void
    {
        $ref = $this->calc->legalReference(FiscalYear::fromInt(2025), '999.999');

        $this->assertStringStartsWith('https://www.boe.es', $ref);
    }

    public function test_is_supported_returns_true_for_known_codes(): void
    {
        $this->assertTrue($this->calc->isSupported(FiscalYear::fromInt(2025), '721.2'));
        $this->assertTrue($this->calc->isSupported(FiscalYear::fromInt(2025), '673.2'));
        $this->assertTrue($this->calc->isSupported(FiscalYear::fromInt(2025), '972.1'));
    }

    public function test_is_supported_returns_false_for_unknown_codes(): void
    {
        $this->assertFalse($this->calc->isSupported(FiscalYear::fromInt(2025), '000.0'));
    }

    public function test_supported_activities_includes_top_20(): void
    {
        $codes = EoModulesDataProvider::supportedActivityCodes(2025);

        // Verificamos top-20 mínimo.
        $this->assertGreaterThanOrEqual(20, count($codes));
        $this->assertContains('721.2', $codes); // taxi
        $this->assertContains('673.2', $codes); // bar
        $this->assertContains('972.1', $codes); // peluquería
    }
}
