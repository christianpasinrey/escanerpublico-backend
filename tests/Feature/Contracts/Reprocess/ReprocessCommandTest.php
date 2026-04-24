<?php

namespace Tests\Feature\Contracts\Reprocess;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Contracts\Models\ReprocessAtomRun;
use Modules\Contracts\Models\ReprocessRun;
use Tests\TestCase;

class ReprocessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reprocess_sync_with_explicit_atom(): void
    {
        $atom = base_path('tests/Fixtures/placsp/sample-02-adj.xml');

        Artisan::call('contracts:reprocess', [
            '--atoms' => $atom,
            '--sync' => true,
        ]);

        $this->assertDatabaseCount('reprocess_runs', 1);
        $run = ReprocessRun::first();
        $this->assertEquals('completed', $run->status);
        $this->assertEquals(1, $run->total_atoms);

        $atomRun = ReprocessAtomRun::first();
        $this->assertEquals('completed', $atomRun->status);
    }

    public function test_resume_skips_completed_atoms(): void
    {
        $run = ReprocessRun::factory()->create(['status' => 'failed']);
        ReprocessAtomRun::factory()->for($run)->create(['status' => 'completed']);
        $atom = base_path('tests/Fixtures/placsp/sample-02-adj.xml');
        ReprocessAtomRun::factory()->for($run)->create(['status' => 'pending', 'atom_path' => $atom]);

        Artisan::call('contracts:reprocess', [
            '--run-id' => $run->id,
            '--sync' => true,
        ]);

        $run->refresh();
        $this->assertEquals('completed', $run->status);
    }
}
