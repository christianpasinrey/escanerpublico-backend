<?php

namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractsLegacyCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_criterios_adjudicacion_column_is_gone(): void
    {
        $this->assertFalse(Schema::hasColumn('contracts', 'criterios_adjudicacion'));
    }
}
