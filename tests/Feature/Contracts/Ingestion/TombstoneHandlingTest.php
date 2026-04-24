<?php

namespace Tests\Feature\Contracts\Ingestion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Services\ContractIngestor;
use Modules\Contracts\Services\Parser\DTOs\TombstoneDTO;
use Tests\TestCase;

class TombstoneHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_tombstone_marks_contract_as_annulled(): void
    {
        $c = Contract::factory()->create(['external_id' => 'https://x/19163035', 'status_code' => 'ADJ']);

        $dto = new TombstoneDTO(ref: 'https://x/19163035', when: new \DateTimeImmutable('2026-03-20T14:48:14+01:00'));
        app(ContractIngestor::class)->handleTombstone($dto);

        $c->refresh();
        $this->assertEquals('ANUL', $c->status_code);
        $this->assertNotNull($c->annulled_at);
    }

    public function test_tombstone_for_unknown_contract_is_noop(): void
    {
        $dto = new TombstoneDTO(ref: 'https://x/99999999', when: new \DateTimeImmutable('2026-03-20T14:48:14+01:00'));
        app(ContractIngestor::class)->handleTombstone($dto);

        $this->assertDatabaseCount('contracts', 0);
    }
}
