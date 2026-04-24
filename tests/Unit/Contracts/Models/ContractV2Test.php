<?php

namespace Tests\Unit\Contracts\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\ContractLot;
use Tests\TestCase;

class ContractV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_has_lots_relation(): void
    {
        $c = Contract::factory()->create();
        ContractLot::factory()->for($c)->create(['lot_number' => 1]);
        $this->assertCount(1, $c->fresh()->lots);
    }

    public function test_casts_v2_columns(): void
    {
        $c = Contract::factory()->create([
            'mix_contract_indicator' => true,
            'over_threshold_indicator' => false,
            'snapshot_updated_at' => '2026-01-01 10:00:00',
            'annulled_at' => '2026-02-01 11:00:00',
        ]);
        $this->assertTrue($c->mix_contract_indicator);
        $this->assertFalse($c->over_threshold_indicator);
        $this->assertNotNull($c->snapshot_updated_at);
        $this->assertNotNull($c->annulled_at);
    }

    public function test_scope_not_annulled(): void
    {
        Contract::factory()->create(['annulled_at' => now()]);
        Contract::factory()->create(['annulled_at' => null]);
        $this->assertCount(1, Contract::notAnnulled()->get());
    }

    public function test_route_key_is_external_id(): void
    {
        $c = Contract::factory()->create();
        $this->assertEquals('external_id', $c->getRouteKeyName());
    }
}
