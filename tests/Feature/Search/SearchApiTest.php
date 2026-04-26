<?php

namespace Tests\Feature\Search;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Company;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\Organization;
use Modules\Legislation\Models\LegislationNorm;
use Modules\Officials\Models\PublicOfficial;
use Modules\Subsidies\Models\SubsidyCall;
use Modules\Subsidies\Models\SubsidyGrant;
use Tests\TestCase;

class SearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_query_returns_empty_buckets(): void
    {
        $r = $this->getJson('/api/v1/search?q=a');

        $r->assertSuccessful();
        $r->assertJsonPath('data.query', 'a');
        $r->assertJsonPath('data.total_hits', 0);
        $r->assertJsonPath('data.buckets', []);
    }

    public function test_finds_organization_by_name(): void
    {
        Organization::factory()->create(['name' => 'Ministerio de Hacienda']);
        Organization::factory()->create(['name' => 'Diputación de Almería']);

        $r = $this->getJson('/api/v1/search?q=Hacienda');

        $r->assertSuccessful();
        $bucket = collect($r->json('data.buckets'))->firstWhere('key', 'organization');
        $this->assertNotNull($bucket);
        $this->assertCount(1, $bucket['hits']);
        $this->assertSame('Ministerio de Hacienda', $bucket['hits'][0]['title']);
        $this->assertStringStartsWith('/organismos/', $bucket['hits'][0]['url']);
    }

    public function test_finds_company_by_name_or_nif(): void
    {
        Company::factory()->create(['name' => 'Ferrovial Construcción SA', 'nif' => 'A12345678']);
        Company::factory()->create(['name' => 'ACS Servicios SA', 'nif' => 'B98765432']);

        $byName = $this->getJson('/api/v1/search?q=Ferrovial');
        $byNif = $this->getJson('/api/v1/search?q=A12345678');

        $byName->assertSuccessful();
        $byNif->assertSuccessful();

        $this->assertCount(1, collect($byName->json('data.buckets'))->firstWhere('key', 'company')['hits']);
        $this->assertCount(1, collect($byNif->json('data.buckets'))->firstWhere('key', 'company')['hits']);
    }

    public function test_finds_contract_by_short_term_via_like_fallback(): void
    {
        // Términos < 4 chars usan LIKE fallback (FULLTEXT requiere min token size).
        Contract::factory()->create([
            'expediente' => 'EXP-001',
            'objeto' => 'Reparación de la autopista A-3 entre Madrid y Valencia',
        ]);
        Contract::factory()->create([
            'expediente' => 'EXP-002',
            'objeto' => 'Suministro de material sanitario para hospitales',
        ]);

        $r = $this->getJson('/api/v1/search?q=A-3');

        $r->assertSuccessful();
        $bucket = collect($r->json('data.buckets'))->firstWhere('key', 'contract');
        $this->assertGreaterThanOrEqual(1, count($bucket['hits']));
    }

    public function test_finds_subsidy_grant_by_beneficiario(): void
    {
        SubsidyGrant::factory()->create(['beneficiario_name' => 'Fundación Tripartita Estatal']);
        SubsidyGrant::factory()->create(['beneficiario_name' => 'Asociación Cultural Andaluza']);

        $r = $this->getJson('/api/v1/search?q=Tripartita');

        $r->assertSuccessful();
        $bucket = collect($r->json('data.buckets'))->firstWhere('key', 'subsidy-grant');
        $this->assertCount(1, $bucket['hits']);
    }

    public function test_finds_legislation_norm_by_external_id_via_like(): void
    {
        // external_id BOE-A-... usa LIKE en fallback corto.
        LegislationNorm::factory()->create([
            'external_id' => 'BOE-A-2026-0001',
            'titulo' => 'Real Decreto sobre transparencia presupuestaria autonómica',
        ]);
        LegislationNorm::factory()->create([
            'external_id' => 'BOE-A-2025-0500',
            'titulo' => 'Orden ministerial de creación del registro digital',
        ]);

        $r = $this->getJson('/api/v1/search?q=BOE-A-2026-0001');

        $r->assertSuccessful();
        $bucket = collect($r->json('data.buckets'))->firstWhere('key', 'legislation');
        $this->assertGreaterThanOrEqual(1, count($bucket['hits']));
    }

    public function test_finds_official_by_short_term_via_like_fallback(): void
    {
        // Términos < 4 chars dan LIKE — FULLTEXT no ve registros uncommitted.
        PublicOfficial::factory()->create([
            'full_name' => 'María González López',
            'normalized_name' => 'maria gonzalez lopez',
        ]);
        PublicOfficial::factory()->create([
            'full_name' => 'Pedro Sánchez Rodríguez',
            'normalized_name' => 'pedro sanchez rodriguez',
        ]);

        $r = $this->getJson('/api/v1/search?q=Mar');

        $r->assertSuccessful();
        $bucket = collect($r->json('data.buckets'))->firstWhere('key', 'official');
        $this->assertGreaterThanOrEqual(1, count($bucket['hits']));
    }

    public function test_respects_per_bucket_limit(): void
    {
        Organization::factory()->count(8)->create(['name' => 'Ayuntamiento de prueba']);

        $r = $this->getJson('/api/v1/search?q=Ayuntamiento&limit=3');

        $r->assertSuccessful();
        $bucket = collect($r->json('data.buckets'))->firstWhere('key', 'organization');
        $this->assertCount(3, $bucket['hits']);
    }

    public function test_response_contains_all_seven_buckets(): void
    {
        SubsidyCall::factory()->create(['description' => 'Convocatoria del programa Erasmus para estudiantes universitarios europeos']);

        $r = $this->getJson('/api/v1/search?q=Erasmus');

        $r->assertSuccessful();
        $keys = collect($r->json('data.buckets'))->pluck('key')->all();

        $this->assertContains('contract', $keys);
        $this->assertContains('organization', $keys);
        $this->assertContains('company', $keys);
        $this->assertContains('subsidy-call', $keys);
        $this->assertContains('subsidy-grant', $keys);
        $this->assertContains('legislation', $keys);
        $this->assertContains('official', $keys);
    }

    public function test_validates_excessive_query_length(): void
    {
        $longQuery = str_repeat('x', 300);

        $r = $this->getJson('/api/v1/search?q='.$longQuery);

        $r->assertStatus(422);
    }
}
