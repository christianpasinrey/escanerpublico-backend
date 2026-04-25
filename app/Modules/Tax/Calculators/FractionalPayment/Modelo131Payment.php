<?php

namespace Modules\Tax\Calculators\FractionalPayment;

use Modules\Tax\DTOs\Breakdown;
use Modules\Tax\DTOs\BreakdownCategory;
use Modules\Tax\DTOs\BreakdownLine;
use Modules\Tax\DTOs\FractionalPayment\FractionalPaymentInput;
use Modules\Tax\DTOs\FractionalPayment\FractionalPaymentResult;
use Modules\Tax\Services\FractionalPayment\DescendantsDeductionCalculator;
use Modules\Tax\Services\IncomeTax\EoModulesCalculator;
use Modules\Tax\Services\Vat\VatPeriodResolver;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\TaxRate;
use RuntimeException;

/**
 * Calculador del pago fraccionado modelo 131 — Estimación Objetiva (módulos).
 *
 * Algoritmo (art. 110.1.b RIRPF):
 *
 *   1. Cuota anual EO = Σ (signo × valor unidad) − reducción 5 % (DT 32ª LIRPF)
 *      calculada por EoModulesCalculator (servicio M6).
 *   2. Tipo aplicable según asalariados a 1-enero del ejercicio:
 *      - 4 % si NO hay asalariados.
 *      - 3 % con 1 asalariado.
 *      - 2 % con ≥ 2 asalariados.
 *      Para actividades agrícolas/ganaderas/forestales se aplica un 2 %
 *      uniforme — fuera de alcance MVP, los IAE EO cubiertos en M6 son
 *      todos no agrarios (top-20 restauración/transporte/comercio/servicios).
 *   3. Pago bruto trimestral = cuota anual × tipo.
 *      (No se prorratea por trimestre dentro del cálculo: el % ya está
 *      diseñado para ser aplicado tal cual sobre la cuota anual completa.)
 *   4. Pago a ingresar = pago bruto
 *      − deducción descendientes (100 €/trim × n_hijos)
 *      − retenciones IRPF soportadas (raras en EO)
 *      − pagos fraccionados de trimestres anteriores del mismo año.
 *      Si el resultado es negativo, se establece a 0,00 € (no devolución).
 *
 * Fuentes BOE:
 *  - Ley 35/2006 LIRPF arts. 99-101 (BOE-A-2006-20764).
 *  - RD 439/2007 RIRPF art. 110.1.b (BOE-A-2007-6820).
 *  - Orden HFP/3666/2024 modelo 131 (BOE-A-2024-26173).
 *  - Orden HFP/1397/2024 módulos 2025 (BOE-A-2024-26896).
 */
class Modelo131Payment implements FractionalPaymentRegimeCalculator
{
    protected const BOE_LIRPF_99 = 'https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764#a99';

    protected const BOE_LIRPF_110 = 'https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764#a110';

    protected const BOE_RIRPF_110 = 'https://www.boe.es/buscar/act.php?id=BOE-A-2007-6820#a110';

    protected const BOE_LIRPF_DT32 = 'https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764#dt32';

    protected const BOE_ORDEN_131 = 'https://www.boe.es/buscar/doc.php?id=BOE-A-2024-26173';

    public function __construct(
        protected readonly DescendantsDeductionCalculator $descendantsCalculator,
        protected readonly EoModulesCalculator $eoCalculator,
        protected readonly VatPeriodResolver $periodResolver,
    ) {}

