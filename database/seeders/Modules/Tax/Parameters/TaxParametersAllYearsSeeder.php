<?php

namespace Database\Seeders\Modules\Tax\Parameters;

use Illuminate\Database\Seeder;

/**
 * Lanza todos los seeders anuales 2023→2026 en orden.
 */
class TaxParametersAllYearsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TaxParameters2023Seeder::class,
            TaxParameters2024Seeder::class,
            TaxParameters2025Seeder::class,
            TaxParameters2026Seeder::class,
        ]);
    }
}
