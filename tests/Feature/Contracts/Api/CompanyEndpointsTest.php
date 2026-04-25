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
        $r->assertJsonStructure(['total_awards', 'total_amount', 'by_year']);
    }
}
