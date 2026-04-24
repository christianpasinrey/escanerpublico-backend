<?php

namespace Tests\Unit\Contracts\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\ParseError;
use Modules\Contracts\Models\ReprocessAtomRun;
use Tests\TestCase;

class ParseErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_valid_parse_error(): void
    {
        $e = ParseError::factory()->create();
        $this->assertNotNull($e->id);
        $this->assertInstanceOf(ReprocessAtomRun::class, $e->reprocessAtomRun);
    }

    public function test_nullable_atom_run(): void
    {
        $e = ParseError::factory()->create(['reprocess_atom_run_id' => null]);
        $this->assertNull($e->reprocess_atom_run_id);
        $this->assertNull($e->reprocessAtomRun);
    }
}
