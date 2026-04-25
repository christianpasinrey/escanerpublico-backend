<?php

namespace Modules\Tax\Calculators\IncomeTax;

use Modules\Tax\DTOs\BreakdownCategory;
use Modules\Tax\DTOs\BreakdownLine;
use Modules\Tax\DTOs\IncomeTax\IncomeTaxInput;
use Modules\Tax\Services\IncomeTax\EoModulesCalculator;
use Modules\Tax\Services\IncomeTax\IncomeTaxDeductionsCalculator;
use Modules\Tax\Services\IncomeTax\PersonalMinimumCalculator;
use Modules\Tax\Services\IncomeTax\WorkIncomeReductionCalculator;
use Modules\Tax\Services\IrpfScaleResolver;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\TaxRate;
use RuntimeException;

/**
 * Estimación Objetiva (módulos) — IRPF actividades económicas.
 *
 * Aplicable a las actividades enumeradas en el Anexo II de la Orden HFP anual
 * (top-20 actividades en MVP) cuando el contribuyente no haya renunciado.
 *
 * Rendimiento neto = Σ (signo × valor unidad) por cada módulo declarado, con
 * una reducción del 5 % (DT 32ª LIRPF) prorrogada por la Ley 31/2022 hasta 2024
 * y por la Ley 7/2024 hasta 2025.
 *
 * En MVP NO calculamos:
 *  - Minoración por incentivos al empleo / inversión.
 *  - Índices correctores (urbano vs rural, antigüedad, temporada, etc.).
 *  - Reducción especial agraria.
 *
 * Si la actividad no está cubierta en MVP, se rechaza con InvalidArgumentException
 * desde el EoModulesCalculator.
 *
 * @see https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764#a32 (art. 32 LIRPF)
 * @see https://www.boe.es/buscar/doc.php?id=BOE-A-2024-26896 (Orden HFP/1397/2024)
 */
class EstimacionObjetivaModulos extends AbstractIncomeTaxRegime
{
    public function __construct(
        IrpfScaleResolver $scaleResolver,
        PersonalMinimumCalculator $minimumCalculator,
        WorkIncomeReductionCalculator $workReductionCalculator,
        IncomeTaxDeductionsCalculator $deductionsCalculator,
        private readonly EoModulesCalculator $eoCalculator,
    ) {
        parent::__construct(
            $scaleResolver,
            $minimumCalculator,
            $workReductionCalculator,
            $deductionsCalculator,
        );
    }

    /**
     * @param  list<BreakdownLine>  $lines
     */
    protected function activityNet(IncomeTaxInput $input, array &$lines): Money
    {
        $activity = $input->economicActivity;
        if ($activity === null) {
            throw new RuntimeException('EO requiere economicActivity.');
        }

        $modulesData = $activity->eoModulesData ?? [];

        $lines[] = new BreakdownLine(
            concept: "Actividad EO declarada — IAE {$activity->activityCode}",
            amount: $activity->grossRevenue,
            category: BreakdownCategory::BASE,
            legalReference: $this->eoCalculator->legalReference($input->year, $activity->activityCode),
            explanation: 'Estimación Objetiva (módulos): el rendimiento se determina aplicando los signos/índices de la Orden HFP, no por ingresos − gastos. La cifra de negocio se conserva sólo a título informativo.',
        );

        // Línea por cada módulo aplicado.
        $moduleLines = $this->eoCalculator->moduleLines(
            $input->year,
            $activity->activityCode,
            $modulesData,
        );

        foreach ($moduleLines as $module) {
            if ($module['units'] <= 0) {
                continue; // No mostramos módulos sin unidades.
            }
            $lines[] = new BreakdownLine(
                concept: "Módulo: {$module['label']}",
                amount: $module['amount'],
                category: BreakdownCategory::BASE,
                rate: TaxRate::fromPercentage(0),
                legalReference: $this->eoCalculator->legalReference($input->year, $activity->activityCode),
                explanation: "Unidades: {$module['units']} × {$module['value_per_unit']} €/unidad",
            );
        }

        // Rendimiento neto previo = Σ módulos.
        $rendimientoPrevio = $this->eoCalculator->calculatePreviousYield(
            $input->year,
            $activity->activityCode,
            $modulesData,
        );

        $lines[] = new BreakdownLine(
            concept: 'Rendimiento neto previo (módulos)',
            amount: $rendimientoPrevio,
            category: BreakdownCategory::BASE,
            legalReference: $this->eoCalculator->legalReference($input->year, $activity->activityCode),
            explanation: 'Suma del rendimiento generado por cada módulo de la actividad antes de aplicar la minoración general del 5 %.',
        );

        // Minoración general 5 % (DT 32ª LIRPF prorrogada).
        $reduction = $rendimientoPrevio->applyRate(TaxRate::fromPercentage(5));
        if (! $reduction->isZero()) {
            $lines[] = new BreakdownLine(
                concept: 'Reducción 5 % rendimiento neto EO (DT 32ª LIRPF)',
                amount: $reduction,
                category: BreakdownCategory::REDUCTION,
                base: $rendimientoPrevio,
                legalReference: 'https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764#dt32',
                explanation: 'Reducción general del 5 % sobre el rendimiento neto en EO, prorrogada por la Ley 31/2022 (BOE-A-2022-22128) y mantenida por la Ley 7/2024 (BOE-A-2024-26905) para 2024-2025.',
            );
        }

        $rendimiento = $rendimientoPrevio->subtract($reduction);
        if ($rendimiento->isNegative()) {
            $rendimiento = Money::zero();
        }

        $lines[] = new BreakdownLine(
            concept: 'Rendimiento neto actividad económica (EO)',
            amount: $rendimiento,
            category: BreakdownCategory::BASE,
            legalReference: 'https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764#a32',
            explanation: 'Rendimiento neto definitivo EO = rendimiento previo − reducción general 5 %.',
        );

        return $rendimiento;
    }
}
