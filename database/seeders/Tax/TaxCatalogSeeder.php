<?php

namespace Database\Seeders\Tax;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeder agregador del catálogo M2: tipos estatales + autonómicos + tasas + tipos vigentes.
 *
 * Uso:
 *   php artisan db:seed --class="Database\\Seeders\\Tax\\TaxCatalogSeeder"
 *
 * Idempotente: usa updateOrCreate, se puede ejecutar múltiples veces sin
 * generar duplicados. El orden importa: primero los `tax_types`, luego
 * `tax_rates` que dependen de ellos.
 */
class TaxCatalogSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            TaxStateTypesSeeder::class,
            TaxRegionalTypesSeeder::class,
            TaxStateFeesSeeder::class,
            TaxRatesSeeder::class,
            TaxRatesGapsSeeder::class,
        ]);
    }
}
