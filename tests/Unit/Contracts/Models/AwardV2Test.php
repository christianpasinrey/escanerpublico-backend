<?php

namespace Tests\Unit\Contracts\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Award;
use Modules\Contracts\Models\ContractLot;
use Tests\TestCase;

class AwardV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_lot(): void
    {
        $lot = ContractLot::factory()->create();
        $a = Award::factory()->for($lot, 'contractLot')->create();
        $this->assertSame($lot->id, $a->contract_lot_id);
    }

    public function test_casts_amounts_and_dates(): void
    {
        $a = Award::factory()->create([
            'lower_tender_amount' => 1000.50,
            'higher_tender_amount' => 5000.00,
            'start_date' => '2026-03-01',
            'sme_awarded' => true,
        ]);
        $this->assertEquals('1000.50', $a->lower_tender_amount);
        $this->assertEquals('5000.00', $a->higher_tender_amount);
        $this->assertTrue($a->sme_awarded);
        $this->assertEquals('2026-03-01', $a->start_date->format('Y-m-d'));
    }
}
