<?php

namespace Modules\Tax\Calculators\Vat;

use Carbon\CarbonImmutable;
use Modules\Tax\DTOs\Breakdown;
use Modules\Tax\DTOs\BreakdownCategory;
use Modules\Tax\DTOs\BreakdownLine;
use Modules\Tax\DTOs\VatReturn\VatReturnInput;
use Modules\Tax\DTOs\VatReturn\VatReturnResult;
use Modules\Tax\DTOs\VatReturn\VatReturnStatus;
use Modules\Tax\DTOs\VatReturn\VatTransactionCategory;
use Modules\Tax\DTOs\VatReturn\VatTransactionDirection;
use Modules\Tax\DTOs\VatReturn\VatTransactionInput;
use Modules\Tax\Services\Vat\Modelo303CasillasMapper;
use Modules\Tax\Services\Vat\VatPeriodResolver;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\TaxRate;

/**
 * Calculator IVA modelo 303 / 390 — Régimen General (IVA_GEN).
 *
 * Devengo: regla general art. 75 LIVA — el IVA repercutido se devenga en
 * el momento de emisión de la factura.
 *
 * Pasos del cálculo:
 *
 *  1. Filtrar transactions por categoría DOMESTIC (operaciones interiores).
 *     Las INTRACOM/IMPORTS/EXPORTS se anotan informativamente pero no
 *     se calculan (fuera de MVP M7 — modelo 349 referenciado, OSS/IOSS
 *     no cubierto).
 *  2. Agrupar OUTGOING por tipo IVA → IVA devengado por tipo.
 *  3. Sumar Recargo de Equivalencia devengado (cuando emisor general
 *     factura a cliente RE).
 *  4. Agrupar INCOMING por tipo IVA → IVA soportado deducible por tipo.
 *  5. Cuota líquida = devengado − deducible − previousQuotaCarryForward.
 *  6. Resultado:
 *      - cuota positiva → A_INGRESAR
 *      - cuota negativa en último período (4T o anual) → A_DEVOLVER
 *      - cuota negativa en período intermedio → A_COMPENSAR
 *
 * Fuentes BOE:
 *  - Ley 37/1992 LIVA — arts. 75 (devengo), 78 (base), 90-91 (tipos),
 *    92-114 (deducciones), 154-163 (Recargo de Equivalencia).
 *  - RD 1624/1992 RIVA — art. 71 (períodos declaración).
 *  - Orden HFP/3666/2024 — modelo 303 vigente desde 2025.
 *  - Orden HAC/819/2024 — modelo 390 anual.
 */
class RegimenGeneralVat implements VatRegimeReturnCalculator
{
    protected const LIVA_75 = 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740#a75';

    protected const LIVA_92 = 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740#a92';

    protected const LIVA_161 = 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740#a161';

    protected const LIVA_25 = 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740#a25';

    protected const ORDEN_303_2024 = 'https://www.boe.es/buscar/doc.php?id=BOE-A-2024-26173';

    public function __construct(
        protected readonly VatPeriodResolver $periodResolver,
        protected readonly Modelo303CasillasMapper $casillasMapper,
    ) {}

    public function calculate(VatReturnInput $input): VatReturnResult
    {
        return $this->doCalculate($input, useCriterioCaja: false);
    }

