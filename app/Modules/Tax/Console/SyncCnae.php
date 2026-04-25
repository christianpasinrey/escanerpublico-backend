<?php

namespace Modules\Tax\Console;

use Illuminate\Console\Command;
use Modules\Tax\Ingestion\CnaeImporter;

/**
 * Sincroniza la CNAE-2025 desde un fichero XLS local.
 *
 * Uso típico:
 *   php artisan tax:sync-cnae --file=storage/imports/cnae2025.xlsx
 *
 * Si --file no se proporciona, se importa el fixture committeado en
 * app/Modules/Tax/Ingestion/data/cnae2025_seed.json.
 */
class SyncCnae extends Command
{
    protected $signature = 'tax:sync-cnae
        {--file= : Ruta absoluta a un fichero XLSX oficial (INE / RD 10/2025). Si se omite, usa el fixture JSON committeado.}';

    protected $description = 'Importa la Clasificación Nacional de Actividades Económicas 2025 a la tabla economic_activities.';

    public function handle(CnaeImporter $importer): int
    {
        $file = $this->option('file');

        if ($file !== null && $file !== '') {
            $this->info("→ Importando CNAE desde XLSX: {$file}");
            $stats = $importer->importFromXlsx($file);
        } else {
            $fixture = base_path('app/Modules/Tax/Ingestion/data/cnae2025_seed.json');
            if (! is_file($fixture)) {
                $this->error("Fixture no encontrado: {$fixture}");

                return self::FAILURE;
            }
            $contents = file_get_contents($fixture);
            $rows = is_string($contents) ? json_decode($contents, true) : null;
            if (! is_array($rows)) {
                $this->error('Fixture JSON inválido.');

                return self::FAILURE;
            }
            $this->info("→ Importando CNAE desde fixture committeado ({$fixture})");
            $stats = $importer->importFromArray($rows);
        }

        $this->info(sprintf(
            '✔ CNAE sync completed: ins=%d upd=%d skp=%d',
            $stats['inserted'],
            $stats['updated'],
            $stats['skipped'],
        ));

        return self::SUCCESS;
    }
}
