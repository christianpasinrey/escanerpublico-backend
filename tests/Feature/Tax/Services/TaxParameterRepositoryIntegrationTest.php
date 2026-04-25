<?php

namespace Tests\Feature\Tax\Services;

use Database\Seeders\Modules\Tax\Parameters\TaxParameters2025Seeder;
use Database\Seeders\Modules\Tax\Parameters\TaxParameters2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Services\TaxParameterRepository;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\RegionCode;
use Tests\TestCase;

class TaxParameterRepositoryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private TaxParameterRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxParameters2025Seeder::class);
        $this->seed(TaxParameters2026Seeder::class);

        $this->repo = $this->app->make(TaxParameterRepository::class);
        $this->repo->bust(); // limpiar cache entre tests
    }

    public function test_get_parameter_returns_state_value(): void
    {
        $value = $this->repo->getParameter(new FiscalYear(2025), 'irpf.minimo_personal_general');

        $this->assertSame(5550, $value);
    }

    public function test_get_parameter_for_region_falls_back_to_state(): void
    {
        // Una clave que solo existe a nivel estatal — region debe devolver el estatal.
        $value = $this->repo->getParameter(
            new FiscalYear(2025),
            'irpf.minimo_personal_general',
            RegionCode::fromCode('MD'),
        );

        $this->assertSame(5550, $value);
    }

    public function test_get_parameter_for_region_returns_regional_value_when_present(): void
    {
        $value = $this->repo->getParameter(
            new FiscalYear(2025),
            'irpf.deduccion_alquiler_vivienda_porcentaje',
            RegionCode::fromCode('MD'),
        );

        $this->assertSame(30, $value);
    }

    public function test_get_brackets_returns_state_irpf_scale(): void
    {
        $brackets = $this->repo->getBrackets(new FiscalYear(2025), 'irpf_general');

        $this->assertGreaterThanOrEqual(5, $brackets->count());
        $this->assertSame('0.00', (string) $brackets->first()->from_amount);
    }

    public function test_get_brackets_with_region_returns_regional_scale(): void
    {
        $brackets = $this->repo->getBrackets(
            new FiscalYear(2025),
            'irpf_general',
            scope: 'regional',
            region: RegionCode::fromCode('MD'),
        );

        $this->assertGreaterThanOrEqual(5, $brackets->count());
        foreach ($brackets as $b) {
            $this->assertSame('MD', $b->region_code);
            $this->assertSame('regional', $b->scope);
        }
    }

    public function test_get_social_security_rate_returns_rg_contingencias_comunes(): void
    {
        $rate = $this->repo->getSocialSecurityRate(new FiscalYear(2025), 'RG', 'contingencias_comunes');

        $this->assertNotNull($rate);
        $this->assertSame('23.6000', (string) $rate->rate_employer);
        $this->assertSame('4.7000', (string) $rate->rate_employee);
    }

    public function test_get_social_security_rate_returns_reta_contingencias_comunes(): void
    {
        $rate = $this->repo->getSocialSecurityRate(new FiscalYear(2025), 'RETA', 'contingencias_comunes');

        $this->assertNotNull($rate);
        $this->assertSame('28.3000', (string) $rate->rate_employee);
    }

    public function test_get_social_security_rate_returns_null_for_unknown(): void
    {
        $rate = $this->repo->getSocialSecurityRate(new FiscalYear(2025), 'RG', 'contingencia_inventada');

        $this->assertNull($rate);
    }

    public function test_get_autonomo_brackets_returns_15_for_2025(): void
    {
        $brackets = $this->repo->getAutonomoBrackets(new FiscalYear(2025));

        $this->assertSame(15, $brackets->count());
    }

    public function test_find_autonomo_bracket_by_yield_low(): void
    {
        $bracket = $this->repo->findAutonomoBracketByYield(new FiscalYear(2025), '500.00');

        // Debe caer en el primer tramo (≤ 670 €)
        $this->assertSame(1, (int) $bracket->bracket_number);
    }

    public function test_find_autonomo_bracket_by_yield_high(): void
    {
        $bracket = $this->repo->findAutonomoBracketByYield(new FiscalYear(2025), '8000.00');

        // Debe caer en el último tramo (> 6000)
        $this->assertSame(15, (int) $bracket->bracket_number);
    }

    public function test_find_autonomo_bracket_by_yield_middle(): void
    {
        $bracket = $this->repo->findAutonomoBracketByYield(new FiscalYear(2025), '2200.00');

        // 2030.01 → 2330 — tramo 9
        $this->assertSame(9, (int) $bracket->bracket_number);
    }

    public function test_repository_returns_different_values_per_year(): void
    {
        $mei2025 = $this->repo->getParameter(new FiscalYear(2025), 'ss.mei_total');
        $mei2026 = $this->repo->getParameter(new FiscalYear(2026), 'ss.mei_total');

        $this->assertSame(0.8, $mei2025);
        $this->assertSame(0.9, $mei2026);
    }
}
