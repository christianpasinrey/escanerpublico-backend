<?php

namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContractsFulltextTest extends TestCase
{
    use RefreshDatabase;

    public function test_contracts_has_fulltext_index_on_objeto_and_expediente(): void
    {
        $rows = DB::select("SHOW INDEX FROM contracts WHERE Index_type = 'FULLTEXT'");
        $indexNames = array_unique(array_column($rows, 'Key_name'));
        $this->assertContains('contracts_objeto_expediente_fulltext', $indexNames);
    }
}
