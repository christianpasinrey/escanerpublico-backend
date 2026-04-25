<?php

namespace Modules\Officials\Console;

use Illuminate\Console\Command;
use Modules\Legislation\Models\BoeItem;
use Modules\Officials\Models\Appointment;
use Modules\Officials\Services\OfficialIngestor;

class ExtractOfficials extends Command
{
    protected $signature = 'officials:extract
        {--force : Reprocesar incluso boe_items que ya tienen appointment asociado}
        {--limit= : Límite total de items a procesar (smoke testing)}';

    protected $description = 'Extrae nombramientos/ceses de boe_items en sección II.A.';

    public function handle(OfficialIngestor $ingestor): int
    {
        $force = (bool) $this->option('force');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $query = BoeItem::where('seccion_code', '2A');
        if (! $force) {
            // Saltamos los que ya tienen appointment para no rehacer trabajo
            $alreadyExtractedIds = Appointment::pluck('boe_item_id');
            if ($alreadyExtractedIds->isNotEmpty()) {
                $query->whereNotIn('id', $alreadyExtractedIds);
            }
        }

        $total = (clone $query)->count();
        if ($limit !== null) {
            $total = min($total, $limit);
        }
        $this->info("Procesando {$total} boe_items de sección II.A...");

        $stats = ['extracted' => 0, 'pattern_not_matched' => 0, 'skipped_collective' => 0];
        $processed = 0;

        $query->orderBy('id')->chunk(500, function ($items) use ($ingestor, $limit, &$stats, &$processed) {
            foreach ($items as $item) {
                if ($limit !== null && $processed >= $limit) {
                    return false;
                }

                try {
                    $result = $ingestor->ingestFromBoeItem($item);
                    $stats[$result['result']]++;
                } catch (\Throwable $e) {
                    $this->warn("  ↳ failed (item {$item->id}): ".$e->getMessage());
                }
                $processed++;

                if ($processed % 100 === 0) {
                    $this->info(sprintf(
                        '  ...%d procesados (extracted=%d, no_match=%d)',
                        $processed,
                        $stats['extracted'],
                        $stats['pattern_not_matched']
                    ));
                }
            }
        });

        $this->newLine();
        $this->info(sprintf(
            '✔ Procesados %d. Extracted=%d. No match=%d. Skipped collective=%d.',
            $processed,
            $stats['extracted'],
            $stats['pattern_not_matched'],
            $stats['skipped_collective']
        ));
        if ($processed > 0) {
            $rate = round(($stats['extracted'] / $processed) * 100, 1);
            $this->info("Tasa de extracción: {$rate}%");
        }

        return self::SUCCESS;
    }
}
