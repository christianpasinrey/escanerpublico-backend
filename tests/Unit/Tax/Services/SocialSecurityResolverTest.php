<?php

namespace Tests\Unit\Tax\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Services\SocialSecurityResolver;
use Modules\Tax\Services\TaxParameterRepository;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use RuntimeException;
use Tests\TestCase;
use Tests\Unit\Tax\Calculators\Payroll\SeedsTaxParameters;

class SocialSecurityResolverTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTaxParameters;

    private SocialSecurityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedTaxParameters(2025);
        $this->resolver = new SocialSecurityResolver(
            $this->app->make(TaxParameterRepository::class),
        );
    }

    public function test_employee_rate_for_common_contingencies_is_4_70(): void
    {
        $rate = $this->resolver->employeeRate(
            FiscalYear::fromInt(2025),
            SocialSecurityResolver::REGIMEN_GENERAL,
            SocialSecurityResolver::CONTINGENCY_COMMON,
        );

        // Tipo del trabajador 4.70 % — Orden anual cotización
        $this->assertSame('4.7000', $rate->percentage);
    }

    public function test_employer_rate_for_common_contingencies_is_23_60(): void
    {
        $rate = $this->resolver->employerRate(
            FiscalYear::fromInt(2025),
            SocialSecurityResolver::REGIMEN_GENERAL,
            SocialSecurityResolver::CONTINGENCY_COMMON,
        );

        $this->assertSame('23.6000', $rate->percentage);
    }

    public function test_employee_rate_unemployment_indefinido_is_1_55(): void
    {
        $rate = $this->resolver->employeeRate(
            FiscalYear::fromInt(2025),
            SocialSecurityResolver::REGIMEN_GENERAL,
            SocialSecurityResolver::CONTINGENCY_DESEMPLEO_INDEFINIDO,
        );

        $this->assertSame('1.5500', $rate->percentage);
    }

    public function test_employee_rate_unemployment_temporal_is_1_60(): void
    {
        $rate = $this->resolver->employeeRate(
            FiscalYear::fromInt(2025),
            SocialSecurityResolver::REGIMEN_GENERAL,
            SocialSecurityResolver::CONTINGENCY_DESEMPLEO_TEMPORAL,
        );

        $this->assertSame('1.6000', $rate->percentage);
    }

    public function test_capped_monthly_base_caps_above_max(): void
    {
        $hugeBase = Money::fromFloat(10000); // > 4909.50 base máx 2025
        $capped = $this->resolver->cappedMonthlyBase(
            FiscalYear::fromInt(2025),
            SocialSecurityResolver::REGIMEN_GENERAL,
            $hugeBase,
        );

        $this->assertSame('4909.50', $capped->amount);
    }

    public function test_capped_monthly_base_caps_below_min(): void
    {
        // Base 100 € es < base mín 1381.20 — debería subir
        $tinyBase = Money::fromFloat(100);
        $capped = $this->resolver->cappedMonthlyBase(
            FiscalYear::fromInt(2025),
            SocialSecurityResolver::REGIMEN_GENERAL,
            $tinyBase,
        );

        $this->assertSame('1381.20', $capped->amount);
    }

    public function test_capped_monthly_base_keeps_value_within_range(): void
    {
        $base = Money::fromFloat(2500);
        $capped = $this->resolver->cappedMonthlyBase(
            FiscalYear::fromInt(2025),
            SocialSecurityResolver::REGIMEN_GENERAL,
            $base,
        );

        $this->assertSame('2500.00', $capped->amount);
    }

    public function test_employee_quota_applies_rate(): void
    {
        $base = Money::fromFloat(2000);
        $quota = $this->resolver->employeeQuota(
            FiscalYear::fromInt(2025),
            SocialSecurityResolver::REGIMEN_GENERAL,
            SocialSecurityResolver::CONTINGENCY_COMMON,
            $base,
        );

        // 2000 * 4.70 % = 94.00
        $this->assertSame('94.00', $quota->amount);
    }

    public function test_employer_quota_applies_rate(): void
    {
        $base = Money::fromFloat(2000);
        $quota = $this->resolver->employerQuota(
            FiscalYear::fromInt(2025),
            SocialSecurityResolver::REGIMEN_GENERAL,
            SocialSecurityResolver::CONTINGENCY_COMMON,
            $base,
        );

        // 2000 * 23.60 % = 472.00
        $this->assertSame('472.00', $quota->amount);
    }

    public function test_throws_when_contingency_not_found(): void
    {
        $this->expectException(RuntimeException::class);
        $this->resolver->employeeRate(
            FiscalYear::fromInt(2025),
            SocialSecurityResolver::REGIMEN_GENERAL,
            'inexistent_contingency',
        );
    }

    public function test_mei_rate_grows_each_year(): void
    {
        $this->seedTaxParameters(2024, 2026);

        $rate2024 = (float) $this->resolver->employeeRate(
            FiscalYear::fromInt(2024),
            SocialSecurityResolver::REGIMEN_GENERAL,
            SocialSecurityResolver::CONTINGENCY_MEI,
        )->percentage;

        $rate2025 = (float) $this->resolver->employeeRate(
            FiscalYear::fromInt(2025),
            SocialSecurityResolver::REGIMEN_GENERAL,
            SocialSecurityResolver::CONTINGENCY_MEI,
        )->percentage;

        $rate2026 = (float) $this->resolver->employeeRate(
            FiscalYear::fromInt(2026),
            SocialSecurityResolver::REGIMEN_GENERAL,
            SocialSecurityResolver::CONTINGENCY_MEI,
        )->percentage;

        $this->assertGreaterThan($rate2024, $rate2025);
        $this->assertGreaterThan($rate2025, $rate2026);
    }
}
