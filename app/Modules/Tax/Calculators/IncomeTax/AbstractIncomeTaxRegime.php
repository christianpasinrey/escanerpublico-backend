<?php

namespace Modules\Tax\Calculators\IncomeTax;

use Modules\Tax\DTOs\Breakdown;
use Modules\Tax\DTOs\BreakdownCategory;
use Modules\Tax\DTOs\BreakdownLine;
use Modules\Tax\DTOs\IncomeTax\IncomeTaxInput;
use Modules\Tax\DTOs\IncomeTax\IncomeTaxResult;
use Modules\Tax\Services\IncomeTax\IncomeTaxDeductionsCalculator;
use Modules\Tax\Services\IncomeTax\PersonalMinimumCalculator;
use Modules\Tax\Services\IncomeTax\WorkIncomeReductionCalculator;
use Modules\Tax\Services\IrpfScaleResolver;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegionCode;
use Modules\Tax\ValueObjects\TaxRate;

/**
 * Lógica compartida por las implementaciones de régimen IRPF (EDN/EDS/EO/ASALARIADO).
 *
 * Cada subclase determina el "rendimiento neto" de su forma específica, y la
 * superclase orquesta el resto (mínimo personal, escala estatal+autonómica,
 * deducciones, pagos a cuenta, resultado).
 *
 * Pasos (orden de operaciones BOE):
 *   1. Rendimiento neto total (sumando trabajo + actividades).
 *   2. Mínimo personal y familiar (no reduce base, modula la cuota).
 *   3. Base liquidable = rendimiento neto (en MVP no aplicamos reducciones por
 *      aportaciones a planes de pensiones, pensiones compensatorias, etc.).
 *   4. Cuota íntegra estatal: scale_estatal(base) − scale_estatal(minimo).
 *   5. Cuota íntegra autonómica: scale_autonomica(base) − scale_autonomica(minimo).
 *   6. Cuota íntegra total = estatal + autonómica.
 *   7. Deducciones (estatales + autonómicas).
 *   8. Cuota líquida = cuota íntegra − deducciones.
 *   9. Pagos a cuenta (retenciones IRPF + pagos fraccionados modelos 130/131).
 *   10. Resultado = cuota líquida − pagos a cuenta.
 *
 * El conjunto produce un Breakdown con cada paso como BreakdownLine.
 */
abstract class AbstractIncomeTaxRegime implements IncomeTaxRegimeCalculator
{
    protected const BOE_LIRPF = 'https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764';

    protected const BOE_LIRPF_REGLAMENTO = 'https://www.boe.es/buscar/act.php?id=BOE-A-2007-6820';

    public function __construct(
        protected readonly IrpfScaleResolver $scaleResolver,
        protected readonly PersonalMinimumCalculator $minimumCalculator,
        protected readonly WorkIncomeReductionCalculator $workReductionCalculator,
        protected readonly IncomeTaxDeductionsCalculator $deductionsCalculator,
    ) {}

