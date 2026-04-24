<?php

namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AwardsV2ColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_awards_has_contract_lot_id_and_new_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('awards', 'contract_lot_id'));
        $this->assertFalse(Schema::hasColumn('awards', 'contract_id'));
        $new = ['description','start_date','lower_tender_amount','higher_tender_amount','smes_received_tender_quantity'];
        foreach ($new as $c) {
            $this->assertTrue(Schema::hasColumn('awards', $c), "Missing {$c}");
        }
    }
}
