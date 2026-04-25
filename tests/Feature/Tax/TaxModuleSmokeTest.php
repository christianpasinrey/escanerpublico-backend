<?php

namespace Tests\Feature\Tax;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Tax\Models\TaxRegime;
use Modules\Tax\Services\TaxParameterRepository;
use Modules\Tax\TaxServiceProvider;
use Tests\TestCase;

class TaxModuleSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_provider_is_registered(): void
    {
        $this->assertArrayHasKey(
            TaxServiceProvider::class,
            $this->app->getLoadedProviders(),
        );
    }

    public function test_repository_is_resolvable_as_singleton(): void
    {
        $a = $this->app->make(TaxParameterRepository::class);
        $b = $this->app->make(TaxParameterRepository::class);

        $this->assertSame($a, $b);
    }

    public function test_all_tax_tables_exist(): void
    {
        $expected = [
            'tax_regimes',
            'tax_regime_compatibility',
            'tax_regime_obligations',
            'economic_activities',
            'activity_regime_mappings',
            'tax_types',
            'tax_rates',
            'vat_product_rates',
            'tax_parameters',
            'tax_brackets',
            'social_security_rates',
            'autonomo_brackets',
        ];

        foreach ($expected as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Tabla {$table} no existe en BD",
            );
        }
    }

    public function test_can_create_regime_with_minimum_fields(): void
    {
        $regime = TaxRegime::create([
            'code' => 'EDS_TEST',
            'scope' => 'irpf',
            'name' => 'Estimación directa simplificada (test)',
        ]);

        $this->assertNotNull($regime->id);
        $this->assertSame('EDS_TEST', $regime->code);

        $regime->delete();
    }
}
