<?php

namespace Tests\Feature\Subsidies;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Company;
use Modules\Contracts\Models\Organization;
use Modules\Subsidies\Models\SubsidyCall;
use Modules\Subsidies\Models\SubsidyGrant;
use Modules\Subsidies\Models\SubsidySnapshot;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_subsidy_call_can_be_created_with_organization(): void
    {
        $org = Organization::factory()->create();
        $call = SubsidyCall::factory()->for($org, 'organization')->create();

        $this->assertEquals($org->id, $call->organization->id);
        $this->assertEquals('BDNS', $call->source);
    }

    public function test_subsidy_grant_belongs_to_call_organization_and_company(): void
    {
        $call = SubsidyCall::factory()->create();
        $org = Organization::factory()->create();
        $company = Company::factory()->create();

        $grant = SubsidyGrant::factory()
            ->for($call, 'call')
            ->for($org, 'organization')
            ->for($company, 'company')
            ->create();

        $this->assertEquals($call->id, $grant->call->id);
        $this->assertEquals($org->id, $grant->organization->id);
        $this->assertEquals($company->id, $grant->company->id);
    }

    public function test_unique_constraint_on_source_external_id_for_grants(): void
    {
        SubsidyGrant::factory()->create(['source' => 'BDNS', 'external_id' => 12345]);
        $this->expectException(UniqueConstraintViolationException::class);
        SubsidyGrant::factory()->create(['source' => 'BDNS', 'external_id' => 12345]);
    }

    public function test_unique_constraint_on_source_external_id_for_calls(): void
    {
        SubsidyCall::factory()->create(['source' => 'BDNS', 'external_id' => 999]);
        $this->expectException(UniqueConstraintViolationException::class);
        SubsidyCall::factory()->create(['source' => 'BDNS', 'external_id' => 999]);
    }

    public function test_subsidy_grant_can_have_snapshots(): void
    {
        $grant = SubsidyGrant::factory()->create();
        SubsidySnapshot::factory()->for($grant, 'grant')->count(3)->create();

        $this->assertCount(3, $grant->refresh()->snapshots);
    }

    public function test_subsidy_call_can_have_grants(): void
    {
        $call = SubsidyCall::factory()->create();
        SubsidyGrant::factory()->for($call, 'call')->count(5)->create();

        $this->assertCount(5, $call->refresh()->grants);
    }
}
