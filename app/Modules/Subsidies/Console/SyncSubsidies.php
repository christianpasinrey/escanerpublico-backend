<?php

namespace Modules\Subsidies\Console;

use Illuminate\Console\Command;
use Modules\Contracts\Services\EntityResolver;
use Modules\Subsidies\Models\SubsidyIngestRun;
use Modules\Subsidies\Services\BdnsClient;
use Modules\Subsidies\Services\SubsidyIngestor;

class SyncSubsidies extends Command
{
    protected $signature = 'subsidies:sync
        {--type=grants : "calls" o "grants"}
        {--from= : Fecha inicio dd/MM/yyyy (opcional)}
        {--to= : Fecha fin dd/MM/yyyy (opcional)}
        {--page-size=100 : Registros por página BDNS}
        {--max-pages= : Límite de páginas (smoke testing)}
        {--resume : Reanudar el último run pausado o fallido}
        {--vpd= : Filtro vpd BDNS opcional (e.g. GE)}';

    protected $description = 'Sincroniza convocatorias o concesiones de BDNS via API REST pública.';

    public function handle(BdnsClient $client, SubsidyIngestor $ingestor, EntityResolver $resolver): int
    {
        $type = $this->option('type');
        if (! in_array($type, ['calls', 'grants'], true)) {
            $this->error('--type debe ser "calls" o "grants"');

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

        $filters = $this->buildFilters();
        $pageSize = (int) $this->option('page-size');
        $maxPages = $this->option('max-pages') !== null ? (int) $this->option('max-pages') : null;

        $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

        try {
            $page = $run->cursor_page;
            while (true) {
                $payload = $type === 'calls'
                    ? $client->searchCalls($page, $pageSize, $filters)
                    : $client->searchGrants($page, $pageSize, $filters);

                $totalPages = (int) ($payload['totalPages'] ?? 0);
                $totalElements = (int) ($payload['totalElements'] ?? 0);
                if ($run->total_pages !== $totalPages || $run->total_elements !== $totalElements) {
                    $run->update(['total_pages' => $totalPages, 'total_elements' => $totalElements]);
                }

                $records = $payload['content'] ?? [];
                if (empty($records)) {
                    break;
                }

                foreach ($records as $record) {
                    try {
                        $result = $type === 'calls'
                            ? $ingestor->ingestCall($record)
                            : $ingestor->ingestGrant($record);
                        $stats[$result['action']]++;
                    } catch (\Throwable $e) {
                        $stats['failed']++;
                        $this->warn(sprintf('  ↳ failed (id=%s): %s', $record['id'] ?? '?', $e->getMessage()));
                    }
                }

                $run->update([
                    'cursor_page' => $page + 1,
                    'processed_records' => $run->processed_records + count($records),
                    'failed_records' => $stats['failed'],
                ]);

                $this->info(sprintf(
                    'page %d/%d  +%d ↻%d ⊘%d ✗%d  (acum: ins=%d upd=%d skp=%d fail=%d)',
                    $page,
                    max(0, $totalPages - 1),
                    array_count_values(array_map(fn ($r) => $r['id'] ?? null, $records))['inserted'] ?? 0,
                    0,
                    0,
                    0,
                    $stats['inserted'],
                    $stats['updated'],
                    $stats['skipped'],
                    $stats['failed'],
                ));

                $page++;
                if ($payload['last'] ?? false) {
                    break;
                }
                if ($maxPages !== null && ($page - $run->cursor_page + ($run->cursor_page - $page + $maxPages)) >= $maxPages) {
                    $this->info("→ alcanzado --max-pages={$maxPages}, parando.");
                    break;
                }
            }

            $resolver->persistCaches();
            $run->update(['status' => 'completed', 'finished_at' => now()]);
            $this->newLine();
            $this->info(sprintf(
                '✔ Completado type=%s: ins=%d upd=%d skp=%d fail=%d',
                $type,
                $stats['inserted'],
                $stats['updated'],
                $stats['skipped'],
                $stats['failed'],
            ));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
            $this->error('Sync abortado: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function createRun(string $type): SubsidyIngestRun
    {
        return SubsidyIngestRun::create([
            'type' => $type,
            'cursor_page' => 0,
            'from_date' => $this->parseInputDate($this->option('from')),
            'to_date' => $this->parseInputDate($this->option('to')),
            'status' => 'pending',
        ]);
    }

    private function resumeRun(string $type): ?SubsidyIngestRun
    {
        return SubsidyIngestRun::where('type', $type)
            ->whereIn('status', ['paused', 'failed', 'running'])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, scalar>
     */
    private function buildFilters(): array
    {
        $filters = [];
        if ($from = $this->option('from')) {
            $filters['fechaDesde'] = $from;
        }
        if ($to = $this->option('to')) {
            $filters['fechaHasta'] = $to;
        }
        if ($vpd = $this->option('vpd')) {
            $filters['vpd'] = $vpd;
        }

        return $filters;
    }

    private function parseInputDate(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        try {
            // Acepta dd/MM/yyyy o yyyy-MM-dd
            $dt = str_contains($input, '/')
                ? \DateTimeImmutable::createFromFormat('d/m/Y', $input)
                : new \DateTimeImmutable($input);
            if ($dt === false) {
                return null;
            }

            return $dt->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
