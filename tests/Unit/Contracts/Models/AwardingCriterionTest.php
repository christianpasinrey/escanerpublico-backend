<?php

namespace Tests\Unit\Contracts\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\AwardingCriterion;
use Modules\Contracts\Models\ContractLot;
use Tests\TestCase;

class AwardingCriterionTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_lot(): void
    {
        $c = AwardingCriterion::factory()->create();
        $this->assertInstanceOf(ContractLot::class, $c->contractLot);
    }

    public function test_weight_casts_decimal(): void
    {
        $c = AwardingCriterion::factory()->create(['weight_numeric' => 70.5]);
        $this->assertEquals('70.50', $c->weight_numeric);
    }
}
