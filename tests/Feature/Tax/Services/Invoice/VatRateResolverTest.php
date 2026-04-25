<?php

namespace Tests\Feature\Tax\Services\Invoice;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\DTOs\Invoice\VatRateType;
use Modules\Tax\Models\VatProductRate;
use Modules\Tax\Services\Invoice\VatRateResolver;
use Modules\Tax\ValueObjects\FiscalYear;
use Tests\TestCase;

class VatRateResolverTest extends TestCase
{
    use RefreshDatabase;

    private VatRateResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new VatRateResolver;
    }

    public function test_falls_back_to_default_rate_when_no_catalog_match(): void
    {
        $rate = $this->resolver->resolve(VatRateType::GENERAL, FiscalYear::fromInt(2025));
        $this->assertSame('21.00', $rate->percentage);

        $rate = $this->resolver->resolve(VatRateType::REDUCED, FiscalYear::fromInt(2025));
        $this->assertSame('10.00', $rate->percentage);

        $rate = $this->resolver->resolve(VatRateType::SUPER_REDUCED, FiscalYear::fromInt(2025));
        $this->assertSame('4.00', $rate->percentage);
    }

    public function test_resolves_from_catalog_by_activity_code(): void
    {
        VatProductRate::query()->create([
            'year' => 2025,
            'activity_code' => '5811',
            'rate_type' => 'super_reduced',
            'rate' => '4.00',
            'description' => 'Edición de libros',
            'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740',
        ]);

        $rate = $this->resolver->resolve(
            VatRateType::SUPER_REDUCED,
            FiscalYear::fromInt(2025),
            activityCode: '5811',
        );

        $this->assertSame('4.00', $rate->percentage);
    }

    public function test_resolves_from_catalog_by_keyword_when_no_activity(): void
    {
        VatProductRate::query()->create([
            'year' => 2025,
            'activity_code' => null,
            'keyword' => 'libros',
            'rate_type' => 'super_reduced',
            'rate' => '4.00',
        ]);

        $rate = $this->resolver->resolve(
            VatRateType::SUPER_REDUCED,
            FiscalYear::fromInt(2025),
            keyword: 'libros',
        );

        $this->assertSame('4.00', $rate->percentage);
    }

    public function test_exempt_always_returns_zero_without_db_lookup(): void
    {
        $rate = $this->resolver->resolve(VatRateType::EXEMPT, FiscalYear::fromInt(2025));
        $this->assertSame('0.0000', $rate->percentage);
    }

    public function test_zero_returns_zero_default(): void
    {
        $rate = $this->resolver->resolve(VatRateType::ZERO, FiscalYear::fromInt(2025));
        $this->assertSame('0.00', $rate->percentage);
    }
}
