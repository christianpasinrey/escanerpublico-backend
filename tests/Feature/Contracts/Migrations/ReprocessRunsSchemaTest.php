<?php

namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReprocessRunsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_reprocess_runs_table(): void
    {
        $this->assertTrue(Schema::hasTable('reprocess_runs'));
        foreach (['id', 'name', 'status', 'started_at', 'finished_at', 'total_atoms', 'processed_atoms', 'total_entries', 'failed_entries', 'config'] as $c) {
            $this->assertTrue(Schema::hasColumn('reprocess_runs', $c), "Missing {$c}");
        }
    }

    public function test_reprocess_atom_runs_table(): void
    {
        $this->assertTrue(Schema::hasTable('reprocess_atom_runs'));
        foreach (['id', 'reprocess_run_id', 'atom_path', 'atom_hash', 'status', 'started_at', 'finished_at', 'entries_processed', 'entries_failed', 'error_message'] as $c) {
            $this->assertTrue(Schema::hasColumn('reprocess_atom_runs', $c), "Missing {$c}");
        }
    }
}
