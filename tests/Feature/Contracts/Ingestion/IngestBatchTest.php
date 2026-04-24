<?php

namespace Tests\Feature\Contracts\Ingestion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Services\ContractIngestor;
use Modules\Contracts\Services\Parser\DTOs\EntryDTO;
use Modules\Contracts\Services\Parser\PlacspStreamParser;
use Tests\TestCase;

class IngestBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingests_sample_02_adj_creating_contract_lot_awards(): void
    {
        $parser = app(PlacspStreamParser::class);
        $entries = collect(iterator_to_array($parser->stream(base_path('tests/Fixtures/placsp/sample-02-adj.xml'))))
            ->filter(fn ($e) => $e instanceof EntryDTO)
            ->values()
            ->all();

        $ingestor = app(ContractIngestor::class);
        $result = $ingestor->ingestBatch($entries);

        $this->assertEquals(1, $result->processed);
        $this->assertDatabaseCount('contracts', 1);
        $this->assertDatabaseCount('contract_lots', 1);
        $this->assertDatabaseCount('awards', 1);
        $this->assertDatabaseCount('contract_snapshots', 1);
    }

    public function test_skips_entry_if_older_snapshot_already_processed(): void
    {
        $parser = app(PlacspStreamParser::class);
        $entries = collect(iterator_to_array($parser->stream(base_path('tests/Fixtures/placsp/sample-02-adj.xml'))))
            ->filter(fn ($e) => $e instanceof EntryDTO)
            ->values()
            ->all();

        $ingestor = app(ContractIngestor::class);
        $ingestor->ingestBatch($entries);
        // Bump snapshot_updated_at to the future so the second ingest skips
        Contract::query()->update(['snapshot_updated_at' => now()->addYear()]);

        $result2 = $ingestor->ingestBatch($entries);
        $this->assertEquals(0, $result2->processed);
        $this->assertEquals(1, $result2->skipped);
    }
}
