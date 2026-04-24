<?php

namespace Tests\Feature\Contracts\Ingestion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Jobs\ProcessPlacspFile;
use Modules\Contracts\Models\ReprocessAtomRun;
use Modules\Contracts\Models\ReprocessRun;
use Modules\Contracts\Services\ContractIngestor;
use Modules\Contracts\Services\Parser\PlacspStreamParser;
use Tests\TestCase;

class ProcessPlacspFileJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_processes_atom_and_updates_atom_run(): void
    {
        $run = ReprocessRun::factory()->create();
        $atomRun = ReprocessAtomRun::factory()->for($run)->create([
            'atom_path' => base_path('tests/Fixtures/placsp/sample-02-adj.xml'),
            'status' => 'pending',
        ]);

        (new ProcessPlacspFile($atomRun->atom_path, $atomRun->id))->handle(
            app(PlacspStreamParser::class),
            app(ContractIngestor::class),
        );

        $atomRun->refresh();
        $this->assertEquals('completed', $atomRun->status);
        $this->assertGreaterThan(0, $atomRun->entries_processed);
    }
}
