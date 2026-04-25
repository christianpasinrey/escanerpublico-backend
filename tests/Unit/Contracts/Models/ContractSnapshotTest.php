<?php

namespace Tests\Unit\Contracts\Models;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\ContractSnapshot;
use Tests\TestCase;

class ContractSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_contract_and_casts_payload(): void
    {
        $s = ContractSnapshot::factory()->create(['payload' => ['foo' => 'bar']]);
        $this->assertInstanceOf(Contract::class, $s->contract);
        $this->assertIsArray($s->payload);
        $this->assertEquals('bar', $s->payload['foo']);
    }

    public function test_unique_per_contract_and_entry_updated_at(): void
    {
        $c = Contract::factory()->create();
        $at = '2026-03-01 10:00:00';
        ContractSnapshot::factory()->for($c)->create(['entry_updated_at' => $at]);
        $this->expectException(QueryException::class);
        ContractSnapshot::factory()->for($c)->create(['entry_updated_at' => $at]);
    }
}
