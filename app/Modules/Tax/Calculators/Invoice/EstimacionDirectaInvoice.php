<?php

namespace Modules\Tax\Calculators\Invoice;

use Modules\Tax\DTOs\Breakdown;
use Modules\Tax\DTOs\BreakdownCategory;
use Modules\Tax\DTOs\BreakdownLine;
use Modules\Tax\DTOs\Invoice\InvoiceInput;
use Modules\Tax\DTOs\Invoice\InvoiceLineInput;
use Modules\Tax\DTOs\Invoice\InvoiceLineResult;
use Modules\Tax\DTOs\Invoice\InvoiceResult;
use Modules\Tax\DTOs\Invoice\VatRateType;
use Modules\Tax\Services\Invoice\IrpfRetentionResolver;
use Modules\Tax\Services\Invoice\SurchargeEquivalenceResolver;
use Modules\Tax\Services\Invoice\VatRateResolver;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\TaxRate;

/**
 * Calculador de factura para autónomo en Estimación Directa (EDN/EDS) con
 * régimen IVA General o Criterio de Caja.
 *
 * Diferencias entre IVA General y Criterio de Caja:
 *  - General: IVA devengado en el momento de emisión (regla general).
 *    Fuente: art. 75 LIVA.
 *  - Criterio de Caja: IVA devengado en el momento del cobro (o el 31 de
 *    diciembre del año siguiente como tope). Fuente: art. 163 decies LIVA,
 *    introducido por Ley 14/2013. La factura se emite igual; solo cambia
 *    cuándo declara y paga el IVA el emisor.
 *
 * En este calculator, AMBOS regímenes producen el mismo desglose. La diferencia
 * de devengo es informativa y se anota como BreakdownLine de tipo INFO.
 *
 * Recargo de Equivalencia al cliente: si el cliente está acogido a RE
 * (surchargeEquivalenceFlag), añade el recargo según tipo IVA al total.
 * Fuente: art. 161 LIVA.
 *
 * Retención IRPF: solo si el cliente es empresa o autónomo (clientType=empresa)
 * y la actividad del emisor está sujeta a retención. Fuente: art. 95 RIRPF.
 *
 * Operaciones intracomunitarias o exportaciones: en MVP, si clientCountry != ES
 * el calculator devuelve un breakdown informativo sin IVA y marca la factura
 * con disclaimer "operación intracomunitaria, fuera de alcance MVP".
 */
class EstimacionDirectaInvoice implements InvoiceRegimeCalculator
{
    private const LIVA_75 = 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740#a75';

    private const LIVA_163DECIES = 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740#a163decies';

    private const LIVA_161 = 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740#a161';

    private const RIRPF_95 = 'https://www.boe.es/buscar/act.php?id=BOE-A-2007-6820#a95';

    private const LIVA_25 = 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740#a25';

    public function __construct(
        private readonly VatRateResolver $vatResolver,
        private readonly IrpfRetentionResolver $irpfResolver,
        private readonly SurchargeEquivalenceResolver $surchargeResolver,
    ) {}

