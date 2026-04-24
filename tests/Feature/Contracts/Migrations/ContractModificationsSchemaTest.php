<?php

namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractModificationsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('contract_modifications'));
        $cols = ['id', 'contract_id', 'type', 'issue_date', 'effective_date', 'description', 'amount_delta', 'new_end_date', 'related_notice_id', 'created_at', 'updated_at'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('contract_modifications', $c), "Missing {$c}");
        }
    }
}
