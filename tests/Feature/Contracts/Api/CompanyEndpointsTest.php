<?php

namespace Tests\Feature\Contracts\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Award;
use Modules\Contracts\Models\Company;
use Modules\Contracts\Models\ContractLot;
use Tests\TestCase;

class CompanyEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index(): void
    {
        Company::factory()->count(3)->create();
        $r = $this->getJson('/api/v1/companies');
        $r->assertSuccessful();
    }

    public function test_show_with_awards(): void
    {
        $c = Company::factory()->create();
        $lot = ContractLot::factory()->create();
        Award::factory()->for($c)->for($lot, 'contractLot')->create();

        $r = $this->getJson("/api/v1/companies/{$c->id}?include=awards");
        $r->assertSuccessful();
        $r->assertJsonCount(1, 'data.awards');
    }

    public function test_stats(): void
    {
        $c = Company::factory()->create();
        $r = $this->getJson("/api/v1/companies/{$c->id}/stats");
        $r->assertSuccessful();
        $r->assertJsonStructure([
            'total_awards',
            'total_amount',
            'avg_amount',
            'unique_organizations',
            'unique_contracts',
            'suspect_awards_count',
            'by_year',
            'by_status',
            'top_organizations',
        ]);
    }

    public function test_stats_excludes_suspect_amount_awards_from_aggregations(): void
    {
        $c = Company::factory()->create();
        $contract = \Modules\Contracts\Models\Contract::factory()->create();
        $lotLegit = ContractLot::factory()->for($contract, 'contract')->create();
        $lotErrata = ContractLot::factory()->for($contract, 'contract')->create();

        // Adjudicación legítima 50.000 €
        Award::factory()->for($c)->for($lotLegit, 'contractLot')->create(['amount' => 50_000]);
        // Adjudicación atípica 251.5B € (errata PLACSP)
        Award::factory()->for($c)->for($lotErrata, 'contractLot')->create(['amount' => 251_520_154_010]);

        $r = $this->getJson("/api/v1/companies/{$c->id}/stats");
        $r->assertSuccessful();
        $r->assertJsonPath('total_awards', 1);
        $r->assertJsonPath('total_amount', 50000);
        $r->assertJsonPath('suspect_awards_count', 1);
    }

    public function test_stats_top_organizations_includes_name_and_dir3(): void
    {
        $org = \Modules\Contracts\Models\Organization::factory()->create([
            'name' => 'Ayuntamiento de Test',
            'identifier' => 'A12345678',
        ]);
        $contract = \Modules\Contracts\Models\Contract::factory()->for($org, 'organization')->create();
        $lot = ContractLot::factory()->for($contract, 'contract')->create();
        $c = Company::factory()->create();
        Award::factory()->for($c)->for($lot, 'contractLot')->create(['amount' => 50000]);

        $r = $this->getJson("/api/v1/companies/{$c->id}/stats");
        $r->assertSuccessful();
        $r->assertJsonPath('top_organizations.0.name', 'Ayuntamiento de Test');
        $r->assertJsonPath('top_organizations.0.dir3_code', 'A12345678');
        $r->assertJsonPath('top_organizations.0.contracts_count', 1);
    }
}
