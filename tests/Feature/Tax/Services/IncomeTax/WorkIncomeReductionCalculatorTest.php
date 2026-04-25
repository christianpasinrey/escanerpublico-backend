<?php

namespace Tests\Feature\Tax\Services\IncomeTax;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Services\IncomeTax\WorkIncomeReductionCalculator;
use Modules\Tax\Services\TaxParameterRepository;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Tests\TestCase;
use Tests\Unit\Tax\Calculators\Payroll\SeedsTaxParameters;

/**
 * Tests del WorkIncomeReductionCalculator.
 *
 * Reducción art. 20 LIRPF (umbrales 2023+):
 *   - rdto neto previo ≤ 14.852 €  → reducción = 6.498 €
 *   - 14.852 < rdto ≤ 19.747,50    → 6.498 - 1,14 × (rdto - 14.852)
 *   - rdto > 19.747,50             → 0
 *
 * Fuente: Ley 31/2022 art. 59 (BOE-A-2022-22128).
 */
class WorkIncomeReductionCalculatorTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTaxParameters;

    private WorkIncomeReductionCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTaxParameters(2025);
        $this->calc = new WorkIncomeReductionCalculator(
            $this->app->make(TaxParameterRepository::class),
        );
    }

    public function test_reduction_max_when_previous_net_below_threshold(): void
    {
        $reduction = $this->calc->calculate(
            FiscalYear::fromInt(2025),
            Money::fromFloat(14000),
        );

        // 14000 ≤ 14.852 → reducción = 6.498 €
        $this->assertSame('6498.00', $reduction->amount);
    }

    public function test_reduction_zero_when_previous_net_above_max_threshold(): void
    {
        $reduction = $this->calc->calculate(
            FiscalYear::fromInt(2025),
            Money::fromFloat(20000),
        );

        // 20.000 > 19.747,50 → 0
        $this->assertSame('0.00', $reduction->amount);
    }

    public function test_reduction_decreasing_in_intermediate_range(): void
    {
        $reduction = $this->calc->calculate(
            FiscalYear::fromInt(2025),
            Money::fromFloat(17000),
        );

        // 6.498 - 1,14 × (17.000 - 14.852) = 6.498 - 2.448,72 = 4.049,28
        $this->assertSame('4049.28', $reduction->amount);
    }

    public function test_zero_when_previous_net_zero(): void
    {
        $reduction = $this->calc->calculate(
            FiscalYear::fromInt(2025),
            Money::zero(),
        );

        $this->assertSame('0.00', $reduction->amount);
    }

    public function test_zero_when_previous_net_negative(): void
    {
        $reduction = $this->calc->calculate(
            FiscalYear::fromInt(2025),
            new Money('-100.00'),
        );

        $this->assertSame('0.00', $reduction->amount);
    }

    public function test_reduction_does_not_exceed_previous_net(): void
    {
        // Si rendimiento previo es muy bajo (ej. 100 €), la reducción 6.498
        // dejaría rendimiento negativo. Debe capar a 100.
        $reduction = $this->calc->calculate(
            FiscalYear::fromInt(2025),
            Money::fromFloat(100),
        );

        $this->assertSame('100.00', $reduction->amount);
    }

    public function test_returns_zero_when_parameters_missing(): void
    {
        // No sembramos 2024; debería devolver 0 sin lanzar.
        $reduction = $this->calc->calculate(
            FiscalYear::fromInt(2024),
            Money::fromFloat(14000),
        );

        $this->assertSame('0.00', $reduction->amount);
    }
}
