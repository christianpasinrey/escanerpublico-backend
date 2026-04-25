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
 * Calculador de factura para autónomo en Régimen Simplificado de IVA
 * (`IVA_SIMPLE`), pareado típicamente con Estimación Objetiva (módulos)
 * en IRPF.
 *
 * Diferencia fundamental con Estimación Directa:
 *  - El IVA del régimen simplificado NO se calcula factura a factura.
 *    Se calcula trimestralmente con índices y módulos publicados por la
 *    Orden HFP de módulos del año (ver M7 — VatReturnCalculator).
 *  - Las facturas se emiten cargando IVA al cliente (igual que en general),
 *    pero el emisor no liquida diferencia entre repercutido y soportado;
 *    paga la cuota fija calculada con módulos.
 *
 * Por tanto, este calculator emite la factura normal (IVA repercutido al
 * cliente al tipo aplicable) pero añade un BreakdownLine INFORMATIVO que
 * recuerda que la liquidación trimestral usa módulos, no esta factura.
 *
 * Fuente: arts. 122-123 LIVA y RD 1624/1992 (Reglamento IVA) art. 38.
 *
 * Retención IRPF: en módulos hay actividades específicas con retención al 1 %
 * (transporte mercancías, construcción, peluquería, etc.) — el resolver lee
 * `activity_regime_mappings.irpf_retention_default` de la actividad.
 */
class EstimacionObjetivaInvoice implements InvoiceRegimeCalculator
{
    private const LIVA_122 = 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740#a122';

    private const RIRPF_95 = 'https://www.boe.es/buscar/act.php?id=BOE-A-2007-6820#a95';

    private const LIVA_75 = 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740#a75';

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
                explanation: 'IVA repercutido al cliente al tipo aplicable (art. 75 LIVA).',
            );
        }

        // Aviso régimen simplificado: la liquidación NO se calcula factura a
        // factura, sino con módulos.
        $breakdownLines[] = new BreakdownLine(
            concept: 'Aviso régimen simplificado',
            amount: Money::zero(),
            category: BreakdownCategory::INFO,
            legalReference: self::LIVA_122,
            explanation: 'En el régimen simplificado de IVA (arts. 122-123 LIVA) la liquidación trimestral se calcula con módulos publicados por la Orden HFP del año, no con la suma de IVA de las facturas. Esta factura se emite con el IVA aplicable al cliente, pero no es base directa para el modelo 303 simplificado.',
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
                concept: 'Retención IRPF '.$this->formatPercent($rateDisplay).'% (módulos)',
                amount: $totalIrpf,
                category: BreakdownCategory::DEDUCTION,
                base: $retainedBase,
                rate: $retainedRate,
                legalReference: self::RIRPF_95,
                explanation: 'Algunas actividades en módulos llevan retención al 1 % (transporte mercancías por carretera, construcción, peluquería, etc.) cuando el cliente es empresa.',
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
            'simplified_regime_note' => 'La cuota IVA del régimen simplificado se calcula con módulos en el modelo 303 trimestral, no factura a factura.',
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
