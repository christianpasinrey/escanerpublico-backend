<?php

namespace Tests\Feature\Tax\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Models\TaxRegime;
use Modules\Tax\Models\TaxRegimeCompatibility;
use Modules\Tax\Models\TaxRegimeObligation;
use Tests\TestCase;

class RegimeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_regimes(): void
    {
        TaxRegime::query()->create(['code' => 'EDS', 'scope' => 'irpf', 'name' => 'EDS']);
        TaxRegime::query()->create(['code' => 'IVA_GEN', 'scope' => 'iva', 'name' => 'IVA General']);
        TaxRegime::query()->create(['code' => 'RG', 'scope' => 'ss', 'name' => 'Régimen General SS']);

        $r = $this->getJson('/api/v1/tax/regimes');
        $r->assertSuccessful();
        $r->assertJsonCount(3, 'data');
    }

    public function test_index_filter_by_scope(): void
    {
        TaxRegime::query()->create(['code' => 'EDS', 'scope' => 'irpf', 'name' => 'EDS']);
        TaxRegime::query()->create(['code' => 'IVA_GEN', 'scope' => 'iva', 'name' => 'IVA General']);

        $r = $this->getJson('/api/v1/tax/regimes?filter[scope]=iva');
        $r->assertSuccessful();
        $r->assertJsonCount(1, 'data');
        $r->assertJsonPath('data.0.code', 'IVA_GEN');
    }

    public function test_index_filter_by_code(): void
    {
        TaxRegime::query()->create(['code' => 'EDS', 'scope' => 'irpf', 'name' => 'EDS']);
        TaxRegime::query()->create(['code' => 'EDN', 'scope' => 'irpf', 'name' => 'EDN']);

        $r = $this->getJson('/api/v1/tax/regimes?filter[code]=EDN');
        $r->assertSuccessful();
        $r->assertJsonCount(1, 'data');
        $r->assertJsonPath('data.0.code', 'EDN');
    }

    public function test_show_regime_by_code(): void
    {
        TaxRegime::query()->create([
            'code' => 'EDS',
            'scope' => 'irpf',
            'name' => 'Estimación Directa Simplificada',
            'description' => 'Régimen test',
            'requirements' => ['turnover_threshold_max' => 600000],
            'editorial_md' => '## Test',
        ]);

        $r = $this->getJson('/api/v1/tax/regimes/EDS');
        $r->assertSuccessful();
        $r->assertJsonPath('data.code', 'EDS');
        $r->assertJsonPath('data.scope', 'irpf');
        $r->assertJsonPath('data.requirements.turnover_threshold_max', 600000);
    }

    public function test_show_with_obligations_include(): void
    {
        $regime = TaxRegime::query()->create(['code' => 'EDS', 'scope' => 'irpf', 'name' => 'EDS']);
        TaxRegimeObligation::query()->create([
            'regime_id' => $regime->id,
            'model_code' => '130',
            'periodicity' => 'quarterly',
            'description' => 'Pago fraccionado IRPF',
        ]);

        $r = $this->getJson('/api/v1/tax/regimes/EDS?include=obligations');
        $r->assertSuccessful();
        $r->assertJsonCount(1, 'data.obligations');
        $r->assertJsonPath('data.obligations.0.model_code', '130');
        $r->assertJsonPath('data.obligations.0.periodicity', 'quarterly');
    }

    public function test_show_with_compatible_regimes_include(): void
    {
        $eds = TaxRegime::query()->create(['code' => 'EDS', 'scope' => 'irpf', 'name' => 'EDS']);
        $eo = TaxRegime::query()->create(['code' => 'EO', 'scope' => 'irpf', 'name' => 'EO']);

        TaxRegimeCompatibility::query()->create([
            'regime_a_id' => $eds->id,
            'regime_b_id' => $eo->id,
            'compatibility' => 'exclusive',
            'notes' => 'No compatibles',
        ]);

        $r = $this->getJson('/api/v1/tax/regimes/EDS?include=compatibleRegimes');
        $r->assertSuccessful();
        $r->assertJsonCount(1, 'data.compatibilities');
        $r->assertJsonPath('data.compatibilities.0.code', 'EO');
        $r->assertJsonPath('data.compatibilities.0.compatibility', 'exclusive');
    }

    public function test_show_404_when_unknown_code(): void
    {
        $r = $this->getJson('/api/v1/tax/regimes/NOPE');
        $r->assertNotFound();
    }

    public function test_index_can_sort_by_code_explicitly(): void
    {
        TaxRegime::query()->create(['code' => 'IS_GEN', 'scope' => 'is', 'name' => 'IS']);
        TaxRegime::query()->create(['code' => 'EDS', 'scope' => 'irpf', 'name' => 'EDS']);
        TaxRegime::query()->create(['code' => 'EDN', 'scope' => 'irpf', 'name' => 'EDN']);

        $r = $this->getJson('/api/v1/tax/regimes?sort=code');
        $r->assertSuccessful();

        $codes = collect($r->json('data'))->pluck('code')->all();
        $this->assertSame(['EDN', 'EDS', 'IS_GEN'], $codes);
    }
}
