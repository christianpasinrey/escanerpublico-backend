<?php

namespace Modules\Tax\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Tax\database\seeders\catalog\ActivityRegimeMappingsSeeder;
use Modules\Tax\database\seeders\catalog\EconomicActivitiesSeeder;
use Modules\Tax\database\seeders\catalog\TaxRegimeCompatibilitySeeder;
use Modules\Tax\database\seeders\catalog\TaxRegimeObligationsSeeder;
use Modules\Tax\database\seeders\catalog\TaxRegimesSeeder;

/**
 * Orquesta el seeding completo del catálogo M1 del módulo Tax.
 *
 * Orden imprescindible:
 *  1. Regímenes (la tabla padre)
 *  2. Compatibilidades (FK a regímenes)
 *  3. Obligaciones (FK a regímenes)
 *  4. Actividades económicas (CNAE/IAE; sin FK)
 *  5. Mappings actividad ↔ regímenes (FK a actividades)
 */
class TaxCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TaxRegimesSeeder::class,
            TaxRegimeCompatibilitySeeder::class,
            TaxRegimeObligationsSeeder::class,
            EconomicActivitiesSeeder::class,
            ActivityRegimeMappingsSeeder::class,
        ]);
    }
}
