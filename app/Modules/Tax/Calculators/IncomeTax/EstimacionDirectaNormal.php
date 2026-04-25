<?php

namespace Modules\Tax\Calculators\IncomeTax;

use Modules\Tax\DTOs\BreakdownCategory;
use Modules\Tax\DTOs\BreakdownLine;
use Modules\Tax\DTOs\IncomeTax\IncomeTaxInput;
use Modules\Tax\ValueObjects\Money;
use RuntimeException;

/**
 * Estimación Directa Normal (EDN) — IRPF actividades económicas.
 *
 * Aplicable a autónomos (empresarios o profesionales) cuyo importe neto de la
 * cifra de negocio del año anterior supere 600.000 €, o que renuncien a EDS.
 *
 * Rendimiento neto = ingresos íntegros − gastos íntegramente deducibles.
 *
 * Particularidad respecto a EDS: en EDN sí se admiten gastos reales
 * íntegramente, sin la limitación de 5/7 % de gastos genéricos no probados.
 *
 * Si el resultado es negativo, en MVP lo capamos a 0 a efectos de base liquidable
 * (en el modelo 100 real podrían compensarse rendimientos negativos con
 * positivos del mismo grupo en años siguientes — fuera de alcance MVP).
 *
 * @see https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764#a28 (art. 28-30 LIRPF)
 */
class EstimacionDirectaNormal extends AbstractIncomeTaxRegime
{
    /**
     * @param  list<BreakdownLine>  $lines
     */
    protected function activityNet(IncomeTaxInput $input, array &$lines): Money
    {
        $activity = $input->economicActivity;
        if ($activity === null) {
            throw new RuntimeException('EDN requiere economicActivity.');
        }

        $lines[] = new BreakdownLine(
            concept: 'Ingresos íntegros actividad económica (EDN)',
            amount: $activity->grossRevenue,
            category: BreakdownCategory::BASE,
            legalReference: self::BOE_LIRPF.'#a27',
            explanation: "Cifra de negocio anual de la actividad {$activity->activityCode}. Aplicable Estimación Directa Normal (cifra > 600.000 €/año).",
        );

        $lines[] = new BreakdownLine(
            concept: 'Gastos íntegramente deducibles (EDN)',
            amount: $activity->deductibleExpenses,
            category: BreakdownCategory::REDUCTION,
            legalReference: self::BOE_LIRPF.'#a28',
            explanation: 'Gastos relacionados con la actividad económica íntegramente deducibles según la normativa del IS (art. 28 LIRPF + Ley 27/2014 IS).',
        );

        $rendimiento = $activity->grossRevenue->subtract($activity->deductibleExpenses);

        $lines[] = new BreakdownLine(
            concept: 'Rendimiento neto actividad económica (EDN)',
            amount: $rendimiento,
            category: BreakdownCategory::BASE,
            legalReference: self::BOE_LIRPF.'#a28',
            explanation: 'Rendimiento neto EDN = ingresos íntegros − gastos íntegramente deducibles. En EDN no aplican gastos genéricos del 5/7 % (sí en EDS).',
        );

        if ($rendimiento->isNegative()) {
            // Capamos a 0 para el cómputo de la base liquidable.
            return Money::zero();
        }

        return $rendimiento;
    }
}
