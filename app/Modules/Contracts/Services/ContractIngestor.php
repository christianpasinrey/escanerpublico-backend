<?php

namespace Modules\Contracts\Services;

use Modules\Contracts\Jobs\PurgeContractUrls;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Services\Cache\ContractCacheInvalidator;
use Modules\Contracts\Services\Parser\DTOs\EntryDTO;
use Modules\Contracts\Services\Parser\DTOs\TombstoneDTO;

class ContractIngestor
{
    public function __construct(
        private EntityResolver $resolver,
        private ContractCacheInvalidator $invalidator,
    ) {}

    public function handleTombstone(TombstoneDTO $t): void
    {
        $c = Contract::where('external_id', $t->ref)->first();
        if (! $c) {
            return;
        }

        $c->update([
            'status_code' => 'ANUL',
            'annulled_at' => $t->when,
            'snapshot_updated_at' => $t->when,
        ]);

        $this->invalidator->invalidateContract((int) $c->id);
        PurgeContractUrls::dispatch([(int) $c->id]);
    }

    /**
     * @param  EntryDTO[]  $entries
     */
    public function ingestBatch(array $entries): BatchResult
    {
        // Full implementation lands in Task 5.
        return new BatchResult(0, 0, 0);
    }
}
