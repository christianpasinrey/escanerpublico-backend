<?php

namespace Tests\Feature\Tax\Console;

use Database\Seeders\Modules\Tax\Parameters\TaxParameters2025Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxValidateCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_fails_with_empty_db(): void
    {
        $this->artisan('tax:validate', ['year' => 2025])
            ->assertExitCode(1)
            ->expectsOutputToContain('Validación fallida');
    }

    public function test_validate_passes_after_seeding_2025(): void
    {
        $this->seed(TaxParameters2025Seeder::class);

        $this->artisan('tax:validate', ['year' => 2025])
            ->assertExitCode(0)
            ->expectsOutputToContain('Parámetros fiscales completos para el año 2025');
    }

    public function test_validate_rejects_year_out_of_range(): void
    {
        $this->artisan('tax:validate', ['year' => 1999])
            ->assertExitCode(1);
    }

    public function test_validate_failure_lists_specific_missing_items(): void
    {
        $this->artisan('tax:validate', ['year' => 2025])
            ->expectsOutputToContain('Escala IRPF estatal incompleta')
            ->assertExitCode(1);
    }
}
