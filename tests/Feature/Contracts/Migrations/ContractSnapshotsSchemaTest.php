<?php

namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractSnapshotsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('contract_snapshots'));
        $cols = ['id', 'contract_id', 'entry_updated_at', 'status_code', 'content_hash', 'payload', 'source_atom', 'ingested_at', 'created_at', 'updated_at'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('contract_snapshots', $c), "Missing {$c}");
        }
    }
}
