<?php

namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractsV2ColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_contracts_has_v2_columns(): void
    {
        $new = ['buyer_profile_uri','activity_code','mix_contract_indicator',
            'funding_program_code','over_threshold_indicator','national_legislation_code',
            'received_appeal_quantity','snapshot_updated_at','annulled_at'];
        foreach ($new as $c) {
            $this->assertTrue(Schema::hasColumn('contracts', $c), "Missing {$c}");
        }
    }
}
