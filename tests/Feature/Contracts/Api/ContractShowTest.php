<?php

namespace Tests\Feature\Contracts\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\ContractLot;
use Tests\TestCase;

class ContractShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_by_external_id(): void
    {
        $c = Contract::factory()->create(['external_id' => 'https://x/12345']);
        $r = $this->getJson('/api/v1/contracts/'.urlencode($c->external_id));
        $r->assertSuccessful();
        $r->assertJsonPath('data.external_id', $c->external_id);
    }

    public function test_show_includes_lots(): void
    {
        $c = Contract::factory()->create();
        ContractLot::factory()->for($c)->create(['lot_number' => 1]);
        $r = $this->getJson('/api/v1/contracts/'.urlencode($c->external_id).'?include=lots');
        $r->assertSuccessful();
        $r->assertJsonCount(1, 'data.lots');
    }
}
