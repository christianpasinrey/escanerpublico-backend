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
        $r->assertJsonStructure([
            'total_contracts',
            'total_amount',
            'avg_amount',
            'unique_companies',
            'by_status',
            'by_type',
            'by_year',
            'top_companies',
        ]);
    }

    public function test_stats_top_companies_includes_name_and_nif(): void
    {
        $org = Organization::factory()->create();
        $contract = Contract::factory()->for($org, 'organization')->create();
        $lot = \Modules\Contracts\Models\ContractLot::factory()->for($contract, 'contract')->create();
        $company = \Modules\Contracts\Models\Company::factory()->create([
            'name' => 'ACME Construcciones SL',
            'nif' => 'B98765432',
        ]);
        \Modules\Contracts\Models\Award::factory()->for($company)->for($lot, 'contractLot')->create(['amount' => 75000]);

        $r = $this->getJson("/api/v1/organizations/{$org->id}/stats");
        $r->assertSuccessful();
        $r->assertJsonPath('top_companies.0.name', 'ACME Construcciones SL');
        $r->assertJsonPath('top_companies.0.nif', 'B98765432');
        $r->assertJsonPath('top_companies.0.contracts_count', 1);
    }

    public function test_top_companies_excludes_suspect_amount_awards(): void
    {
        $org = Organization::factory()->create();
        $contract = Contract::factory()->for($org, 'organization')->create();
        $lot = \Modules\Contracts\Models\ContractLot::factory()->for($contract, 'contract')->create();

        $legit = \Modules\Contracts\Models\Company::factory()->create(['name' => 'Empresa Legítima', 'nif' => 'B11111111']);
        $erratic = \Modules\Contracts\Models\Company::factory()->create(['name' => 'Talleres Errata', 'nif' => 'B22222222']);

        \Modules\Contracts\Models\Award::factory()->for($legit)->for($lot, 'contractLot')->create(['amount' => 100_000]);
        // Errata PLACSP — 251.5B €. No debe encabezar el ranking.
        \Modules\Contracts\Models\Award::factory()->for($erratic)->for($lot, 'contractLot')->create(['amount' => 251_520_154_010]);

        $r = $this->getJson("/api/v1/organizations/{$org->id}/stats");
        $r->assertSuccessful();
        // El primer puesto debe ser la empresa legítima, no la del importe sospechoso.
        $r->assertJsonPath('top_companies.0.name', 'Empresa Legítima');
    }
}
