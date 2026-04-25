<?php

namespace Modules\Tax\Console;

use Illuminate\Console\Command;
use Modules\Tax\Enums\LevyType;
use Modules\Tax\Enums\Scope;
use Modules\Tax\Models\TaxRate;
use Modules\Tax\Models\TaxType;

/**
 * Reporta cobertura del catálogo M2: cuántos tax_types y tax_rates hay
 * por scope, levy_type y region_code. Útil tras seed o ingestión.
 */
class ReportTypesCoverage extends Command
{
    protected $signature = 'tax:report-types-coverage
        {--json : Devuelve el reporte como JSON}';

    protected $description = 'Reporta cobertura de tax_types y tax_rates por scope/levy_type/region_code (M2).';

    public function handle(): int
    {
        $report = $this->buildReport();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->renderHumanReport($report);

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildReport(): array
    {
        $totalTypes = TaxType::count();
        $totalRates = TaxRate::count();

        $byScope = [];
        foreach (Scope::cases() as $scope) {
            $byScope[$scope->value] = [
                'label' => $scope->label(),
                'types' => TaxType::where('scope', $scope->value)->count(),
                'rates' => TaxRate::whereHas('taxType', fn ($q) => $q->where('scope', $scope->value))->count(),
            ];
        }

        $byLevy = [];
        foreach (LevyType::cases() as $levy) {
            $byLevy[$levy->value] = [
                'label' => $levy->label(),
                'types' => TaxType::where('levy_type', $levy->value)->count(),
            ];
        }

        $byRegion = TaxType::query()
            ->selectRaw('region_code, COUNT(*) as total')
            ->whereNotNull('region_code')
            ->groupBy('region_code')
            ->orderBy('region_code')
            ->pluck('total', 'region_code')
            ->all();

        $byYear = TaxRate::query()
            ->selectRaw('year, COUNT(*) as total')
            ->groupBy('year')
            ->orderBy('year')
            ->pluck('total', 'year')
            ->all();

        return [
            'total_types' => $totalTypes,
            'total_rates' => $totalRates,
            'by_scope' => $byScope,
            'by_levy' => $byLevy,
            'by_region' => $byRegion,
            'by_year' => $byYear,
            'meets_m2_minimums' => $totalTypes >= 25 && $totalRates >= 50,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderHumanReport(array $report): void
    {
        $this->info('Tax catalog coverage report (M2)');
        $this->line('================================');
        $this->line("Total tax_types: {$report['total_types']}");
        $this->line("Total tax_rates: {$report['total_rates']}");
        $this->line('');

        $this->info('Por scope:');
        $rows = [];
        foreach ($report['by_scope'] as $scope => $data) {
            $rows[] = [$scope, $data['label'], $data['types'], $data['rates']];
        }
        $this->table(['scope', 'label', 'types', 'rates'], $rows);

        $this->info('Por levy_type:');
        $rows = [];
        foreach ($report['by_levy'] as $levy => $data) {
            $rows[] = [$levy, $data['label'], $data['types']];
        }
        $this->table(['levy_type', 'label', 'types'], $rows);

        $this->info('Por region_code (regional/local):');
        $rows = [];
        foreach ($report['by_region'] as $region => $count) {
            $rows[] = [$region, $count];
        }
        $this->table(['region_code', 'count'], $rows);

        $this->info('Por año (rates):');
        $rows = [];
        foreach ($report['by_year'] as $year => $count) {
            $rows[] = [$year, $count];
        }
        $this->table(['year', 'count'], $rows);

        if ($report['meets_m2_minimums']) {
            $this->info('Cumple mínimos M2 (>= 25 types, >= 50 rates).');
        } else {
            $this->warn('No cumple mínimos M2 (>= 25 types, >= 50 rates).');
        }
    }
}
