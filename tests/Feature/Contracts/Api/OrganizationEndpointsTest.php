<?php

namespace Tests\Feature\Contracts\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\Organization;
use Tests\TestCase;

class OrganizationEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index(): void
    {
        Organization::factory()->count(5)->create();
        $r = $this->getJson('/api/v1/organizations');
        $r->assertSuccessful();
        $r->assertJsonCount(5, 'data');
    }

    public function test_show_with_contracts_include(): void
    {
        $o = Organization::factory()->create();
        Contract::factory()->for($o, 'organization')->count(3)->create();
        $r = $this->getJson("/api/v1/organizations/{$o->id}?include=contracts");
        $r->assertSuccessful();
        $r->assertJsonCount(3, 'data.contracts');
    }

    public function test_stats(): void
    {
        $o = Organization::factory()->create();
        Contract::factory()->for($o, 'organization')->count(10)->create([
            'importe_con_iva' => 1000,
            'status_code' => 'ADJ',
        ]);
        $r = $this->getJson("/api/v1/organizations/{$o->id}/stats");
        $r->assertSuccessful();
        $r->assertJsonStructure(['total_contracts', 'total_amount', 'by_status', 'by_year']);
    }
}
