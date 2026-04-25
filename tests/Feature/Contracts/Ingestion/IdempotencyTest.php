<?php

namespace Tests\Feature\Contracts\Ingestion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Services\ContractIngestor;
use Modules\Contracts\Services\Parser\DTOs\EntryDTO;
use Modules\Contracts\Services\Parser\PlacspStreamParser;
use Tests\Feature\Contracts\Support\DatabaseSnapshot;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_reingest_same_atom_produces_zero_diff(): void
    {
        $parser = app(PlacspStreamParser::class);
        $ingestor = app(ContractIngestor::class);

        $atom = base_path('tests/Fixtures/placsp/full-20-entries.atom');

        $entries = collect(iterator_to_array($parser->stream($atom)))
            ->filter(fn ($e) => $e instanceof EntryDTO)
            ->values()
            ->all();
        $ingestor->ingestBatch($entries);
        $snap1 = DatabaseSnapshot::capture();

        $entries2 = collect(iterator_to_array($parser->stream($atom)))
            ->filter(fn ($e) => $e instanceof EntryDTO)
            ->values()
            ->all();
        $ingestor->ingestBatch($entries2);
        $snap2 = DatabaseSnapshot::capture();

        $this->assertSame(
            $snap1->hash(),
            $snap2->hash(),
            'DB state changed after re-ingesting the same atom — ingestion is not idempotent.'
        );
    }
}
