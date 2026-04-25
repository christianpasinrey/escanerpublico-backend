<?php

namespace Tests\Unit\Tax\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Services\IrpfScaleResolver;
use Modules\Tax\Services\TaxParameterRepository;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegionCode;
use RuntimeException;
use Tests\TestCase;
use Tests\Unit\Tax\Calculators\Payroll\SeedsTaxParameters;

class IrpfScaleResolverTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTaxParameters;

    private IrpfScaleResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedTaxParameters(2025);
        $this->resolver = new IrpfScaleResolver(
            $this->app->make(TaxParameterRepository::class),
        );
    }

    public function test_state_scale_zero_base_returns_zero(): void
    {
        $quota = $this->resolver->applyStateScale(
            FiscalYear::fromInt(2025),
            Money::zero(),
        );

        $this->assertTrue($quota->isZero());
    }

    public function test_state_scale_first_bracket(): void
    {
        // 12000 € -> primer tramo 9.5 % sobre todo
        // 12000 * 9.5 % = 1140
        $quota = $this->resolver->applyStateScale(
            FiscalYear::fromInt(2025),
            Money::fromFloat(12000),
        );

        $this->assertSame('1140.00', $quota->amount);
    }

    public function test_state_scale_at_top_of_first_bracket(): void
    {
        // 12450 € (límite primer tramo) -> 12450 * 9.5 % = 1182.75
        $quota = $this->resolver->applyStateScale(
            FiscalYear::fromInt(2025),
            Money::fromFloat(12450),
        );

        $this->assertSame('1182.75', $quota->amount);
    }

    public function test_state_scale_in_second_bracket(): void
    {
        // 20200 € (límite del segundo tramo) -> fixed 1182.75 + (20200-12450)*12% = 1182.75 + 930 = 2112.75
        $quota = $this->resolver->applyStateScale(
            FiscalYear::fromInt(2025),
            Money::fromFloat(20200),
        );

        $this->assertSame('2112.75', $quota->amount);
    }

    public function test_state_scale_in_third_bracket(): void
    {
        // 30000 € -> fixed 2112.75 + (30000-20200)*15% = 2112.75 + 1470 = 3582.75
        $quota = $this->resolver->applyStateScale(
            FiscalYear::fromInt(2025),
            Money::fromFloat(30000),
        );

        $this->assertSame('3582.75', $quota->amount);
    }

    public function test_state_scale_in_fourth_bracket(): void
    {
        // 50000 € -> fixed 4362.75 + (50000-35200)*18.5% = 4362.75 + 2738 = 7100.75
        $quota = $this->resolver->applyStateScale(
            FiscalYear::fromInt(2025),
            Money::fromFloat(50000),
        );

        $this->assertSame('7100.75', $quota->amount);
    }

    public function test_state_scale_top_bracket(): void
    {
        // 350000 € -> fixed 62950.75 + (350000-300000)*24.5% = 62950.75 + 12250 = 75200.75
        $quota = $this->resolver->applyStateScale(
            FiscalYear::fromInt(2025),
            Money::fromFloat(350000),
        );

        $this->assertSame('75200.75', $quota->amount);
    }

    public function test_madrid_regional_scale_first_bracket(): void
    {
        // 13000 € en Madrid -> 13000 * 8.5 % = 1105
        $quota = $this->resolver->applyRegionalScale(
            FiscalYear::fromInt(2025),
            RegionCode::fromCode('MD'),
            Money::fromFloat(13000),
        );

        $this->assertSame('1105.00', $quota->amount);
    }

    public function test_catalonia_regional_scale_top_bracket(): void
    {
        // 200000 en CT -> tramo 175000-> 36428.13 + (200000-175000)*25.5% = 36428.13 + 6375 = 42803.13
        $quota = $this->resolver->applyRegionalScale(
            FiscalYear::fromInt(2025),
            RegionCode::fromCode('CT'),
            Money::fromFloat(200000),
        );

        $this->assertSame('42803.13', $quota->amount);
    }

    public function test_andalucia_regional_scale_third_bracket(): void
    {
        // 30000 € AN -> tramo 21000 -> 2195 + (30000-21000)*15% = 2195+1350 = 3545
        $quota = $this->resolver->applyRegionalScale(
            FiscalYear::fromInt(2025),
            RegionCode::fromCode('AN'),
            Money::fromFloat(30000),
        );

        $this->assertSame('3545.00', $quota->amount);
    }

    public function test_valencia_regional_scale(): void
    {
        // 60000 € VC -> tramo 52000-65000 -> 7530 + (60000-52000)*22.5% = 7530+1800 = 9330
        $quota = $this->resolver->applyRegionalScale(
            FiscalYear::fromInt(2025),
            RegionCode::fromCode('VC'),
            Money::fromFloat(60000),
        );

        $this->assertSame('9330.00', $quota->amount);
    }

    public function test_state_scale_throws_when_passed_through_regional(): void
    {
        $this->expectException(RuntimeException::class);
        $this->resolver->applyRegionalScale(
            FiscalYear::fromInt(2025),
            RegionCode::state(),
            Money::fromFloat(20000),
        );
    }

    public function test_apply_brackets_throws_when_empty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->resolver->applyBrackets([], Money::fromFloat(10000));
    }
}
