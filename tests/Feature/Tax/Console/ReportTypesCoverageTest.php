<?php

namespace Tests\Feature\Tax\Console;

use Database\Seeders\Tax\TaxCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Console\ReportTypesCoverage;
use Modules\Tax\Models\TaxRate;
use Modules\Tax\Models\TaxType;
use Tests\TestCase;

class ReportTypesCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_runs_successfully_with_empty_database(): void
    {
        $this->artisan('tax:report-types-coverage')
            ->assertExitCode(0)
            ->expectsOutputToContain('Total tax_types: 0')
            ->expectsOutputToContain('No cumple mínimos M2');
    }

    public function test_command_emits_json_with_flag(): void
    {
        TaxType::factory()->create();

        $this->artisan('tax:report-types-coverage --json')
            ->assertExitCode(0);
    }

    public function test_command_reports_meeting_minimums_after_full_seed(): void
    {
        $this->seed(TaxCatalogSeeder::class);

        $this->artisan('tax:report-types-coverage')
            ->assertExitCode(0)
            ->expectsOutputToContain('Cumple mínimos M2');
    }

    public function test_build_report_produces_expected_keys(): void
    {
        TaxType::factory()->create();
        TaxRate::factory()->create();

        $cmd = $this->app->make(ReportTypesCoverage::class);
        $report = $cmd->buildReport();

        $this->assertArrayHasKey('total_types', $report);
        $this->assertArrayHasKey('total_rates', $report);
        $this->assertArrayHasKey('by_scope', $report);
        $this->assertArrayHasKey('by_levy', $report);
        $this->assertArrayHasKey('by_region', $report);
        $this->assertArrayHasKey('by_year', $report);
        $this->assertArrayHasKey('meets_m2_minimums', $report);
    }
}