    public function calculate(FractionalPaymentInput $input): FractionalPaymentResult
    {
        if ($input->activityCode === null || $input->activityCode === '') {
            throw new RuntimeException('Modelo 131 requiere activityCode (cubierto por validación de DTO).');
        }

        $period = $this->periodResolver->resolve($input->year, $input->quarter);
        $lines = [];

        // 1. Cuota anual EO = rendimiento neto previo − reducción 5 %.
        $rendimientoPrevio = $this->eoCalculator->calculatePreviousYield(
            $input->year,
            $input->activityCode,
            $input->eoModulesData,
        );

        // Líneas informativas por cada módulo aplicado.
        $moduleLines = $this->eoCalculator->moduleLines(
            $input->year,
            $input->activityCode,
            $input->eoModulesData,
        );
        foreach ($moduleLines as $module) {
            if ($module['units'] <= 0) {
                continue;
            }
            $lines[] = new BreakdownLine(
                concept: "Módulo: {$module['label']}",
                amount: $module['amount'],
                category: BreakdownCategory::INFO,
                legalReference: $this->eoCalculator->legalReference($input->year, $input->activityCode),
                explanation: "Unidades: {$module['units']} × {$module['value_per_unit']} €/unidad",
            );
        }

        $lines[] = new BreakdownLine(
            concept: "Rendimiento neto previo EO ({$input->activityCode})",
            amount: $rendimientoPrevio,
            category: BreakdownCategory::BASE,
            legalReference: $this->eoCalculator->legalReference($input->year, $input->activityCode),
            explanation: 'Suma de los signos/índices de la actividad (rendimiento neto previo, art. 32 LIRPF + Anexo II Orden HFP).',
        );

        // Reducción 5 % DT 32ª LIRPF.
        $reduction = $rendimientoPrevio->applyRate(TaxRate::fromPercentage(5));
        if (! $reduction->isZero()) {
            $lines[] = new BreakdownLine(
                concept: 'Reducción 5 % rendimiento neto EO (DT 32ª LIRPF)',
                amount: $reduction,
                category: BreakdownCategory::DEDUCTION,
                base: $rendimientoPrevio,
                legalReference: self::BOE_LIRPF_DT32,
                explanation: 'Reducción general del 5 % sobre el rendimiento neto en EO, prorrogada por la Ley 31/2022 y mantenida por la Ley 7/2024 para 2024-2025.',
            );
        }

        $cuotaAnualEo = $rendimientoPrevio->subtract($reduction);
        if ($cuotaAnualEo->isNegative()) {
            $cuotaAnualEo = Money::zero();
        }

        $lines[] = new BreakdownLine(
            concept: 'Cuota anual EO (rendimiento neto definitivo)',
            amount: $cuotaAnualEo,
            category: BreakdownCategory::BASE,
            legalReference: self::BOE_RIRPF_110,
            explanation: 'Rendimiento neto EO definitivo = previo − reducción 5 %. Esta es la base sobre la que se aplica el % del pago fraccionado modelo 131.',
        );

        // 2. Tipo aplicable según asalariados.
        $rate = $this->resolveApplicableRate($input->salariedEmployees);
        $rateLabel = $this->rateLabel($input->salariedEmployees);

        // 3. Pago bruto.
        $grossPayment = $cuotaAnualEo->applyRate($rate);
        $lines[] = new BreakdownLine(
            concept: "Pago fraccionado bruto ({$rate->percentage} % cuota anual EO — {$rateLabel})",
            amount: $grossPayment,
            category: BreakdownCategory::TAX,
            base: $cuotaAnualEo,
            rate: $rate,
            legalReference: self::BOE_RIRPF_110,
            explanation: 'Tipo del '.$rate->percentage.' % sobre la cuota anual EO (art. 110.1.b RIRPF). Personal asalariado a 1-enero del ejercicio: '.$input->salariedEmployees.'.',
        );

        // 4. Deducción por descendientes.
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

        // 5. Retenciones IRPF soportadas (raras en EO).
        if (! $input->withholdingsApplied->isZero()) {
            $lines[] = new BreakdownLine(
                concept: 'Retenciones IRPF soportadas (proporción correspondiente)',
                amount: $input->withholdingsApplied,
                category: BreakdownCategory::DEDUCTION,
                legalReference: self::BOE_LIRPF_110,
                explanation: 'Retenciones a cuenta del IRPF soportadas durante el período (art. 110.3.b LIRPF). En EO son raras; típicas en transporte intermediado por plataformas.',
            );
        }

        // 6. Pagos trimestres anteriores.
        if (! $input->previousQuartersPayments->isZero()) {
            $lines[] = new BreakdownLine(
                concept: 'Pagos fraccionados ya ingresados (trimestres anteriores)',
                amount: $input->previousQuartersPayments,
                category: BreakdownCategory::DEDUCTION,
                legalReference: self::BOE_LIRPF_110,
                explanation: 'Suma de pagos modelo 131 ya ingresados en trimestres anteriores del mismo ejercicio (art. 110.3.a LIRPF).',
            );
        }

        // 7. Resultado: pago bruto − deducciones, truncado a 0.
        $rawResult = $grossPayment
            ->subtract($deductionDescendants)
            ->subtract($input->withholdingsApplied)
            ->subtract($input->previousQuartersPayments);

        $result = $rawResult->isNegative() ? Money::zero() : $rawResult;

        $resultExplanation = $rawResult->isNegative()
            ? 'Resultado negativo (exceso de retenciones / pagos previos): se establece a 0,00 €. Los modelos 130/131 NUNCA pueden ser a devolver — el exceso se compensará en la Renta anual.'
            : 'Resultado a ingresar = pago bruto − deducciones (descendientes, retenciones, pagos previos).';

        $lines[] = new BreakdownLine(
            concept: $rawResult->isNegative()
                ? 'Resultado a ingresar — 0,00 € (no procede pago)'
                : 'Resultado a ingresar',
            amount: $result,
            category: BreakdownCategory::NET,
            legalReference: self::BOE_ORDEN_131,
            explanation: $resultExplanation,
        );

        $meta = [
            'model' => '131',
            'regime' => $input->regime->code,
            'fiscal_year' => $input->year->year,
            'quarter' => $input->quarter,
            'period_label' => $input->quarterLabel(),
            'period_from' => $period->from?->toDateString(),
            'period_to' => $period->to?->toDateString(),
            'activity_code' => $input->activityCode,
            'salaried_employees' => $input->salariedEmployees,
            'applicable_rate_percent' => $rate->percentage,
            'clamped_to_zero' => $rawResult->isNegative(),
            'disclaimer' => 'Cálculo informativo basado en la legislación vigente (Ley 35/2006 LIRPF, RD 439/2007 RIRPF, Orden HFP/3666/2024 modelo 131, Orden HFP/1397/2024 módulos 2025). No sustituye al asesoramiento profesional ni a la presentación oficial AEAT.',
        ];

        $breakdown = new Breakdown(
            lines: $lines,
            netResult: $result,
            currency: 'EUR',
            meta: $meta,
        );

        return new FractionalPaymentResult(
            breakdown: $breakdown,
            model: '131',
            period: $input->quarterLabel(),
            cumulativeNetIncome: $cuotaAnualEo,
            applicableRate: $rate,
            grossPayment: $grossPayment,
            deductionDescendants: $deductionDescendants,
            withholdingsApplied: $input->withholdingsApplied,
            previousQuartersDeducted: $input->previousQuartersPayments,
            result: $result,
        );
    }

    /**
     * Tipo aplicable según número de asalariados a 1-enero (art. 110.1.b RIRPF).
     *
     *  - 0 asalariados → 4 %
     *  - 1 asalariado  → 3 %
     *  - ≥ 2           → 2 %
     */
    protected function resolveApplicableRate(int $salariedEmployees): TaxRate
    {
        return match (true) {
            $salariedEmployees <= 0 => TaxRate::fromPercentage('4.00'),
            $salariedEmployees === 1 => TaxRate::fromPercentage('3.00'),
            default => TaxRate::fromPercentage('2.00'),
        };
    }

    protected function rateLabel(int $salariedEmployees): string
    {
        return match (true) {
            $salariedEmployees <= 0 => 'sin asalariados',
            $salariedEmployees === 1 => '1 asalariado',
            default => "{$salariedEmployees} asalariados",
        };
    }
}
