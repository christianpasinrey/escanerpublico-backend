<?php

namespace Tests\Unit\Tax\Calculators\Payroll;

use Database\Seeders\Modules\Tax\Parameters\TaxParameters2024Seeder;
use Database\Seeders\Modules\Tax\Parameters\TaxParameters2025Seeder;
use Database\Seeders\Modules\Tax\Parameters\TaxParameters2026Seeder;
use Illuminate\Support\Facades\Cache;
use Modules\Tax\Services\TaxParameterRepository;

/**
 * Trait que asegura que los parámetros fiscales 2024-2026 están sembrados
 * en la BD de testing. Se usa en tests unitarios y feature.
 */
trait SeedsTaxParameters
{
    protected function seedTaxParameters(int ...$years): void
    {
        $years = $years === [] ? [2025] : $years;

        $map = [
            2024 => TaxParameters2024Seeder::class,
            2025 => TaxParameters2025Seeder::class,
            2026 => TaxParameters2026Seeder::class,
        ];

        foreach ($years as $year) {
            if (! isset($map[$year])) {
                throw new \InvalidArgumentException("Año {$year} no soportado en el helper de tests");
            }
            $this->seed($map[$year]);
        }

        // Limpiamos cache en memoria del repositorio para no arrastrar valores de
        // otros tests dentro del mismo proceso.
        $this->app->forgetInstance(TaxParameterRepository::class);
        // En testing usamos array cache; flush para invalidar.
        Cache::flush();
    }
}
