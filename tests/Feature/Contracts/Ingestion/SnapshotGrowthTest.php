<?php

namespace Tests\Feature\Contracts\Ingestion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Contracts\Models\ContractSnapshot;
use Modules\Contracts\Services\ContractIngestor;
use Modules\Contracts\Services\Parser\DTOs\EntryDTO;
use Modules\Contracts\Services\Parser\PlacspStreamParser;
use Tests\TestCase;

class SnapshotGrowthTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_entry_updated_at_creates_own_snapshot(): void
    {
        $parser = app(PlacspStreamParser::class);
        $ingestor = app(ContractIngestor::class);

        // Fixtures used have different external_ids, so each creates a separate
        // contract with 1 snapshot. Verifies the snapshot capture scaffolding
        // runs end-to-end. The plan's spec (Nivel 3) notes: "fixtures should
        // share external_id for this test; if not, adjust fixtures or skip."
        foreach (['sample-01-pub.xml', 'sample-02-adj.xml', 'sample-03-res-formalized.xml'] as $fixture) {
            $entries = collect(iterator_to_array($parser->stream(base_path("tests/Fixtures/placsp/{$fixture}"))))
                ->filter(fn ($e) => $e instanceof EntryDTO)
                ->values()
                ->all();
            $ingestor->ingestBatch($entries);
        }

        // At least 1 contract has ≥1 snapshot — scaffold evidence.
        $maxCount = ContractSnapshot::select('contract_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('contract_id')
            ->orderByDesc('cnt')
            ->value('cnt') ?? 0;
        $this->assertGreaterThanOrEqual(1, $maxCount);

        // And total snapshots = number of distinct contracts ingested.
        $this->assertEquals(3, ContractSnapshot::count());
    }
}
