<?php

namespace Tests\Feature\Contracts\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Contract;
use Tests\TestCase;

class ContractIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_paginated(): void
    {
        Contract::factory()->count(30)->create(['status_code' => 'ADJ']);
        $r = $this->getJson('/api/v1/contracts?per_page=10');
        $r->assertSuccessful();
        $r->assertJsonStructure(['data', 'meta', 'links']);
        $r->assertJsonCount(10, 'data');
    }

    public function test_filter_by_status(): void
    {
        Contract::factory()->count(3)->create(['status_code' => 'ADJ']);
        Contract::factory()->count(5)->create(['status_code' => 'RES']);
        $r = $this->getJson('/api/v1/contracts?filter[status_code]=ADJ');
        $r->assertSuccessful();
        $r->assertJsonCount(3, 'data');
    }

    public function test_include_organization(): void
    {
        $c = Contract::factory()->create();
        $r = $this->getJson('/api/v1/contracts?include=organization&per_page=1');
        $r->assertSuccessful();
        $r->assertJsonPath('data.0.organization.id', $c->organization_id);
    }

    public function test_disallowed_include_returns_400(): void
    {
        Contract::factory()->create();
        $r = $this->getJson('/api/v1/contracts?include=evil');
        $r->assertStatus(400);
    }

    public function test_cache_headers_present(): void
    {
        $r = $this->getJson('/api/v1/contracts');
        $r->assertHeader('Cache-Control');
        $this->assertStringContainsString('s-maxage', (string) $r->headers->get('Cache-Control'));
    }
}
