<?php

namespace Tests\Feature\Tax;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Models\EconomicActivity;
use Tests\TestCase;

class ConsoleCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tax_sync_cnae_uses_committed_fixture(): void
    {
        $this->artisan('tax:sync-cnae')
            ->expectsOutputToContain('CNAE sync completed')
            ->assertExitCode(0);

        $this->assertGreaterThan(50, EconomicActivity::query()->where('system', 'cnae')->count());
    }

    public function test_tax_sync_iae_uses_committed_fixture(): void
    {
        $this->artisan('tax:sync-iae')
            ->expectsOutputToContain('IAE sync completed')
            ->assertExitCode(0);

        $this->assertGreaterThan(50, EconomicActivity::query()->where('system', 'iae')->count());
    }

    public function test_tax_report_regime_coverage_runs(): void
    {
        $this->artisan('tax:report-regime-coverage')
            ->expectsOutputToContain('Catálogo Tax M1')
            ->assertExitCode(0);
    }
}
