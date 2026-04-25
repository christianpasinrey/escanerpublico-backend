<?php

namespace Modules\Tax\Calculators\IncomeTax;

use Modules\Tax\DTOs\BreakdownCategory;
use Modules\Tax\DTOs\BreakdownLine;
use Modules\Tax\DTOs\IncomeTax\IncomeTaxInput;
use Modules\Tax\Services\IncomeTax\IncomeTaxDeductionsCalculator;
use Modules\Tax\Services\IncomeTax\PersonalMinimumCalculator;
use Modules\Tax\Services\IncomeTax\WorkIncomeReductionCalculator;
use Modules\Tax\Services\IrpfScaleResolver;
use Modules\Tax\Services\TaxParameterRepository;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegionCode;
use RuntimeException;

/**
 * Estimación Directa Simplificada (EDS) — IRPF actividades económicas.
 *
 * Aplicable a autónomos cuyo importe neto de la cifra de negocio del año anterior
 * sea ≤ 600.000 €. Es la opción por defecto si no se renuncia.
 *
 * Rendimiento neto = ingresos − gastos − reducción genérica de gastos no probados.
 *
 * La reducción genérica del 5 % (subida transitoriamente al 7 % en 2023+ por
 * Ley 31/2022 art. 64; mantenida 2024-2025 por la Ley 7/2024) se aplica sobre
 * el rendimiento neto previo (ingresos − gastos), con un tope de 2.000 €.
 *
 * Para más detalle se consulta la tabla de parámetros:
 *   - irpf.gastos_genericos_eds_porcentaje (5 % o 7 % según año)
 *   - irpf.gastos_genericos_eds_tope (2.000 €)
 *
 * @see https://www.boe.es/buscar/act.php?id=BOE-A-2007-6820#a30 (art. 30 RIRPF)
 * @see https://www.boe.es/buscar/act.php?id=BOE-A-2022-22128 (Ley 31/2022 — subida 7 %)
 */
class EstimacionDirectaSimplificada extends AbstractIncomeTaxRegime
{
    public function __construct(
        IrpfScaleResolver $scaleResolver,
        PersonalMinimumCalculator $minimumCalculator,
        WorkIncomeReductionCalculator $workReductionCalculator,
        IncomeTaxDeductionsCalculator $deductionsCalculator,
        private readonly TaxParameterRepository $parameterRepository,
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
            throw new RuntimeException('EDS requiere economicActivity.');
        }

        $lines[] = new BreakdownLine(
            concept: 'Ingresos íntegros actividad económica (EDS)',
            amount: $activity->grossRevenue,
            category: BreakdownCategory::BASE,
            legalReference: self::BOE_LIRPF.'#a27',
            explanation: "Cifra de negocio anual de la actividad {$activity->activityCode}. Aplicable Estimación Directa Simplificada (cifra ≤ 600.000 €/año).",
        );

        $lines[] = new BreakdownLine(
            concept: 'Gastos deducibles (EDS)',
            amount: $activity->deductibleExpenses,
            category: BreakdownCategory::REDUCTION,
            legalReference: self::BOE_LIRPF_REGLAMENTO.'#a30',
            explanation: 'Gastos íntegramente probados deducibles. EDS añade además una reducción genérica para gastos de difícil justificación.',
        );

        $previousNet = $activity->grossRevenue->subtract($activity->deductibleExpenses);
        if ($previousNet->isNegative()) {
            // No se aplica la reducción del 5/7 % sobre rendimiento negativo.
            $lines[] = new BreakdownLine(
                concept: 'Rendimiento neto actividad económica (EDS)',
                amount: $previousNet,
                category: BreakdownCategory::BASE,
                legalReference: self::BOE_LIRPF_REGLAMENTO.'#a30',
                explanation: 'Rendimiento neto EDS negativo: no se aplica la reducción genérica del art. 30 RIRPF.',
            );

            return Money::zero();
        }

        $rate = $this->genericExpensesPercent($input);
        $cap = $this->genericExpensesCap($input);
        $genericExpenses = $previousNet->multiply((string) ($rate / 100));
        if ($genericExpenses->compare($cap) > 0) {
            $genericExpenses = $cap;
        }

        $lines[] = new BreakdownLine(
            concept: 'Reducción genérica gastos difícil justificación (EDS)',
            amount: $genericExpenses,
            category: BreakdownCategory::REDUCTION,
            base: $previousNet,
            legalReference: self::BOE_LIRPF_REGLAMENTO.'#a30',
            explanation: "Reducción del {$rate} % sobre rendimiento neto previo (tope ".$cap->amount.' €) por gastos de difícil justificación (art. 30 RIRPF; subida al 7 % desde 2023 Ley 31/2022).',
        );

        $rendimiento = $previousNet->subtract($genericExpenses);
        if ($rendimiento->isNegative()) {
            $rendimiento = Money::zero();
        }

        $lines[] = new BreakdownLine(
            concept: 'Rendimiento neto actividad económica (EDS)',
            amount: $rendimiento,
            category: BreakdownCategory::BASE,
            legalReference: self::BOE_LIRPF_REGLAMENTO.'#a30',
            explanation: 'Rendimiento neto EDS = ingresos − gastos deducibles − reducción genérica.',
        );

        return $rendimiento;
    }

    private function genericExpensesPercent(IncomeTaxInput $input): float
    {
        $value = $this->parameterRepository->getParameter(
            $input->year,
            'irpf.gastos_genericos_eds_porcentaje',
            RegionCode::state(),
        );

        if (is_numeric($value)) {
            return (float) $value;
        }

        // Fallback: 7 % (vigente 2023-2025).
        return 7.0;
    }

    private function genericExpensesCap(IncomeTaxInput $input): Money
    {
        $value = $this->parameterRepository->getParameter(
            $input->year,
            'irpf.gastos_genericos_eds_tope',
            RegionCode::state(),
        );

        if (is_numeric($value)) {
            return Money::fromFloat((float) $value);
        }

        return Money::fromFloat(2000.00);
    }
}