    public function calculate(InvoiceInput $input): InvoiceResult
    {
        $year = FiscalYear::fromInt($input->issueDate->year);

        $intracommunity = $input->isIntracommunity();
        $extracommunity = ! $input->isDomestic() && ! $intracommunity;

        $lineResults = [];
        $breakdownLines = [];
        $subtotal = Money::zero();
        $totalVat = Money::zero();
        $totalIrpf = Money::zero();
        $totalSurcharge = Money::zero();

        // 1. Calcular cada línea: subtotal y BreakdownLine BASE.
        $vatBuckets = []; // ['rate_key' => ['rate' => TaxRate, 'base' => Money, 'type' => VatRateType]]
        $surchargeBuckets = [];

        foreach ($input->lines as $idx => $line) {
            $lineSubtotal = $line->subtotal();
            $subtotal = $subtotal->add($lineSubtotal);

            // Resolver el rate de IVA aplicable a esta línea.
            $effectiveRateType = $this->effectiveVatRateType($line, $intracommunity, $extracommunity);
            $vatRate = $this->vatResolver->resolve(
                $effectiveRateType,
                $year,
                $input->issuerActivityCode,
            );
            $vatBase = $lineSubtotal;
            $vatAmount = $vatBase->applyRate($vatRate);

            // Acumular en bucket por tipo + rate concreto, para presentar
            // el breakdown agrupado y no una línea TAX por línea de factura.
            $bucketKey = $effectiveRateType->value.':'.$vatRate->percentage;
            if (! isset($vatBuckets[$bucketKey])) {
                $vatBuckets[$bucketKey] = [
                    'rate' => $vatRate,
                    'base' => Money::zero(),
                    'type' => $effectiveRateType,
                ];
            }
            $vatBuckets[$bucketKey]['base'] = $vatBuckets[$bucketKey]['base']->add($vatBase);

            // Recargo de equivalencia (cliente RE) — agrupar igual que IVA.
            $surchargeRate = TaxRate::zero();
            $surchargeAmount = Money::zero();
            if ($input->surchargeEquivalenceFlag && ! $intracommunity && ! $extracommunity
                && $effectiveRateType !== VatRateType::EXEMPT
                && $effectiveRateType !== VatRateType::ZERO) {
                $surchargeRate = $this->surchargeResolver->resolve($effectiveRateType);
                $surchargeAmount = $vatBase->applyRate($surchargeRate);
                $bucketKeyS = $effectiveRateType->value.':'.$surchargeRate->percentage;
                if (! isset($surchargeBuckets[$bucketKeyS])) {
                    $surchargeBuckets[$bucketKeyS] = [
                        'rate' => $surchargeRate,
                        'base' => Money::zero(),
                        'type' => $effectiveRateType,
                    ];
                }
                $surchargeBuckets[$bucketKeyS]['base'] = $surchargeBuckets[$bucketKeyS]['base']->add($vatBase);
            }

            // Retención IRPF: aplica solo si el cliente retiene Y la línea
            // está marcada como sujeta a retención (services-yes, goods-no).
            $irpfRate = TaxRate::zero();
            $irpfBase = Money::zero();
            $irpfAmount = Money::zero();
            if ($this->retentionApplies($input) && $line->irpfRetentionApplies) {
                $irpfRate = $this->irpfResolver->resolve(
                    $input->issuerIrpfRegime,
                    $year,
                    $input->issuerNewActivityFlag,
                    $input->issuerActivityCode,
                );
                if (! $irpfRate->isZero()) {
                    $irpfBase = $vatBase;
                    $irpfAmount = $vatBase->applyRate($irpfRate);
                }
            }

            $lineResults[] = new InvoiceLineResult(
                description: $line->description,
                quantity: $line->quantity,
                unitPrice: $line->unitPrice,
                subtotal: $lineSubtotal,
                vatBase: $vatBase,
                vatRate: $vatRate,
                vatAmount: $vatAmount,
                vatRateType: $effectiveRateType,
                irpfRetentionBase: $irpfBase,
                irpfRetentionRate: $irpfRate,
                irpfRetentionAmount: $irpfAmount,
            );

            $totalVat = $totalVat->add($vatAmount);
            $totalSurcharge = $totalSurcharge->add($surchargeAmount);
            $totalIrpf = $totalIrpf->add($irpfAmount);

            $breakdownLines[] = new BreakdownLine(
                concept: 'Línea '.($idx + 1).': '.$line->description,
                amount: $lineSubtotal,
                category: BreakdownCategory::BASE,
                base: $line->unitPrice,
                rate: null,
                legalReference: null,
                explanation: 'Cantidad '.$line->quantity.' × '.$line->unitPrice->amount.' EUR',
            );
        }

        // 2. Subtotal global como línea BASE agregada.
        $breakdownLines[] = new BreakdownLine(
            concept: 'Subtotal (base imponible)',
            amount: $subtotal,
            category: BreakdownCategory::BASE,
            explanation: 'Suma de subtotales de las líneas, antes de IVA y retenciones.',
        );

        // 3. IVA agrupado por tipo aplicable.
        if ($intracommunity) {
            $breakdownLines[] = new BreakdownLine(
                concept: 'IVA — Operación intracomunitaria (inversión sujeto pasivo)',
                amount: Money::zero(),
                category: BreakdownCategory::INFO,
                base: $subtotal,
                rate: TaxRate::zero(),
                legalReference: self::LIVA_25,
                explanation: 'Entrega intracomunitaria de bienes/servicios a empresario UE: el IVA lo autorrepercute el destinatario (art. 25 LIVA). MVP no calcula este caso; presenta factura sin IVA.',
            );
        } elseif ($extracommunity) {
            $breakdownLines[] = new BreakdownLine(
                concept: 'IVA — Exportación fuera UE',
                amount: Money::zero(),
                category: BreakdownCategory::INFO,
                base: $subtotal,
                rate: TaxRate::zero(),
                legalReference: self::LIVA_25,
                explanation: 'Exportación sujeta y exenta de IVA. Comprobar cumplimiento documental (art. 21 LIVA).',
            );
        } else {
            foreach ($vatBuckets as $bucket) {
                /** @var TaxRate $rate */
                $rate = $bucket['rate'];
                /** @var Money $base */
                $base = $bucket['base'];
                /** @var VatRateType $type */
                $type = $bucket['type'];
                if ($type === VatRateType::EXEMPT || $type === VatRateType::ZERO) {
                    $breakdownLines[] = new BreakdownLine(
                        concept: 'IVA '.$type->label(),
                        amount: Money::zero(),
                        category: BreakdownCategory::INFO,
                        base: $base,
                        rate: $rate,
                        legalReference: self::LIVA_75,
                        explanation: $type === VatRateType::EXEMPT
                            ? 'Operación exenta de IVA (art. 20 LIVA).'
                            : 'Tipo cero — operación no sujeta a IVA repercutido.',
                    );

                    continue;
                }
                $vatAmount = $base->applyRate($rate);
                $breakdownLines[] = new BreakdownLine(
                    concept: 'IVA '.$this->formatPercent($rate->percentage).'% sobre '.$base->amount.' EUR',
                    amount: $vatAmount,
                    category: BreakdownCategory::TAX,
                    base: $base,
                    rate: $rate,
                    legalReference: $input->issuerVatRegime->code === 'IVA_CAJA'
                        ? self::LIVA_163DECIES
                        : self::LIVA_75,
                    explanation: $input->issuerVatRegime->code === 'IVA_CAJA'
                        ? 'IVA repercutido — devengo según criterio de caja (art. 163 decies LIVA): se ingresa cuando se cobra.'
                        : 'IVA repercutido — devengo en el momento de emisión (art. 75 LIVA).',
                );
            }
        }

        // 4. Recargo de Equivalencia (si aplica).
        foreach ($surchargeBuckets as $bucket) {
            /** @var TaxRate $rate */
            $rate = $bucket['rate'];
            /** @var Money $base */
            $base = $bucket['base'];
            $reAmount = $base->applyRate($rate);
            $breakdownLines[] = new BreakdownLine(
                concept: 'Recargo de Equivalencia '.$this->formatPercent($rate->percentage).'% sobre '.$base->amount.' EUR',
                amount: $reAmount,
                category: BreakdownCategory::ADDITION,
                base: $base,
                rate: $rate,
                legalReference: self::LIVA_161,
                explanation: 'El cliente está acogido al Régimen de Recargo de Equivalencia: se añade el recargo correspondiente al tipo de IVA (art. 161 LIVA).',
            );
        }

        // 5. Retención IRPF (si aplica), una sola línea con la base agregada
        // de las líneas sujetas a retención.
        if (! $totalIrpf->isZero()) {
            $retainedBase = Money::zero();
            $retainedRate = null;
            foreach ($lineResults as $r) {
                if (! $r->irpfRetentionAmount->isZero()) {
                    $retainedBase = $retainedBase->add($r->irpfRetentionBase);
                    $retainedRate = $r->irpfRetentionRate;
                }
            }
            $rateDisplay = $retainedRate?->percentage ?? '0.00';
            $breakdownLines[] = new BreakdownLine(
                concept: 'Retención IRPF '.$this->formatPercent($rateDisplay).'% sobre '.$retainedBase->amount.' EUR',
                amount: $totalIrpf,
                category: BreakdownCategory::DEDUCTION,
                base: $retainedBase,
                rate: $retainedRate,
                legalReference: self::RIRPF_95,
                explanation: $input->issuerNewActivityFlag
                    ? 'Retención reducida al 7 % por inicio de actividad profesional (art. 95.1 RIRPF, primeros 3 años).'
                    : 'Retención IRPF aplicada al pagador (cliente empresa). El emisor cobra el neto y la retención la ingresa el cliente en el modelo 111.',
            );
        }

        // 6. Total a cobrar = subtotal + IVA + RE − retención IRPF.
        $totalToCharge = $subtotal->add($totalVat)->add($totalSurcharge)->subtract($totalIrpf);

        $breakdownLines[] = new BreakdownLine(
            concept: 'Total a cobrar',
            amount: $totalToCharge,
            category: BreakdownCategory::NET,
            explanation: 'Importe efectivo que el cliente abonará al emisor.',
        );

        $meta = [
            'issuer_vat_regime' => $input->issuerVatRegime->code,
            'issuer_irpf_regime' => $input->issuerIrpfRegime->code,
            'issue_date' => $input->issueDate->toDateString(),
            'fiscal_year' => $year->year,
            'client_country' => $input->clientCountry,
            'is_intracommunity' => $intracommunity,
            'disclaimer' => 'Cálculo informativo basado en la legislación vigente. No sustituye al asesoramiento profesional ni a la presentación oficial AEAT.',
        ];

        $breakdown = new Breakdown(
            lines: $breakdownLines,
            netResult: $totalToCharge,
            currency: 'EUR',
            meta: $meta,
        );

        return new InvoiceResult(
            breakdown: $breakdown,
            lines: $lineResults,
            subtotal: $subtotal,
            totalVat: $totalVat,
            totalSurchargeEquivalence: $totalSurcharge,
            totalIrpfRetention: $totalIrpf,
            totalToCharge: $totalToCharge,
        );
    }

    private function effectiveVatRateType(InvoiceLineInput $line, bool $intracommunity, bool $extracommunity): VatRateType
    {
        if ($intracommunity || $extracommunity) {
            return VatRateType::ZERO;
        }

        return $line->vatRateType;
    }

    private function retentionApplies(InvoiceInput $input): bool
    {
        // Solo si cliente es empresa (consumidor final no retiene),
        // operación doméstica, y emisor en EDN/EDS o EO con actividad sujeta.
        if (! $input->clientType->withholdsIrpf()) {
            return false;
        }

        if (! $input->isDomestic()) {
            return false;
        }

        return true;
    }

    /**
     * Convierte una percentage TaxRate (escala 4) a un string compacto:
     *  - "21.0000" → "21"
     *  - "10.0000" → "10"
     *  - "5.2000"  → "5.2"
     *  - "0.6200"  → "0.62"
     */
    private function formatPercent(string $percentage): string
    {
        $trimmed = rtrim(rtrim($percentage, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }
}
