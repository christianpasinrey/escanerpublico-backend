<?php

namespace Modules\Tax\Services;

use Modules\Tax\DTOs\Calendar\CalendarEntry;
use Modules\Tax\DTOs\Calendar\ObligationCalendar;
use Modules\Tax\Models\TaxRegime;
use Modules\Tax\Models\TaxRegimeObligation;
use Modules\Tax\ValueObjects\ObligationDeadline;
use RuntimeException;

/**
 * Resolución de obligaciones fiscales en fechas concretas para un régimen y año.
 *
 * Detecta el preset adecuado de ObligationDeadline según
 *  - el modelo (130, 131, 303, 390, 100, 184, 200, 202, 220, 222, 349, 369…)
 *  - la periodicidad (quarterly, monthly, annual, on_event)
 *  - el scope del régimen (irpf, iva, is, ss)
 *
 * y materializa todas las fechas del calendario para el año solicitado.
 */
class ObligationsResolver
{
    public function calendarFor(TaxRegime $regime, int $year): ObligationCalendar
    {
        $obligations = $regime->obligations()
            ->orderBy('periodicity')
            ->orderBy('model_code')
            ->get();

        $entries = [];
        foreach ($obligations as $obligation) {
            foreach ($this->datesForObligation($obligation, $regime, $year) as $entry) {
                $entries[] = $entry;
            }
        }

        // Orden cronológico
        usort($entries, fn (CalendarEntry $a, CalendarEntry $b) => $a->date <=> $b->date);

        return new ObligationCalendar(
            regimeCode: $regime->code,
            regimeName: $regime->name,
            regimeScope: $regime->scope,
            year: $year,
            entries: $entries,
        );
    }

    /**
     * @return iterable<CalendarEntry>
     */
    private function datesForObligation(TaxRegimeObligation $obligation, TaxRegime $regime, int $year): iterable
    {
        $deadline = $this->resolveDeadline($obligation);
        $description = $obligation->description ?? '';

        foreach ($deadline->datesFor($year) as $resolved) {
            yield new CalendarEntry(
                date: $resolved['date'],
                modelCode: $obligation->model_code,
                regimeCode: $regime->code,
                periodicity: $obligation->periodicity,
                label: trim(sprintf(
                    'Modelo %s%s',
                    $obligation->model_code,
                    $resolved['label'] !== '' ? ' — '.$resolved['label'] : '',
                )),
                description: $description,
                sourceUrl: $obligation->source_url,
            );
        }
    }

    private function resolveDeadline(TaxRegimeObligation $obligation): ObligationDeadline
    {
        $model = $obligation->model_code;
        $periodicity = $obligation->periodicity;

        // Modelos anuales
        if ($periodicity === 'annual') {
            return match ($model) {
                '100' => ObligationDeadline::irpfAnnual(),
                '184' => ObligationDeadline::arAnnual(),
                '200', '220' => ObligationDeadline::isAnnual(),
                '390' => ObligationDeadline::vatAnnual(),
                default => throw new RuntimeException("No hay preset anual para modelo {$model}."),
            };
        }

        // Modelos trimestrales
        if ($periodicity === 'quarterly') {
            return match ($model) {
                '202', '222' => ObligationDeadline::isFractional(),
                '369' => ObligationDeadline::ossQuarterly(),
                default => ObligationDeadline::quarterly(),
            };
        }

        // Mensuales (gran empresa o intracomunitario)
        if ($periodicity === 'monthly') {
            return ObligationDeadline::intracomMonthly();
        }

        throw new RuntimeException("Periodicidad desconocida: {$periodicity}");
    }
}
