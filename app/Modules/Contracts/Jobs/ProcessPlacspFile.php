<?php

namespace Modules\Contracts\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Contracts\Models\ParseError;
use Modules\Contracts\Models\ReprocessAtomRun;
use Modules\Contracts\Services\BatchResult;
use Modules\Contracts\Services\ContractIngestor;
use Modules\Contracts\Services\Parser\DTOs\EntryDTO;
use Modules\Contracts\Services\Parser\DTOs\TombstoneDTO;
use Modules\Contracts\Services\Parser\PlacspStreamParser;

class ProcessPlacspFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public string $atomPath,
        public ?int $atomRunId = null,
    ) {}

    public function handle(PlacspStreamParser $parser, ContractIngestor $ingestor): void
    {
        $atomRun = $this->atomRunId !== null ? ReprocessAtomRun::find($this->atomRunId) : null;
        $atomRun?->update(['status' => 'running', 'started_at' => now()]);

        /** @var EntryDTO[] $batch */
        $batch = [];
        $processed = 0;
        $failed = 0;
        $batchSize = 500;

        try {
            foreach ($parser->stream($this->atomPath) as $item) {
                if ($item instanceof TombstoneDTO) {
                    try {
                        $ingestor->handleTombstone($item);
                    } catch (\Throwable $e) {
                        $failed++;
                        $this->logParseError($item->ref, 'TOMBSTONE_FAILED', $e->getMessage(), null);
                    }

                    continue;
                }
                if ($item instanceof EntryDTO) {
                    $batch[] = $item;
                    if (count($batch) >= $batchSize) {
                        $result = $this->flushBatch($ingestor, $batch);
                        $processed += $result->processed;
                        $failed += $result->errored;
                        $batch = [];
                    }
                }
            }
            if ($batch !== []) {
                $result = $this->flushBatch($ingestor, $batch);
                $processed += $result->processed;
                $failed += $result->errored;
            }

            $atomRun?->update([
                'status' => 'completed',
                'finished_at' => now(),
                'entries_processed' => $processed,
                'entries_failed' => $failed,
            ]);

            Log::info('PLACSP atom processed', [
                'atom' => $this->atomPath,
                'entries_ok' => $processed,
                'entries_failed' => $failed,
            ]);
        } catch (\Throwable $e) {
            $atomRun?->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  EntryDTO[]  $batch
     */
    private function flushBatch(ContractIngestor $ingestor, array $batch): BatchResult
    {
        try {
            return $ingestor->ingestBatch($batch);
        } catch (\Throwable $e) {
            foreach ($batch as $entry) {
                $this->logParseError($entry->external_id, 'INGEST_BATCH_FAILED', $e->getMessage(), null);
            }

            return new BatchResult(0, 0, count($batch));
        }
    }

    private function logParseError(?string $externalId, string $code, string $message, ?string $fragment): void
    {
        ParseError::create([
            'reprocess_atom_run_id' => $this->atomRunId,
            'atom_path' => $this->atomPath,
            'entry_external_id' => $externalId,
            'error_code' => $code,
            'error_message' => $message,
            'raw_fragment' => $fragment,
        ]);
    }
}
