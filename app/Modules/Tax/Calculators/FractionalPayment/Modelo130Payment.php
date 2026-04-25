<?php

namespace Modules\Tax\Calculators\FractionalPayment;

use Modules\Tax\DTOs\Breakdown;
use Modules\Tax\DTOs\BreakdownCategory;
use Modules\Tax\DTOs\BreakdownLine;
use Modules\Tax\DTOs\FractionalPayment\FractionalPaymentInput;
use Modules\Tax\DTOs\FractionalPayment\FractionalPaymentResult;
use Modules\Tax\Services\FractionalPayment\DescendantsDeductionCalculator;
use Modules\Tax\Services\TaxParameterRepository;
use Modules\Tax\Services\Vat\VatPeriodResolver;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegionCode;
use Modules\Tax\ValueObjects\TaxRate;

/**
 * Calculador del pago fraccionado modelo 130 — Estimación Directa
 * (Normal o Simplificada).
 *
 * Algoritmo (art. 110.1.a LIRPF):
 *
 *   1. Rendimiento neto acumulado = ingresos íntegros acumulados
 *      − gastos deducibles acumulados [− reducción genérica EDS si EDS].
 *   2. Pago bruto = rendimiento neto × 20 %.
 *      Si rendimiento neto < 0, pago bruto = 0 (la base se trunca a 0).
 *   3. Pago a ingresar = pago bruto
 *      − deducción descendientes (100 €/trim × n_hijos)
 *      − retenciones IRPF soportadas en facturas profesionales del año
 *      − pagos fraccionados de trimestres anteriores del mismo año.
 *      Si el resultado es negativo, se establece a 0,00 € — los modelos
 *      130/131 NUNCA pueden ser a devolver (art. 110 RIRPF).
 *
 * Reducción genérica EDS:
 *   Sólo si regime=EDS. Se calcula como % (5 % o 7 % según parámetro) sobre
 *   el rendimiento previo acumulado, con tope anual 2.000 €. El tope se
 *   aplica de forma absoluta (no proporcional al trimestre): el RIRPF
 *   art. 110.1.a establece "minorando con la cuantía de las cotizaciones
 *   a la Mutualidad…", no proporciona regla de reparto trimestral del tope,
 *   y la AEAT acepta que se aplique íntegro desde el primer trimestre que
 *   lo alcance.
 *
 * Periodicidad: trimestral (1T-4T). El cálculo es siempre acumulado desde
 * 1-enero hasta el último día del trimestre (sistema "cumulativo" del IRPF
 * fraccionado, art. 110.1 RIRPF).
 *
 * Fuentes BOE:
 *  - Ley 35/2006 LIRPF arts. 99-101 (BOE-A-2006-20764).
 *  - RD 439/2007 RIRPF arts. 105-110 (BOE-A-2007-6820).
 *  - Orden HFP/3666/2024 modelo 130 (BOE-A-2024-26173).
 */
class Modelo130Payment implements FractionalPaymentRegimeCalculator
{
    protected const RATE_PERCENT = '20.00';

    protected const BOE_LIRPF_99 = 'https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764#a99';

    protected const BOE_LIRPF_110 = 'https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764#a110';

    protected const BOE_RIRPF_110 = 'https://www.boe.es/buscar/act.php?id=BOE-A-2007-6820#a110';

    protected const BOE_RIRPF_30 = 'https://www.boe.es/buscar/act.php?id=BOE-A-2007-6820#a30';

    protected const BOE_ORDEN_130 = 'https://www.boe.es/buscar/doc.php?id=BOE-A-2024-26173';

    public function __construct(
        protected readonly DescendantsDeductionCalculator $descendantsCalculator,
        protected readonly VatPeriodResolver $periodResolver,
        protected readonly TaxParameterRepository $parameterRepository,
    ) {}

