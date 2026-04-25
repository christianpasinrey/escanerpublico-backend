<?php

namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AwardingCriteriaSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_awarding_criteria_table_exists_with_columns(): void
    {
        $this->assertTrue(Schema::hasTable('awarding_criteria'));
        $cols = ['id', 'contract_lot_id', 'type_code', 'subtype_code', 'description', 'note', 'weight_numeric', 'sort_order', 'created_at', 'updated_at'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('awarding_criteria', $c), "Missing {$c}");
        }
    }
}
