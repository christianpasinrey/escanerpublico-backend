<?php

namespace Tests\Feature\Tax\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Models\ActivityRegimeMapping;
use Modules\Tax\Models\EconomicActivity;
use Tests\TestCase;

class EconomicActivityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_activities(): void
    {
        EconomicActivity::query()->create(['system' => 'cnae', 'code' => 'A', 'level' => 1, 'name' => 'Agricultura', 'year' => 2025]);
        EconomicActivity::query()->create(['system' => 'cnae', 'code' => 'F', 'level' => 1, 'name' => 'Construcción', 'year' => 2025]);
        EconomicActivity::query()->create(['system' => 'iae', 'code' => '6', 'level' => 1, 'name' => 'Comercio', 'year' => 1992]);

        $r = $this->getJson('/api/v1/tax/activities');
        $r->assertSuccessful();
        $r->assertJsonCount(3, 'data');
    }

    public function test_index_filter_by_system(): void
    {
        EconomicActivity::query()->create(['system' => 'cnae', 'code' => 'A', 'level' => 1, 'name' => 'Agricultura', 'year' => 2025]);
        EconomicActivity::query()->create(['system' => 'iae', 'code' => '6', 'level' => 1, 'name' => 'Comercio', 'year' => 1992]);

        $r = $this->getJson('/api/v1/tax/activities?filter[system]=cnae');
        $r->assertSuccessful();
        $r->assertJsonCount(1, 'data');
        $r->assertJsonPath('data.0.code', 'A');
    }

    public function test_index_filter_by_parent_code(): void
    {
        EconomicActivity::query()->create(['system' => 'cnae', 'code' => 'A', 'level' => 1, 'name' => 'Agricultura', 'year' => 2025]);
        EconomicActivity::query()->create(['system' => 'cnae', 'code' => '01', 'parent_code' => 'A', 'level' => 2, 'name' => 'Agricultura, ganadería', 'year' => 2025]);
        EconomicActivity::query()->create(['system' => 'cnae', 'code' => '02', 'parent_code' => 'A', 'level' => 2, 'name' => 'Silvicultura', 'year' => 2025]);
        EconomicActivity::query()->create(['system' => 'cnae', 'code' => '41', 'parent_code' => 'F', 'level' => 2, 'name' => 'Construcción de edificios', 'year' => 2025]);

        $r = $this->getJson('/api/v1/tax/activities?filter[parent_code]=A&filter[system]=cnae');
        $r->assertSuccessful();
        $r->assertJsonCount(2, 'data');
    }

    public function test_index_filter_by_search_term(): void
    {
        EconomicActivity::query()->create(['system' => 'cnae', 'code' => '5510', 'level' => 4, 'name' => 'Hoteles y alojamientos similares', 'year' => 2025]);
        EconomicActivity::query()->create(['system' => 'cnae', 'code' => '5610', 'level' => 4, 'name' => 'Restaurantes y puestos de comidas', 'year' => 2025]);

        $r = $this->getJson('/api/v1/tax/activities?filter[search]=hotel');
        $r->assertSuccessful();
        $r->assertJsonCount(1, 'data');
        $r->assertJsonPath('data.0.code', '5510');
    }

    public function test_show_activity_by_system_and_code(): void
    {
        EconomicActivity::query()->create([
            'system' => 'cnae',
            'code' => '5510',
            'level' => 4,
            'name' => 'Hoteles y alojamientos similares',
            'year' => 2025,
        ]);

        $r = $this->getJson('/api/v1/tax/activities/cnae/5510');
        $r->assertSuccessful();
        $r->assertJsonPath('data.code', '5510');
        $r->assertJsonPath('data.system', 'cnae');
    }

    public function test_show_with_regime_mapping_include(): void
    {
        $activity = EconomicActivity::query()->create([
            'system' => 'cnae',
            'code' => '5510',
            'level' => 4,
            'name' => 'Hoteles',
            'year' => 2025,
        ]);
        ActivityRegimeMapping::query()->create([
            'activity_id' => $activity->id,
            'eligible_regimes' => ['EDS', 'EDN', 'IVA_GEN', 'IS_GEN'],
            'vat_rate_default' => 10,
            'irpf_retention_default' => null,
        ]);

        $r = $this->getJson('/api/v1/tax/activities/cnae/5510?include=regimeMapping');
        $r->assertSuccessful();
        $r->assertJsonPath('data.regime_mapping.vat_rate_default', 10);
        $r->assertJsonCount(4, 'data.regime_mapping.eligible_regimes');
    }

    public function test_show_404_for_unknown_code(): void
    {
        $r = $this->getJson('/api/v1/tax/activities/cnae/9999');
        $r->assertNotFound();
    }

    public function test_show_404_for_unknown_system(): void
    {
        // El where() en la ruta filtra por cnae|iae, así que cualquier otro devuelve 404 sin tocar el controller
        $r = $this->getJson('/api/v1/tax/activities/foo/A');
        $r->assertNotFound();
    }
}
