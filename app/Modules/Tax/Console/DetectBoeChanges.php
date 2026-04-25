<?php

namespace Modules\Tax\Console;

use Illuminate\Console\Command;
use Modules\Tax\Services\BoeParameterDetector;

/**
 * Lanza el detector de cambios en parámetros fiscales sobre la tabla
 * legislation_norms del módulo Legislation. Crea entradas en
 * tax_parameter_alerts para revisión administrativa.
 */
class DetectBoeChanges extends Command
{
    protected $signature = 'tax:detect-boe-changes';

    protected $description = 'Escanea legislation_norms y crea alertas en tax_parameter_alerts.';

    public function handle(BoeParameterDetector $detector): int
    {
        $created = $detector->scan();
        $statusCounts = $detector->statusCounts();

        $this->info("Alertas nuevas creadas: {$created}");

        if (! empty($statusCounts)) {
            $rows = [];
            foreach ($statusCounts as $status => $total) {
                $rows[] = [$status, (string) $total];
            }
            $this->table(['Estado', 'Total'], $rows);
        }

        return self::SUCCESS;
    }
}
