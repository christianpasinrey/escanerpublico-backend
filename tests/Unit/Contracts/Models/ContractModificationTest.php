<?php

namespace Tests\Unit\Contracts\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\ContractModification;
use Tests\TestCase;

class ContractModificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_contract(): void
    {
        $m = ContractModification::factory()->create();
        $this->assertInstanceOf(Contract::class, $m->contract);
    }

    public function test_casts_dates_and_decimal(): void
    {
        $m = ContractModification::factory()->create([
            'issue_date' => '2026-05-01',
            'effective_date' => '2026-05-10',
            'new_end_date' => '2026-12-31',
            'amount_delta' => 1234.50,
        ]);
        $this->assertEquals('2026-05-01', $m->issue_date->format('Y-m-d'));
        $this->assertEquals('2026-05-10', $m->effective_date->format('Y-m-d'));
        $this->assertEquals('2026-12-31', $m->new_end_date->format('Y-m-d'));
        $this->assertEquals('1234.50', $m->amount_delta);
    }
}
