<?php

namespace Tests\Unit\Contracts\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\ContractLot;
use Tests\TestCase;

class ContractLotTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_valid_lot(): void
    {
        $lot = ContractLot::factory()->create();
        $this->assertNotNull($lot->id);
        $this->assertInstanceOf(Contract::class, $lot->contract);
    }

    public function test_casts(): void
    {
        $lot = ContractLot::factory()->create([
            'cpv_codes' => ['12345678', '87654321'],
            'start_date' => '2026-01-01',
        ]);
        $this->assertIsArray($lot->cpv_codes);
        $this->assertEquals('2026-01-01', $lot->start_date->format('Y-m-d'));
    }
}