    public function calculate(IncomeTaxInput $input): IncomeTaxResult
    {
        $lines = [];

        // 1. Rendimientos del trabajo (si hay).
        $workNet = $this->workNet($input, $lines);

        // 2. Rendimientos actividades económicas (según régimen de la subclase).
        $activityNet = $this->activityNet($input, $lines);

        // 3. Rendimiento neto total.
        $totalNet = $workNet->add($activityNet);
        if ($totalNet->isNegative()) {
            // Si todo da negativo (ej. gastos > ingresos en EDN), capamos a cero
            // para el cómputo de la base liquidable y dejamos el componente
            // negativo expuesto en su línea correspondiente del breakdown.
            $totalNet = Money::zero();
        }
        $lines[] = new BreakdownLine(
            concept: 'Rendimiento neto total',
            amount: $totalNet,
            category: BreakdownCategory::BASE,
            legalReference: self::BOE_LIRPF,
            explanation: 'Suma de los rendimientos netos del trabajo y de las actividades económicas. Base de partida para calcular la base liquidable.',
        );

        // 4. Base liquidable (= rendimiento neto en MVP — no aplicamos
        //    reducciones por aportaciones a planes de pensiones, pensiones
        //    compensatorias, etc., que están fuera del alcance MVP).
        $taxableBase = $totalNet;
        $lines[] = new BreakdownLine(
            concept: 'Base liquidable',
            amount: $taxableBase,
            category: BreakdownCategory::BASE,
            legalReference: self::BOE_LIRPF.'#a50',
            explanation: 'Base liquidable general (art. 50 LIRPF). En MVP coincide con el rendimiento neto total — no se aplican reducciones por aportaciones a planes de pensiones, pensiones compensatorias u otras (fuera de alcance).',
        );

        // 5. Mínimo personal y familiar — no reduce base, modula cuota.
        $personalMinimum = $this->minimumCalculator->calculate($input->year, $input->taxpayerSituation);
        $lines[] = new BreakdownLine(
            concept: 'Mínimo personal y familiar (art. 56-61 LIRPF)',
            amount: $personalMinimum,
            category: BreakdownCategory::REDUCTION,
            legalReference: self::BOE_LIRPF.'#a56',
            explanation: 'Cuantía exenta del IRPF: la escala se aplica a la base completa y al mínimo, restándose la segunda cuota.',
        );

        // 6. Cuota íntegra estatal.
        $stateQuotaOnBase = $this->scaleResolver->applyStateScale($input->year, $taxableBase);
        $stateQuotaOnMinimum = $this->scaleResolver->applyStateScale($input->year, $personalMinimum);
        $stateQuota = $stateQuotaOnBase->subtract($stateQuotaOnMinimum);
        if ($stateQuota->isNegative()) {
            $stateQuota = Money::zero();
        }
        $lines[] = new BreakdownLine(
            concept: 'Cuota íntegra estatal IRPF (art. 63 LIRPF)',
            amount: $stateQuota,
            category: BreakdownCategory::TAX,
            base: $taxableBase,
            legalReference: self::BOE_LIRPF.'#a63',
            explanation: 'Cuota íntegra estatal: escala progresiva sobre la base liquidable menos la cuota correspondiente al mínimo personal+familiar.',
        );

        // 7. Cuota íntegra autonómica.
        $regionalQuota = Money::zero();
        if (! $input->region->isState()) {
            $regionalOnBase = $this->scaleResolver->applyRegionalScale($input->year, $input->region, $taxableBase);
            $regionalOnMinimum = $this->scaleResolver->applyRegionalScale($input->year, $input->region, $personalMinimum);
            $regionalQuota = $regionalOnBase->subtract($regionalOnMinimum);
            if ($regionalQuota->isNegative()) {
                $regionalQuota = Money::zero();
            }
            $lines[] = new BreakdownLine(
                concept: "Cuota íntegra autonómica IRPF — {$input->region->name()}",
                amount: $regionalQuota,
                category: BreakdownCategory::TAX,
                base: $taxableBase,
                legalReference: $this->regionalLegalReference($input->region),
                explanation: 'Cuota íntegra autonómica: misma técnica que la estatal aplicando la escala propia de la CCAA.',
            );
        }

        // 8. Cuota íntegra total.
        $totalGross = $stateQuota->add($regionalQuota);
        $lines[] = new BreakdownLine(
            concept: 'Cuota íntegra total',
            amount: $totalGross,
            category: BreakdownCategory::TAX,
            base: $taxableBase,
            legalReference: self::BOE_LIRPF.'#a63',
            explanation: 'Cuota íntegra IRPF = cuota estatal + cuota autonómica (antes de aplicar deducciones).',
        );

        // 9. Deducciones (estatales + autonómicas).
        $deductionsBundle = $this->deductionsCalculator->applyAll(
            $input->year,
            $input->region,
            $input->taxpayerSituation,
        );
        $totalDeductions = $deductionsBundle['total'];
        foreach ($deductionsBundle['lines'] as $deductionLine) {
            $lines[] = $deductionLine;
        }

        if (! $totalDeductions->isZero()) {
            $lines[] = new BreakdownLine(
                concept: 'Total deducciones',
                amount: $totalDeductions,
                category: BreakdownCategory::DEDUCTION,
                legalReference: self::BOE_LIRPF.'#a68',
                explanation: 'Suma de las deducciones estatales y autonómicas aplicables al contribuyente.',
            );
        }

        // 10. Cuota líquida = cuota íntegra − deducciones.
        $liquidQuota = $totalGross->subtract($totalDeductions);
        if ($liquidQuota->isNegative()) {
            $liquidQuota = Money::zero();
        }
        $lines[] = new BreakdownLine(
            concept: 'Cuota líquida',
            amount: $liquidQuota,
            category: BreakdownCategory::TAX,
            legalReference: self::BOE_LIRPF.'#a67',
            explanation: 'Cuota líquida = cuota íntegra − deducciones (art. 67 LIRPF).',
        );

        // 11. Pagos a cuenta: retenciones (modelo 111 vía nómina) + pagos
        //     fraccionados (modelos 130/131 trimestrales).
        $withholdings = $input->workIncome?->irpfWithheld ?? Money::zero();
        if (! $withholdings->isZero()) {
            $lines[] = new BreakdownLine(
                concept: 'Retenciones IRPF practicadas (pago a cuenta)',
                amount: $withholdings,
                category: BreakdownCategory::DEDUCTION,
                legalReference: self::BOE_LIRPF_REGLAMENTO.'#a78',
                explanation: 'Importe retenido por el pagador (modelo 111 trimestral) durante el año y consignado como pago a cuenta.',
            );
        }

        $quarterlyPayments = $input->economicActivity?->quarterlyPaymentsAlreadyPaid ?? Money::zero();
        if (! $quarterlyPayments->isZero()) {
            $lines[] = new BreakdownLine(
                concept: 'Pagos fraccionados ya ingresados (modelos 130/131)',
                amount: $quarterlyPayments,
                category: BreakdownCategory::DEDUCTION,
                legalReference: self::BOE_LIRPF.'#a99',
                explanation: 'Suma de pagos fraccionados trimestrales ingresados durante el año a cuenta de la cuota anual.',
            );
        }

        $totalPaymentsOnAccount = $withholdings->add($quarterlyPayments);

        // 12. Resultado de la declaración.
        $result = $liquidQuota->subtract($totalPaymentsOnAccount);

        $resultConcept = $result->isNegative()
            ? 'Resultado: A devolver'
            : ($result->isZero() ? 'Resultado: Cuota cero' : 'Resultado: A ingresar');
        $lines[] = new BreakdownLine(
            concept: $resultConcept,
            amount: $result,
            category: BreakdownCategory::NET,
            legalReference: self::BOE_LIRPF.'#a103',
            explanation: 'Resultado de la declaración del IRPF: si es positivo, a ingresar en Hacienda; si es negativo, a devolver al contribuyente; si es cero, cuota cero.',
        );

        // 13. Tipo medio efectivo (cuota íntegra / rendimiento neto total).
        $effective = $this->effectiveRate($totalNet, $totalGross);

        $meta = [
            'regime' => $input->regime->code,
            'year' => $input->year->year,
            'region' => $input->region->code,
            'region_name' => $input->region->name(),
            'disclaimer' => 'Calculadora informativa del modelo 100 (IRPF anual). No incluye rendimientos del capital mobiliario, ganancias y pérdidas patrimoniales, pluriempleo, rendimientos irregulares, imputación de rentas inmobiliarias ni regímenes especiales (Beckham, foral PV/Navarra). Consulte a un asesor fiscal antes de presentar la declaración.',
        ];

        $breakdown = new Breakdown(
            lines: array_values($lines),
            netResult: $result,
            currency: 'EUR',
            meta: $meta,
        );

        return new IncomeTaxResult(
            breakdown: $breakdown,
            netIncome: $totalNet,
            taxableBase: $taxableBase,
            stateQuota: $stateQuota,
            regionalQuota: $regionalQuota,
            totalGross: $totalGross,
            totalDeductions: $totalDeductions,
            liquidQuota: $liquidQuota,
            quarterlyPaymentsApplied: $quarterlyPayments,
            withholdingsApplied: $withholdings,
            effectiveTaxRate: $effective,
            result: $result,
        );
    }

