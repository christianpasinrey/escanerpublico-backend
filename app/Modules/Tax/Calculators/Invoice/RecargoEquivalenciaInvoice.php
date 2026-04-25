<?php

namespace Modules\Tax\Calculators\Invoice;

use Modules\Tax\DTOs\Breakdown;
use Modules\Tax\DTOs\BreakdownCategory;
use Modules\Tax\DTOs\BreakdownLine;
use Modules\Tax\DTOs\Invoice\InvoiceInput;
use Modules\Tax\DTOs\Invoice\InvoiceLineResult;
use Modules\Tax\DTOs\Invoice\InvoiceResult;
use Modules\Tax\DTOs\Invoice\VatRateType;
use Modules\Tax\Services\Invoice\IrpfRetentionResolver;
use Modules\Tax\Services\Invoice\VatRateResolver;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\TaxRate;

/**
 * Calculador de factura para autónomo MINORISTA acogido al Régimen de
 * Recargo de Equivalencia (`IVA_RE`).
 *
 * Particularidades del régimen (arts. 154-163 LIVA):
 *  - El minorista NO presenta declaraciones de IVA. No liquida diferencia
 *    entre IVA repercutido y soportado.
 *  - El recargo lo paga el minorista a sus PROVEEDORES, no a sus clientes.
 *  - Cuando el minorista emite factura, repercute el IVA al cliente al tipo
 *    aplicable (igual que cualquier régimen general), pero el ingreso de ese
 *    IVA queda absorbido por el recargo que pagó al comprar.
 *
 * Por eso, este calculator emite la factura con el IVA aplicable al tipo
 * estándar y añade un BreakdownLine INFO recordando que el minorista no
 * declara IVA.
 *
 * Retención IRPF: el minorista comerciante NORMAL no factura a empresas
 * con retención (suele facturar a particulares). Si excepcionalmente factura
 * a empresa y la actividad lleva retención (raro en RE), se aplicaría.
 *
 * Notas operativas:
 *  - El minorista no puede deducirse el IVA soportado.
 *  - Es típico de comercios al por menor (sección retail CNAE 47).
 */
class RecargoEquivalenciaInvoice implements InvoiceRegimeCalculator
{
    private const LIVA_154 = 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740#a154';

    private const LIVA_75 = 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740#a75';

    private const RIRPF_95 = 'https://www.boe.es/buscar/act.php?id=BOE-A-2007-6820#a95';

    public function __construct(
        private readonly VatRateResolver $vatResolver,
        private readonly IrpfRetentionResolver $irpfResolver,
    ) {}

    public function calculate(InvoiceInput $input): InvoiceResult
    {
        $year = FiscalYear::fromInt($input->issueDate->year);

        $lineResults = [];
        $breakdownLines = [];
        $subtotal = Money::zero();
        $totalVat = Money::zero();
        $totalIrpf = Money::zero();

        $vatBuckets = [];

        foreach ($input->lines as $idx => $line) {
            $lineSubtotal = $line->subtotal();
            $subtotal = $subtotal->add($lineSubtotal);

            $vatRate = $this->vatResolver->resolve($line->vatRateType, $year, $input->issuerActivityCode);
            $vatBase = $lineSubtotal;
            $vatAmount = $vatBase->applyRate($vatRate);

            $bucketKey = $line->vatRateType->value.':'.$vatRate->percentage;
            if (! isset($vatBuckets[$bucketKey])) {
                $vatBuckets[$bucketKey] = ['rate' => $vatRate, 'base' => Money::zero(), 'type' => $line->vatRateType];
            }
            $vatBuckets[$bucketKey]['base'] = $vatBuckets[$bucketKey]['base']->add($vatBase);

            $irpfRate = TaxRate::zero();
            $irpfBase = Money::zero();
            $irpfAmount = Money::zero();
            if ($input->clientType->withholdsIrpf() && $input->isDomestic() && $line->irpfRetentionApplies) {
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
                vatRateType: $line->vatRateType,
                irpfRetentionBase: $irpfBase,
                irpfRetentionRate: $irpfRate,
                irpfRetentionAmount: $irpfAmount,
            );

            $totalVat = $totalVat->add($vatAmount);
            $totalIrpf = $totalIrpf->add($irpfAmount);

            $breakdownLines[] = new BreakdownLine(
                concept: 'Línea '.($idx + 1).': '.$line->description,
                amount: $lineSubtotal,
                category: BreakdownCategory::BASE,
                base: $line->unitPrice,
                explanation: 'Cantidad '.$line->quantity.' × '.$line->unitPrice->amount.' EUR',
            );
        }

        $breakdownLines[] = new BreakdownLine(
            concept: 'Subtotal (base imponible)',
            amount: $subtotal,
            category: BreakdownCategory::BASE,
        );

        foreach ($vatBuckets as $bucket) {
            /** @var TaxRate $rate */
            $rate = $bucket['rate'];
            /** @var Money $base */
            $base = $bucket['base'];
            /** @var VatRateType $type */
            $type = $bucket['type'];
            $vatAmount = $base->applyRate($rate);
            $breakdownLines[] = new BreakdownLine(
                concept: 'IVA '.$this->formatPercent($rate->percentage).'% sobre '.$base->amount.' EUR',
                amount: $vatAmount,
                category: $type === VatRateType::EXEMPT || $type === VatRateType::ZERO
                    ? BreakdownCategory::INFO
                    : BreakdownCategory::TAX,
                base: $base,
                rate: $rate,
                legalReference: self::LIVA_75,
                explanation: 'IVA repercutido al tipo aplicable. En recargo de equivalencia, el minorista no liquida este IVA en el modelo 303.',
            );
        }

        // Aviso esencial del régimen.
        $breakdownLines[] = new BreakdownLine(
            concept: 'Aviso Recargo de Equivalencia',
            amount: Money::zero(),
            category: BreakdownCategory::INFO,
            legalReference: self::LIVA_154,
            explanation: 'El emisor está en Recargo de Equivalencia (arts. 154-163 LIVA). NO presenta declaraciones de IVA: el recargo lo abonó al proveedor al comprar la mercancía. Esta factura repercute IVA al cliente, pero el emisor no liquida ese IVA con AEAT.',
        );

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
                concept: 'Retención IRPF '.$this->formatPercent($rateDisplay).'%',
                amount: $totalIrpf,
                category: BreakdownCategory::DEDUCTION,
                base: $retainedBase,
                rate: $retainedRate,
                legalReference: self::RIRPF_95,
                explanation: 'Retención IRPF aplicable cuando el comprador es empresa y la actividad está sujeta a retención.',
            );
        }

        $totalToCharge = $subtotal->add($totalVat)->subtract($totalIrpf);

        $breakdownLines[] = new BreakdownLine(
            concept: 'Total a cobrar',
            amount: $totalToCharge,
            category: BreakdownCategory::NET,
        );

        $meta = [
            'issuer_vat_regime' => $input->issuerVatRegime->code,
            'issuer_irpf_regime' => $input->issuerIrpfRegime->code,
            'issue_date' => $input->issueDate->toDateString(),
            'fiscal_year' => $year->year,
            're_regime_note' => 'Minorista en Recargo de Equivalencia: no liquida IVA con AEAT (lo abona al proveedor).',
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
            totalSurchargeEquivalence: Money::zero(),
            totalIrpfRetention: $totalIrpf,
            totalToCharge: $totalToCharge,
        );
    }

    private function formatPercent(string $percentage): string
    {
        $trimmed = rtrim(rtrim($percentage, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }
}
