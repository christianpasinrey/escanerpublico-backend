<?php

namespace Modules\Tax\Console;

use Illuminate\Console\Command;
use Modules\Tax\Models\ActivityRegimeMapping;
use Modules\Tax\Models\EconomicActivity;
use Modules\Tax\Models\TaxRegime;
use Modules\Tax\Models\TaxRegimeCompatibility;
use Modules\Tax\Models\TaxRegimeObligation;

/**
 * Reporte de cobertura del catálogo Tax: cuántos regímenes/actividades/obligaciones
 * están sembrados, agrupados por scope/system.
 */
class ReportRegimeCoverage extends Command
{
    protected $signature = 'tax:report-regime-coverage';

    protected $description = 'Reporta el estado de cobertura del catálogo Tax M1 (regímenes, actividades, obligaciones).';

    public function handle(): int
    {
        $this->info('=== Catálogo Tax M1 — Cobertura ===');
        $this->newLine();

        // Regímenes por scope
        $this->info('• Regímenes por scope:');
        $byScope = TaxRegime::query()
            ->selectRaw('scope, COUNT(*) as total')
            ->groupBy('scope')
            ->pluck('total', 'scope')
            ->all();

        foreach (['irpf', 'iva', 'is', 'ss'] as $scope) {
            $count = $byScope[$scope] ?? 0;
            $this->line(sprintf('   %-6s %d regímenes', strtoupper($scope), $count));
        }
        $this->line(sprintf('   %-6s %d total', 'TOTAL', TaxRegime::query()->count()));
        $this->newLine();

        // Compatibilidades
        $compatTotal = TaxRegimeCompatibility::query()->count();
        $byType = TaxRegimeCompatibility::query()
            ->selectRaw('compatibility, COUNT(*) as total')
            ->groupBy('compatibility')
            ->pluck('total', 'compatibility')
            ->all();
        $this->info("• Compatibilidades: {$compatTotal} total");
        foreach (['exclusive', 'required', 'optional'] as $type) {
            $count = $byType[$type] ?? 0;
            $this->line(sprintf('   %-12s %d', $type, $count));
        }
        $this->newLine();

        // Obligaciones
        $oblTotal = TaxRegimeObligation::query()->count();
        $byPeriod = TaxRegimeObligation::query()
            ->selectRaw('periodicity, COUNT(*) as total')
            ->groupBy('periodicity')
            ->pluck('total', 'periodicity')
            ->all();
        $this->info("• Obligaciones: {$oblTotal} total");
        foreach (['monthly', 'quarterly', 'annual', 'on_event'] as $p) {
            $count = $byPeriod[$p] ?? 0;
            $this->line(sprintf('   %-12s %d', $p, $count));
        }
        $this->newLine();

        // Actividades por sistema y nivel
        $this->info('• Actividades económicas:');
        foreach (['cnae', 'iae'] as $system) {
            $total = EconomicActivity::query()->where('system', $system)->count();
            $this->line("   System {$system}: {$total} total");
            $byLevel = EconomicActivity::query()
                ->where('system', $system)
                ->selectRaw('level, COUNT(*) as total')
                ->groupBy('level')
                ->pluck('total', 'level')
                ->all();
            for ($l = 1; $l <= 5; $l++) {
                $count = $byLevel[$l] ?? 0;
                $this->line(sprintf('     L%d %d', $l, $count));
            }
        }
        $this->newLine();

        // Mappings
        $this->info('• Mappings actividad → régimen: '.ActivityRegimeMapping::query()->count());
        $withVat = ActivityRegimeMapping::query()->whereNotNull('vat_rate_default')->count();
        $withRet = ActivityRegimeMapping::query()->whereNotNull('irpf_retention_default')->count();
        $this->line("   con vat_rate_default: {$withVat}");
        $this->line("   con irpf_retention_default: {$withRet}");

        return self::SUCCESS;
    }
}
