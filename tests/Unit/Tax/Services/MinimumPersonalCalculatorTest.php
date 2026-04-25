<?php

namespace Tests\Unit\Tax\Services;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\DTOs\Payroll\ContractType;
use Modules\Tax\DTOs\Payroll\PayrollInput;
use Modules\Tax\Services\MinimumPersonalCalculator;
use Modules\Tax\Services\TaxParameterRepository;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegionCode;
use Tests\TestCase;
use Tests\Unit\Tax\Calculators\Payroll\SeedsTaxParameters;

class MinimumPersonalCalculatorTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTaxParameters;

    private MinimumPersonalCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedTaxParameters(2025);
        $this->calculator = new MinimumPersonalCalculator(
            $this->app->make(TaxParameterRepository::class),
        );
    }

    private function input(array $overrides = []): PayrollInput
    {
        return new PayrollInput(
            grossAnnual: $overrides['grossAnnual'] ?? Money::fromFloat(30000),
            paymentsCount: $overrides['paymentsCount'] ?? 14,
            region: $overrides['region'] ?? RegionCode::fromCode('MD'),
            year: $overrides['year'] ?? FiscalYear::fromInt(2025),
            contractType: $overrides['contractType'] ?? ContractType::Indefinido,
            birthDate: $overrides['birthDate'] ?? null,
            disabilityPercent: $overrides['disabilityPercent'] ?? null,
            descendants: $overrides['descendants'] ?? 0,
            descendantsUnder3: $overrides['descendantsUnder3'] ?? 0,
            ascendantsOver65Living: $overrides['ascendantsOver65Living'] ?? 0,
            ascendantsDisabledLiving: $overrides['ascendantsDisabledLiving'] ?? 0,
            married: $overrides['married'] ?? false,
            spouseHasIncome: $overrides['spouseHasIncome'] ?? true,
        );
    }

    public function test_baseline_is_5550(): void
    {
        $minimum = $this->calculator->calculate($this->input());

        // Mínimo personal art. 57 LIRPF
        $this->assertSame('5550.00', $minimum->amount);
    }

    public function test_one_descendant_adds_2400(): void
    {
        $minimum = $this->calculator->calculate($this->input(['descendants' => 1]));

        // 5550 + 2400 = 7950
        $this->assertSame('7950.00', $minimum->amount);
    }

    public function test_two_descendants_progressive(): void
    {
        $minimum = $this->calculator->calculate($this->input(['descendants' => 2]));

        // 5550 + 2400 + 2700 = 10650
        $this->assertSame('10650.00', $minimum->amount);
    }

    public function test_three_descendants_progressive(): void
    {
        $minimum = $this->calculator->calculate($this->input(['descendants' => 3]));

        // 5550 + 2400 + 2700 + 4000 = 14650
        $this->assertSame('14650.00', $minimum->amount);
    }

    public function test_four_descendants_progressive(): void
    {
        $minimum = $this->calculator->calculate($this->input(['descendants' => 4]));

        // 5550 + 2400 + 2700 + 4000 + 4500 = 19150
        $this->assertSame('19150.00', $minimum->amount);
    }

    public function test_descendants_under_3_increment(): void
    {
        $minimum = $this->calculator->calculate($this->input([
            'descendants' => 2,
            'descendantsUnder3' => 1,
        ]));

        // 5550 + 2400 + 2700 + 2800 (incremento <3) = 13450
        $this->assertSame('13450.00', $minimum->amount);
    }

    public function test_ascendant_over_65(): void
    {
        $minimum = $this->calculator->calculate($this->input([
            'ascendantsOver65Living' => 1,
        ]));

        // 5550 + 1150 = 6700
        $this->assertSame('6700.00', $minimum->amount);
    }

    public function test_two_ascendants_over_65(): void
    {
        $minimum = $this->calculator->calculate($this->input([
            'ascendantsOver65Living' => 2,
        ]));

        // 5550 + 1150*2 = 7850
        $this->assertSame('7850.00', $minimum->amount);
    }

    public function test_age_over_65_increment(): void
    {
        $birth = CarbonImmutable::create(1958, 6, 15); // 67 años en 2025
        $minimum = $this->calculator->calculate($this->input([
            'birthDate' => $birth,
        ]));

        // 5550 + 1150 (incremento >65 art. 57) = 6700
        $this->assertSame('6700.00', $minimum->amount);
    }

    public function test_age_over_75_increment(): void
    {
        $birth = CarbonImmutable::create(1948, 6, 15); // 77 años en 2025
        $minimum = $this->calculator->calculate($this->input([
            'birthDate' => $birth,
        ]));

        // 5550 + 1150 (>65) + 1400 (>75) = 8100
        $this->assertSame('8100.00', $minimum->amount);
    }

    public function test_disability_general(): void
    {
        $minimum = $this->calculator->calculate($this->input([
            'disabilityPercent' => 33,
        ]));

        // 5550 + 3000 (discapacidad general 33-65) = 8550
        $this->assertSame('8550.00', $minimum->amount);
    }

    public function test_disability_grave(): void
    {
        $minimum = $this->calculator->calculate($this->input([
            'disabilityPercent' => 70,
        ]));

        // 5550 + 9000 (discapacidad ≥65 %) = 14550
        $this->assertSame('14550.00', $minimum->amount);
    }

    public function test_disability_below_threshold_no_increment(): void
    {
        $minimum = $this->calculator->calculate($this->input([
            'disabilityPercent' => 20, // < 33
        ]));

        $this->assertSame('5550.00', $minimum->amount);
    }

    public function test_combined_factors(): void
    {
        $birth = CarbonImmutable::create(1958, 6, 15); // 67 años en 2025
        $minimum = $this->calculator->calculate($this->input([
            'birthDate' => $birth,
            'descendants' => 2,
            'descendantsUnder3' => 0,
            'ascendantsOver65Living' => 1,
            'disabilityPercent' => 35,
        ]));

        // 5550 + 1150 (>65) + 2400 + 2700 + 1150 (asc) + 3000 (disc gen) = 15950
        $this->assertSame('15950.00', $minimum->amount);
    }
}
