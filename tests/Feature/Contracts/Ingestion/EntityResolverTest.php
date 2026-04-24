<?php

namespace Tests\Feature\Contracts\Ingestion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Organization;
use Modules\Contracts\Services\EntityResolver;
use Tests\TestCase;

class EntityResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_organization_by_dir3(): void
    {
        $org = Organization::factory()->create(['identifier' => 'L01101954', 'nif' => 'P1019900H']);
        $r = app(EntityResolver::class);
        $r->preload();

        $this->assertSame($org->id, $r->resolveOrganizationId(dir3: 'L01101954', nif: null, name: null));
    }

    public function test_resolves_organization_by_nif_fallback(): void
    {
        $org = Organization::factory()->create(['identifier' => null, 'nif' => 'P9999999X']);
        $r = app(EntityResolver::class);
        $r->preload();

        $this->assertSame($org->id, $r->resolveOrganizationId(dir3: null, nif: 'P9999999X', name: null));
    }

    public function test_resolves_by_normalized_name(): void
    {
        $org = Organization::factory()->create(['name' => 'Ayuntamiento de Trujillo, S.L.']);
        $r = app(EntityResolver::class);
        $r->preload();

        $this->assertSame($org->id, $r->resolveOrganizationId(dir3: null, nif: null, name: 'AYUNTAMIENTO DE TRUJILLO SL'));
    }
}