    /**
     * Calcula los rendimientos netos del trabajo (si los hay) y añade las líneas
     * correspondientes al breakdown.
     *
     * @param  list<BreakdownLine>  $lines
     */
    protected function workNet(IncomeTaxInput $input, array &$lines): Money
    {
        if ($input->workIncome === null) {
            return Money::zero();
        }

        $work = $input->workIncome;

        $lines[] = new BreakdownLine(
            concept: 'Rendimientos íntegros del trabajo',
            amount: $work->gross,
            category: BreakdownCategory::BASE,
            legalReference: self::BOE_LIRPF.'#a17',
            explanation: 'Importe íntegro percibido por cuenta ajena durante el ejercicio (art. 17 LIRPF).',
        );

        // Cotizaciones SS son gasto deducible art. 19 LIRPF.
        $previousNet = $work->gross->subtract($work->socialSecurityPaid);
        if ($previousNet->isNegative()) {
            $previousNet = Money::zero();
        }
        $lines[] = new BreakdownLine(
            concept: 'Cotizaciones a la Seguridad Social del trabajador',
            amount: $work->socialSecurityPaid,
            category: BreakdownCategory::REDUCTION,
            legalReference: self::BOE_LIRPF.'#a19',
            explanation: 'Cotizaciones efectivamente abonadas por el trabajador (gasto íntegramente deducible art. 19 LIRPF).',
        );

        // Reducción rendimientos del trabajo art. 20 LIRPF.
        $reduction = $this->workReductionCalculator->calculate($input->year, $previousNet);
        $workNet = $previousNet->subtract($reduction);
        if ($workNet->isNegative()) {
            $workNet = Money::zero();
        }

        if (! $reduction->isZero()) {
            $lines[] = new BreakdownLine(
                concept: 'Reducción por rendimientos del trabajo (art. 20 LIRPF)',
                amount: $reduction,
                category: BreakdownCategory::REDUCTION,
                base: $previousNet,
                legalReference: self::BOE_LIRPF.'#a20',
                explanation: 'Reducción del art. 20 LIRPF: 6.498 € si rendimiento neto previo ≤ 14.852 €; decreciente entre 14.852 € y 19.747,50 €; 0 a partir de ahí.',
            );
        }

        $lines[] = new BreakdownLine(
            concept: 'Rendimientos netos del trabajo',
            amount: $workNet,
            category: BreakdownCategory::BASE,
            legalReference: self::BOE_LIRPF.'#a19',
            explanation: 'Rendimiento neto del trabajo = bruto − cotizaciones SS − reducción rendimientos del trabajo.',
        );

        return $workNet;
    }

    /**
     * Calcula los rendimientos netos de actividades económicas. Lo implementa
     * cada régimen (EDN, EDS, EO).
     *
     * @param  list<BreakdownLine>  $lines
     */
    abstract protected function activityNet(IncomeTaxInput $input, array &$lines): Money;

    protected function effectiveRate(Money $base, Money $tax): TaxRate
    {
        if ($base->isZero()) {
            return TaxRate::zero();
        }

        $percentage = bcmul(bcdiv($tax->amount, $base->amount, 8), '100', 4);

        return TaxRate::fromPercentage($percentage);
    }

    protected function regionalLegalReference(RegionCode $region): string
    {
        return match ($region->code) {
            'MD' => 'https://www.bocm.es/',
            'CT' => 'https://dogc.gencat.cat/',
            'AN' => 'https://www.juntadeandalucia.es/boja/',
            'VC' => 'https://dogv.gva.es/',
            default => 'https://www.boe.es/',
        };
    }
}
