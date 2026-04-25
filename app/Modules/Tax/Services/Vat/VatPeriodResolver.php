<?php

namespace Modules\Tax\Services\Vat;

use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Period;

/**
 * Resuelve el rango de fechas de un período de autoliquidación IVA.
 *
 *  - Trimestral (modelo 303 — quarter 1..4):
 *      1T: 01-ene a 31-mar
 *      2T: 01-abr a 30-jun
 *      3T: 01-jul a 30-sep
 *      4T: 01-oct a 31-dic
 *  - Anual (modelo 390 — quarter null):
 *      01-ene a 31-dic
 *
 * Fuente: art. 71 RIVA (períodos de declaración del IVA).
 */
class VatPeriodResolver
{
    public function resolve(FiscalYear $year, ?int $quarter): Period
    {
        if ($quarter === null) {
            return Period::fromYear($year);
        }

        return new Period(
            from: $year->quarterStart($quarter),
            to: $year->quarterEnd($quarter),
        );
    }

    /**
     * Indica si un período es el último del ejercicio. Determina si una
     * cuota negativa puede solicitar devolución (4T o anual) o solo
     * compensación (1T-3T).
     */
    public function isLastPeriod(?int $quarter): bool
    {
        return $quarter === null || $quarter === 4;
    }

    public function label(FiscalYear $year, ?int $quarter): string
    {
        return $quarter === null ? "Anual {$year->year}" : "{$quarter}T {$year->year}";
    }
}
