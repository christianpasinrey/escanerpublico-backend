<?php

namespace Tests\Unit\Contracts\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\ReprocessAtomRun;
use Modules\Contracts\Models\ReprocessRun;
use Tests\TestCase;

class ReprocessRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_many_atom_runs_and_casts_config(): void
    {
        $run = ReprocessRun::factory()->create(['config' => ['foo' => 'bar']]);
        ReprocessAtomRun::factory()->for($run, 'reprocessRun')->count(2)->create();

        $this->assertIsArray($run->config);
        $this->assertEquals('bar', $run->config['foo']);
        $this->assertCount(2, $run->fresh()->atomRuns);
    }

    public function test_atom_run_belongs_to_run(): void
    {
        $atom = ReprocessAtomRun::factory()->create();
        $this->assertInstanceOf(ReprocessRun::class, $atom->reprocessRun);
    }
}
