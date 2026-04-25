<?php

namespace Tests\Feature\Tax\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Enums\LevyType;
use Modules\Tax\Enums\Scope;
use Modules\Tax\Models\TaxRate;
use Modules\Tax\Models\TaxType;
use Tests\TestCase;

class TaxTypeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated(): void
    {
        TaxType::factory()->count(3)->create();

        $r = $this->getJson('/api/v1/tax/types');

        $r->assertSuccessful();
        $r->assertJsonCount(3, 'data');
    }

    public function test_index_response_has_cache_headers(): void
    {
        TaxType::factory()->create();

        $r = $this->getJson('/api/v1/tax/types');

        $r->assertSuccessful();
        $r->assertHeader('Cache-Control', 'public, s-maxage=300, stale-while-revalidate=900');
    }

    public function test_index_filter_by_scope(): void
    {
        TaxType::factory()->create(['code' => 'A_TEST', 'scope' => Scope::State->value]);
        TaxType::factory()->regional('MD')->create(['code' => 'B_TEST']);
        TaxType::factory()->regional('CT')->create(['code' => 'C_TEST']);

        $r = $this->getJson('/api/v1/tax/types?filter[scope]=regional');

        $r->assertSuccessful();
        $r->assertJsonCount(2, 'data');
    }

    public function test_index_filter_by_levy_type(): void
    {
        TaxType::factory()->create(['code' => 'IMP_X']);
        TaxType::factory()->tasa()->create(['code' => 'TASA_X']);
        TaxType::factory()->tasa()->create(['code' => 'TASA_Y']);

        $r = $this->getJson('/api/v1/tax/types?filter[levy_type]=tasa');

        $r->assertSuccessful();
        $r->assertJsonCount(2, 'data');
    }

    public function test_index_filter_by_region_code(): void
    {
        TaxType::factory()->regional('MD')->create(['code' => 'AAA_MD']);
        TaxType::factory()->regional('CT')->create(['code' => 'AAA_CT']);

        $r = $this->getJson('/api/v1/tax/types?filter[region_code]=MD');

        $r->assertSuccessful();
        $r->assertJsonCount(1, 'data');
        $r->assertJsonPath('data.0.region_code', 'MD');
    }

    public function test_index_filter_by_search(): void
    {
        TaxType::factory()->create(['code' => 'IRPF_TEST', 'name' => 'Impuesto sobre la Renta de las Personas Físicas']);
        TaxType::factory()->create(['code' => 'IS_TEST', 'name' => 'Impuesto sobre Sociedades']);

        $r = $this->getJson('/api/v1/tax/types?filter[search]=Renta');

        $r->assertSuccessful();
        $r->assertJsonCount(1, 'data');
        $r->assertJsonPath('data.0.code', 'IRPF_TEST');
    }

    public function test_index_disallowed_filter_returns_400(): void
    {
        TaxType::factory()->create();

        $r = $this->getJson('/api/v1/tax/types?filter[hacker]=evil');

        $r->assertStatus(400);
    }

    public function test_index_can_include_rates_count(): void
    {
        $type = TaxType::factory()->create(['code' => 'WITHRATES']);
        TaxRate::factory()->count(3)->for($type, 'taxType')->create();

        $r = $this->getJson('/api/v1/tax/types');

        $r->assertSuccessful();
        $r->assertJsonPath('data.0.rates_count', 3);
    }

    public function test_index_can_include_rates(): void
    {
        $type = TaxType::factory()->create(['code' => 'INCRATES']);
        TaxRate::factory()->count(2)->for($type, 'taxType')->create([
            'year' => 2025,
            'rate' => 21,
        ]);

        $r = $this->getJson('/api/v1/tax/types?include=rates');

        $r->assertSuccessful();
        $r->assertJsonCount(2, 'data.0.rates');
    }

    public function test_show_returns_single_type_by_code(): void
    {
        TaxType::factory()->create(['code' => 'UNIQUE_TEST']);

        $r = $this->getJson('/api/v1/tax/types/UNIQUE_TEST');

        $r->assertSuccessful();
        $r->assertJsonPath('data.code', 'UNIQUE_TEST');
    }

    public function test_show_returns_404_when_not_found(): void
    {
        $r = $this->getJson('/api/v1/tax/types/NOPE');
        $r->assertStatus(404);
    }

    public function test_show_returns_state_by_default_with_regional_variants_in_meta(): void
    {
        // mismo code en estatal y regional MD y regional CT.
        TaxType::factory()->create(['code' => 'AMBI', 'name' => 'AMBI estatal']);
        TaxType::factory()->regional('MD')->create(['code' => 'AMBI', 'name' => 'AMBI Madrid']);
        TaxType::factory()->regional('CT')->create(['code' => 'AMBI', 'name' => 'AMBI Cataluña']);

        $r = $this->getJson('/api/v1/tax/types/AMBI');

        // Devuelve la fila estatal por defecto, no 422.
        $r->assertSuccessful();
        $r->assertJsonPath('data.name', 'AMBI estatal');
        $r->assertJsonPath('data.region_code', null);

        // Las variantes autonómicas se exponen en meta.regional_variants.
        $variants = $r->json('meta.regional_variants');
        $this->assertCount(2, $variants);
        $regions = array_column($variants, 'region_code');
        sort($regions);
        $this->assertSame(['CT', 'MD'], $regions);
    }

    public function test_show_disambiguates_with_region_code(): void
    {
        TaxType::factory()->create(['code' => 'DISAMB']);
        TaxType::factory()->regional('MD')->create(['code' => 'DISAMB', 'name' => 'DISAMB Madrid']);
        TaxType::factory()->regional('CT')->create(['code' => 'DISAMB', 'name' => 'DISAMB Cataluña']);

        $r = $this->getJson('/api/v1/tax/types/DISAMB?region_code=MD');

        $r->assertSuccessful();
        $r->assertJsonPath('data.region_code', 'MD');
        $r->assertJsonPath('data.name', 'DISAMB Madrid');
    }

    public function test_show_disambiguates_with_scope(): void
    {
        TaxType::factory()->create(['code' => 'DISAMB2']);
        TaxType::factory()->regional('MD')->create(['code' => 'DISAMB2']);

        $r = $this->getJson('/api/v1/tax/types/DISAMB2?scope=state');

        $r->assertSuccessful();
        $r->assertJsonPath('data.scope', 'state');
        $r->assertJsonPath('data.region_code', null);
    }

    public function test_show_response_has_cache_headers(): void
    {
        TaxType::factory()->create(['code' => 'CACHE_TEST']);

        $r = $this->getJson('/api/v1/tax/types/CACHE_TEST');

        $r->assertSuccessful();
        $r->assertHeader('Cache-Control', 'public, s-maxage=600, stale-while-revalidate=3600');
    }

    public function test_show_can_include_rates(): void
    {
        $type = TaxType::factory()->create(['code' => 'SHOWRATES']);
        TaxRate::factory()->for($type, 'taxType')->forYear(2025)->create();
        TaxRate::factory()->for($type, 'taxType')->forYear(2026)->create();

        $r = $this->getJson('/api/v1/tax/types/SHOWRATES?include=rates');

        $r->assertSuccessful();
        $r->assertJsonCount(2, 'data.rates');
    }

    public function test_response_exposes_scope_and_levy_type_labels(): void
    {
        TaxType::factory()->tasa()->create(['code' => 'LBL_TEST']);

        $r = $this->getJson('/api/v1/tax/types/LBL_TEST');

        $r->assertSuccessful();
        $r->assertJsonPath('data.levy_type', LevyType::Tasa->value);
        $r->assertJsonPath('data.levy_type_label', 'Tasa');
        $r->assertJsonPath('data.scope', Scope::State->value);
        $r->assertJsonPath('data.scope_label', 'Estatal');
    }
}
