<?php

namespace Modules\Tax\Services\IncomeTax;

use Database\Seeders\Modules\Tax\IncomeTax\EoModulesDataProvider;
use InvalidArgumentException;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;

/**
 * Calculador del rendimiento neto en Estimación Objetiva (módulos) IRPF.
 *
 * Algoritmo art. 32 LIRPF + Anexo II Orden HFP anual:
 *   rendimiento_neto_previo = Σ (signo_i × valor_unidad_i) por cada módulo de la actividad
 *
 * Para MVP ofrecemos:
 *   - Cobertura de top-20 epígrafes IAE (taxi, bar, peluquería, etc.).
 *   - Reducción general del 5 % vigente (DT 32ª LIRPF) ya integrada en la
 *     calculadora general — aquí solo devolvemos el rendimiento previo.
 *
 * Si el código IAE no está cubierto, lanza InvalidArgumentException con un
 * mensaje claro indicando "fuera de MVP" y la lista de soportados.
 *
 * @see https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764#a32 (art. 32 LIRPF)
 * @see https://www.boe.es/buscar/doc.php?id=BOE-A-2024-26896 (Orden HFP/1397/2024 — módulos 2025)
 */
class EoModulesCalculator
{
    /**
     * Calcula el rendimiento neto previo (sin minoración 5 %) para una actividad
     * EO según los módulos declarados.
     *
     * @param  array<string, float|int>|null  $modulesData
     */
    public function calculatePreviousYield(
        FiscalYear $year,
        string $activityCode,
        ?array $modulesData,
    ): Money {
        $activity = EoModulesDataProvider::activity($year->year, $activityCode);

        if ($activity === null) {
            $supported = implode(', ', EoModulesDataProvider::supportedActivityCodes($year->year));
            throw new InvalidArgumentException(
                "El epígrafe IAE '{$activityCode}' no está cubierto en MVP de Estimación Objetiva. "
                ."Actividades soportadas: {$supported}. ".
                'Consulte la Orden HFP anual para cálculo manual.',
            );
        }

        $modulesData ??= [];

        $total = Money::zero();
        foreach ($activity['modules'] as $key => $module) {
            $units = (float) ($modulesData[$key] ?? 0);
            if ($units <= 0) {
                continue;
            }

            $valuePerUnit = (float) $module['value_per_unit'];
            $contribution = Money::fromFloat($units * $valuePerUnit);
            $total = $total->add($contribution);
        }

        return $total;
    }

    /**
     * Devuelve los detalles de los módulos aplicados por una actividad. Útil
     * para construir el desglose línea a línea (cada módulo una BreakdownLine).
     *
     * @param  array<string, float|int>|null  $modulesData
     * @return list<array{
     *   key: string,
     *   label: string,
     *   units: float,
     *   value_per_unit: float,
     *   amount: Money
     * }>
     */
    public function moduleLines(FiscalYear $year, string $activityCode, ?array $modulesData): array
    {
        $activity = EoModulesDataProvider::activity($year->year, $activityCode);
        if ($activity === null) {
            return [];
        }

        $modulesData ??= [];
        $lines = [];

        foreach ($activity['modules'] as $key => $module) {
            $units = (float) ($modulesData[$key] ?? 0);
            $valuePerUnit = (float) $module['value_per_unit'];
            $amount = Money::fromFloat($units * $valuePerUnit);

            $lines[] = [
                'key' => $key,
                'label' => (string) $module['label'],
                'units' => $units,
                'value_per_unit' => $valuePerUnit,
                'amount' => $amount,
            ];
        }

        return $lines;
    }

    /**
     * Devuelve la URL BOE de la Orden HFP que regula los módulos del año.
     */
    public function legalReference(FiscalYear $year, string $activityCode): string
    {
        $activity = EoModulesDataProvider::activity($year->year, $activityCode);

        return $activity['source_url'] ?? 'https://www.boe.es/';
    }

    public function isSupported(FiscalYear $year, string $activityCode): bool
    {
        return EoModulesDataProvider::activity($year->year, $activityCode) !== null;
    }

    /**
     * @return list<string>
     */
    public function supportedActivityCodes(FiscalYear $year): array
    {
        return EoModulesDataProvider::supportedActivityCodes($year->year);
    }
}
