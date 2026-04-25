<?php

namespace Modules\Legislation\Console;

use Illuminate\Console\Command;
use Modules\Contracts\Services\EntityResolver;
use Modules\Legislation\Models\LegislationIngestRun;
use Modules\Legislation\Services\BoeClient;
use Modules\Legislation\Services\LegislationIngestor;

class SyncLegislation extends Command
{
    protected $signature = 'legislation:sync
        {--type=summaries : "summaries" o "consolidated"}
        {--from= : Fecha inicio YYYY-MM-DD (solo summaries)}
        {--to= : Fecha fin YYYY-MM-DD (solo summaries; default: hoy)}
        {--limit=50 : Registros por página (consolidated)}
        {--max-pages= : Límite páginas (smoke testing)}
        {--max-days= : Límite días (smoke testing summaries)}
        {--resume : Reanudar último run pausado/fallido}';

    protected $description = 'Sincroniza sumarios diarios o legislación consolidada del BOE.';

    public function handle(BoeClient $client, LegislationIngestor $ingestor, EntityResolver $resolver): int
    {
        $type = $this->option('type');
        if (! in_array($type, ['summaries', 'consolidated'], true)) {
            $this->error('--type debe ser "summaries" o "consolidated"');

            return self::FAILURE;
        }

        $resolver->preload();

        $run = $this->option('resume')
            ? $this->resumeRun($type)
            : $this->createRun($type);

        if ($run === null) {
            $this->error('No hay run reanudable para type='.$type);

            return self::FAILURE;
        }

        $run->update(['status' => 'running', 'started_at' => $run->started_at ?? now()]);

        try {
            if ($type === 'summaries') {
                $this->syncSummaries($client, $ingestor, $run);
            } else {
                $this->syncConsolidated($client, $ingestor, $run);
            }

            $resolver->persistCaches();
            $run->update(['status' => 'completed', 'finished_at' => now()]);
            $this->info('✔ Completado type='.$type);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $run->update(['status' => 'failed', 'finished_at' => now(), 'error_message' => $e->getMessage()]);
            $this->error('Sync abortado: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function syncSummaries(BoeClient $client, LegislationIngestor $ingestor, LegislationIngestRun $run): void
    {
        $cursorDate = $run->cursor_date
            ? \DateTimeImmutable::createFromInterface($run->cursor_date)
            : ($run->from_date ? \DateTimeImmutable::createFromInterface($run->from_date) : new \DateTimeImmutable('2024-01-01'));

        $endDate = $run->to_date
            ? \DateTimeImmutable::createFromInterface($run->to_date)
            : new \DateTimeImmutable('today');

        $maxDays = $this->option('max-days') !== null ? (int) $this->option('max-days') : null;
        $consecutiveFails = 0;
        $stats = ['days_processed' => 0, 'items_inserted' => 0, 'items_updated' => 0, 'items_skipped' => 0, 'days_empty' => 0];

        $current = $cursorDate;
        while ($current <= $endDate) {
            $yyyymmdd = $current->format('Ymd');
            $isoDate = $current->format('Y-m-d');

            try {
                $summaryData = $client->getDailySummary($yyyymmdd);
                $consecutiveFails = 0;
            } catch (\Throwable $e) {
                $consecutiveFails++;
                $this->warn("  ↳ {$isoDate} failed (try {$consecutiveFails}/10): ".$e->getMessage());
                if ($consecutiveFails >= 10) {
                    throw new \RuntimeException("Aborted after 10 consecutive day failures starting at {$isoDate}", 0, $e);
                }
                $current = $current->modify('+1 day');
                $run->update(['cursor_date' => $isoDate]);
                sleep(min(60, 5 * $consecutiveFails));

                continue;
            }

            if ($summaryData === null) {
                // Domingo / festivo — no hay BOE. Saltamos.
                $stats['days_empty']++;
                $this->info("  · {$isoDate} sin sumario (festivo/domingo)");
            } else {
                $result = $ingestor->ingestDailySummary($summaryData, $isoDate);
                $stats['items_inserted'] += $result['items_inserted'];
                $stats['items_updated'] += $result['items_updated'];
                $stats['items_skipped'] += $result['items_skipped'];
                $this->info(sprintf(
                    '  ✓ %s id=%s items: +%d ↻%d ⊘%d',
                    $isoDate,
                    $result['summary']->identificador,
                    $result['items_inserted'],
                    $result['items_updated'],
                    $result['items_skipped']
                ));
            }

            $stats['days_processed']++;
            $current = $current->modify('+1 day');
            $run->update([
                'cursor_date' => $current->format('Y-m-d'),
                'processed_records' => $run->processed_records + 1,
            ]);

            if ($maxDays !== null && $stats['days_processed'] >= $maxDays) {
                $this->info("→ alcanzado --max-days={$maxDays}, parando.");
                break;
            }
        }

        $this->info(sprintf(
            '✔ Summaries: %d días (vacíos %d). Items: ins=%d upd=%d skp=%d',
            $stats['days_processed'],
            $stats['days_empty'],
            $stats['items_inserted'],
            $stats['items_updated'],
            $stats['items_skipped']
        ));
    }

    private function syncConsolidated(BoeClient $client, LegislationIngestor $ingestor, LegislationIngestRun $run): void
    {
        $limit = (int) $this->option('limit');
        $maxPages = $this->option('max-pages') !== null ? (int) $this->option('max-pages') : null;
        $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        $consecutiveFails = 0;
        $offset = $run->cursor_offset;
        $page = 0;

        while (true) {
            try {
                $response = $client->searchConsolidatedLegislation($offset, $limit);
                $consecutiveFails = 0;
            } catch (\Throwable $e) {
                $consecutiveFails++;
                $this->warn(sprintf('offset=%d failed (try %d/10): %s', $offset, $consecutiveFails, $e->getMessage()));
                if ($consecutiveFails >= 10) {
                    throw new \RuntimeException("Aborted after 10 consecutive failures at offset {$offset}", 0, $e);
                }
                $offset += $limit;
                $run->update(['cursor_offset' => $offset]);
                sleep(min(60, 5 * $consecutiveFails));

                continue;
            }

            $records = $response['data'] ?? [];
            if (! is_array($records) || empty($records)) {
                break;
            }

            foreach ($records as $record) {
                if (! is_array($record)) {
                    continue;
                }
                try {
                    $result = $ingestor->ingestConsolidatedNorm($record);
                    $stats[$result['action']]++;
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    $this->warn(sprintf('  ↳ failed (id=%s): %s', $record['identificador'] ?? '?', $e->getMessage()));
                }
            }

            $offset += count($records);
            $page++;
            $run->update([
                'cursor_offset' => $offset,
                'processed_records' => $run->processed_records + count($records),
                'failed_records' => $stats['failed'],
            ]);

            $this->info(sprintf(
                'page %d offset=%d  +%d (acum: ins=%d upd=%d skp=%d fail=%d)',
                $page,
                $offset,
                count($records),
                $stats['inserted'],
                $stats['updated'],
                $stats['skipped'],
                $stats['failed']
            ));

            if (count($records) < $limit) {
                break; // última página
            }
            if ($maxPages !== null && $page >= $maxPages) {
                $this->info("→ alcanzado --max-pages={$maxPages}, parando.");
                break;
            }
        }

        $this->info(sprintf(
            '✔ Consolidated: ins=%d upd=%d skp=%d fail=%d',
            $stats['inserted'],
            $stats['updated'],
            $stats['skipped'],
            $stats['failed']
        ));
    }

    private function createRun(string $type): LegislationIngestRun
    {
        return LegislationIngestRun::create([
            'type' => $type,
            'cursor_offset' => 0,
            'cursor_date' => null,
            'from_date' => $this->option('from'),
            'to_date' => $this->option('to'),
            'status' => 'pending',
        ]);
    }

    private function resumeRun(string $type): ?LegislationIngestRun
    {
        return LegislationIngestRun::where('type', $type)
            ->whereIn('status', ['paused', 'failed', 'running'])
            ->orderByDesc('id')
            ->first();
    }
}
