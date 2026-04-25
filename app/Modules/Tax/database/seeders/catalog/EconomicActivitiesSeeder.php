<?php

namespace Modules\Tax\database\seeders\catalog;

use Illuminate\Database\Seeder;
use Modules\Tax\Ingestion\CnaeImporter;
use Modules\Tax\Ingestion\IaeImporter;

/**
 * Seeder de actividades económicas.
 *
 * Estrategia:
 *  - Importa el fixture CNAE-2025 committeado en app/Modules/Tax/Ingestion/data/cnae2025_seed.json
 *    como baseline (~150 actividades de los 5 niveles, las más comunes).
 *  - Importa el fixture IAE committeado en app/Modules/Tax/Ingestion/data/iae_seed.json
 *    con la estructura jerárquica top + epígrafes habituales.
 *
 * Para una importación completa (~1.700 actividades CNAE + ~750 epígrafes IAE)
 * usar los comandos `tax:sync-cnae` y `tax:sync-iae`, que descargan/parsean
 * desde fuentes oficiales (INE / AEAT).
 */
class EconomicActivitiesSeeder extends Seeder
{
    public function __construct(
        private readonly CnaeImporter $cnae,
        private readonly IaeImporter $iae,
    ) {}

    public function run(): void
    {
        $cnaeFixture = __DIR__.'/../../../Ingestion/data/cnae2025_seed.json';
        $iaeFixture = __DIR__.'/../../../Ingestion/data/iae_seed.json';

        $this->seedCnaeFromJson($cnaeFixture);
        $this->seedIaeFromJson($iaeFixture);
    }

    private function seedCnaeFromJson(string $path): void
    {
        if (! is_file($path)) {
            return;
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            return;
        }
        /** @var array<int, array<string, mixed>>|null $rows */
        $rows = json_decode($contents, true);
        if (! is_array($rows)) {
            return;
        }
        $this->cnae->importFromArray($rows);
    }

    private function seedIaeFromJson(string $path): void
    {
        if (! is_file($path)) {
            return;
        }
        $this->iae->importFromJson($path);
    }
}
