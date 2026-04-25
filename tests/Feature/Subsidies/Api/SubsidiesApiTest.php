<?php

namespace Tests\Feature\Subsidies\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Company;
use Modules\Contracts\Models\Organization;
use Modules\Subsidies\Models\SubsidyCall;
use Modules\Subsidies\Models\SubsidyGrant;
use Tests\TestCase;

class SubsidiesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_calls_index_returns_paginated(): void
    {
        SubsidyCall::factory()->count(3)->create();
        $r = $this->getJson('/api/v1/subsidies/calls');
        $r->assertSuccessful();
        $r->assertJsonCount(3, 'data');
    }

    public function test_calls_show_with_organization_include(): void
    {
        $org = Organization::factory()->create();
        $call = SubsidyCall::factory()->for($org, 'organization')->create();
        $r = $this->getJson("/api/v1/subsidies/calls/{$call->id}?include=organization");
        $r->assertSuccessful();
        $r->assertJsonPath('data.organization.id', $org->id);
    }

    public function test_calls_filter_by_nivel1(): void
    {
        SubsidyCall::factory()->create(['nivel1' => 'AUTONOMICA']);
        SubsidyCall::factory()->create(['nivel1' => 'ESTATAL']);
        $r = $this->getJson('/api/v1/subsidies/calls?filter[nivel1]=AUTONOMICA');
        $r->assertSuccessful();
        $r->assertJsonCount(1, 'data');
        $r->assertJsonPath('data.0.nivel1', 'AUTONOMICA');
    }

    public function test_calls_index_includes_grants_count(): void
    {
        $call = SubsidyCall::factory()->create();
        SubsidyGrant::factory()->for($call, 'call')->count(3)->create(['amount' => 1000]);
        $r = $this->getJson('/api/v1/subsidies/calls');
        $r->assertSuccessful();
        $r->assertJsonPath('data.0.grants_count', 3);
        $this->assertEquals(3000, (float) $r->json('data.0.grants_sum_amount'));
    }

    public function test_grants_index(): void
    {
        SubsidyGrant::factory()->count(2)->create();
        $r = $this->getJson('/api/v1/subsidies/grants');
        $r->assertSuccessful();
        $r->assertJsonCount(2, 'data');
    }

    public function test_grants_show_with_full_includes(): void
    {
        $call = SubsidyCall::factory()->create();
        $org = Organization::factory()->create();
        $company = Company::factory()->create();
        $grant = SubsidyGrant::factory()
            ->for($call, 'call')
            ->for($org, 'organization')
            ->for($company, 'company')
            ->create();

        $r = $this->getJson("/api/v1/subsidies/grants/{$grant->id}?include=call,organization,company");
        $r->assertSuccessful();
        $r->assertJsonPath('data.call.id', $call->id);
        $r->assertJsonPath('data.organization.id', $org->id);
        $r->assertJsonPath('data.company.id', $company->id);
    }

    public function test_grants_filter_by_company_id(): void
    {
        $company = Company::factory()->create();
        SubsidyGrant::factory()->for($company, 'company')->count(2)->create();
        SubsidyGrant::factory()->count(3)->create();

        $r = $this->getJson("/api/v1/subsidies/grants?filter[company_id]={$company->id}");
        $r->assertSuccessful();
        $r->assertJsonCount(2, 'data');
    }

    public function test_grants_filter_by_amount_above(): void
    {
        SubsidyGrant::factory()->create(['amount' => 500]);
        SubsidyGrant::factory()->create(['amount' => 5000]);
        SubsidyGrant::factory()->create(['amount' => 50000]);

        $r = $this->getJson('/api/v1/subsidies/grants?filter[amount_above]=4000');
        $r->assertSuccessful();
        $r->assertJsonCount(2, 'data');
    }

    public function test_grants_filter_by_grant_date_range(): void
    {
        SubsidyGrant::factory()->create(['grant_date' => '2024-06-15']);
        SubsidyGrant::factory()->create(['grant_date' => '2025-06-15']);
        SubsidyGrant::factory()->create(['grant_date' => '2026-06-15']);

        $r = $this->getJson('/api/v1/subsidies/grants?filter[grant_date_from]=2025-01-01&filter[grant_date_to]=2025-12-31');
        $r->assertSuccessful();
        $r->assertJsonCount(1, 'data');
    }

    public function test_grants_sort_by_amount_desc(): void
    {
        SubsidyGrant::factory()->create(['amount' => 1000]);
        SubsidyGrant::factory()->create(['amount' => 5000]);

        $r = $this->getJson('/api/v1/subsidies/grants?sort=-amount');
        $r->assertSuccessful();
        $this->assertEquals(5000, (float) $r->json('data.0.amount'));
    }

    public function test_grants_disallowed_filter_returns_400(): void
    {
        SubsidyGrant::factory()->create();
        $r = $this->getJson('/api/v1/subsidies/grants?filter[hacker_field]=evil');
        $r->assertStatus(400);
    }

    public function test_calls_response_has_cache_headers(): void
    {
        SubsidyCall::factory()->create();
        $r = $this->getJson('/api/v1/subsidies/calls');
        $r->assertSuccessful();
        $r->assertHeader('Cache-Control', 'public, s-maxage=15, stale-while-revalidate=60');
    }
}