    public function calculate(FractionalPaymentInput $input): FractionalPaymentResult
    {
        $period = $this->periodResolver->resolve($input->year, $input->quarter);
        $lines = [];

        // 1. Ingresos íntegros acumulados.
        $lines[] = new BreakdownLine(
            concept: 'Ingresos íntegros acumulados (1-ene → fin trimestre)',
            amount: $input->cumulativeGrossRevenue,
            category: BreakdownCategory::BASE,
            legalReference: self::BOE_RIRPF_110,
            explanation: 'Suma de ingresos íntegros desde 1 de enero hasta el último día del trimestre actual (art. 110.1.a RIRPF). Sistema acumulativo: cada trimestre se recalcula sobre el total del año.',
        );

        // 2. Gastos deducibles acumulados.
        $lines[] = new BreakdownLine(
            concept: 'Gastos deducibles acumulados',
            amount: $input->cumulativeDeductibleExpenses,
            category: BreakdownCategory::DEDUCTION,
            legalReference: self::BOE_LIRPF_99,
            explanation: 'Suma de gastos íntegramente probados deducibles desde 1 de enero (art. 28-30 LIRPF).',
        );

        $previousNet = $input->cumulativeGrossRevenue->subtract($input->cumulativeDeductibleExpenses);

        // 3. Reducción genérica EDS — sólo si regime=EDS y rendimiento previo > 0.
        $genericReduction = Money::zero();
        if ($input->regime->code === 'EDS' && ! $previousNet->isNegative() && ! $previousNet->isZero()) {
            $genericReduction = $this->edsGenericReduction($input, $previousNet);
            if (! $genericReduction->isZero()) {
                $rateLabel = $this->genericExpensesPercent($input);
                $cap = $this->genericExpensesCap($input);
                $lines[] = new BreakdownLine(
                    concept: "Reducción genérica gastos difícil justificación (EDS — {$rateLabel} %, tope {$cap->amount} €/año)",
                    amount: $genericReduction,
                    category: BreakdownCategory::DEDUCTION,
                    base: $previousNet,
                    rate: TaxRate::fromPercentage($rateLabel),
                    legalReference: self::BOE_RIRPF_30,
                    explanation: 'Reducción genérica del '.$rateLabel.' % sobre el rendimiento previo acumulado, art. 30.2.4ª RIRPF. Tope anual 2.000 € (Ley 31/2022 elevó el porcentaje al 7 % para 2023+).',
                );
            }
        }

        $netIncome = $previousNet->subtract($genericReduction);
        $clampedBase = $netIncome->isNegative() ? Money::zero() : $netIncome;

        // 4. Rendimiento neto acumulado.
        $lines[] = new BreakdownLine(
            concept: 'Rendimiento neto acumulado',
            amount: $clampedBase,
            category: BreakdownCategory::BASE,
            legalReference: self::BOE_RIRPF_110,
            explanation: $netIncome->isNegative()
                ? 'Rendimiento neto acumulado negativo: la base del pago fraccionado se trunca a 0,00 € (no existe pago a devolver en el modelo 130).'
                : 'Rendimiento neto acumulado = ingresos − gastos deducibles'.($input->regime->code === 'EDS' ? ' − reducción genérica EDS' : '').' (art. 110.1.a RIRPF).',
        );

        // 5. Tipo aplicable 20 %.
        $rate = TaxRate::fromPercentage(self::RATE_PERCENT);

        // 6. Pago bruto.
        $grossPayment = $clampedBase->applyRate($rate);
        $lines[] = new BreakdownLine(
            concept: 'Pago fraccionado bruto (20 % rendimiento neto)',
            amount: $grossPayment,
            category: BreakdownCategory::TAX,
            base: $clampedBase,
            rate: $rate,
            legalReference: self::BOE_LIRPF_110,
            explanation: 'Tipo del 20 % sobre el rendimiento neto acumulado (art. 110.1.a LIRPF).',
        );

        // 7. Deducción por descendientes (100 €/trimestre × hijos).
        $deductionDescendants = $this->descendantsCalculator->calculate($input->taxpayerSituation);
        if (! $deductionDescendants->isZero()) {
            $lines[] = new BreakdownLine(
                concept: "Deducción descendientes ({$input->taxpayerSituation->descendants} × 100 €/trim)",
                amount: $deductionDescendants,
                category: BreakdownCategory::DEDUCTION,
                legalReference: $this->descendantsCalculator->legalReference(),
                explanation: $this->descendantsCalculator->explanation($input->taxpayerSituation->descendants),
            );
        }

        // 8. Retenciones IRPF soportadas en facturas profesionales.
        if (! $input->withholdingsApplied->isZero()) {
            $lines[] = new BreakdownLine(
                concept: 'Retenciones IRPF soportadas en facturas profesionales del año',
                amount: $input->withholdingsApplied,
                category: BreakdownCategory::DEDUCTION,
                legalReference: self::BOE_LIRPF_110,
                explanation: 'Retenciones a cuenta del IRPF practicadas por los pagadores en facturas profesionales emitidas durante el ejercicio (art. 110.3.b LIRPF).',
            );
        }

        // 9. Pagos fraccionados de trimestres anteriores del mismo año.
        if (! $input->previousQuartersPayments->isZero()) {
            $lines[] = new BreakdownLine(
                concept: 'Pagos fraccionados ya ingresados (trimestres anteriores)',
                amount: $input->previousQuartersPayments,
                category: BreakdownCategory::DEDUCTION,
                legalReference: self::BOE_LIRPF_110,
                explanation: 'Suma de pagos fraccionados modelo 130 ya ingresados en trimestres anteriores del mismo ejercicio (art. 110.3.a LIRPF). Sistema acumulativo: cada trimestre se ingresa la diferencia sobre lo ya pagado.',
            );
        }

        // 10. Resultado: pago bruto − deducciones, truncado a 0.
        $rawResult = $grossPayment
            ->subtract($deductionDescendants)
            ->subtract($input->withholdingsApplied)
            ->subtract($input->previousQuartersPayments);

        $result = $rawResult->isNegative() ? Money::zero() : $rawResult;

        $resultExplanation = $rawResult->isNegative()
            ? 'Resultado negativo (exceso de retenciones / pagos previos): se establece a 0,00 €. Los modelos 130/131 NUNCA pueden ser a devolver — el exceso se compensará en la Renta anual o en el siguiente trimestre.'
            : 'Resultado a ingresar = pago bruto − deducciones (descendientes, retenciones, pagos previos).';

        $lines[] = new BreakdownLine(
            concept: $rawResult->isNegative()
                ? 'Resultado a ingresar — 0,00 € (no procede pago)'
                : 'Resultado a ingresar',
            amount: $result,
            category: BreakdownCategory::NET,
            legalReference: self::BOE_ORDEN_130,
            explanation: $resultExplanation,
        );

        $meta = [
            'model' => '130',
            'regime' => $input->regime->code,
            'fiscal_year' => $input->year->year,
            'quarter' => $input->quarter,
            'period_label' => $input->quarterLabel(),
            'period_from' => $period->from?->toDateString(),
            'period_to' => $period->to?->toDateString(),
            'clamped_to_zero' => $rawResult->isNegative(),
            'disclaimer' => 'Cálculo informativo basado en la legislación vigente (Ley 35/2006 LIRPF, RD 439/2007 RIRPF, Orden HFP/3666/2024 modelo 130). No sustituye al asesoramiento profesional ni a la presentación oficial AEAT. Verifique cada importe con su asesor antes de presentar el modelo.',
        ];

        $breakdown = new Breakdown(
            lines: $lines,
            netResult: $result,
            currency: 'EUR',
            meta: $meta,
        );

        return new FractionalPaymentResult(
            breakdown: $breakdown,
            model: '130',
            period: $input->quarterLabel(),
            cumulativeNetIncome: $clampedBase,
            applicableRate: $rate,
            grossPayment: $grossPayment,
            deductionDescendants: $deductionDescendants,
            withholdingsApplied: $input->withholdingsApplied,
            previousQuartersDeducted: $input->previousQuartersPayments,
            result: $result,
        );
    }

    /**
     * Reducción genérica EDS aplicable acumulada hasta el trimestre actual,
     * con tope anual fijo (2.000 € por defecto).
     *
     * El tope NO se prorratea por trimestre: si el rendimiento previo lo
     * alcanza ya en 1T, se aplica íntegro desde 1T (la AEAT no exige
     * prorrateo en RIRPF art. 110.1.a).
     */
    protected function edsGenericReduction(FractionalPaymentInput $input, Money $previousNet): Money
    {
        $rate = $this->genericExpensesPercent($input);
        $cap = $this->genericExpensesCap($input);

        $reduction = $previousNet->multiply((string) ($rate / 100));
        if ($reduction->compare($cap) > 0) {
            $reduction = $cap;
        }

        return $reduction;
    }

    protected function genericExpensesPercent(FractionalPaymentInput $input): float
    {
        $value = $this->parameterRepository->getParameter(
            $input->year,
            'irpf.gastos_genericos_eds_porcentaje',
            RegionCode::state(),
        );

        if (is_numeric($value)) {
            return (float) $value;
        }

        // Fallback documentado: 7 % vigente 2023-2025.
        return 7.0;
    }

    protected function genericExpensesCap(FractionalPaymentInput $input): Money
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
