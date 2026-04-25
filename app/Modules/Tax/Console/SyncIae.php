<?php

namespace Modules\Tax\Console;

use Illuminate\Console\Command;
use Modules\Tax\Ingestion\IaeImporter;

/**
 * Sincroniza el IAE desde un fixture JSON o desde un HTML local.
 *
 * Uso típico:
 *   php artisan tax:sync-iae                       # usa fixture committeado
 *   php artisan tax:sync-iae --json=path/to/iae.json
 *   php artisan tax:sync-iae --html=path/to/iae.html
 */
class SyncIae extends Command
{
    protected $signature = 'tax:sync-iae
        {--json= : Ruta a un fichero JSON con la estructura jerárquica IAE.}
        {--html= : Ruta a un fichero HTML descargado de la sede AEAT.}';

    protected $description = 'Importa el Impuesto sobre Actividades Económicas a la tabla economic_activities.';

    public function handle(IaeImporter $importer): int
    {
        $json = $this->option('json');
        $html = $this->option('html');

        if ($html !== null && $html !== '') {
            $contents = @file_get_contents($html);
            if ($contents === false) {
                $this->error("No se pudo leer HTML: {$html}");

                return self::FAILURE;
            }
            $stats = $importer->importFromHtml($contents);
        } else {
            $path = $json !== null && $json !== ''
                ? $json
                : base_path('app/Modules/Tax/Ingestion/data/iae_seed.json');
            if (! is_file($path)) {
                $this->error("Fixture IAE no encontrado: {$path}");

                return self::FAILURE;
            }
            $stats = $importer->importFromJson($path);
        }

        $this->info(sprintf(
            '✔ IAE sync completed: ins=%d upd=%d skp=%d',
            $stats['inserted'],
            $stats['updated'],
            $stats['skipped'],
        ));

        return self::SUCCESS;
    }
}