    /**
     * Lógica principal compartida con CriterioCajaVat. La única diferencia
     * es qué fecha se usa para decidir si una transacción está devengada
     * dentro del período (date vs paidDate con tope 31-dic año posterior).
     */
    protected function doCalculate(VatReturnInput $input, bool $useCriterioCaja): VatReturnResult
    {
        $period = $this->periodResolver->resolve($input->year, $input->quarter);

        // Filtrado por período según devengo aplicable.
        $domesticOutgoing = [];
        $domesticIncoming = [];
        $informativeLines = [];

        foreach ($input->transactions as $tx) {
            /** @var VatTransactionInput $tx */
            $accrualDate = $useCriterioCaja
                ? $this->criterioCajaAccrualDate($tx)
                : $tx->date;

            if (! $period->contains($accrualDate)) {
                continue;
            }

            if ($tx->category !== VatTransactionCategory::DOMESTIC) {
                $informativeLines[] = $this->intracomExportInfoLine($tx);

                continue;
            }

            if ($tx->direction === VatTransactionDirection::OUTGOING) {
                $domesticOutgoing[] = $tx;
            } else {
                $domesticIncoming[] = $tx;
            }
        }

        // Agrupar IVA devengado por tipo.
        $vatBuckets = []; // ['rate%' => ['base' => Money, 'vat' => Money]]
        $surchargeBuckets = []; // ['rate%' => ['base' => Money, 'vat' => Money]]

        foreach ($domesticOutgoing as $tx) {
            $key = $this->normalizeRateKey($tx->vatRate->percentage);
            if (! isset($vatBuckets[$key])) {
                $vatBuckets[$key] = ['base' => Money::zero(), 'vat' => Money::zero()];
            }
            $vatBuckets[$key]['base'] = $vatBuckets[$key]['base']->add($tx->base);
            $vatBuckets[$key]['vat'] = $vatBuckets[$key]['vat']->add($tx->vatAmount);

            // Recargo de equivalencia (si emisor general factura a cliente RE
            // — el surchargeEquivalenceAmount viene precalculado por el
            // emisor en M5 InvoiceCalculator).
            if ($tx->surchargeEquivalenceAmount !== null && ! $tx->surchargeEquivalenceAmount->isZero()) {
                $reKey = $this->surchargeRateForVatRate($this->normalizeRateKey($tx->vatRate->percentage));
                if (! isset($surchargeBuckets[$reKey])) {
                    $surchargeBuckets[$reKey] = ['base' => Money::zero(), 'vat' => Money::zero()];
                }
                $surchargeBuckets[$reKey]['base'] = $surchargeBuckets[$reKey]['base']->add($tx->base);
                $surchargeBuckets[$reKey]['vat'] = $surchargeBuckets[$reKey]['vat']
                    ->add($tx->surchargeEquivalenceAmount);
            }
        }

        // Agrupar IVA soportado por tipo.
        $deductibleBuckets = []; // ['rate%' => ['base' => Money, 'vat' => Money]]
        foreach ($domesticIncoming as $tx) {
            $key = $this->normalizeRateKey($tx->vatRate->percentage);
            if (! isset($deductibleBuckets[$key])) {
                $deductibleBuckets[$key] = ['base' => Money::zero(), 'vat' => Money::zero()];
            }
            $deductibleBuckets[$key]['base'] = $deductibleBuckets[$key]['base']->add($tx->base);
            $deductibleBuckets[$key]['vat'] = $deductibleBuckets[$key]['vat']->add($tx->vatAmount);
        }

        $totalVatAccrued = Money::zero();
        foreach ($vatBuckets as $bucket) {
            $totalVatAccrued = $totalVatAccrued->add($bucket['vat']);
        }

        $totalSurchargeAccrued = Money::zero();
        foreach ($surchargeBuckets as $bucket) {
            $totalSurchargeAccrued = $totalSurchargeAccrued->add($bucket['vat']);
        }

        $totalDeductibleBase = Money::zero();
        $totalDeductibleAmount = Money::zero();
        foreach ($deductibleBuckets as $bucket) {
            $totalDeductibleBase = $totalDeductibleBase->add($bucket['base']);
            $totalDeductibleAmount = $totalDeductibleAmount->add($bucket['vat']);
        }

        // Cuota líquida = devengado − deducible − compensaciones previas.
        // El recargo de equivalencia se ingresa junto al IVA, pero no entra en
        // la diferencia devengado−deducible: es un "ingreso adicional" a favor
        // de la AEAT que recauda el emisor general. Por eso lo sumamos al
        // resultado final tras la diferencia.
        $diferencia = $totalVatAccrued->subtract($totalDeductibleAmount);
        $diferenciaConRecargo = $diferencia->add($totalSurchargeAccrued);
        $liquidQuota = $diferenciaConRecargo->subtract($input->previousQuotaCarryForward);

        $isLastPeriod = $this->periodResolver->isLastPeriod($input->quarter);

        $status = $this->resolveStatus($liquidQuota, $isLastPeriod);

        $breakdown = $this->buildBreakdown(
            input: $input,
            vatBuckets: $vatBuckets,
            surchargeBuckets: $surchargeBuckets,
            deductibleBuckets: $deductibleBuckets,
            totalVatAccrued: $totalVatAccrued,
            totalSurchargeAccrued: $totalSurchargeAccrued,
            totalDeductibleAmount: $totalDeductibleAmount,
            previousQuotaCarryForward: $input->previousQuotaCarryForward,
            liquidQuota: $liquidQuota,
            status: $status,
            informativeLines: $informativeLines,
            useCriterioCaja: $useCriterioCaja,
        );

        $casillas = $this->casillasMapper->map(
            vatBucketsByRate: $vatBuckets,
            surchargeBucketsByRate: $surchargeBuckets,
            totalVatAccrued: $totalVatAccrued->add($totalSurchargeAccrued),
            totalDeductibleBase: $totalDeductibleBase,
            totalDeductibleAmount: $totalDeductibleAmount,
            previousQuotaCarryForward: $input->previousQuotaCarryForward,
            liquidQuota: $liquidQuota,
            isLastPeriod: $isLastPeriod,
            requestRefund: $status === VatReturnStatus::A_DEVOLVER,
        );

        return new VatReturnResult(
            breakdown: $breakdown,
            model: $input->model(),
            period: $input->periodLabel(),
            totalVatAccrued: $totalVatAccrued,
            totalVatDeductible: $totalDeductibleAmount,
            totalSurchargeEquivalenceAccrued: $totalSurchargeAccrued,
            liquidQuota: $liquidQuota,
            result: $status,
            casillas: $casillas,
        );
    }

