<?php

namespace Tests\Feature\Tax\Services\Invoice;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Models\ActivityRegimeMapping;
use Modules\Tax\Models\EconomicActivity;
use Modules\Tax\Services\Invoice\IrpfRetentionResolver;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\RegimeCode;
use Tests\TestCase;

/**
 * Tests del resolver de retención IRPF.
 *
 * Fuentes legales:
 *  - art. 95 RD 439/2007 RIRPF (retención profesionales 15 %).
 *  - DA 31ª RIRPF / RD 145/2024 (retención reducida 7 % primeros 3 años).
 *  - art. 101 LIRPF (retención agraria 2 %).
 */
class IrpfRetentionResolverTest extends TestCase
{
    use RefreshDatabase;

    private IrpfRetentionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new IrpfRetentionResolver;
    }

    public function test_new_activity_in_eds_returns_7_percent(): void
    {
        $rate = $this->resolver->resolve(
            irpfRegime: RegimeCode::fromString('EDS'),
            year: FiscalYear::fromInt(2025),
            newActivityFlag: true,
        );

        $this->assertSame('7.00', $rate->percentage);
    }

    public function test_new_activity_in_edn_returns_7_percent(): void
    {
        $rate = $this->resolver->resolve(
            irpfRegime: RegimeCode::fromString('EDN'),
            year: FiscalYear::fromInt(2025),
            newActivityFlag: true,
        );

        $this->assertSame('7.00', $rate->percentage);
    }

    public function test_eds_without_activity_defaults_to_15_percent(): void
    {
        $rate = $this->resolver->resolve(
            irpfRegime: RegimeCode::fromString('EDS'),
            year: FiscalYear::fromInt(2025),
            newActivityFlag: false,
        );

        $this->assertSame('15.00', $rate->percentage);
    }

    public function test_eo_without_activity_defaults_to_zero(): void
    {
        $rate = $this->resolver->resolve(
            irpfRegime: RegimeCode::fromString('EO'),
            year: FiscalYear::fromInt(2025),
            newActivityFlag: false,
        );

        $this->assertSame('0.00', $rate->percentage);
    }

    public function test_resolves_15_for_professional_activity_from_catalog(): void
    {
        $activity = EconomicActivity::query()->create([
            'system' => 'cnae',
            'code' => '69200',
            'level' => 5,
            'name' => 'Asesoría fiscal/contable',
            'year' => 2025,
        ]);
        ActivityRegimeMapping::query()->create([
            'activity_id' => $activity->id,
            'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN'],
            'irpf_retention_default' => '15.00',
        ]);

        $rate = $this->resolver->resolve(
            irpfRegime: RegimeCode::fromString('EDS'),
            year: FiscalYear::fromInt(2025),
            newActivityFlag: false,
            activityCode: '69200',
        );

        $this->assertSame('15.00', $rate->percentage);
    }

    public function test_resolves_2_for_agricultural_activity(): void
    {
        $activity = EconomicActivity::query()->create([
            'system' => 'cnae',
            'code' => '01',
            'level' => 2,
            'name' => 'Agricultura',
            'year' => 2025,
        ]);
        ActivityRegimeMapping::query()->create([
            'activity_id' => $activity->id,
            'eligible_regimes' => ['EDS', 'IVA_REAGP'],
            'irpf_retention_default' => '2.00',
        ]);

        $rate = $this->resolver->resolve(
            irpfRegime: RegimeCode::fromString('EDS'),
            year: FiscalYear::fromInt(2025),
            newActivityFlag: false,
            activityCode: '01',
        );

        $this->assertSame('2.00', $rate->percentage);
    }

    public function test_new_activity_overrides_catalog_in_eds(): void
    {
        $activity = EconomicActivity::query()->create([
            'system' => 'cnae',
            'code' => '69200',
            'level' => 5,
            'name' => 'Asesoría',
            'year' => 2025,
        ]);
        ActivityRegimeMapping::query()->create([
            'activity_id' => $activity->id,
            'eligible_regimes' => ['EDS'],
            'irpf_retention_default' => '15.00',
        ]);

        $rate = $this->resolver->resolve(
            irpfRegime: RegimeCode::fromString('EDS'),
            year: FiscalYear::fromInt(2025),
            newActivityFlag: true,
            activityCode: '69200',
        );

        // 7 % por nueva actividad (anula 15 % del catálogo).
        $this->assertSame('7.00', $rate->percentage);
    }

    public function test_unknown_activity_falls_back_to_default(): void
    {
        $rate = $this->resolver->resolve(
            irpfRegime: RegimeCode::fromString('EDN'),
            year: FiscalYear::fromInt(2025),
            newActivityFlag: false,
            activityCode: '99999',
        );

        $this->assertSame('15.00', $rate->percentage);
    }
}
