<?php

namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ParseErrorsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_errors_table(): void
    {
        $this->assertTrue(Schema::hasTable('parse_errors'));
        foreach (['id', 'reprocess_atom_run_id', 'atom_path', 'entry_external_id', 'error_code', 'error_message', 'raw_fragment'] as $c) {
            $this->assertTrue(Schema::hasColumn('parse_errors', $c), "Missing {$c}");
        }
    }
}