    /**
     * @param  array<string, array{base: Money, vat: Money}>  $vatBuckets
     * @param  array<string, array{base: Money, vat: Money}>  $surchargeBuckets
     * @param  array<string, array{base: Money, vat: Money}>  $deductibleBuckets
     * @param  list<BreakdownLine>  $informativeLines
     */
    protected function buildBreakdown(
        VatReturnInput $input,
        array $vatBuckets,
        array $surchargeBuckets,
        array $deductibleBuckets,
        Money $totalVatAccrued,
        Money $totalSurchargeAccrued,
        Money $totalDeductibleAmount,
        Money $previousQuotaCarryForward,
        Money $liquidQuota,
        VatReturnStatus $status,
        array $informativeLines,
        bool $useCriterioCaja,
    ): Breakdown {
        $lines = [];

        // 1. IVA devengado por tipo.
        foreach ($vatBuckets as $rate => $bucket) {
            $rateLabel = $this->formatPercent($rate);
            $lines[] = new BreakdownLine(
                concept: "IVA devengado al {$rateLabel}% (operaciones interiores)",
                amount: $bucket['vat'],
                category: BreakdownCategory::TAX,
                base: $bucket['base'],
                rate: TaxRate::fromPercentage($rate),
                legalReference: $useCriterioCaja
                    ? 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740#a163decies'
                    : self::LIVA_75,
                explanation: $useCriterioCaja
                    ? 'Devengo según criterio de caja (art. 163 decies LIVA): se ingresa al cobrar.'
                    : 'IVA repercutido en facturas emitidas, devengo en el momento de emisión (art. 75 LIVA).',
            );
        }

        // 2. Total IVA devengado (agregado).
        $lines[] = new BreakdownLine(
            concept: 'Total IVA devengado',
            amount: $totalVatAccrued,
            category: BreakdownCategory::TAX,
            legalReference: self::ORDEN_303_2024,
            explanation: 'Casilla 27 modelo 303: suma del IVA repercutido por todos los tipos.',
        );

        // 3. Recargo de equivalencia devengado.
        foreach ($surchargeBuckets as $rate => $bucket) {
            $rateLabel = $this->formatPercent($rate);
            $lines[] = new BreakdownLine(
                concept: "Recargo de Equivalencia al {$rateLabel}% devengado",
                amount: $bucket['vat'],
                category: BreakdownCategory::ADDITION,
                base: $bucket['base'],
                legalReference: self::LIVA_161,
                explanation: 'Recargo cobrado al cliente acogido a Recargo de Equivalencia (art. 161 LIVA). El emisor lo ingresa junto con el IVA.',
            );
        }

        // 4. IVA soportado deducible por tipo.
        foreach ($deductibleBuckets as $rate => $bucket) {
            $rateLabel = $this->formatPercent($rate);
            $lines[] = new BreakdownLine(
                concept: "IVA soportado deducible al {$rateLabel}% (operaciones interiores corrientes)",
                amount: $bucket['vat'],
                category: BreakdownCategory::DEDUCTION,
                base: $bucket['base'],
                legalReference: self::LIVA_92,
                explanation: 'IVA soportado en compras y servicios afectos a la actividad — deducible (art. 92 LIVA).',
            );
        }

        // 5. Total IVA deducible.
        $lines[] = new BreakdownLine(
            concept: 'Total IVA soportado deducible',
            amount: $totalDeductibleAmount,
            category: BreakdownCategory::DEDUCTION,
            legalReference: self::ORDEN_303_2024,
            explanation: 'Casilla 45 modelo 303: total a deducir.',
        );

        // 6. Compensaciones de períodos anteriores.
        if (! $previousQuotaCarryForward->isZero()) {
            $lines[] = new BreakdownLine(
                concept: 'Compensación de cuotas a compensar de períodos anteriores',
                amount: $previousQuotaCarryForward,
                category: BreakdownCategory::DEDUCTION,
                legalReference: 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740#a99',
                explanation: 'Cuotas pendientes de compensar acumuladas de autoliquidaciones anteriores (casilla 78 modelo 303, art. 99 LIVA).',
            );
        }

        // 7. Operaciones intracomunitarias / exportaciones (informativas).
        foreach ($informativeLines as $line) {
            $lines[] = $line;
        }

        // 8. Cuota líquida.
        $lines[] = new BreakdownLine(
            concept: 'Cuota líquida — '.$status->label(),
            amount: $liquidQuota,
            category: BreakdownCategory::NET,
            legalReference: self::ORDEN_303_2024,
            explanation: $this->resultExplanation($status),
        );

        $coverage = $this->casillasMapper->coverage();
        $meta = [
            'regime' => $input->regime->code,
            'fiscal_year' => $input->year->year,
            'quarter' => $input->quarter,
            'model' => $input->model(),
            'period_label' => $input->periodLabel(),
            'use_criterio_caja' => $useCriterioCaja,
            'covered_casillas' => $coverage['covered'],
            'uncovered_casillas' => $coverage['uncovered'],
            'disclaimer' => 'Cálculo informativo basado en la legislación vigente (Ley 37/1992 LIVA, RD 1624/1992 RIVA, Orden HFP/3666/2024 modelo 303). No sustituye al asesoramiento profesional ni a la presentación oficial AEAT.',
        ];

        return new Breakdown(
            lines: $lines,
            netResult: $liquidQuota,
            currency: 'EUR',
            meta: $meta,
        );
    }

    protected function intracomExportInfoLine(VatTransactionInput $tx): BreakdownLine
    {
        return new BreakdownLine(
            concept: 'Operación '.$tx->category->label().' (informativa)',
            amount: Money::zero(),
            category: BreakdownCategory::INFO,
            base: $tx->base,
            legalReference: self::LIVA_25,
            explanation: 'Operaciones intracomunitarias / exportaciones / importaciones no calculadas en MVP M7. Modelo 349 referenciado pero no calculado. OSS/IOSS (modelo 369) fuera de alcance.',
        );
    }

    protected function criterioCajaAccrualDate(VatTransactionInput $tx): CarbonImmutable
    {
        // En régimen general este método no se usa, pero compartimos la
        // signatura para que CriterioCajaVat pueda override sólo el doCalculate.
        return $tx->date;
    }

    protected function resolveStatus(Money $liquidQuota, bool $isLastPeriod): VatReturnStatus
    {
        if (! $liquidQuota->isNegative() && ! $liquidQuota->isZero()) {
            return VatReturnStatus::A_INGRESAR;
        }

        if ($liquidQuota->isZero()) {
            return VatReturnStatus::A_INGRESAR; // 0,00 € — formalmente "a ingresar 0".
        }

        // liquidQuota negativa.
        if ($isLastPeriod) {
            return VatReturnStatus::A_DEVOLVER;
        }

        return VatReturnStatus::A_COMPENSAR;
    }

    protected function resultExplanation(VatReturnStatus $status): string
    {
        return match ($status) {
            VatReturnStatus::A_INGRESAR => 'Cuota líquida positiva: el contribuyente debe ingresar el importe a la AEAT (casilla 71 modelo 303).',
            VatReturnStatus::A_COMPENSAR => 'Cuota líquida negativa en período intermedio: se traslada como cuota a compensar a la siguiente autoliquidación (casilla 72 modelo 303).',
            VatReturnStatus::A_DEVOLVER => 'Cuota líquida negativa en último período (4T o anual): se solicita devolución a la AEAT (casilla 73 modelo 303).',
        };
    }

    /**
     * "21.0000" → "21" ; "10.0000" → "10" ; "5.2000" → "5.2".
     */
    protected function formatPercent(string $percentage): string
    {
        $trimmed = rtrim(rtrim($percentage, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }

    /**
     * Mapea el tipo IVA al tipo de Recargo de Equivalencia correspondiente
     * (art. 161 LIVA): 21→5,20 ; 10→1,40 ; 4→0,50 ; 5→0,62.
     */
    protected function surchargeRateForVatRate(string $vatRatePercentage): string
    {
        return match ($vatRatePercentage) {
            '21.0000' => '5.2000',
            '10.0000' => '1.4000',
            '4.0000' => '0.5000',
            '5.0000' => '0.6200',
            default => '0.0000',
        };
    }

    /**
     * Normaliza una representación de TaxRate (que puede llegar como
     * "21", "21.00" o "21.0000" según constructor) a la clave canónica
     * de 4 decimales que usa Modelo303CasillasMapper.
     */
    protected function normalizeRateKey(string $percentage): string
    {
        return number_format((float) $percentage, 4, '.', '');
    }
}
